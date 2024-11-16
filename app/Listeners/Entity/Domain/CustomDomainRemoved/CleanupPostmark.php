<?php

namespace App\Listeners\Entity\Domain\CustomDomainRemoved;

use App\Enums\CustomDomain\Group;
use App\Events\Entity\Domain\CustomDomainRemoved;
use App\Models\Tenant;
use Illuminate\Support\Str;
use Postmark\Models\PostmarkException;
use Throwable;

use function Sentry\captureException;

class CleanupPostmark
{
    /**
     * Handle the event.
     */
    public function handle(CustomDomainRemoved $event): void
    {
        $tenant = Tenant::withTrashed()->find($event->tenantId);

        if (!($tenant instanceof Tenant)) {
            return;
        }

        $postmarkId = $tenant->postmark_id;

        if (empty($postmarkId)) {
            $postmarkId = $tenant->postmark['id'] ?? null;

            if (!is_int($postmarkId)) {
                $postmarkId = null;
            }
        }

        if (!empty($postmarkId)) {
            try {
                app('postmark.account')->deleteDomain($postmarkId);
            } catch (PostmarkException $e) {
                if (!Str::contains($e->getMessage(), 'This domain was not found', true)) {
                    captureException($e);
                }
            } catch (Throwable $e) {
                captureException($e);
            }
        }

        $tenant->update([
            'postmark' => null,
            'postmark_id' => null,
            'mail_domain' => null,
        ]);

        $tenant->custom_domains()
            ->where('group', '=', Group::mail())
            ->delete();
    }
}
