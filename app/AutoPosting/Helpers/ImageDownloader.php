<?php

namespace App\AutoPosting\Helpers;

class ImageDownloader
{
    public static function download(string $url, ?string $path = null): string
    {
        $path = $path ?: temp_file();

        app('http')->withOptions(['sink' => $path])->get($url);

        return $path;
    }
}
