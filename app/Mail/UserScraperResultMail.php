<?php

namespace App\Mail;

class UserScraperResultMail extends Mailable
{
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(
        protected string $token,
        protected int $articlesCount,
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function server(): string
    {
        return 'app_server_token';
    }

    /**
     * {@inheritdoc}
     */
    protected function id(): int
    {
        return 29596971;
    }

    /**
     * {@inheritdoc}
     */
    protected function sender(): array
    {
        return $this->fromStoripress();
    }

    /**
     * {@inheritdoc}
     */
    protected function data(): array
    {
        return [
            'imported_articles' => number_format($this->articlesCount),
            'action_url' => $this->actionUrl(
                path: sprintf('/%s/articles/desks/all', $this->client),
                queries: [
                    'scraper-token' => $this->token,
                ],
            ),
        ];
    }
}
