<?php

declare(strict_types=1);

namespace App\Http\Controllers\Partners\WordPress;

use App\Events\Partners\WordPress\Webhooks\CategoryCreated;
use App\Events\Partners\WordPress\Webhooks\CategoryDeleted;
use App\Events\Partners\WordPress\Webhooks\CategoryEdited;
use App\Events\Partners\WordPress\Webhooks\PluginUpgraded;
use App\Events\Partners\WordPress\Webhooks\PostDeleted;
use App\Events\Partners\WordPress\Webhooks\PostSaved;
use App\Events\Partners\WordPress\Webhooks\TagCreated;
use App\Events\Partners\WordPress\Webhooks\TagDeleted;
use App\Events\Partners\WordPress\Webhooks\TagEdited;
use App\Events\Partners\WordPress\Webhooks\UserCreated;
use App\Events\Partners\WordPress\Webhooks\UserDeleted;
use App\Events\Partners\WordPress\Webhooks\UserEdited;
use App\Models\Tenant;
use App\Models\Tenants\Integrations\WordPress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EventsController
{
    /**
     * @var array<string, array{
     *     event: class-string,
     *     rule: array<string, string>
     * }>
     */
    public array $topics = [
        'post_saved' => [
            'event' => PostSaved::class,
            'rule' => [
                'post_id' => 'required|int',
            ],
        ],
        'post_deleted' => [
            'event' => PostDeleted::class,
            'rule' => [
                'post_id' => 'required|int',
            ],
        ],
        'tag_created' => [
            'event' => TagCreated::class,
            'rule' => [
                'term_id' => 'required|int',
            ],
        ],
        'tag_edited' => [
            'event' => TagEdited::class,
            'rule' => [
                'term_id' => 'required|int',
            ],
        ],
        'tag_deleted' => [
            'event' => TagDeleted::class,
            'rule' => [
                'term_id' => 'required|int',
            ],
        ],
        'category_created' => [
            'event' => CategoryCreated::class,
            'rule' => [
                'term_id' => 'required|int',
            ],
        ],
        'category_edited' => [
            'event' => CategoryEdited::class,
            'rule' => [
                'term_id' => 'required|int',
            ],
        ],
        'category_deleted' => [
            'event' => CategoryDeleted::class,
            'rule' => [
                'term_id' => 'required|int',
            ],
        ],
        'user_created' => [
            'event' => UserCreated::class,
            'rule' => [
                'user_id' => 'required|int',
            ],
        ],
        'user_edited' => [
            'event' => UserEdited::class,
            'rule' => [
                'user_id' => 'required|int',
            ],
        ],
        'user_deleted' => [
            'event' => UserDeleted::class,
            'rule' => [
                'user_id' => 'required|int',
                'reassign' => 'nullable|int',
            ],
        ],
        'plugin_upgraded' => [
            'event' => PluginUpgraded::class,
            'rule' => [
                'version' => 'required|string',
                'url' => 'required|string',
                'site_name' => 'required|string',
                'rest_prefix' => 'required',
                'permalink_structure' => 'required',
            ],
        ],
    ];

    public Tenant $tenant;

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $topic = $request->input('topic');

        if (! isset($this->topics[$topic])) {
            return response()->json(['ok' => false]);
        }

        $rule = $this->topics[$topic]['rule'];

        $validator = Validator::make($request->all(), array_merge([
            'topic' => 'required|string',
            'client' => 'required|string',
        ], $rule));

        if ($validator->fails()) {
            return response()->json(['ok' => false]);
        }

        $tenantId = $request->input('client');

        if (! is_not_empty_string($tenantId)) {
            return response()->json(['ok' => false]);
        }

        if (! $this->verifySignature($request, $tenantId)) {
            return response()->json(['ok' => false]);
        }

        $tenant = $this->tenant($tenantId);

        if (! ($tenant instanceof Tenant)) {
            return response()->json(['ok' => false]);
        }

        $activated = $tenant->run(function () use ($topic) {
            $wordpress = WordPress::retrieve();

            if (! $wordpress->is_connected) {
                return false;
            }

            if ($topic !== 'plugin_upgraded' && version_compare($wordpress->config->version, '0.0.14', '<')) {
                return false;
            }

            return true;
        });

        if (! $activated) {
            return response()->json(['ok' => false]);
        }

        $payload = $request->all();

        $event = $this->topics[$topic]['event'];

        event(new $event($tenantId, $payload));

        return response()->json(['ok' => true]);
    }

    public function tenant(string $tenantId): Tenant|false
    {
        if (! isset($this->tenant)) {
            $tenant = Tenant::initialized()
                ->withoutEagerLoads()
                ->find($tenantId);

            if (! ($tenant instanceof Tenant)) {
                return false;
            }

            $this->tenant = $tenant;
        }

        return $this->tenant;
    }

    public function verifySignature(Request $request, string $tenantId): bool
    {
        $timestamp = $request->header('X-Storipress-Timestamp');

        if (! is_not_empty_string($timestamp)) {
            return false;
        }

        // dismiss if timestamp exceeds 5 minutes
        if (((now()->getTimestamp() - (int) $timestamp)) > 5 * 60) {
            return false;
        }

        $tenant = $this->tenant($tenantId);

        if (! ($tenant instanceof Tenant)) {
            return false;
        }

        $secret = $tenant->wordpress_data['hash_key'] ?? null;

        if (! is_not_empty_string($secret)) {
            return false;
        }

        $signature = $request->header('X-Storipress-Signature');

        if (! is_not_empty_string($signature) || strlen($signature) !== 64) {
            return false;
        }

        $payload = $request->all();

        ksort($payload);

        $data = json_encode($payload);

        if ($data === false) {
            return false;
        }

        $known = hash_hmac(
            'sha256',
            $data,
            $secret,
        );

        return hash_equals($known, $signature);
    }
}
