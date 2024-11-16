<?php

namespace App\GraphQL\Mutations\Upload;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Webmozart\Assert\Assert;

class UploadAvatar extends UploadMutation
{
    /**
     * @param  array{ file: UploadedFile }  $args
     */
    public function __invoke($_, array $args): string
    {
        tenancy()->end();

        $user = auth()->user();

        Assert::isInstanceOf($user, User::class);

        $file = $args['file'];

        $path = $this->upload($file);

        $user->avatar()->delete();

        $user->avatar()->create(
            $this->getImageAttributes($path, $file),
        );

        return $path;
    }

    /**
     * {@inheritDoc}
     */
    protected function group(): string
    {
        return 'avatars';
    }

    /**
     * {@inheritDoc}
     */
    protected function directory(): ?string
    {
        return null;
    }
}
