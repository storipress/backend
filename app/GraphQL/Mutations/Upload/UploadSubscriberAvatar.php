<?php

namespace App\GraphQL\Mutations\Upload;

use App\Models\Subscriber;
use Illuminate\Http\UploadedFile;

final class UploadSubscriberAvatar extends UploadMutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): string
    {
        /** @var Subscriber $subscriber */
        $subscriber = auth()->guard('subscriber')->user();

        /** @var UploadedFile $file */
        $file = $args['file'];

        $path = $this->upload($file);

        $subscriber->avatar()->delete();

        $subscriber->avatar()->create(
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
