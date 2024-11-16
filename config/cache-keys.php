<?php

return [

    'billing' => [
        'ttl' => 60 * 60, // 1 hour in seconds

        'tag' => 'billing',

        'prices' => 'app-subscription-prices',

        'invoice' => 'app-subscription-next-invoice-%s',
    ],

    'cors' => [
        'domains' => 'cors-domains',
    ],

];
