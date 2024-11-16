<?php

namespace App\Jobs\Subscriber;

use App\Models\Subscriber;
use App\Models\Tenant;
use App\Models\Tenants\Subscriber as TenantSubscriber;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Csv\Exception;
use League\Csv\Reader;
use League\Csv\SyntaxError;
use League\Csv\UnavailableStream;
use RuntimeException;
use Segment\Segment;
use Throwable;

/**
 * @phpstan-type TValidation array{
 *     email: string,
 *     verdict: 'Valid'|'Risky'|'Invalid',
 *     score: double,
 *     local: string,
 *     host: string,
 *     checks: mixed,
 *     ip_address: string,
 * }
 */
class ImportSubscribersFromCsvFile implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    /**
     * CSV header info.
     *
     * @var array<int, string>
     */
    protected array $header = [];

    /**
     * Subscriber email.
     */
    protected int $email = -1;

    /**
     * Subscriber first name.
     */
    protected int $firstName = -1;

    /**
     * Subscriber last name.
     */
    protected int $lastName = -1;

    /**
     * Subscriber email verified at.
     */
    protected int $verifiedAt = -1;

    /**
     * Subscriber enable newsletter or not.
     */
    protected int $newsletter = -1;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected string $tenantId,
        protected string $path,
    ) {
        //
    }

    /**
     * Execute the job.
     *
     * @throws UnavailableStream
     * @throws Exception
     */
    public function handle(): void
    {
        $stream = Storage::drive('nfs')->readStream($this->path);

        if ($stream === null) {
            throw new RuntimeException('Unable to read file stream.');
        }

        $csv = Reader::createFromStream($stream);

        $csv->setHeaderOffset(0);

        try {
            $this->header = $csv->getHeader();
        } catch (SyntaxError) {
            //
        }

        if (empty($this->header)) {
            throw new RuntimeException('Missing header line for CSV file.');
        }

        $this->parseHeader();

        if ($this->email === -1) {
            throw new RuntimeException('Missing email field for CSV file.');
        }

        $csv->setHeaderOffset(null);

        $tenant = Tenant::find($this->tenantId);

        if (! ($tenant instanceof Tenant)) {
            return;
        }

        $tenant->run(function (Tenant $tenant) use ($csv) {
            $reports = [
                'import' => 0,
                'new' => 0,
            ];

            /** @var array<int, string> $record */
            foreach ($csv->getRecords() as $idx => $record) {
                if ($idx === 0) {
                    continue;
                }

                $email = filter_var(trim($record[$this->email]), FILTER_VALIDATE_EMAIL);

                if (empty($email) || ! is_string($email)) {
                    continue;
                }

                $verifiedAt = null;

                if ($this->verifiedAt === -1 || empty($verifiedAt = trim($record[$this->verifiedAt]))) {
                    $verifiedAt = null;
                } else {
                    try {
                        if (ctype_digit($verifiedAt)) {
                            $verifiedAt = Carbon::createFromTimestampUTC($verifiedAt);
                        } else {
                            $verifiedAt = Carbon::parse($verifiedAt);
                        }
                    } catch (InvalidFormatException) {
                        $verifiedAt = null;
                    }
                }

                $subscriber = Subscriber::firstOrCreate([
                    'email' => $email,
                ], [
                    'first_name' => trim($record[$this->firstName] ?? '') ?: null,
                    'last_name' => trim($record[$this->lastName] ?? '') ?: null,
                    'verified_at' => $verifiedAt,
                ]);

                if (empty($subscriber->validation)) {
                    $validation = $this->validateEmail($subscriber->email);

                    $subscriber->update([
                        'bounced' => ($validation['verdict'] ?? '') === 'Invalid',
                        'validation' => $validation,
                    ]);
                }

                $tenantSubscriber = TenantSubscriber::firstOrCreate([
                    'id' => $subscriber->id,
                ], [
                    'signed_up_source' => 'import',
                    'newsletter' => true,
                ]);

                if ($tenantSubscriber->wasRecentlyCreated) {
                    $reports['import']++;
                }

                if ($subscriber->wasRecentlyCreated) {
                    $reports['new']++;
                }
            }

            Segment::track([
                'userId' => (string) $tenant->owner->id,
                'event' => 'tenant_subscribers_imported',
                'properties' => [
                    'tenant_uid' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'imported_subscribers' => $reports['import'],
                    'recently_created_subscribers' => $reports['new'],
                ],
                'context' => [
                    'groupId' => $tenant->id,
                ],
            ]);
        });
    }

    /**
     * Mapping csv header.
     */
    protected function parseHeader(): void
    {
        $patterns = [
            'email' => ['email'],
            'firstName' => ['first_name', 'first name'],
            'lastName' => ['last_name', 'last name'],
            'verifiedAt' => ['confirm_time', 'verified at'],
            'newsletter' => [],
        ];

        foreach ($this->header as $idx => $name) {
            foreach ($patterns as $key => $pattern) {
                if ($this->{$key} !== -1) {
                    continue;
                }

                if (! Str::contains($name, $pattern, true)) {
                    continue;
                }

                $this->{$key} = $idx;

                break;
            }
        }
    }

    /**
     * @return TValidation|null
     */
    protected function validateEmail(string $email): ?array
    {
        try {
            /** @var TValidation $result */
            $result = app('sendgrid')
                ->post('/validations/email', ['email' => $email])
                ->json('result');

            return $result;
        } catch (Throwable) {
            return null;
        }
    }
}
