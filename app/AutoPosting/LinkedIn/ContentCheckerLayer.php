<?php

namespace App\AutoPosting\LinkedIn;

use App\AutoPosting\Dispatcher;
use App\AutoPosting\Layers\ContentCheckerLayer as BaseLayer;
use App\Exceptions\ErrorException;

class ContentCheckerLayer extends BaseLayer
{
    use HasFailedHandler;
    use HasStoppedHandler;

    /**
     * {@inheritdoc}
     *
     * @throws ErrorException
     */
    public function handle(Dispatcher $dispatcher, array $data, array $extra): bool
    {
        return true;
    }
}
