<?php

namespace App\Jobs\Tenants;

use App\Models\Tenant;
use App\Models\Tenants\Article;
use App\Models\Tenants\Desk;
use App\Observers\WebhookPushingObserver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Webmozart\Assert\Assert;

class ImportTutorialContent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected TenantWithDatabase $tenant;

    /**
     * Create a new job instance.
     */
    public function __construct(TenantWithDatabase $tenant)
    {
        $this->tenant = $tenant;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // @todo check models' events will trigger rebuild or not

        WebhookPushingObserver::mute();

        Article::disableSearchSyncing();

        Assert::isInstanceOf($this->tenant, Tenant::class);

        $userId = $this->tenant->owner->id;

        Assert::integerish($userId);

        $this->tenant->run(function () use ($userId) {
            Desk::create(['name' => 'Drag a card here']);

            $desk = Desk::create(['name' => 'Delete me']);

            $now = now();

            $shared = $this->shared();

            $document = $this->content();

            foreach ($this->data() as $datum) {
                $article = Article::create(array_merge(
                    $shared,
                    Arr::only($datum, ['title', 'slug', 'stage_id']),
                    [
                        'desk_id' => $desk->id,
                        'document' => array_merge($document, ['title' => $datum['title_doc']]),
                        'encryption_key' => base64_encode(random_bytes(32)),
                        'published_at' => $datum['published'] ? $now : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                ));

                $article->authors()->attach($userId);
            }

            $desk->articles()->searchable();
        });

        Article::enableSearchSyncing();

        WebhookPushingObserver::unmute();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function data(): array
    {
        return [
            [
                'title' => '<p>New articles start here ☝️ Click me first!</p>',
                'slug' => 'new-articles-start-here-click-me',
                'stage_id' => 1,
                'published' => false,
                'title_doc' => json_decode(<<<'EOD'
{"type":"doc","content":[{"type":"paragraph","content":[{"text":"New articles start here ☝️ Click me first!","type":"text"}]}]}
EOD),
            ],
            [
                'title' => '<p>👈 Drag me to the sidebar to change my desk (category)</p>',
                'slug' => 'drag-me-to-the-sidebar-to-change-my-desk-category',
                'stage_id' => 1,
                'published' => false,
                'title_doc' => json_decode(<<<'EOD'
{"type":"doc","content":[{"type":"paragraph","content":[{"text":"👈 Drag me to the sidebar to change my desk (category)","type":"text"}]}]}
EOD),
            ],
            [
                'title' => '<p>📆 To schedule content, click &#039;Schedule&#039; in the navbar</p>',
                'slug' => 'to-schedule-content-click-schedule-in-the-navbar',
                'stage_id' => 2,
                'published' => false,
                'title_doc' => json_decode(<<<'EOD'
{"type":"doc","content":[{"type":"paragraph","content":[{"text":"📆 To schedule content, click 'Schedule' in the navbar","type":"text"}]}]}
EOD),
            ],
            [
                'title' => '<p>❗️ Drag me to another column to change my stage</p>',
                'slug' => 'drag-me-to-another-column-to-change-my-stage',
                'stage_id' => 2,
                'published' => false,
                'title_doc' => json_decode(<<<'EOD'
{"type":"doc","content":[{"type":"paragraph","content":[{"text":"❗️ Drag me to another column to change my stage","type":"text"}]}]}
EOD),
            ],
            [
                'title' => '<p>✅ Only articles in this stage will publish as scheduled</p>',
                'slug' => 'only-final-stage-articles-will-publish-as-scheduled',
                'stage_id' => 3,
                'published' => false,
                'title_doc' => json_decode(<<<'EOD'
{"type":"doc","content":[{"type":"paragraph","content":[{"text":"✅ Only articles in this stage will publish as scheduled","type":"text"}]}]}
EOD),
            ],
            [
                'title' => '<p>Drag me to Published to publish instantly 👉</p>',
                'slug' => 'drag-me-to-published-to-publish-instantly',
                'stage_id' => 3,
                'published' => false,
                'title_doc' => json_decode(<<<'EOD'
{"type":"doc","content":[{"type":"paragraph","content":[{"text":"Drag me to Published to publish instantly 👉","type":"text"}]}]}
EOD),
            ],
            [
                'title' => '<p>⚙️ Go to desk settings on bottom left to delete tutorial</p>',
                'slug' => 'go-to-desk-settings-on-bottom-left-to-delete-tutorial',
                'stage_id' => 3,
                'published' => true,
                'title_doc' => json_decode(<<<'EOD'
{"type":"doc","content":[{"type":"paragraph","content":[{"text":"⚙️ Go to desk settings on bottom left to delete tutorial","type":"text"}]}]}
EOD),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function content(): array
    {
        return [
            'default' => json_decode(<<<'EOD'
{"type":"doc","content":[{"type":"paragraph","content":[{"text":"Welcome to Storipress' ","type":"text"},{"text":"collaborative real-time","type":"text","marks":[{"type":"bold","attrs":[]}]},{"text":" editor. We think it's the best editor in the world. Why? Well, unlike other editors you've used like Google Docs, ","type":"text"},{"text":"Storipress is designed to push content to the Web, not to a printer.","type":"text","marks":[{"type":"bold","attrs":[]}]}]},{"type":"paragraph","content":[{"text":"So, what does that mean? Well, let's discover its features 👇","type":"text"}]},{"type":"resource","attrs":{"url":"https:\/\/www.youtube.com\/watch?v=Hr9ECcdigEo","meta":"{\"title\":\"LET ME SHOW YOU ITS FEATURES\",\"description\":\"I know people want this.\",\"author\":\"Sm1ley\",\"icon\":\"https:\/\/www.youtube.com\/s\/desktop\/afaf5292\/img\/favicon_32x32.png\",\"publisher\":\"YouTube\",\"url\":\"https:\/\/www.youtube.com\/watch?v=Hr9ECcdigEo\",\"thumbnail\":\"https:\/\/i.ytimg.com\/vi\/Hr9ECcdigEo\/hqdefault.jpg\",\"html\":\"<div><div style=\\\"left: 0; width: 100%; height: 0; position: relative; padding-bottom: 56.25%;\\\"><iframe data-iframely-url=\\\"https:\/\/cdn.iframe.ly\/api\/iframe?playerjs=1&url=https%3A%2F%2Fwww.youtube.com%2Fwatch%3Fv%3DHr9ECcdigEo&key=6d002d15348823942403bf5e779d2cca\\\" style=\\\"top: 0; left: 0; width: 100%; height: 100%; position: absolute; border: 0;\\\" allowfullscreen scrolling=\\\"no\\\" allow=\\\"autoplay *; accelerometer *; clipboard-write *; encrypted-media *; gyroscope *; picture-in-picture *; web-share *;\\\"><\/iframe><\/div><\/div>\",\"iframe0\":\"<div style=\\\"left: 0; width: 100%; height: 0; position: relative; padding-bottom: 56.25%;\\\"><iframe src=\\\"https:\/\/www.youtube.com\/embed\/Hr9ECcdigEo?rel=0\\\" style=\\\"top: 0; left: 0; width: 100%; height: 100%; position: absolute; border: 0;\\\" allowfullscreen scrolling=\\\"no\\\" allow=\\\"accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share;\\\"><\/iframe><\/div>\",\"aspectRadio\":1}","type":"embed","caption":"","showMenu":false}},{"type":"paragraph"},{"type":"heading","attrs":{"level":2},"content":[{"text":"🌆 Cards and Embeds","type":"text"}]},{"type":"heading","attrs":{"level":3},"content":[{"text":"Embeds","type":"text"}]},{"type":"paragraph","content":[{"text":"How was the above YouTube embed created? ","type":"text"},{"text":"Just paste a link in a new line","type":"text","marks":[{"type":"bold","attrs":[]}]},{"text":" 👇","type":"text"}]},{"type":"image","attrs":{"alt":"","src":"https:\/\/assets.stori.press\/storipress\/tutorial\/2023-001\/cards-and-embeds.gif","link":"","type":"regular","title":"","source":[]}},{"type":"paragraph","content":[{"text":"Storipress supports over 3,000+ sites","type":"text","marks":[{"type":"bold","attrs":[]}]},{"text":" so paste any link and we'll try to embed it!","type":"text"}]},{"type":"paragraph","content":[{"text":"You can also insert rich media blocks in two other ways:","type":"text"}]},{"type":"orderedList","attrs":{"start":1},"content":[{"type":"listItem","content":[{"type":"paragraph","content":[{"text":"By clicking the plus button on the left when you hover over text, or","type":"text"}]}]},{"type":"listItem","content":[{"type":"paragraph","content":[{"text":"By using slash commands (which we'll get into later)","type":"text"}]}]}]},{"type":"paragraph"},{"type":"heading","attrs":{"level":2},"content":[{"text":"🤖 Storipress AI + Spellcheck","type":"text"}]},{"type":"heading","attrs":{"level":3},"content":[{"text":"Storipress AI","type":"text"}]},{"type":"paragraph","content":[{"text":"Storipress AI creates content based on your prompts and current article context. ","type":"text"},{"text":"Pull up Storipress AI in two ways:","type":"text","marks":[{"type":"bold","attrs":[]}]}]},{"type":"orderedList","attrs":{"start":1},"content":[{"type":"listItem","content":[{"type":"paragraph","content":[{"text":"To improve existing content,","type":"text","marks":[{"type":"bold","attrs":[]}]},{"text":" highlight text and select Ask AI. Then, pick an option from the dropdown or write a custom prompt.","type":"text"}]}]},{"type":"listItem","content":[{"type":"paragraph","content":[{"text":"To draft new text,","type":"text","marks":[{"type":"bold","attrs":[]}]},{"text":" use the ","type":"text"},{"text":"space","type":"text","marks":[{"type":"code","attrs":[]}]},{"text":" key on a new line and enter any prompt.","type":"text"}]}]}]},{"type":"image","attrs":{"alt":"","src":"https:\/\/assets.stori.press\/storipress\/tutorial\/2023-001\/storipress-ai-spellcheck.gif","link":"","type":"regular","title":"","source":[]}},{"type":"paragraph"},{"type":"heading","attrs":{"level":3},"content":[{"text":"AI Spellcheck","type":"text"}]},{"type":"paragraph","content":[{"text":"Powered by ","type":"text"},{"text":"Grammarly","type":"text","marks":[{"type":"bold","attrs":[]}]},{"text":", Storipress' spellcheck underlines typos and offers suggestions for how to improve your writing.","type":"text"}]},{"type":"paragraph","content":[{"text":"If you're a Grammarly customer","type":"text","marks":[{"type":"bold","attrs":[]}]},{"text":", you can even connect your account to Storipress by clicking the Grammarly button at the bottom left.","type":"text"}]},{"type":"image","attrs":{"alt":"","src":"https:\/\/assets.stori.press\/storipress\/tutorial\/2023-001\/ai-spellcheck.png","link":"","type":"regular","title":"","source":[]}},{"type":"paragraph","content":[{"text":"If you're not seeing the Grammarly button, it's as you've installed the Grammarly browser or computer extension. To use the native Storipress integration, disable the extensions for the stori.press domain. ","type":"text","marks":[{"type":"italic","attrs":[]}]}]},{"type":"paragraph","content":[]},{"type":"heading","attrs":{"level":2},"content":[{"text":"📣 Automatically Share Articles to Socials","type":"text"}]},{"type":"paragraph","content":[{"text":"At the top of the editor, click the social sharing tab to connect your social accounts.","type":"text"}]},{"type":"image","attrs":{"alt":"","src":"https:\/\/assets.stori.press\/storipress\/tutorial\/2023-001\/social-sharing-1.png","link":"","type":"regular","title":"","source":[]}},{"type":"paragraph","content":[{"text":"In this pane, you can draft social posts right within Storipress so that when your article goes live, they're also shared across all your channels.","type":"text"}]},{"type":"image","attrs":{"alt":"","src":"https:\/\/assets.stori.press\/storipress\/tutorial\/2023-001\/social-sharing-2.gif","link":"","type":"regular","title":"","source":[]}},{"type":"paragraph"},{"type":"heading","attrs":{"level":2},"content":[{"text":"⌨️ Never leave your keyboard","type":"text"}]},{"type":"paragraph","content":[{"text":"With a wide range of keyboard commands, you never need to touch your mouse. ","type":"text"}]},{"type":"heading","attrs":{"level":3},"content":[{"text":"Shortcuts","type":"text"}]},{"type":"paragraph","content":[{"text":"On top of the standard italic\/bold shortcuts, there are additional shortcuts to try:","type":"text"}]},{"type":"bulletList","attrs":{"type":"dash"},"content":[{"type":"listItem","content":[{"type":"paragraph","content":[{"text":"If on Mac","type":"text","marks":[{"type":"italic","attrs":[]}]},{"text":": Press ","type":"text"},{"text":"⌘ + option + 1\/2\/3\/4\/0","type":"text","marks":[{"type":"bold","attrs":[]}]}]}]},{"type":"listItem","content":[{"type":"paragraph","content":[{"text":"If on Windows","type":"text","marks":[{"type":"italic","attrs":[]}]},{"text":": Press ","type":"text"},{"text":"ctrl + shift + 1\/2\/3\/4\/0","type":"text","marks":[{"type":"bold","attrs":[]}]}]}]}]},{"type":"paragraph","content":[{"text":"Give it a whirl!","type":"text"}]},{"type":"heading","attrs":{"level":3},"content":[{"text":"Slash Commands","type":"text"}]},{"type":"image","attrs":{"alt":"","src":"https:\/\/assets.stori.press\/storipress\/tutorial\/2023-001\/slash-command.png","link":"","type":"regular","title":"","source":[]}},{"type":"paragraph","content":[{"text":"Storipress also supports slash commands to insert blocks or embeds. ","type":"text"}]},{"type":"orderedList","attrs":{"start":1},"content":[{"type":"listItem","content":[{"type":"paragraph","content":[{"text":"On a new line","type":"text","marks":[{"type":"bold","attrs":[]}]},{"text":", press ","type":"text"},{"text":"\/","type":"text","marks":[{"type":"bold","attrs":[]}]},{"text":" (forward-slash) to activate the slash menu","type":"text"}]}]},{"type":"listItem","content":[{"type":"paragraph","content":[{"text":"Type the name of the block you want to insert","type":"text"}]}]},{"type":"listItem","content":[{"type":"paragraph","content":[{"text":"Click Enter or Return","type":"text"}]}]}]},{"type":"paragraph","content":[{"text":"Boom! New block. Without ever having to touch your keyboard.","type":"text"}]},{"type":"paragraph"},{"type":"heading","attrs":{"level":2},"content":[{"text":"👁️ Live Preview","type":"text"}]},{"type":"paragraph","content":[{"text":"View your article as it looks on your site by clicking on the live preview button.","type":"text"}]},{"type":"image","attrs":{"alt":"","src":"https:\/\/assets.stori.press\/storipress\/tutorial\/2023-001\/live-preview.png","link":"","type":"regular","title":"","source":[]}},{"type":"paragraph"},{"type":"heading","attrs":{"level":2},"content":[{"text":"💰 Paywalls & Member Gating","type":"text"}]},{"type":"paragraph","content":[{"text":"Finally, when you're done, you can paywall your content by clicking the dropdown.","type":"text"}]},{"type":"image","attrs":{"alt":"","src":"https:\/\/assets.stori.press\/storipress\/tutorial\/2023-001\/paywall.png","link":"","type":"regular","title":"","source":[]}},{"type":"paragraph"},{"type":"paragraph","content":[{"text":"There's a lot more to discover in Storipress' Editor, but here are the basics.","type":"text","marks":[{"type":"bold","attrs":[]}]},{"text":" Our editor is powerful enough to do whatever you want it to do. With a little exploration, you'll be up and running in no time.","type":"text"}]},{"type":"paragraph"},{"type":"paragraph"}]}
EOD),
            'blurb' => json_decode(<<<'EOD'
{"type":"doc","content":[{"type":"paragraph","content":[{"text":"Learn how to use Storipress in 5 minutes.","type":"text"}]}]}
EOD),
            'annotations' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function shared(): array
    {
        return [
            'blurb' => <<<'EOD'
<p>Learn how to use Storipress in 5 minutes.</p>
EOD,
            'html' => <<<'EOD'
<p>Welcome to Storipress' <strong>collaborative real-time</strong> editor. We think it's the best editor in the world. Why? Well, unlike other editors you've used like Google Docs, <strong>Storipress is designed to push content to the Web, not to a printer.</strong></p><p>So, what does that mean? Well, let's discover its features 👇</p><div class="clear-both" data-format="resource" data-url="https://www.youtube.com/watch?v=Hr9ECcdigEo" data-meta="{&quot;title&quot;:&quot;LET ME SHOW YOU ITS FEATURES&quot;,&quot;description&quot;:&quot;I know people want this.&quot;,&quot;author&quot;:&quot;Sm1ley&quot;,&quot;icon&quot;:&quot;https://www.youtube.com/s/desktop/afaf5292/img/favicon_32x32.png&quot;,&quot;publisher&quot;:&quot;YouTube&quot;,&quot;url&quot;:&quot;https://www.youtube.com/watch?v=Hr9ECcdigEo&quot;,&quot;thumbnail&quot;:&quot;https://i.ytimg.com/vi/Hr9ECcdigEo/hqdefault.jpg&quot;,&quot;html&quot;:&quot;<div><div style=\&quot;left: 0; width: 100%; height: 0; position: relative; padding-bottom: 56.25%;\&quot;><iframe data-iframely-url=\&quot;https://cdn.iframe.ly/api/iframe?playerjs=1&amp;url=https%3A%2F%2Fwww.youtube.com%2Fwatch%3Fv%3DHr9ECcdigEo&amp;key=6d002d15348823942403bf5e779d2cca\&quot; style=\&quot;top: 0; left: 0; width: 100%; height: 100%; position: absolute; border: 0;\&quot; allowfullscreen scrolling=\&quot;no\&quot; allow=\&quot;autoplay *; accelerometer *; clipboard-write *; encrypted-media *; gyroscope *; picture-in-picture *; web-share *;\&quot;></iframe></div></div>&quot;,&quot;iframe0&quot;:&quot;<div style=\&quot;left: 0; width: 100%; height: 0; position: relative; padding-bottom: 56.25%;\&quot;><iframe src=\&quot;https://www.youtube.com/embed/Hr9ECcdigEo?rel=0\&quot; style=\&quot;top: 0; left: 0; width: 100%; height: 100%; position: absolute; border: 0;\&quot; allowfullscreen scrolling=\&quot;no\&quot; allow=\&quot;accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share;\&quot;></iframe></div>&quot;,&quot;aspectRadio&quot;:1}" data-type="embed"><figure><div><div style="left: 0; width: 100%; height: 0; position: relative; padding-bottom: 56.25%;"><iframe src="https://www.youtube.com/embed/Hr9ECcdigEo?rel=0" style="top: 0; left: 0; width: 100%; height: 100%; position: absolute; border: 0;" allowfullscreen="" scrolling="no" allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share;"></iframe></div></div><figcaption class="text-center"></figcaption></figure></div><p></p><h2>🌆 Cards and Embeds</h2><h3>Embeds</h3><p>How was the above YouTube embed created? <strong>Just paste a link in a new line</strong> 👇</p><div class="image clear-both mx-auto" data-format="image" data-src="https://assets.stori.press/storipress/tutorial/2023-001/cards-and-embeds.gif" data-alt="" data-link="" data-source="[]" data-title="" data-type="regular"><figure><picture><img src="https://assets.stori.press/storipress/tutorial/2023-001/cards-and-embeds.gif" source="" alt="" title="" link="" type="regular"></picture><figcaption class="pt-2 block caption-text"></figcaption></figure></div><p><strong>Storipress supports over 3,000+ sites</strong> so paste any link and we'll try to embed it!</p><p>You can also insert rich media blocks in two other ways:</p><ol><li class="base-text"><p>By clicking the plus button on the left when you hover over text, or</p></li><li class="base-text"><p>By using slash commands (which we'll get into later)</p></li></ol><p></p><h2>🤖 Storipress AI + Spellcheck</h2><h3>Storipress AI</h3><p>Storipress AI creates content based on your prompts and current article context. <strong>Pull up Storipress AI in two ways:</strong></p><ol><li class="base-text"><p><strong>To improve existing content,</strong> highlight text and select Ask AI. Then, pick an option from the dropdown or write a custom prompt.</p></li><li class="base-text"><p><strong>To draft new text,</strong> use the <code>space</code> key on a new line and enter any prompt.</p></li></ol><div class="image clear-both mx-auto" data-format="image" data-src="https://assets.stori.press/storipress/tutorial/2023-001/storipress-ai-spellcheck.gif" data-alt="" data-link="" data-source="[]" data-title="" data-type="regular"><figure><picture><img src="https://assets.stori.press/storipress/tutorial/2023-001/storipress-ai-spellcheck.gif" source="" alt="" title="" link="" type="regular"></picture><figcaption class="pt-2 block caption-text"></figcaption></figure></div><p></p><h3>AI Spellcheck</h3><p>Powered by <strong>Grammarly</strong>, Storipress' spellcheck underlines typos and offers suggestions for how to improve your writing.</p><p><strong>If you're a Grammarly customer</strong>, you can even connect your account to Storipress by clicking the Grammarly button at the bottom left.</p><div class="image clear-both mx-auto" data-format="image" data-src="https://assets.stori.press/storipress/tutorial/2023-001/ai-spellcheck.png" data-alt="" data-link="" data-source="[]" data-title="" data-type="regular"><figure><picture><img src="https://assets.stori.press/storipress/tutorial/2023-001/ai-spellcheck.png" source="" alt="" title="" link="" type="regular"></picture><figcaption class="pt-2 block caption-text"></figcaption></figure></div><p><em>If you're not seeing the Grammarly button, it's as you've installed the Grammarly browser or computer extension. To use the native Storipress integration, disable the extensions for the stori.press domain. </em></p><p></p><h2>📣 Automatically Share Articles to Socials</h2><p>At the top of the editor, click the social sharing tab to connect your social accounts.</p><div class="image clear-both mx-auto" data-format="image" data-src="https://assets.stori.press/storipress/tutorial/2023-001/social-sharing-1.png" data-alt="" data-link="" data-source="[]" data-title="" data-type="regular"><figure><picture><img src="https://assets.stori.press/storipress/tutorial/2023-001/social-sharing-1.png" source="" alt="" title="" link="" type="regular"></picture><figcaption class="pt-2 block caption-text"></figcaption></figure></div><p>In this pane, you can draft social posts right within Storipress so that when your article goes live, they're also shared across all your channels.</p><div class="image clear-both mx-auto" data-format="image" data-src="https://assets.stori.press/storipress/tutorial/2023-001/social-sharing-2.gif" data-alt="" data-link="" data-source="[]" data-title="" data-type="regular"><figure><picture><img src="https://assets.stori.press/storipress/tutorial/2023-001/social-sharing-2.gif" source="" alt="" title="" link="" type="regular"></picture><figcaption class="pt-2 block caption-text"></figcaption></figure></div><p></p><h2>⌨️ Never leave your keyboard</h2><p>With a wide range of keyboard commands, you never need to touch your mouse. </p><h3>Shortcuts</h3><p>On top of the standard italic/bold shortcuts, there are additional shortcuts to try:</p><ul type="dash"><li class="base-text"><p><em>If on Mac</em>: Press <strong>⌘ + option + 1/2/3/4/0</strong></p></li><li class="base-text"><p><em>If on Windows</em>: Press <strong>ctrl + shift + 1/2/3/4/0</strong></p></li></ul><p>Give it a whirl!</p><h3>Slash Commands</h3><div class="image clear-both mx-auto" data-format="image" data-src="https://assets.stori.press/storipress/tutorial/2023-001/slash-command.png" data-alt="" data-link="" data-source="[]" data-title="" data-type="regular"><figure><picture><img src="https://assets.stori.press/storipress/tutorial/2023-001/slash-command.png" source="" alt="" title="" link="" type="regular"></picture><figcaption class="pt-2 block caption-text"></figcaption></figure></div><p>Storipress also supports slash commands to insert blocks or embeds. </p><ol><li class="base-text"><p><strong>On a new line</strong>, press <strong>/</strong> (forward-slash) to activate the slash menu</p></li><li class="base-text"><p>Type the name of the block you want to insert</p></li><li class="base-text"><p>Click Enter or Return</p></li></ol><p>Boom! New block. Without ever having to touch your keyboard.</p><p></p><h2>👁️ Live Preview</h2><p>View your article as it looks on your site by clicking on the live preview button.</p><div class="image clear-both mx-auto" data-format="image" data-src="https://assets.stori.press/storipress/tutorial/2023-001/live-preview.png" data-alt="" data-link="" data-source="[]" data-title="" data-type="regular"><figure><picture><img src="https://assets.stori.press/storipress/tutorial/2023-001/live-preview.png" source="" alt="" title="" link="" type="regular"></picture><figcaption class="pt-2 block caption-text"></figcaption></figure></div><p></p><h2>💰 Paywalls &amp; Member Gating</h2><p>Finally, when you're done, you can paywall your content by clicking the dropdown.</p><div class="image clear-both mx-auto" data-format="image" data-src="https://assets.stori.press/storipress/tutorial/2023-001/paywall.png" data-alt="" data-link="" data-source="[]" data-title="" data-type="regular"><figure><picture><img src="https://assets.stori.press/storipress/tutorial/2023-001/paywall.png" source="" alt="" title="" link="" type="regular"></picture><figcaption class="pt-2 block caption-text"></figcaption></figure></div><p></p><p><strong>There's a lot more to discover in Storipress' Editor, but here are the basics.</strong> Our editor is powerful enough to do whatever you want it to do. With a little exploration, you'll be up and running in no time.</p><p></p><p></p>
EOD,
            'plaintext' => <<<'EOD'
Welcome to Storipress' collaborative real-time editor. We think it's the best editor in the world. Why? Well, unlike other editors you've used like Google Docs, Storipress is designed to push content to the Web, not to a printer.

So, what does that mean? Well, let's discover its features 👇

🌆 Cards and Embeds

Embeds

How was the above YouTube embed created? Just paste a link in a new line 👇

Storipress supports over 3,000+ sites so paste any link and we'll try to embed it!

You can also insert rich media blocks in two other ways:

By clicking the plus button on the left when you hover over text, or

By using slash commands (which we'll get into later)

🤖 Storipress AI + Spellcheck

Storipress AI

Storipress AI creates content based on your prompts and current article context. Pull up Storipress AI in two ways:

To improve existing content, highlight text and select Ask AI. Then, pick an option from the dropdown or write a custom prompt.

To draft new text, use the space key on a new line and enter any prompt.

AI Spellcheck

Powered by Grammarly, Storipress' spellcheck underlines typos and offers suggestions for how to improve your writing.

If you're a Grammarly customer, you can even connect your account to Storipress by clicking the Grammarly button at the bottom left.

If you're not seeing the Grammarly button, it's as you've installed the Grammarly browser or computer extension. To use the native Storipress integration, disable the extensions for the stori.press domain.

📣 Automatically Share Articles to Socials

At the top of the editor, click the social sharing tab to connect your social accounts.

In this pane, you can draft social posts right within Storipress so that when your article goes live, they're also shared across all your channels.

⌨️ Never leave your keyboard

With a wide range of keyboard commands, you never need to touch your mouse.

Shortcuts

On top of the standard italic/bold shortcuts, there are additional shortcuts to try:

If on Mac: Press ⌘ + option + 1/2/3/4/0

If on Windows: Press ctrl + shift + 1/2/3/4/0

Give it a whirl!

Slash Commands

Storipress also supports slash commands to insert blocks or embeds.

On a new line, press / (forward-slash) to activate the slash menu

Type the name of the block you want to insert

Click Enter or Return

Boom! New block. Without ever having to touch your keyboard.

👁️ Live Preview

View your article as it looks on your site by clicking on the live preview button.

💰 Paywalls & Member Gating

Finally, when you're done, you can paywall your content by clicking the dropdown.

There's a lot more to discover in Storipress' Editor, but here are the basics. Our editor is powerful enough to do whatever you want it to do. With a little exploration, you'll be up and running in no time.
EOD,
            'cover' => [
                'alt' => '',
                'url' => 'https://assets.stori.press/storipress/tutorial/2023-001/cover.png?crop=1227,690,203,0',
                'crop' => [
                    'top' => 34.375,
                    'left' => 50.983300373544,
                    'zoom' => 1.3044,
                    'realWidth' => 1600,
                    'realHeight' => 900,
                ],
                'caption' => '',
            ],
        ];
    }
}
