<?php

namespace App\Jobs;

use App\Builder\ReleaseEventsBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stancl\Tenancy\Exceptions\TenantCouldNotBeIdentifiedById;

final class InitializeSite implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @var array<string, string>
     */
    protected $data;

    /**
     * @var string|bool
     */
    protected $env;

    /**
     * Create a new job instance.
     *
     * @param  array<string, string>  $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;

        $this->env = app()->environment();
    }

    /**
     * Execute the job.
     *
     *
     * @throws TenantCouldNotBeIdentifiedById
     */
    public function handle(): void
    {
        if (! in_array($this->env, ['staging', 'development'], true)) {
            return;
        }

        tenancy()->initialize($this->data['id']);

        $builder = new ReleaseEventsBuilder();

        $builder->handle('site:initialize');

        tenancy()->end();
    }
}
