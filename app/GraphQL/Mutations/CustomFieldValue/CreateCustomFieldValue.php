<?php

namespace App\GraphQL\Mutations\CustomFieldValue;

use App\Enums\CustomField\GroupType;
use App\Enums\CustomField\Type;
use App\Events\Entity\CustomField\CustomFieldValueCreated;
use App\Exceptions\NotFoundHttpException;
use App\Exceptions\ValidationException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\CustomField;
use App\Models\Tenants\CustomFieldValue;
use App\Models\Tenants\Desk;
use App\Models\Tenants\Tag;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mime\MimeTypes;
use Webmozart\Assert\Assert;

class CreateCustomFieldValue extends Mutation
{
    use ValidateCustomFieldOptions;

    /**
     * @param  array{
     *     id: string,
     *     target_id: string,
     *     value: mixed,
     * }  $args
     *
     * @throws ValidationException
     */
    public function __invoke($_, array $args): CustomFieldValue
    {
        /** @var CustomField|null $field */
        $field = CustomField::with('group')->find($args['id']);

        if (
            $field === null ||
            $field->group === null ||
            $field->group->type === null
        ) {
            throw new NotFoundHttpException();
        }

        $tenant = tenant();

        if (!($tenant instanceof Tenant)) {
            throw new NotFoundHttpException();
        }

        $this->validateOptions($field, $args['value']);

        if (GroupType::publicationMetafield()->is($field->group->type)) {
            $model = $tenant;
        } else {
            $model = match ($field->group->type->value) {
                GroupType::articleMetafield()->value => new Article(),
                GroupType::articleContentBlock()->value => new Article(),
                GroupType::deskMetafield()->value => new Desk(),
                GroupType::tagMetafield()->value => new Tag(),
                default => null,
            };

            if ($model === null || !$model->where('id', $args['target_id'])->exists()) {
                throw new NotFoundHttpException();
            }
        }

        if (Type::date()->is($field->type)) {
            Assert::nullOrStringNotEmpty($args['value']);

            if ($args['value'] !== null) {
                $args['value'] = Carbon::parse($args['value'])->toIso8601String();
            }
        } elseif (Type::file()->is($field->type)) {
            if (!is_string($args['value'])) {
                throw new NotFoundHttpException();
            }

            $args['value'] = $this->handleFileType($args['value']);
        }

        /** @var CustomFieldValue $value */
        $value = $field->values()->create([
            'custom_field_morph_id' => $args['target_id'],
            'custom_field_morph_type' => get_class($model),
            'type' => $field->type,
            'value' => $args['value'],
        ]);

        $value->refresh();

        UserActivity::log(
            name: 'custom-field-value.create',
            subject: $value,
        );

        CustomFieldValueCreated::dispatch($tenant->id, $value->id);

        return $value;
    }

    /**
     * @return array<string, int|string>
     */
    protected function handleFileType(string $key): array
    {
        $path = tenancy()->central(fn () => Cache::pull($key));

        if (!is_string($path)) {
            throw new NotFoundHttpException();
        }

        $cloud = Storage::cloud();

        $mime = $cloud->mimeType($path);

        if ($mime === false) {
            throw new NotFoundHttpException();
        }

        $extension = Arr::first((new MimeTypes())->getExtensions($mime));

        if (!is_string($extension)) {
            throw new NotFoundHttpException();
        }

        $to = sprintf(
            'assets/attachments/%s/origin.%s',
            unique_token(),
            $extension,
        );

        $cloud->move($path, $to);

        return [
            'key' => $to,
            'url' => sprintf('https://assets.stori.press/%s', Str::after($to, '/')),
            'size' => $cloud->size($to),
            'mime_type' => $mime,
        ];
    }
}
