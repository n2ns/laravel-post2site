<?php

use N2ns\LaravelPost2Site\Indexing\CompositeIndexingNotifier;
use N2ns\LaravelPost2Site\Models\Post2SiteApiKey;
use N2ns\LaravelPost2Site\Support\NullPost2SiteAdapter;

return [
    'version' => '0.3.0',
    'route_prefix' => env('POST2SITE_ROUTE_PREFIX', 'api/v1/mcp'),

    'route_middleware' => ['api'],
    'rate_limit' => env('POST2SITE_RATE_LIMIT', '60,1'),

    'auth' => [
        'driver' => env('POST2SITE_AUTH_DRIVER', 'database'),
        'header' => env('POST2SITE_AUTH_HEADER', 'X-API-KEY'),
        'static_key' => env('POST2SITE_API_KEY'),
        'model' => Post2SiteApiKey::class,
    ],

    'bindings' => [
        'adapter' => NullPost2SiteAdapter::class,
        'indexing_notifier' => CompositeIndexingNotifier::class,
    ],

    'workflow' => [
        'statuses' => ['draft', 'published'],
        'default_status' => 'draft',
        'supports_drafts' => true,
        'supports_preview' => true,
        'supports_assets' => true,
        'supports_publish_confirmation' => true,
    ],

    'drafts' => [
        'modes' => ['create', 'update_existing'],
        'per_page_max' => 100,
        'forbidden_payload_fields' => [
            'status',
            'published_at',
            'author',
            'content_origin',
            'managed_by',
            'authoring_source',
            'source_type',
            'content_scope',
        ],
    ],

    'indexing' => [
        'enabled' => env('POST2SITE_INDEXING_ENABLED', true),
        'queue' => env('POST2SITE_INDEXING_QUEUE', 'default'),
        'dedupe_minutes' => env('POST2SITE_INDEXING_DEDUPE_MINUTES', 10),
        'sitemap' => [
            'enabled' => env('POST2SITE_SITEMAP_ENABLED', true),
        ],
        'indexnow' => [
            'enabled' => env('POST2SITE_INDEXNOW_ENABLED', false),
            'endpoint' => env('POST2SITE_INDEXNOW_ENDPOINT', 'https://api.indexnow.org/indexnow'),
            'key' => env('POST2SITE_INDEXNOW_KEY'),
            'key_location' => env('POST2SITE_INDEXNOW_KEY_LOCATION'),
            'auto_publish_key_file' => env('POST2SITE_INDEXNOW_AUTO_PUBLISH_KEY_FILE', false),
        ],
        'google' => [
            'auto_submit' => false,
            'recommendation' => 'Use sitemap plus Google Search Console for ordinary resources. Do not expose a generic URL submitter.',
        ],
    ],
];
