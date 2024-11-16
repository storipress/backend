<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TakeoutController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! ($user instanceof User)) {
            abort(401);
        }

        if (tenant('user_id') !== $user->id) {
            abort(403);
        }

        $s3 = Str::lower(sprintf('takeouts/storipress-takeout-%s.zip', tenant('id')));

        if (! Storage::cloud()->exists($s3)) {
            abort(404);
        }

        $url = Storage::cloud()->temporaryUrl($s3, now()->addHour());

        return redirect()->away($url);
    }
}
