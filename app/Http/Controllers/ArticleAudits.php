<?php

namespace App\Http\Controllers;

use App\Models\Tenants\UserActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ArticleAudits extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $id = $request->input('id', 0);

        $query = UserActivity::whereSubjectId($id)
            ->orderByDesc('id')
            ->take(50);

        if (!empty($before = $request->input('before'))) {
            $query->where('id', '<', $before);
        }

        $audits = $query->get()->toArray();

        return response()->json($audits);
    }
}
