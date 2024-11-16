<?php

namespace App\Monitor\Actions;

use App\Monitor\BaseAction;
use Illuminate\Support\Facades\Log;

class LogAction extends BaseAction
{
    /**
     * @param  array{messages:string[], data:array{level:string}, level:string}  $data
     */
    public function run(array $data): void
    {
        $level = $data['data']['level'];

        foreach ($data['messages'] as $message) {
            Log::{$level}($message);
        }
    }
}
