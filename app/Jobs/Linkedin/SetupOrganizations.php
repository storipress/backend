<?php

declare(strict_types=1);

namespace App\Jobs\Linkedin;

use App\Models\Tenant;
use App\Models\Tenants\Integration;
use App\SDK\LinkedIn\LinkedIn;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

use function Sentry\captureException;

class SetupOrganizations implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $tenantId,
    ) {
        //
    }

    /**
     * Handle the given event.
     */
    public function handle(): void
    {
        $tenant = Tenant::withoutEagerLoads()
            ->initialized()
            ->find($this->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function () {
            try {
                $linkedin = Integration::where('key', 'linkedin')->sole();

                /** @var array{
                 *     setup_organizations: boolean,
                 *     access_token: string,
                 *     authors: array{array{
                 *      id: string,
                 *      name: string,
                 *      thumbnail: string
                 *     }}
                 * }|null $configuration
                 */
                $configuration = $linkedin->internals;

                if ($configuration === null) {
                    return;
                }

                /** @var array{
                 *     setup_organizations: boolean,
                 *     authors: array{array{
                 *      id: string,
                 *      name: string,
                 *      thumbnail: string
                 *     }}
                 * } $data
                 */
                $data = $linkedin->data;

                $token = $configuration['access_token'];

                $organizations = (new LinkedIn())->getOrganizations($token);

                $authors = $configuration['authors'];

                $authors = array_merge($authors, $organizations);

                $configuration['authors'] = $authors;

                $configuration['setup_organizations'] = true;

                $data['authors'] = $authors;

                $data['setup_organizations'] = true;

                $linkedin->update([
                    'data' => $data,
                    'internals' => $configuration,
                ]);
            } catch (Throwable $e) {
                captureException($e);
            }
        });
    }
}
