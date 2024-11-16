<?php

namespace App;

use Illuminate\Support\Str;
use Illuminate\Support\Stringable;

final readonly class Sluggable
{
    /**
     * @param  string  $string
     *
     * @link https://storipress-media.atlassian.net/browse/SPMVP-3708
     */
    public static function slug($string): string
    {
        return Str::of($string)
            ->stripTags()
            ->pipe(function (string $value): string {
                return transliterator_transliterate(
                    'Any-Latin; Latin-ASCII',
                    $value,
                ) ?: '';
            })
            ->slug(dictionary: [
                '&' => 'and',
                '%' => 'percent',
                '@' => 'at',
            ])
            ->whenEmpty(fn (Stringable $str) => $str->append(Str::random(8))->lower())
            ->value();
    }
}
