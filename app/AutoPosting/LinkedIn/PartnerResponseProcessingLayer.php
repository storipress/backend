<?php

namespace App\AutoPosting\LinkedIn;

use App\AutoPosting\Dispatcher;
use App\AutoPosting\Layers\PartnerResponseProcessingLayer as BaseLayer;

class PartnerResponseProcessingLayer extends BaseLayer
{
    use HasFailedHandler;
    use HasStoppedHandler;

    /**
     * {@inheritdoc}
     *
     * @param  array{post_id: string}  $data
     * @return array{post_id: string}
     */
    public function handle(Dispatcher $dispatcher, array $data, array $extra): array
    {
        // pass $data for next layer
        return $data;
    }
}
