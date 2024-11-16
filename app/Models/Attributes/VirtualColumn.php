<?php

declare(strict_types=1);

namespace App\Models\Attributes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use RuntimeException;
use Webmozart\Assert\Assert;

/**
 * This trait lets you add a "data" column functionality to any Eloquent model.
 * It serializes attributes which don't exist as columns on the model's table
 * into a JSON column named data (customizable by overriding getDataColumn).
 *
 * @mixin Model
 */
trait VirtualColumn
{
    /**
     * We need this property, because both created & saved event listeners
     * decode the data (to take precedence before other created & saved)
     * listeners, but we don't want the data to be decoded twice.
     */
    public string $dataEncodingStatus = 'decoded';

    protected function decodeVirtualColumn(bool $force = false): void
    {
        if (!$force && $this->dataEncodingStatus === 'decoded') {
            return;
        }

        $dataColumn = $this->getDataColumn();

        $data = $this->getAttribute($dataColumn) ?: [];

        Assert::isArray($data);

        foreach ($data as $key => $value) {
            $this->setAttribute($key, $value);
            $this->syncOriginalAttribute($key);
        }

        $this->setAttribute($dataColumn, null);

        $this->dataEncodingStatus = 'decoded';
    }

    protected function encodeAttributes(): void
    {
        if ($this->dataEncodingStatus === 'encoded') {
            return;
        }

        $dataColumn = $this->getDataColumn();

        $data = $this->getAttribute($dataColumn) ?: [];

        Assert::isArray($data);

        $attributes = Arr::except($this->getAttributes(), $this->getCustomColumns());

        foreach ($attributes as $key => $value) {
            Assert::stringNotEmpty($key);

            $data[$key] = $value;

            unset($this->attributes[$key]);
            unset($this->original[$key]);
        }

        $this->setAttribute($dataColumn, $data);

        $this->dataEncodingStatus = 'encoded';
    }

    public static function bootVirtualColumn(): void
    {
        static::retrieved(function ($model) {
            $model->decodeVirtualColumn(true);
        });

        // Encode before write
        static::saving(function ($model) {
            $model->encodeAttributes();
        });

        static::creating(function ($model) {
            $model->encodeAttributes();
        });

        static::updating(function ($model) {
            $model->encodeAttributes();
        });

        // Decode after write
        static::saved(function ($model) {
            $model->decodeVirtualColumn();
        });

        static::updated(function ($model) {
            $model->decodeVirtualColumn();
        });

        static::created(function ($model) {
            $model->decodeVirtualColumn();
        });
    }

    public function initializeVirtualColumn(): void
    {
        $this->casts[$this->getDataColumn()] = 'array';
    }

    /**
     * @param  array{ touch?: bool }  $options
     */
    public function saveQuietly(array $options = [])
    {
        throw new RuntimeException('Virtual Column is not compatible with "quietly" methods.');
    }

    /**
     * Get the name of the column that stores additional data.
     */
    public function getDataColumn(): string
    {
        return 'data';
    }

    public function getCustomColumns(): array
    {
        return [
            'id',
        ];
    }
}
