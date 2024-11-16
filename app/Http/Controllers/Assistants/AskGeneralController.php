<?php

namespace App\Http\Controllers\Assistants;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AskGeneralController extends AssistantController
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): StreamedResponse
    {
        return $this->stream(function () use ($request) {
            $user = $request->user();

            if (!($user instanceof User)) {
                return $this->error('Unauthorized.');
            }

            $prompt = $request->input('prompt', '');

            if (!is_string($prompt) || Str::length($prompt) < 10) {
                return $this->error('Prompt must be at least 10 characters long.');
            }

            foreach ($this->ask($prompt) as $content) {
                $this->ok($content);
            }
        });
    }
}
