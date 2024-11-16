<?php

namespace App\GraphQL\Queries;

use App\Exceptions\AccessDeniedHttpException;
use App\Exceptions\NotFoundHttpException;
use App\Models\Tenants\Image as ImageModel;
use App\Models\User;

final class Image
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): ImageModel
    {
        /** @var User|null $authed */
        $authed = auth()->user();

        if ($authed === null) {
            throw new AccessDeniedHttpException();
        }

        $token = $args['key'];

        /** @var ImageModel|null $image */
        $image = ImageModel::where('token', $token)->first();

        if ($image === null) {
            throw new NotFoundHttpException();
        }

        return $image;
    }
}
