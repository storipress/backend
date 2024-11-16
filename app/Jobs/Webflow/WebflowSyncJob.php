<?php

declare(strict_types=1);

namespace App\Jobs\Webflow;

use App\Enums\CustomField\Type;
use App\Events\Partners\Webflow\CollectionSchemaOutdated;
use App\Models\Tenants\Article;
use App\Models\Tenants\CustomField;
use App\Models\Tenants\Desk;
use App\Models\Tenants\Entity;
use App\Models\Tenants\Integrations\Configurations\WebflowConfiguration;
use App\Models\Tenants\Tag;
use App\Models\Tenants\User;
use App\Models\Tenants\WebflowReference;
use App\Notifications\Webflow\WebflowValidationNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Sentry\State\Scope;
use stdClass;
use Storipress\Webflow\Exceptions\Exception as WebflowException;
use Storipress\Webflow\Exceptions\HttpBadRequest;
use Storipress\Webflow\Exceptions\HttpConflict;
use Storipress\Webflow\Exceptions\HttpException;
use Storipress\Webflow\Exceptions\HttpNotFound;
use Storipress\Webflow\Exceptions\UnexpectedValueException;
use Storipress\Webflow\Facades\Webflow;
use Storipress\Webflow\Objects\Item;
use Storipress\Webflow\Objects\Validations\Validation;

use function Sentry\configureScope;

/**
 * @phpstan-import-type WebflowCollectionFields from WebflowConfiguration
 */
abstract class WebflowSyncJob extends WebflowJob
{
    public string $group;

    public string $tenantId;

    public ?int $entityId;

    /**
     * @var array<int, non-empty-string>
     */
    public array $validations = [];

    /**
     * {@inheritdoc}
     */
    public function rateLimitingKey(): string
    {
        return sprintf('%s:%d', $this->tenantId, $this->entityId);
    }

    /**
     * {@inheritdoc}
     */
    public function overlappingKey(): string
    {
        return sprintf('%s:%d', $this->tenantId, $this->entityId);
    }

    /**
     * {@inheritdoc}
     */
    public function throttlingKey(): string
    {
        return sprintf('webflow:%s:general', $this->tenantId);
    }

    /**
     * 刪除指定 item。
     */
    public function trash(string $collectionId, string $itemId): void
    {
        try {
            app('webflow')->item()->delete(
                $collectionId,
                $itemId,
                true,
            );
        } catch (WebflowException) {
            // ignored
        }
    }

    /**
     * @param  WebflowCollectionFields  $fields
     * @param  array<non-empty-string, non-empty-string|null>  $mappings
     * @return array<non-empty-string, mixed>
     */
    public function toFieldData(Entity $model, array $fields, array $mappings): array
    {
        $data = [];

        foreach ($fields as $field) {
            if (! isset($mappings[$field['id']])) {
                continue;
            }

            $key = $mappings[$field['id']];

            $value = data_get($model, $key);

            if ($value instanceof Desk) {
                $value = $value->webflow_id;
            } elseif ($value instanceof Carbon) {
                $value = $value->toIso8601String();
            } elseif ($value instanceof Collection) {
                $value = $value->pluck('webflow_id')->toArray();

                if ($field['type'] === 'Reference') {
                    $value = Arr::first($value);
                }
            } elseif ($field['type'] === 'PlainText') {
                if (! is_not_empty_string($value)) {
                    $value = '';
                } else {
                    $value = trim(html_entity_decode(strip_tags($value)));
                }
            } elseif ($field['type'] === 'Link') {
                if (! is_string($value) || empty($value)) {
                    $value = '';
                } elseif (! Str::startsWith($value, ['https://', 'http://', 'mailto:', 'tel:', 'sms:'])) {
                    $value = sprintf('https://%s', $value);
                }
            } elseif (in_array($field['type'], ['Image', 'MultiImage'], true) && is_not_empty_string($value)) {
                $value = sprintf(
                    '%s%sclass=webflow',
                    $value,
                    Str::contains($value, '?') ? '&' : '?',
                );

                if ($field['type'] === 'MultiImage') {
                    $value = Arr::wrap($value);
                }
            } elseif ($value instanceof CustomField) {
                $isRepeat = $value->options['repeat'] ?? false;

                $isMultiple = $value->options['multiple'] ?? false;

                $isFile = Type::file()->is($value->type);

                if ($isRepeat) {
                    $value = $value->values->pluck('value')->toArray();

                    if ($isFile && ! empty($value)) {
                        $value = array_map(
                            fn (string $url) => sprintf('%s?class=webflow', $url),
                            array_column($value, 'url'),
                        );
                    }
                } else {
                    $value = $value->values->first()?->value ?: '';

                    if ($isFile) {
                        if (! empty($value)) {
                            $value = sprintf('%s?class=webflow', $value['url']); // @phpstan-ignore-line
                        }
                    } elseif (is_array($value)) {
                        if ($value[0] instanceof WebflowReference) {
                            $value = array_map(
                                fn (WebflowReference $reference) => $reference->id,
                                $value,
                            );
                        }

                        if (! $isMultiple) {
                            $value = Arr::first($value);
                        }
                    }
                }
            }

            if ($key === 'html' && is_not_empty_string($value)) {
                $value = preg_replace(
                    '#(src="https://assets\.stori\.press/media/images/.+?\.\w{3,5})(")#im',
                    '$1?class=webflow$2',
                    $value,
                );

                $script = script_tag('webflow', $this->tenantId);

                $value = $value.PHP_EOL.$script;
            }

            $data[$field['slug']] = $value;
        }

        return $data;
    }

