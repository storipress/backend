<?php

namespace App\Console\Schedules\Daily;

use App\Console\Schedules\Command;
use App\Enums\Analyze\Type;
use App\Enums\Email\EmailUserType;
use App\Mail\SubscriberColdEmail;
use App\Models\Tenant;
use App\Models\Tenants\AiAnalysis;
use App\Models\Tenants\Article;
use App\Models\Tenants\Subscriber;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Throwable;

class SendColdEmailToSubscribers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscribers:cold-email:send {--tenants=*}';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = Tenant::withoutEagerLoads()
            ->with(['owner', 'owner.accessTokens'])
            ->initialized();

        if (! empty($this->option('tenants'))) {
            $query->whereIn('id', $this->option('tenants'));
        }

        $tenants = $query->lazyById(50);

        $api = app('http2')
            ->baseUrl($this->llm())
            ->timeout(120)
            ->withHeaders([
                'Origin' => rtrim(app_url('/'), '/'),
            ]);

        runForTenants(function (Tenant $tenant) use ($api) {
            if (! $tenant->has_prophet) {
                return;
            }

            if ($tenant->owner->id === 1521) {
                return; // Nathan, https://storipress.slack.com/archives/D016BGE64BB/p1719900442939079
            }

            $token = $tenant->owner->accessTokens->first()?->token;

            if ($token === null) {
                return;
            }

            $isGmailConnected = app()->environment('development') && $this->isConnectedToGmail($tenant->id);

            $skip = is_not_empty_string($tenant->mail_domain) ? [$tenant->mail_domain] : [];

            $signOff = trim($tenant->prophet_config['email']['sign_off'] ?? '');

            $hold = max((int) ($tenant->prophet_config['days_on_hold'] ?? 3), 1);

            $from = now()->subDays($hold)->startOfDay()->toImmutable();

            $to = $from->endOfDay();

            $input = [
                'company' => trim($tenant->prophet_config['company'] ?? '') ?: $tenant->name,
                'description' => trim($tenant->prophet_config['core_competency'] ?? ''),
                'publicationName' => $tenant->name,
                'goal' => '',
                'targetCompany' => '',
                'targetJobTitle' => '',
            ];

            $subscribers = Subscriber::withoutEagerLoads()
                ->with([
                    'parent',
                    'pain_point',
                    'events' => function (HasMany $query) use ($from) {
                        $query
                            ->where('name', 'like', 'article.%')
                            ->where('occurred_at', '>=', $from)
                            ->select('subscriber_id', 'target_id');
                    },
                ])
                ->where('id', '>', 0)
                ->where('newsletter', '=', true)
                ->whereBetween('created_at', [$from, $to])
                ->lazyById(50);

            foreach ($subscribers as $subscriber) {
                if ($subscriber->bounced) {
                    continue;
                }

                if (! empty($skip)) {
                    $domain = explode('@', $subscriber->email, 2)[1];

                    if (Str::contains($domain, $skip, true)) {
                        continue;
                    }
                }

                $ids = $subscriber->events->pluck('target_id')->toArray();

                if (empty($ids)) {
                    continue;
                }

                $points = AiAnalysis::withoutEagerLoads()
                    ->where('target_type', '=', Article::class)
                    ->whereIn('target_id', $ids)
                    ->where('type', '=', Type::articlePainPoints())
                    ->get()
                    ->flatMap(function (AiAnalysis $analysis) {
                        return array_column(
                            $analysis->data['insights'],
                            'pain_point',
                        );
                    })
                    ->filter()
                    ->values()
                    ->toArray();

                if (empty($points)) {
                    continue;
                }

                $input['targetName'] = trim($subscriber->first_name ?: $subscriber->email);

                if (empty($input['targetName'])) {
                    continue;
                }

                $input['painPoint'] = $points;

                $response = $api->withToken($token)->post('/', [
                    'type' => 'craft-email',
                    'data' => [
                        'system' => $input,
                        'human' => $input,
                    ],
                    'client_id' => $tenant->id,
                ]);

                if (! $response->ok()) {
                    continue;
                }

                $subject = $response->json('subject');

                $content = $response->json('content');

                if (! is_not_empty_string($subject) || ! is_not_empty_string($content)) {
                    continue;
                }

                $unsubscribeUrl = $this->unsubscribeUrl($tenant->id, $subscriber->id);

                $content = Str::of($content)
                    ->trim()
                    ->when(! empty($signOff), function (Stringable $string) use ($signOff) {
                        return $string->newLine(2)->append($signOff);
                    })
                    ->when(
                        $tenant->prophet_config['email']['unsubscribe_link'] ?? false,
                        fn (Stringable $value) => $value->newLine(3)->append(
                            sprintf('Unsubscribe ( %s )', $unsubscribeUrl),
                        ),
                    )
                    ->trim()
                    ->value();

                if ($isGmailConnected) {
                    app('http2')->withToken($this->jwt($tenant->id))->post('https://api.integration.app/connections/gmail/flows/prophet-send-cold-email/run', [
                        'input' => [
                            'to' => $subscriber->email,
                            'subject' => sprintf('=?utf-8?B?%s?=', base64_encode($subject)),
                            'body' => $content,
                            'unsubscribe_url' => $unsubscribeUrl,
                            'storipress' => encrypt([
                                'tenant_id' => $tenant->id,
                                'subscriber_id' => $subscriber->id,
                            ]),
                        ],
                    ]);
                } else {
                    $bcc = trim($tenant->prophet_config['email']['bcc'] ?? '');

                    $bcc = empty($bcc) ? ['alex@storipress.com'] : [$bcc, 'alex@storipress.com'];

                    Mail::to($subscriber->email)->bcc($bcc)->send(
                        new SubscriberColdEmail(
                            $subscriber->id,
                            $subject,
                            $content,
                        ),
                    );
                }
            }
        }, $tenants);

        return static::SUCCESS;
    }

    public function isConnectedToGmail(string $tenantId): bool
    {
        return is_not_empty_string($this->getConnectionId($tenantId));
    }

    public function getConnectionId(string $tenantId): ?string
    {
        try {
            // @phpstan-ignore-next-line
            return app('http2')
                ->withToken($this->jwt('N/A', true))
                ->get('https://api.integration.app/connections', [
                    'integrationKey' => 'gmail',
                    'userId' => $tenantId,
                    'isTest' => false,
                    'disconnected' => false,
                    'includeArchived' => false,
                ])
                ->json('items.0.id');
        } catch (Throwable) {
            return null;
        }
    }

    public function jwt(string $tenantId, bool $isAdmin = false): string
    {
        $jwt = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::base64Encoded(config('services.integration-app.signing_key')), // @phpstan-ignore-line
        );

        return $jwt
            ->builder()
            ->issuedBy(config('services.integration-app.workspace_key')) // @phpstan-ignore-line
            ->expiresAt(now()->addHour()->startOfSecond()->toImmutable())
            ->withClaim(
                $isAdmin ? 'isAdmin' : 'id',
                $isAdmin ? true : $tenantId,
            )
            ->getToken($jwt->signer(), $jwt->signingKey())
            ->toString();
    }

    public function llm(): string
    {
        if (app()->isProduction()) {
            return 'gpt-assistant-v2.storipress.workers.dev';
        }

        return 'gpt-assistant-v2-staging.storipress.workers.dev';
    }

    /**
     * Generate unsubscribe url.
     */
    protected function unsubscribeUrl(string $tenantId, int $subscriberId): string
    {
        $data = [
            'user_type' => EmailUserType::subscriber()->value,
            'user_id' => $subscriberId,
            'tenant' => $tenantId,
        ];

        return route('unsubscribe-from-mailing-list', [
            'payload' => encrypt($data),
        ]);
    }
}
