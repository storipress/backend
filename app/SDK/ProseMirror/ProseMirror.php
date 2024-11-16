<?php

namespace App\SDK\ProseMirror;

use Aws\Lambda\LambdaClient;
use Exception;
use GuzzleHttp\Psr7\Stream;
use Illuminate\Support\Str;
use Webmozart\Assert\Assert;

class ProseMirror
{
    protected LambdaClient $lambda;

    public function __construct()
    {
        $this->lambda = app('aws')->createLambda();
    }

    /**
     * @param  array<mixed>  $document
     * @param  array{
     *     client_id: string,
     *     article_id: int,
     * }  $attributes
     */
    public function toHTML(array $document, array $attributes): string
    {
        return $this->transform('html', $document, $attributes);
    }

    /**
     * @param  array<mixed>  $document
     */
    public function toPlainText(array $document): string
    {
        return $this->transform('plaintext', $document);
    }

    /**
     * @return array<mixed>
     */
    public function toProseMirror(string $document): array
    {
        return $this->transform('prose_mirror', $document);
    }

    /**
     * @param  array<mixed>  $document
     */
    public function toNewsletter(array $document): string
    {
        return $this->transform('newsletter', $document);
    }

    /**
     * @param  array<int, string>  $preprocess
     */
    public function rewriteHTML(string $document, array $preprocess = []): string
    {
        return $this->transform('rewrite_html', $document, [
            'preprocess' => $preprocess,
        ]);
    }

    /**
     * @param  array<mixed>  $document
     * @param  array{
     *     client_id: string,
     *     article_id: int,
     * }  $attributes
     */
    public function escapeHTML(array $document, array $attributes): string
    {
        return $this->transform('escape_html', $document, $attributes);
    }

    /**
     * @param  'html'|'plaintext'|'prose_mirror'|'newsletter'|'rewrite_html'|'escape_html'  $to
     * @param  array<mixed>|string  $document
     * @param  array<string, mixed>  $attributes
     * @return ($to is 'prose_mirror' ? array<mixed> : string)
     *
     * @throws Exception
     */
    protected function transform(
        string $to,
        array|string $document,
        array $attributes = [],
    ): array|string {
        if (is_array($document)) {
            $document = json_encode($document);

            Assert::notFalse($document);
        }

        $payload = json_encode(
            array_merge($attributes,
                [
                    'to' => Str::upper($to),
                    'payload' => $document,
                ],
            ),
        );

        Assert::stringNotEmpty($payload);

        // @phpstan-ignore-next-line
        return tap(
            $this->invoke($payload),
            fn ($result) => Assert::notNull($result),
        );
    }

    /**
     * @return array<mixed>|string|null
     */
    protected function invoke(string $payload): array|string|null
    {
        $result = $this->lambda->invoke([
            'FunctionName' => 'prosemirror-translator',
            'InvocationType' => 'RequestResponse',
            'Payload' => $payload,
        ]);

        if ($result->get('StatusCode') !== 200) {
            return null;
        }

        $payload = $result->get('Payload');

        if (! ($payload instanceof Stream)) {
            return null;
        }

        $content = $payload->getContents();

        $context = json_decode($content, true);

        if (! is_array($context)) {
            return null;
        }

        if (! array_key_exists('result', $context)) {
            return null;
        }

        return $context['result'];
    }

    public function setLambda(LambdaClient $lambda): self
    {
        $this->lambda = $lambda;

        return $this;
    }
}