    /**
     * 透過 Webflow SDK 對個欄位的值做預檢查。
     *
     * @param  array<non-empty-string, mixed>  $data
     * @param  WebflowCollectionFields  $fields
     * @param  Article|Desk|Tag|User  $entity
     */
    public function validate(array $data, array $fields, Entity $entity): bool
    {
        foreach ($fields as $field) {
            [
                'slug' => $key,
                'displayName' => $name,
                'type' => $type,
            ] = $field;

            // do not need to validate if the field is not existed when updating.
            if (($entity->webflow_id !== null) && ! array_key_exists($key, $data)) {
                continue;
            }

            // required validation
            if ($field['isRequired'] && (! isset($data[$key]) || $this->isEmpty($data[$key]))) {
                $this->validations[] = sprintf('The %s field is required.', $name);

                continue;
            }

            // do not need to validate if the field is not existed and is not required.
            if (! array_key_exists($key, $data)) {
                continue;
            }

            if ($this->isEmpty($data[$key])) {
                continue;
            }

            if (app()->isProduction()) {
                continue; // @todo - webflow - remove when stable
            }

            if ($type === 'Switch') {
                $type = 'SwitchType';
            }

            $class = sprintf('Storipress\Webflow\Objects\Validations\%s', $type);

            if (! is_a($class, Validation::class, true)) {
                continue; // Validator 未實作（不存在）
            }

            $encoded = json_encode($field['validations']);

            if ($encoded === false) {
                continue;
            }

            $validations = json_decode($encoded, false);

            $validator = $class::from($validations ?: new stdClass());

            if ($validator->validate($data[$key])) {
                continue;
            }

            $this->validations[] = sprintf(
                'The %s field has an incorrect value ((%s) "%s").',
                $name,
                gettype($data[$key]),
                Str::limit($data[$key], 150), // @phpstan-ignore-line
            );
        }

        if (count($this->validations) === 0) {
            return true;
        }

        $this->notifyValidationIssue();

        return false;
    }

