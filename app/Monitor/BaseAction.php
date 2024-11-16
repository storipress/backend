<?php

namespace App\Monitor;

abstract class BaseAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    abstract public function run(array $data): void;
}
