<?php

namespace App\AutoPosting\Layers;

use App\AutoPosting\Dispatcher;
use App\Exceptions\ErrorException;
use Throwable;

abstract class AbstractLayer
{
    /**
     * Execute the current layer.
     *
     * @param  array<mixed>  $data  the data from previous layer
     * @param  array<mixed>  $extra  the data for all layers
     */
    abstract public function handle(Dispatcher $dispatcher, array $data, array $extra): mixed;

    /**
     * Send logs to the engineering team when any of the layers return false.
     */
    abstract public function logStopped(ErrorException $e, string $layer): void;

    /**
     * Send logs to the engineering team when something went wrong.
     */
    abstract public function logFailed(Throwable $e, string $layer): void;

    /**
     * Send reports to the customer when any of the layers return false.
     */
    abstract public function reportStopped(ErrorException $e): void;

    /**
     * Send reports to the customer when something went wrong.
     */
    abstract public function reportFailed(Throwable $e): void;
}
