<?php

namespace App\GraphQL\Mutations\CustomFieldValue;

use App\Enums\CustomField\Type;
use App\Events\Entity\CustomField\CustomFieldValueUpdated;
use App\Exceptions\BadRequestHttpException;
use App\Exceptions\InternalServerErrorHttpException;
use App\Exceptions\NotFoundHttpException;
use App\Exceptions\ValidationException;
use App\GraphQL\Mutations\Mutation;
use App\Models\Tenant;
use App\Models\Tenants\CustomFieldValue;
use App\Models\Tenants\UserActivity;
use Illuminate\Support\Carbon;
use Webmozart\Assert\Assert;

class UpdateCustomFieldValue extends Mutation
{
    use ValidateCustomFieldOptions;

    /**
     * @param  array{
     *     id: string,
     *     value: mixed,
     * }  $args
     *
     * @throws ValidationException
     */
    public function __invoke($_, array $args): CustomFieldValue
    {
        $tenant = tenant();

        if (! ($tenant instanceof Tenant)) {
            throw new NotFoundHttpException();
        }

        /** @var CustomFieldValue|null $value */
        $value = CustomFieldValue::with('customField')->find($args['id']);

        if ($value === null || $value->customField === null) {
            throw new NotFoundHttpException();
        }

        if (Type::file()->is($value->customField->type)) {
            throw new BadRequestHttpException();
        }

        $this->validateOptions($value->customField, $new = $args['value']);

        if (Type::date()->is($value->customField->type)) {
            Assert::nullOrStringNotEmpty($new);

            if ($new !== null) {
                $new = Carbon::parse($new)->toIso8601String();
            }
        }

        $origin = $value->value;

        $updated = $value->update(['value' => $new]);

        if (! $updated) {
            throw new InternalServerErrorHttpException();
        }

        $value->refresh();

        UserActivity::log(
            name: 'custom-field-value.update',
            subject: $value,
            data: [
                'old' => $origin,
                'new' => $new,
            ],
        );

        CustomFieldValueUpdated::dispatch($tenant->id, $value->id);

        return $value;
    }
}
