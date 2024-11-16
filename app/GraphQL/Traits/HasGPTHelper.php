<?php

namespace App\GraphQL\Traits;

use Illuminate\Support\Arr;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponseChoice;

trait HasGPTHelper
{
    /**
     * Give a prompt, response with completions.
     */
    protected function chat(string $prompt): string
    {
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'n' => 1,
            'temperature' => 0.2,
            'presence_penalty' => -0.2,
            'frequency_penalty' => 0.8,
        ]);

        $choice = Arr::last($response->choices);

        if (! ($choice instanceof CreateResponseChoice)) {
            return '';
        }

        return trim($choice->message->content ?: '');
    }
}
