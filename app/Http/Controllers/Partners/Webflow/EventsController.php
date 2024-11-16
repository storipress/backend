<?php

declare(strict_types=1);

namespace App\Http\Controllers\Partners\Webflow;

use App\Events\Partners\Webflow\Webhooks\CollectionItemChanged;
use App\Events\Partners\Webflow\Webhooks\CollectionItemCreated;
use App\Events\Partners\Webflow\Webhooks\CollectionItemDeleted;
use App\Events\Partners\Webflow\Webhooks\CollectionItemUnpublished;
use App\Exceptions\ErrorCode;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class EventsController extends WebflowController
{
    /**
     * @var array<string, class-string>
     */
    protected array $topics = [
        'collection_item_created' => CollectionItemCreated::class,
        'collection_item_changed' => CollectionItemChanged::class,
        'collection_item_deleted' => CollectionItemDeleted::class,
        'collection_item_unpublished' => CollectionItemUnpublished::class,
    ];

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $verified = $this->verifyRequest($request);

        if (!$verified) {
            return $this->failed(ErrorCode::OAUTH_INVALID_PAYLOAD);
        }

        $type = $request->json('triggerType');

        if (!is_not_empty_string($type)) {
            return $this->failed(ErrorCode::OAUTH_INVALID_PAYLOAD);
        }

        if (!array_key_exists($type, $this->topics)) {
            return $this->failed(ErrorCode::OAUTH_INVALID_PAYLOAD);
        }

        $siteId = $request->json('payload.siteId');

        $itemId = $request->json('payload.id');

        $ok = response()->json(['ok' => true]);

        if (!is_not_empty_string($siteId) || !is_not_empty_string($itemId)) {
            return $ok;
        }

        $key = sprintf('webflow-%s', $itemId);

        if (Cache::has($key)) {
            return $ok;
        }

        $tenantIds = Tenant::withoutEagerLoads()
            ->initialized()
            ->whereJsonContains('data->webflow_data->site_id', $siteId)
            ->pluck('id')
            ->toArray();

        foreach ($tenantIds as $tenantId) {
            event(new $this->topics[$type]($tenantId, $request->json('payload')));
        }

        return $ok;
    }
}
