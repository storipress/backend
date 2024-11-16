<?php

namespace App\Http\Controllers;

use App\Enums\CustomDomain\Group;
use App\Models\CustomDomain;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CaddyOnDemandAsk extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        $domain = $request->query('domain');

        abort_if(empty($domain) || ! is_string($domain), 404);

        $exists = CustomDomain::where('domain', '=', $domain)
            ->whereIn('group', [Group::site(), Group::redirect()])
            ->where('ok', '=', true)
            ->exists();

        abort_unless($exists, 404);

        return response('ok', 200);
    }
}
