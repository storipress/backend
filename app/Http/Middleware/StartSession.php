<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession as BaseStartSession;
use Symfony\Component\HttpFoundation\Response;

class StartSession extends BaseStartSession
{
    /**
     * @var bool
     */
    protected $isGraphQLRequest = false;

    /**
     * @var bool
     */
    protected $isSocialPlatformsRequest = false;

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     */
    public function handle($request, Closure $next): mixed
    {
        $this->isGraphQLRequest = $request->is([
            'graphql',
            'client/*/graphql',
            'hocuspocus-webhook',
        ]);

        $this->isSocialPlatformsRequest = $request->is([
            'client/*/facebook/connect',
            'client/*/facebook/disconnect',
            'client/*/twitter/connect',
            'client/*/twitter/disconnect',
            'client/*/slack/connect',
            'client/*/shopify/connect',
            'client/*/shopify/disconnect',
        ]);

        return parent::handle($request, $next);
    }

    /**
     * Get the session implementation from the manager.
     */
    public function getSession(Request $request): Session
    {
        if (!$this->isGraphQLRequest && !$this->isSocialPlatformsRequest) {
            return parent::getSession($request);
        }

        /** @var Session $session */
        $session = $this->manager->driver();

        return tap($session, function ($session) use ($request) {
            if ($request->is('hocuspocus-webhook')) {
                $sessionId = $request->json('payload.requestParameters.uid');
            } else {
                $key = 'api-token';

                // first, find from header
                $sessionId = $request->header($key);

                if (!is_string($sessionId)) {
                    // then, find from query string
                    $sessionId = $request->input($key);

                    if (!is_string($sessionId)) {
                        // last, use bearer token
                        $sessionId = $request->bearerToken();
                    }
                }
            }

            if (is_string($sessionId)) {
                $session->setId($sessionId);
            }
        });
    }

    /**
     * Add the session cookie to the application response.
     */
    protected function addCookieToResponse(Response $response, Session $session): void
    {
        if ($this->isGraphQLRequest) {
            return;
        }

        parent::addCookieToResponse($response, $session);
    }
}
