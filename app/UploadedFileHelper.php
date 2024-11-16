<?php

namespace App;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Symfony\Component\Mime\MimeTypes;
use Throwable;

use function Sentry\captureException;

trait UploadedFileHelper
{
    public function toUploadedFile(string $url): UploadedFile|false
    {
        try {
            $temp = temp_file();

            app('http2')
                ->withoutVerifying()
                ->withOptions(['sink' => $temp])
                ->throw()
                ->get($url);

            $mime = mime_content_type($temp);

            if (! $mime) {
                return false;
            }

            $types = new MimeTypes();

            $ext = $types->getExtensions($mime);

            if (empty($ext)) {
                return false;
            }

            // file extension is needed, otherwise there will be an error.
            $name = sprintf('%s.%s', unique_token(), $ext[0]);

            return new UploadedFile($temp, $name, $mime);
        } catch (RequestException $e) {
            if ($e->getCode() !== 404) {
                captureException($e);
            }
        } catch (Throwable $e) {
            captureException($e);
        }

        return false;
    }
}
