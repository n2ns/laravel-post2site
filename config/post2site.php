<?php

use N2ns\LaravelPost2Site\Indexing\CompositeIndexingNotifier;
use N2ns\LaravelPost2Site\Models\Post2SiteApiKey;
use N2ns\LaravelPost2Site\Repositories\ConfigScopeContextProvider;
use N2ns\LaravelPost2Site\Repositories\ConfigurablePublicationTarget;
use N2ns\LaravelPost2Site\Repositories\EloquentPostRepository;
use N2ns\LaravelPost2Site\Support\ConfiguredPublicUrlResolver;
use N2ns\LaravelPost2Site\Support\NullContentScopeValidator;

return [
    'version' => '0.1.1',
    'route_prefix' => env('POST2SITE_ROUTE_PREFIX', 'api/v1/mcp'),

    'route_middleware' => ['api'],

    // Rate limit applied to every Post2Site route, in "max,minutes" form.
    // Mitigates brute-forcing the API key and request floods.
    'rate_limit' => env('POST2SITE_RATE_LIMIT', '60,1'),

    'auth' => [
        'driver' => env('POST2SITE_AUTH_DRIVER', 'database'),
        'header' => env('POST2SITE_AUTH_HEADER', 'X-API-KEY'),
        'static_key' => env('POST2SITE_API_KEY'),
        'model' => Post2SiteApiKey::class,
    ],

    'content' => [
        'types' => ['technical', 'announcement', 'changelog', 'guide'],
        // Types that require a content_scope (and for which it is allowed).
        // Any other type prohibits content_scope.
        'scoped_types' => ['guide'],
        'statuses' => ['draft', 'published'],
        'locales' => ['en'],
        'default_locale' => 'en',
        'per_page_max' => 100,
    ],

    // Public URL for published posts. The package makes no assumptions about
    // content categories; this pattern decides the URL shape.
    // Placeholders: {slug} {locale} {content_scope} {key}
    // Hosts needing per-category URLs bind their own PublicUrlResolver instead.
    'public_url' => [
        'pattern' => env('POST2SITE_PUBLIC_URL_PATTERN', '/{slug}'),
    ],

    'content_scope' => [
        // Optional whitelist of allowed kinds (the part before ":").
        // Empty = accept any well-formed kind:key. Comma-separated env value.
        'kinds' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('POST2SITE_CONTENT_SCOPE_KINDS', ''))
        ))),
        // Illustrative examples surfaced in /capabilities for AI clients.
        'examples' => [],
    ],

    'publishing' => [
        'mode' => env('POST2SITE_PUBLISHING_MODE', 'review'),
        'target' => [
            'model' => env('POST2SITE_TARGET_MODEL'),
            'lookup' => ['slug' => 'slug'],
            'fields' => [
                'slug' => 'slug',
                'type' => 'type',
                'content_scope' => 'content_scope',
                'thumbnail' => 'thumbnail',
                'status' => ['column' => 'status', 'value' => 'published'],
                'published_at' => ['column' => 'published_at', 'value' => 'now'],
            ],
            'translations' => [
                'driver' => 'spatie_translatable',
                'fields' => [
                    'title' => 'title',
                    'excerpt' => 'excerpt',
                    'content' => 'content',
                ],
            ],
            'url' => [
                // Placeholders: {slug} {locale} {content_scope} {key}.
                // Falls back to post2site.public_url.pattern when unset.
                'pattern' => env('POST2SITE_PUBLISH_URL_PATTERN', '/{slug}'),
            ],
        ],
    ],

    'bindings' => [
        'repository' => EloquentPostRepository::class,
        'publication_target' => ConfigurablePublicationTarget::class,
        'scope_context_provider' => ConfigScopeContextProvider::class,
        'indexing_notifier' => CompositeIndexingNotifier::class,
        'public_url_resolver' => ConfiguredPublicUrlResolver::class,
        'content_scope_validator' => NullContentScopeValidator::class,
    ],

    'seo' => [
        'metadata' => [
            'enabled' => true,
            'description_max_length' => 160,
            'fallback_image' => null,
        ],
        'structured_data' => [
            'enabled' => true,
            'article_type' => 'Article',
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
            'recommendation' => 'Use sitemap plus Google Search Console for ordinary articles. Do not expose a generic URL submitter.',
        ],
    ],

    // Controlled context per content_scope, keyed by the full kind:key value.
    // Surfaced via GET /scopes/{content_scope} and capabilities.scopes.
    'scopes' => [],
];