    /**
     * @param  Article|Desk|Tag|User  $entity
     * @param array{
     *     isArchived: bool,
     *     isDraft: bool,
     *     fieldData: non-empty-array<non-empty-string, mixed>,
     * } $params
     *
     * @throws HttpException
     * @throws UnexpectedValueException
     */
    public function createOrUpdateItem(
        string $collectionId,
        Entity $entity,
        array $params,
        bool $publish,
    ): ?Item {
        $api = Webflow::item();

        $tried = false;

        begin:

        try {
            if (is_not_empty_string($entity->webflow_id)) {
                $key = sprintf('webflow-%s', $entity->webflow_id);

                tenancy()->central(fn () => Cache::add($key, true, 10));

                try {
                    if ($publish && ($params['isArchived'] || $params['isDraft'])) {
                        try {
                            $api->delete($collectionId, $entity->webflow_id, true);
                        } catch (WebflowException) {
                            // ignored
                        }

                        $publish = false;
                    }

                    return $api->update(
                        $collectionId,
                        $entity->webflow_id,
                        $params,
                        $publish,
                    );
                } catch (HttpConflict $e) {
                    $message = $e->getMessage();

                    if (! $tried) {
                        $needles = [
                            'The site is not published',
                            'The site hasn\'t been published',
                            'Site is published to multiple domains at different times',
                        ];

                        if (Str::contains($message, $needles, true)) {
                            $publish = false;

                            $tried = true;

                            goto begin;
                        } elseif (Str::contains($message, 'have never been published', true)) {
                            try {
                                $published = $api->publish($collectionId, [$entity->webflow_id]);

                                if (! empty($published->errors) && is_array($published->errors)) {
                                    configureScope(function (Scope $scope) use ($published) {
                                        $scope->setContext('errors', $published->errors);
                                    });

                                    throw $e;
                                }

                                $tried = true;

                                if ($params['isArchived'] || $params['isDraft']) {
                                    $publish = false;
                                }

                                goto begin;
                            } catch (WebflowException) {
                                // ignored
                            }
                        }
                    }

                    throw $e;
                } catch (HttpNotFound) {
                    // ignored
                }
            }

            try {
                $item = $api->create($collectionId, $params, $publish);

                $key = sprintf('webflow-%s', $item->id);

                tenancy()->central(fn () => Cache::add($key, true, 10));

                return $item;
            } catch (HttpConflict $e) {
                if (! $tried) {
                    $needles = [
                        'The site is not published',
                        'The site hasn\'t been published',
                        'Site is published to multiple domains at different times',
                    ];

                    if (Str::contains($e->getMessage(), $needles, true)) {
                        $publish = false;

                        $tried = true;

                        goto begin;
                    }
                }

                throw $e;
            } catch (HttpNotFound) {
                $this->validations[] = 'It looks like your Webflow site might have been restored from a backup. If this is the case, please reconnect your publication to the Webflow site. If not, you can reply to this email for assistance.';
            }
        } catch (HttpConflict $e) {
            if (Str::contains($e->getMessage(), 'The collection structure changed since the last publish', true)) {
                $this->validations[] = sprintf('One of your Webflow collection structures has changed. Please publish your site on Webflow first.');

                CollectionSchemaOutdated::dispatch($this->tenantId);
            } else {
                throw $e;
            }
        } catch (HttpBadRequest $e) {
            $message = $e->getMessage();

            if (Str::contains($message, 'Field not described in schema', true)) {
                $this->validations[] = sprintf('One of your Webflow collection structures has changed. Please publish your site on Webflow first.');

                CollectionSchemaOutdated::dispatch($this->tenantId);
            } elseif (Str::contains($message, 'Unique value is already in database', true) && ! empty($e->error->details)) {
                $this->validations[] = sprintf('The value for the %s field is already being used.', $e->error->details[0]->param);
            } elseif (Str::contains($message, 'Is too short', true) && ! empty($e->error->details)) {
                $this->validations[] = sprintf('The value for the %s field is too short.', $e->error->details[0]->param);
            } elseif (Str::contains($message, 'Is too long', true) && ! empty($e->error->details)) {
                $this->validations[] = sprintf('The value for the %s field is too long.', $e->error->details[0]->param);
            } elseif (Str::contains($message, 'Required field cannot be cleared', true) && ! empty($e->error->details)) {
                $this->validations[] = sprintf('The value for the %s field is missing.', $e->error->details[0]->param);
            } elseif (Str::contains($message, 'Remote image failed to import: Unsupported file type', true)) {
                $this->validations[] = 'There are image links in the content that are in an unsupported format.';
            } elseif (Str::contains($message, 'Remote image failed to import: File size limit reached', true)) {
                $this->validations[] = 'There are image links in the content that are over 4MB in size.';
            } elseif (Str::contains($message, 'Expected value to be an ItemRef: \'null\'', true)) {
                $this->validations[] = 'It looks like your Webflow site might have been restored from a backup. If this is the case, please reconnect your publication to the Webflow site. If not, you can reply to this email for further assistance.';
            } elseif (Str::contains($message, 'Referenced item not found', true)) {
                if (preg_match('/\w{24}/i', $message, $ids) > 0) {
                    Desk::withTrashed()->where('webflow_id', '=', $ids[0])->update(['webflow_id' => null]);

                    Tag::withTrashed()->where('webflow_id', '=', $ids[0])->update(['webflow_id' => null]);

                    User::where('webflow_id', '=', $ids[0])->update(['webflow_id' => null]);
                }
            } else {
                throw $e;
            }
        }

        if (! empty($this->validations)) {
            $this->notifyValidationIssue();
        }

        return null;
    }

    /**
     * 通知使用者欄位驗證問題。
     */
    public function notifyValidationIssue(): void
    {
        $tenant = tenant_or_fail();

        $notification = new WebflowValidationNotification(
            $tenant->id,
            $tenant->name,
            $this->group,
            $this->entityId ?: 0,
            $this->validations,
        );

        $tenant->owner->notify($notification);
    }

    /**
     * 根據不同型態判斷 $value 是否為空。
     */
    public function isEmpty(mixed $value): bool
    {
        return (is_string($value) && strlen($value) === 0) ||
            (is_array($value) && count($value) === 0) ||
            $value === null;
    }
}
