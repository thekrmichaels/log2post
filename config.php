<?php

return [
    'views' => [
        'pattern' => '#GET\s/cs-db-migration/listings/(\d+).*200#',
        'capturingGroup' => 1,

        'endpoint' => '/listings/:id/views',
        'payload' => 'quantity',
    ]
];
