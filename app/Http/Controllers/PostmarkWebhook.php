<?php

namespace App\Http\Controllers;

use App\Events\Partners\Postmark\WebhookReceiving;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

class PostmarkWebhook extends Controller
{
    protected Request $request;

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        $this->request = $request;

        if (
            ! Hash::check($request->getUser(), '$argon2id$v=19$m=65536,t=16,p=1$S29ZWEdXNTNaMzlTYTdFTg$NePGfI0s25jZe3wiJDkGRZKK5M0MwxBC8VLYpLYFpeM') ||
            ! Hash::check($request->getPassword(), '$argon2id$v=19$m=65536,t=16,p=1$ZHFMTzBLT0tzYmw3YXdKMw$XMpdv9aNfTmQKUfNRmX32ZFNHpjs6z2Gl62FlsqiCYA')
        ) {
            return response('Unauthorized', 401, ['WWW-Authenticate' => 'Basic']);
        }

        WebhookReceiving::dispatch(
            $request->all(),
            $request->getContent(),
        );

        return response('', 200);
    }
}
