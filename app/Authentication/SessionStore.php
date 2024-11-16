<?php

namespace App\Authentication;

use App\Models\AccessToken;
use Illuminate\Contracts\Session\Session;
use Illuminate\Session\NullSessionHandler;
use Illuminate\Support\Arr;

class SessionStore implements Session
{
    /**
     * @var array<int|string, mixed>
     */
    protected array $data;

    public function __construct(
        protected AccessToken $accessToken,
    ) {
        $data = $this->accessToken->data;

        if ($data === null) {
            $data = [];
        }

        $this->data = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'storipress';
    }

    /**
     * {@inheritdoc}
     */
    public function setName($name)
    {
        // ignore
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return $this->accessToken->token;
    }

    /**
     * {@inheritdoc}
     */
    public function setId($id)
    {
        // ignore
    }

    /**
     * {@inheritdoc}
     */
    public function start(): bool
    {
        return $this->accessToken->exists;
    }

    /**
     * {@inheritdoc}
     */
    public function save()
    {
        $this->accessToken->updateQuietly([
            'data' => $this->data,
        ]);
    }

    /**
     * Get all the session data.
     *
     * @return array<int|string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Checks if a key exists.
     *
     * @param  string|array<string>  $key
     */
    public function exists($key): bool
    {
        return Arr::has($this->data, $key);
    }

    /**
     * Checks if a key is present and not null.
     *
     * @param  string|array<string>  $key
     */
    public function has($key): bool
    {
        return Arr::has($this->data, $key);
    }

    /**
     * {@inheritdoc}
     */
    public function get($key, $default = null)
    {
        return Arr::get($this->data, $key, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function pull($key, $default = null)
    {
        $value = Arr::pull($this->data, $key, $default);

        $this->save();

        return $value;
    }

    /**
     * Put a key / value pair or array of key / value pairs in the session.
     *
     * @param  string|array<string, mixed>  $key
     * @param  mixed  $value
     */
    public function put($key, $value = null): void
    {
        if (!is_array($key)) {
            Arr::set($this->data, $key, $value);
        } else {
            foreach ($key as $k => $v) {
                Arr::set($this->data, $k, $v);
            }
        }

        $this->save();
    }

    /**
     * {@inheritdoc}
     */
    public function token(): string
    {
        return $this->accessToken->token;
    }

    /**
     * {@inheritdoc}
     */
    public function regenerateToken()
    {
        // ignore
    }

    /**
     * {@inheritdoc}
     */
    public function remove($key)
    {
        return $this->pull($key);
    }

    /**
     * Remove one or many items from the session.
     *
     * @param  string|array<string>  $keys
     */
    public function forget($keys): void
    {
        Arr::forget($this->data, $keys);

        $this->save();
    }

    /**
     * {@inheritdoc}
     */
    public function flush()
    {
        $this->data = [];

        $this->save();
    }

    /**
     * {@inheritdoc}
     */
    public function invalidate(): bool
    {
        $this->flush();

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function regenerate($destroy = false): bool
    {
        if ($destroy) {
            $this->flush();
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function migrate($destroy = false): bool
    {
        if ($destroy) {
            $this->flush();
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isStarted(): bool
    {
        return $this->accessToken->exists;
    }

    /**
     * {@inheritdoc}
     */
    public function previousUrl(): ?string
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function setPreviousUrl($url)
    {
        // ignore
    }

    /**
     * {@inheritdoc}
     */
    public function getHandler(): NullSessionHandler
    {
        return new NullSessionHandler();
    }

    /**
     * {@inheritdoc}
     */
    public function handlerNeedsRequest(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function setRequestOnHandler($request)
    {
        // ignore
    }
}
