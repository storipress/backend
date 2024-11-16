<?php

namespace App\Maker\Integrations;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

abstract class Integration
{
    /**
     * @var array<mixed>
     */
    protected array $attributes;

    /**
     * @param  array<mixed>|null  $attributes
     */
    public function __construct(?array $attributes)
    {
        $this->attributes = $attributes ?: [];
    }

    /**
     * ensure the integration is connected
     *
     * @return string[]
     */
    abstract protected function getRules(): array;

    public function validate(): bool
    {
        return Validator::make($this->attributes, $this->getRules())->passes();
    }

    /**
     * ensure the integration can post articles
     *
     * @return string[]
     */
    abstract protected function getPostRules(): array;

    public function postValidate(): bool
    {
        return Validator::make($this->attributes, $this->getPostRules())->passes();
    }

    /**
     * ensure the integration can update data.
     *
     * @return string[]
     */
    abstract protected function getUpdateRules(): array;

    public function updateValidate(): bool
    {
        return Validator::make($this->attributes, $this->getUpdateRules())->passes();
    }

    /**
     * the attributes that will be sent as configuration
     *
     * @return string[]
     */
    abstract protected function getAllowFields(): array;

    /**
     * @return array<mixed>
     */
    public function configuration(): array
    {
        return Arr::only($this->attributes, $this->getAllowFields());
    }
}
