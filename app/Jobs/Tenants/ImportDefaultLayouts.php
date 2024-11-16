<?php

namespace App\Jobs\Tenants;

use App\Models\Tenants\Layout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Webmozart\Assert\Assert;

final class ImportDefaultLayouts implements ShouldQueue
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
        $this->tenant->run(function () {
            foreach ($this->layouts() as $data) {
                $attributes = json_decode($data, true);

                Assert::isArray($attributes);

                (new Layout($attributes))->saveQuietly();
            }
        });
    }

    /**
     * @return array<int, string>
     */
    protected function layouts(): array
    {
        return [
            <<<'EOD'
{"name":"Template 1","template":"basically-one","data":{"styles":{"name":"article","styles":[],"children":{"author-name":{"meta":{"dirty":{"bold":"lg","fontSize":"lg","fontFamily":"lg","lineHeight":"lg"}},"name":"author-name","styles":{"bold":{"lg":true,"md":true,"xs":true},"fontSize":{"lg":15,"md":15,"xs":15},"fontFamily":{"lg":"Archivo Black","md":"Archivo Black","xs":"Archivo Black"},"lineHeight":{"lg":1,"md":1,"xs":1}},"children":[]},"article-date":{"meta":{"dirty":{"fontFamily":"lg"}},"name":"article-date","styles":{"fontFamily":{"lg":"Archivo Black","md":"Archivo Black","xs":"Archivo Black"}},"children":[]},"article-desk":{"meta":{"dirty":{"lowercase":"lg","uppercase":"lg","fontFamily":"lg"}},"name":"article-desk","styles":{"lowercase":{"lg":false,"md":false,"xs":false},"uppercase":{"lg":true,"md":true,"xs":true},"fontFamily":{"lg":"Archivo Black","md":"Archivo Black","xs":"Archivo Black"}},"children":[]},"article-title":{"meta":{"dirty":{"bold":"lg","color":"lg","fontSize":"xs","lowercase":"lg","uppercase":"lg","fontFamily":"lg","lineHeight":"lg"}},"name":"article-title","styles":{"bold":{"lg":true,"md":true,"xs":true},"color":{"lg":"000000ff","md":"000000ff","xs":"000000ff"},"fontSize":{"lg":60,"md":48,"xs":36},"lowercase":{"lg":false,"md":false,"xs":false},"uppercase":{"lg":false,"md":false,"xs":false},"fontFamily":{"lg":"Archivo Black","md":"Archivo Black","xs":"Archivo Black"},"lineHeight":{"lg":1.3,"md":1.3,"xs":1.3}},"children":[]},"article-author":{"meta":{"dirty":{"fontFamily":"lg","lineHeight":"lg"}},"name":"article-author","styles":{"fontFamily":{"lg":"Archivo Black","md":"Archivo Black","xs":"Archivo Black"},"lineHeight":{"lg":1,"md":1,"xs":1}},"children":[]},"article-content":{"name":"article-content","styles":[],"children":{"& .main-content p":{"meta":{"dirty":{"fontSize":"xs","fontFamily":"lg"}},"name":"& .main-content p","styles":{"fontSize":{"lg":22,"md":22,"xs":18},"fontFamily":{"lg":"Jost","md":"Jost","xs":"Jost"}},"children":[]},"& .main-content h1":{"meta":{"dirty":{"bold":"lg","fontSize":"xs","fontFamily":"lg","lineHeight":"md"}},"name":"& .main-content h1","styles":{"bold":{"lg":true,"md":true,"xs":true},"fontSize":{"lg":74,"md":74,"xs":48},"fontFamily":{"lg":"League Gothic","md":"League Gothic","xs":"League Gothic"},"lineHeight":{"md":1.1,"xs":1.1}},"children":[]},"& .main-content h2":{"meta":{"dirty":{"fontSize":"xs","fontFamily":"lg","lineHeight":"md"}},"name":"& .main-content h2","styles":{"fontSize":{"lg":32,"md":32,"xs":24},"fontFamily":{"lg":"Archivo Black","md":"Archivo Black","xs":"Archivo Black"},"lineHeight":{"lg":2,"md":1.1,"xs":1.1}},"children":[]},"& .main-content blockquote":{"meta":{"dirty":{"fontSize":"lg"}},"name":"& .main-content blockquote","styles":{"fontSize":{"lg":38,"md":38,"xs":38}},"children":[]},"& .main-content > p:first-of-type::first-letter":{"meta":{"dirty":[]},"name":"& .main-content > p:first-of-type::first-letter","styles":[],"children":[]}}},"headline-caption":{"meta":{"dirty":{"fontFamily":"lg"}},"name":"headline-caption","styles":{"fontFamily":{"lg":"Cormorant Garamond","md":"Cormorant Garamond","xs":"Cormorant Garamond"}},"children":[]},"article-description":{"meta":{"dirty":{"color":"lg","fontSize":"xs","fontFamily":"lg"}},"name":"article-description","styles":{"color":{"lg":"000000ff","md":"000000ff","xs":"000000ff"},"fontSize":{"lg":24,"md":18,"xs":18},"fontFamily":{"lg":"Barlow","md":"Barlow","xs":"Barlow"}},"children":[]}}},"elements":{"dropcap":"none","blockquote":"regular"}}}
EOD,
            <<<'EOD'
{"name":"Template 2","template":"nytmag-2","data":{"styles":{"name":"article","styles":[],"children":{"hero-title":{"meta":{"dirty":{"fontSize":"md","lowercase":"lg","uppercase":"lg","fontFamily":"lg"}},"name":"hero-title","styles":{"fontSize":{"lg":100,"md":80,"xs":80},"lowercase":{"lg":false,"md":false,"xs":false},"uppercase":{"lg":true,"md":true,"xs":true},"fontFamily":{"lg":"League Gothic","md":"League Gothic","xs":"League Gothic"}},"children":[]},"author-name":{"meta":{"dirty":{"fontSize":"lg","fontFamily":"lg"}},"name":"author-name","styles":{"fontSize":{"lg":15,"md":15,"xs":15},"fontFamily":{"lg":"Archivo Black","md":"Archivo Black","xs":"Archivo Black"}},"children":[]},"article-date":{"meta":{"dirty":{"fontFamily":"lg"}},"name":"article-date","styles":{"fontFamily":{"lg":"Archivo Black","md":"Archivo Black","xs":"Archivo Black"}},"children":[]},"article-desk":{"meta":{"dirty":{"fontSize":"lg","fontFamily":"lg","lineHeight":"xs"}},"name":"article-desk","styles":{"fontSize":{"lg":16,"md":16,"xs":16},"fontFamily":{"lg":"Archivo Black","md":"Archivo Black","xs":"Archivo Black"},"lineHeight":{"xs":2}},"children":[]},"content-title":{"meta":{"dirty":{"fontSize":"xs","lowercase":"xs","uppercase":"xs","fontFamily":"xs"}},"name":"content-title","styles":{"fontSize":{"xs":40},"lowercase":{"xs":false},"uppercase":{"xs":true},"fontFamily":{"xs":"League Gothic"}},"children":[]},"article-author":{"meta":{"dirty":{"align":"xs","fontFamily":"lg"}},"name":"article-author","styles":{"align":{"xs":"left"},"fontFamily":{"lg":"Archivo Black","md":"Archivo Black","xs":"Archivo Black"}},"children":[]},"article-content":{"name":"article-content","styles":[],"children":{"& .main-content p":{"meta":{"dirty":{"fontSize":"xs","fontFamily":"lg"}},"name":"& .main-content p","styles":{"fontSize":{"lg":22,"md":22,"xs":20},"fontFamily":{"lg":"Jost","md":"Jost","xs":"Jost"}},"children":[]},"& .main-content h1":{"meta":{"dirty":{"bold":"lg","fontSize":"xs","fontFamily":"lg","lineHeight":"xs"}},"name":"& .main-content h1","styles":{"bold":{"lg":true,"md":true,"xs":true},"fontSize":{"lg":74,"md":74,"xs":54},"fontFamily":{"lg":"League Gothic","md":"League Gothic","xs":"League Gothic"},"lineHeight":{"xs":1}},"children":[]},"& .main-content h2":{"meta":{"dirty":{"fontSize":"xs","fontFamily":"lg","lineHeight":"xs"}},"name":"& .main-content h2","styles":{"fontSize":{"lg":32,"md":32,"xs":24},"fontFamily":{"lg":"Archivo Black","md":"Archivo Black","xs":"Archivo Black"},"lineHeight":{"xs":1.1}},"children":[]},"& .main-content > p:first-of-type::first-letter":{"meta":{"dirty":{"color":"lg","fontSize":"xs","fontFamily":"lg"}},"name":"& .main-content > p:first-of-type::first-letter","styles":{"color":{"lg":"000000ff","md":"000000ff","xs":"000000ff"},"fontSize":{"lg":22,"md":22,"xs":22},"fontFamily":{"lg":"Cormorant Garamond","md":"Cormorant Garamond","xs":"Cormorant Garamond"}},"children":[]}}},"hero-description":{"meta":{"dirty":{"fontSize":"md","fontFamily":"lg","lineHeight":"lg"}},"name":"hero-description","styles":{"fontSize":{"lg":24,"md":20,"xs":20},"fontFamily":{"lg":"Jost","md":"Jost","xs":"Jost"},"lineHeight":{"lg":1.4,"md":1.4,"xs":1.4}},"children":[]},"content-description":{"meta":{"dirty":{"fontSize":"xs","fontFamily":"xs"}},"name":"content-description","styles":{"fontSize":{"xs":16},"fontFamily":{"xs":"Archivo Black"}},"children":[]}}},"elements":{"dropcap":"none","blockquote":"regular"}}}
EOD,
            <<<'EOD'
{"name":"Template 3","template":"nytmag-1","data":{"styles":{"name":"article","styles":[],"children":{"author-name":{"meta":{"dirty":{"bold":"lg","align":"lg","fontFamily":"lg"}},"name":"author-name","styles":{"bold":{"lg":true,"md":true,"xs":true},"align":{"lg":"left","md":"left","xs":"left"},"fontFamily":{"lg":"Archivo Black","md":"Archivo Black","xs":"Archivo Black"}},"children":[]},"article-date":{"meta":{"dirty":{"fontFamily":"lg"}},"name":"article-date","styles":{"fontFamily":{"lg":"Butler","md":"Butler","xs":"Butler"}},"children":[]},"article-desk":{"meta":{"dirty":{"fontFamily":"lg"}},"name":"article-desk","styles":{"fontFamily":{"lg":"Archivo Black","md":"Archivo Black","xs":"Archivo Black"}},"children":[]},"article-title":{"meta":{"dirty":{"fontSize":"xs","fontFamily":"lg","lineHeight":"xs"}},"name":"article-title","styles":{"fontSize":{"xs":34},"fontFamily":{"lg":"Archivo Black","md":"Archivo Black","xs":"Archivo Black"},"lineHeight":{"xs":1.2}},"children":[]},"article-author":{"meta":{"dirty":{"align":"lg","fontSize":"lg","fontFamily":"lg"}},"name":"article-author","styles":{"align":{"lg":"left","md":"left","xs":"left"},"fontSize":{"lg":16,"md":16,"xs":16},"fontFamily":{"lg":"Archivo Black","md":"Archivo Black","xs":"Archivo Black"}},"children":[]},"article-content":{"name":"article-content","styles":[],"children":{"& .main-content p":{"meta":{"dirty":{"fontSize":"lg","fontFamily":"lg"}},"name":"& .main-content p","styles":{"fontSize":{"lg":22,"md":22,"xs":22},"fontFamily":{"lg":"Jost","md":"Jost","xs":"Jost"}},"children":[]},"& .main-content h1":{"meta":{"dirty":{"fontFamily":"lg"}},"name":"& .main-content h1","styles":{"fontFamily":{"lg":"Archivo Black","md":"Archivo Black","xs":"Archivo Black"}},"children":[]},"& .main-content h2":{"meta":{"dirty":{"fontFamily":"lg"}},"name":"& .main-content h2","styles":{"fontFamily":{"lg":"Archivo Black","md":"Archivo Black","xs":"Archivo Black"}},"children":[]},"& .main-content > p:first-of-type::first-letter":{"meta":{"dirty":{"color":"lg","fontSize":"lg","fontFamily":"lg"}},"name":"& .main-content > p:first-of-type::first-letter","styles":{"color":{"lg":"000000ff","md":"000000ff","xs":"000000ff"},"fontSize":{"lg":22,"md":22,"xs":22},"fontFamily":{"lg":"Cormorant Garamond","md":"Cormorant Garamond","xs":"Cormorant Garamond"}},"children":[]}}},"headline-caption":{"meta":{"dirty":{"fontFamily":"lg"}},"name":"headline-caption","styles":{"fontFamily":{"lg":"Jost","md":"Jost","xs":"Jost"}},"children":[]},"article-description":{"meta":{"dirty":{"fontFamily":"lg"}},"name":"article-description","styles":{"fontFamily":{"lg":"Barlow","md":"Barlow","xs":"Barlow"}},"children":[]},"article-description-box-kind":{"meta":{"dirty":{"backgroundColor":"md"}},"name":"article-description-box-kind","styles":{"backgroundColor":{"lg":"13223aff","md":"ffffffff","xs":"ffffffff"}},"children":[]}}},"elements":{"dropcap":"none","blockquote":"regular"}}}
EOD,
        ];
    }
}
