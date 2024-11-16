<?php

namespace App\AutoPosting\LinkedIn;

use App\AutoPosting\Dispatcher;
use App\AutoPosting\Layers\PostProcessingLayer as BaseLayer;
use App\Enums\AutoPosting\State;

class PostProcessingLayer extends BaseLayer
{
    use HasFailedHandler;
    use HasStoppedHandler;

    /**
     * {@inheritdoc}
     *
     * @param  array{post_id: string}  $data
     */
    public function handle(Dispatcher $dispatcher, array $data, array $extra): bool
    {
        $dispatcher->article->autoPostings()->create([
            'platform' => 'linkedin',
            'state' => State::posted(),
            'target_id' => $data['post_id'],
            'domain' => 'www.linkedin.com',
            'prefix' => null,
            'pathname' => sprintf('/feed/update/%s', $data['post_id']),
        ]);

        return true;
    }
}
