<?php

namespace App\AutoPosting\LinkedIn;

use App\AutoPosting\Dispatcher;
use App\AutoPosting\Layers\PreProcessingLayer as BaseLayer;

class PreProcessingLayer extends BaseLayer
{
    use HasFailedHandler;
    use HasStoppedHandler;

    /**
     * {@inheritdoc}
     *
     * @param  array{}  $data
     */
    public function handle(Dispatcher $dispatcher, array $data, array $extra): bool
    {
        return true;
    }
}
