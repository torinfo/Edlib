<?php

declare(strict_types=1);

return [

    'domain' => env('NDLA_LEGACY_DOMAIN'),

    'content-author' => [
        'host' => env('NDLA_LEGACY_CONTENTAUTHOR_HOST'),
    ],

    'public-key-or-jwks-uri' => env('NDLA_LEGACY_PUBLIC_KEY_OR_JWKS_URI'),

    /**
     * This does not need to be changed, but is left configurable for existing
     * environments that do not use the default key.
     */
    'internal-lti-platform-key' => env('NDLA_LEGACY_INTERNAL_LTI_PLATFORM_KEY', 'ndla'),

];
