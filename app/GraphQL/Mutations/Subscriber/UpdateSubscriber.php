<?php

namespace App\GraphQL\Mutations\Subscriber;

use App\Exceptions\AccessDeniedHttpException;
use App\Exceptions\InternalServerErrorHttpException;
use App\Exceptions\NotFoundHttpException;
use App\Models\Subscriber;
use App\Models\Tenants\Subscriber as TenantSubscriber;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class UpdateSubscriber
{
    /**
     * @param  array{
     *     email?: string,
     *     first_name?: string,
     *     last_name?: string,
     *     newsletter?: bool,
     * }  $args
     */
    public function __invoke($_, array $args): TenantSubscriber
    {
        $subscriber = auth()->user();

        if (! ($subscriber instanceof Subscriber)) {
            throw new AccessDeniedHttpException();
        }

        $tenantSubscriber = TenantSubscriber::find($subscriber->id);

        if (! ($tenantSubscriber instanceof TenantSubscriber)) {
            throw new NotFoundHttpException();
        }

        if (isset($args['email'])) {
            $args['email'] = Str::lower($args['email']);
        }

        $changes = Arr::except($args, ['newsletter']);

        if (! empty($changes)) {
            $origin = $subscriber->only(array_keys($args));

            $updated = $subscriber->update($changes);

            if (! $updated) {
                throw new InternalServerErrorHttpException();
            }

            if ($subscriber->wasChanged(['email'])) {
                // @todo handle email changed
            }

            if ($subscriber->wasChanged(['email', 'first_name', 'last_name'])) {
                $subscriber->tenants->runForEach(
                    fn () => TenantSubscriber::find($subscriber->getKey())?->searchable(),
                );
            }

            $subscriber->events()->create([
                'name' => 'profile.update',
                'data' => [
                    'origin' => $origin,
                    'changes' => $changes,
                ],
            ]);
        }

        if (isset($args['newsletter'])) {
            $tenantSubscriber->update([
                'newsletter' => $args['newsletter'],
            ]);

            $tenantSubscriber->events()->create([
                'name' => 'profile.update',
                'data' => [
                    'newsletter' => $args['newsletter'],
                ],
            ]);
        }

        return $tenantSubscriber;
    }
}
