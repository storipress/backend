<?php

namespace App\Mail;

use App\Enums\Email\EmailUserType;
use App\Models\SpamEmail;
use App\Models\Subscriber;
use App\Models\Tenant;
use App\Models\User;
use CraigPaul\Mail\PostmarkServerTokenHeader;
use CraigPaul\Mail\PostmarkTransportException;
use CraigPaul\Mail\TemplatedMailable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Mail\Factory;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\SentMessage;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;
use Webmozart\Assert\Assert;

abstract class Mailable extends TemplatedMailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /**
     * Tenant client id.
     */
    protected ?string $client = null;

    /**
     * Publication name.
     */
    protected ?string $publication = null;

    /**
     * Publication support email address.
     */
    protected ?string $supportEmail = null;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct()
    {
        /** @var Tenant|null $tenant */
        $tenant = tenant();

        if ($tenant !== null) {
            $this->client = $tenant->getTenantKey();

            $this->publication = $tenant->name;

            $this->supportEmail = $tenant->email;
        }
    }

    /**
     * Send the message using the given mailer.
     *
     * @param  Factory|Mailer  $mailer
     */
    public function send($mailer): ?SentMessage
    {
        if (empty($this->to) && empty($this->cc) && empty($this->bcc)) {
            Log::warning('Missing recipient', debug_backtrace(limit: 10));

            return null;
        }

        if ($this instanceof UserInviteMail) {
            foreach ($this->to as $data) {
                if (Str::contains($data['address'], '@storipress.com', true)) {
                    if (Str::startsWith($data['address'], 'e2e')) {
                        return null;
                    }
                }
            }
        }

        $blackList = $this->getBlackList();

        foreach ($this->to as $data) {
            if (in_array($data['address'], $blackList, true)) {
                Log::debug(sprintf('Skipping blacklisted email address: %s', $data['address']));

                return null;
            }
        }

        $mail = null;

        try {
            if ($this instanceof SubscriberNewsletterMail || $this instanceof SubscriberColdEmail) {
                config([
                    'mail.mailers.postmark.message_stream_id' => 'broadcast',
                ]);
            }

            $mail = parent::send($mailer);
        } catch (PostmarkTransportException $e) {
            // add to black list
            /** @var array{array{name:string, address:string}} $to */
            $to = $this->to;

            $message = $e->getMessage();

            if (Str::contains($message, 'Found inactive addresses')) {
                /** @var string[] $emails */
                $emails = array_column($to, 'address');

                $this->addToBlackList($emails);

                Log::channel('slack')->debug(
                    'Found inactive addresses: adding to black list',
                    [
                        'env' => app()->environment(),
                        'emails' => $emails,
                    ],
                );

                Log::debug('Found inactive addresses', [
                    'emails' => $emails,
                ]);
            }
        } finally {
            config([
                'mail.mailers.postmark.message_stream_id' => 'outbound',
            ]);
        }

        if ($mail !== null) {
            $to = $mail->getEnvelope()->getRecipients()[0]->getAddress();

            $model = ($this instanceof SubscriberMailable) ? new Subscriber() : new User();

            \App\Models\Email::create(array_merge([
                'tenant_id' => $this->client ?: 'N/A',
                'user_id' => $model->where('email', '=', $to)->first()?->id ?: 0,
                'user_type' => ($this instanceof SubscriberMailable) ? EmailUserType::subscriber() : EmailUserType::user(),
                'message_id' => $mail->getMessageId(),
                'template_id' => $this->id(),
                'from' => $mail->getEnvelope()->getSender()->getAddress(),
                'to' => $to,
                'data' => $this->data(),
                'subject' => $this->subject ?: 'N/A',
                'content' => $mail->toString(),
            ], $this->target()));
        }

        return $mail;
    }

    /**
     * Build the message.
     */
    public function build(): self
    {
        $this->withSymfonyMessage(function (Email $message) {
            $key = sprintf('services.postmark.%s', $this->server());

            $token = config($key);

            Assert::stringNotEmpty($token);

            $message->getHeaders()->add(
                new PostmarkServerTokenHeader($token),
            );
        });

        /** @var self $mail */
        $mail = tenancy()->central(function () {
            return parent::build()
                ->from(...$this->sender())
                ->identifier($this->id())
                ->include($this->data());
        });

        return $mail;
    }

    /**
     * Sender address and name.
     *
     * @return array<int, string>
     */
    protected function sender(): array
    {
        $sender = $this->fromCustomDomain();

        if ($sender !== null) {
            return $sender;
        }

        return $this->fromStoripress(true);
    }

    /**
     * @return array<int, string>|null
     */
    protected function fromCustomDomain(): ?array
    {
        $tenant = Tenant::with(['owner'])->find($this->client);

        if (! ($tenant instanceof Tenant)) {
            return null;
        }

        $from = trim($tenant->prophet_config['email']['bcc'] ?? '') ?: $tenant->email;

        $name = ($this instanceof SubscriberColdEmail) ? ($tenant->owner->name ?: $tenant->name) : $tenant->name;

        if (! empty($tenant->mail_domain)) {
            if ($from && Str::endsWith($from, '@'.$tenant->mail_domain)) {
                return [$from, $name];
            }

            $email = sprintf('noreply@%s', $tenant->mail_domain);

            return [$email, $name];
        }

        if (
            $tenant->custom_domain !== null &&
            ! empty($tenant->postmark) &&
            ! empty($tenant->postmark['dkimverified']) &&
            ! empty($tenant->postmark['returnpathdomainverified'])
        ) {
            if ($from && Str::endsWith($from, '@'.$tenant->custom_domain)) {
                return [$from, $name];
            }

            $email = sprintf('noreply@%s', $tenant->custom_domain);

            return [$email, $name];
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    protected function fromStoripressAlternative(bool $usePublication = false): array
    {
        return [
            'noreply@storipress.xyz',
            ($usePublication ? $this->publication : '') ?: 'Storipress',
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function fromStoripress(bool $usePublication = false): array
    {
        return [
            'noreply@storipress.com',
            ($usePublication ? $this->publication : '') ?: 'Storipress',
        ];
    }

    /**
     * Get publication site url.
     */
    protected function siteUrl(): ?string
    {
        /** @var Tenant|null $tenant */
        $tenant = Tenant::find($this->client);

        if ($tenant === null) {
            return null;
        }

        return sprintf('https://%s', $tenant->url);
    }

    /**
     * Email action url.
     *
     * @param  string[]  $queries
     */
    protected function actionUrl(string $path, array $queries): string
    {
        $host = match (app()->environment()) {
            'local' => 'http://localhost:3333',
            'development' => 'https://storipress.dev',
            'staging' => 'https://storipress.pro',
            default => 'https://stori.press',
        };

        $queries['signature'] = hmac($queries);

        return sprintf(
            '%s/%s?%s',
            $host,
            ltrim($path, '/'),
            http_build_query($queries),
        );
    }

    /**
     * @return array{target_id: int|string|null, target_type: string|null}
     */
    protected function target(): array
    {
        return [
            'target_id' => null,
            'target_type' => null,
        ];
    }

    /**
     * Postmark server token.
     */
    abstract protected function server(): string;

    /**
     * Postmark template id.
     */
    abstract protected function id(): int;

    /**
     * Postmark template data.
     *
     * @return mixed[]
     */
    abstract protected function data(): array;

    /**
     * a list of mail addresses
     *
     * @return string[]
     */
    protected function getBlackList(): array
    {
        /** @var string[] $emails */
        $emails = SpamEmail::where('expired_at', '>=', now())
            ->pluck('email')
            ->all();

        return $emails;
    }

    /**
     * Add email to black list.
     *
     * @param  string[]  $emails
     */
    protected function addToBlackList(array $emails): void
    {
        foreach ($emails as $email) {
            /** @var SpamEmail|null $spamEmail */
            $spamEmail = SpamEmail::where('email', $email)->first();

            if ($spamEmail === null) {
                SpamEmail::create([
                    'email' => $email,
                    'times' => 1,
                    'expired_at' => now()->addDay(),
                ]);

                return;
            }

            $records = [time() => $spamEmail->expired_at] + ($spamEmail->records ?: []);

            $spamEmail->update([
                'times' => $spamEmail->times + 1,
                'records' => $records,
                'expired_at' => now()->addDays($spamEmail->ban_days),
            ]);
        }
    }
}
