<?php

namespace App\Console\Schedules;

use Illuminate\Console\Command as BaseCommand;
use Illuminate\Support\Str;

abstract class Command extends BaseCommand
{
    /**
     * {@inheritdoc}
     */
    protected $hidden = true;

    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        $this->configureSignature();

        parent::__construct();
    }

    /**
     * Configure the signature base on class name and directory.
     */
    protected function configureSignature(): void
    {
        if ($this->signature !== null) {
            return;
        }

        $class = get_class($this);

        $chunks = explode('\\', $class);

        $name = Str::kebab(array_pop($chunks));

        $frequency = count($chunks)
            ? Str::kebab(array_pop($chunks))
            : 'unknown';

        $this->signature = sprintf('scheduler:%s:%s', $frequency, $name);
    }
}
