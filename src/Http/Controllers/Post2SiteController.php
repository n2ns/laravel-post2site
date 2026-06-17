<?php

namespace N2ns\LaravelPost2Site\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use N2ns\LaravelPost2Site\Contracts\PostRepository;
use N2ns\LaravelPost2Site\Contracts\PublicationTarget;
use N2ns\LaravelPost2Site\Contracts\ScopeContextProvider;
use N2ns\LaravelPost2Site\Data\PublishedPostData;
use N2ns\LaravelPost2Site\Events\Post2SitePostPublished;
use N2ns\LaravelPost2Site\Http\Requests\ListPostsRequest;
use N2ns\LaravelPost2Site\Http\Requests\StorePostRequest;
use N2ns\LaravelPost2Site\Http\Requests\UpdatePostRequest;
use N2ns\LaravelPost2Site\Jobs\SubmitPublishedPostForIndexing;
use N2ns\LaravelPost2Site\Repositories\NullPublicationTarget;
use N2ns\LaravelPost2Site\Support\PostResponseFactory;

class Post2SiteController extends Controller
{
    public function __construct(
        private readonly PostRepository $posts,
        private readonly PublicationTarget $publicationTarget,
        private readonly ScopeContextProvider $scopes,
        private readonly PostResponseFactory $responses,
    ) {}

    public function capabilities(): JsonResponse
    {
        return response()->json([
            'package_version' => config('post2site.version', '0.1.0'),
            'contract' => 'Content Publishing API Contract',
            'contract_version' => '1.0',

            'base_path' => '/'.trim(config('post2site.route_prefix'), '/'),
            'auth' => ['required_headers' => [config('post2site.auth.header', 'X-API-KEY')]],
            'endpoints' => [
                'capabilities' => 'GET /capabilities',
                'scope_context' => 'GET /scopes/{content_scope}',
                'list_posts' => 'GET /posts',
                'create_post' => 'POST /posts',
                'get_post' => 'GET /posts/{id_or_slug}',
                'update_post' => 'PATCH /posts/{id_or_slug}',
                'publish_post' => 'POST /posts/{id_or_slug}/publish',
            ],
            'content' => [
                'input_model' => 'single_locale',
                'locale_field' => 'locale',
                'types' => config('post2site.content.types'),
                'statuses' => config('post2site.content.statuses'),
                'locales' => config('post2site.content.locales'),
                'default_locale' => config('post2site.content.default_locale'),
                'recommended_locales' => config('post2site.content.locales'),
                'create_update_prohibited_fields' => ['status', 'published_at', 'user_id', 'author'],
                'content_scope' => [
                    'field' => 'content_scope',
                    'format' => 'kind:key',
                    'required_for_types' => config('post2site.content.scoped_types', []),
                    'kinds' => config('post2site.content_scope.kinds', []),
                    'examples' => config('post2site.content_scope.examples', []),
                ],
            ],
            'translation' => [
                'backend_auto_translate' => false,
                'client_should_complete_missing_locales' => true,
                'missing_locales_returned' => true,
            ],
            'scopes' => $this->scopes->availableScopes(),
            'publishing' => [
                'mode' => config('post2site.publishing.mode'),
                'manual_review_required' => false,
            ],
            'indexing' => [
                'sitemap' => config('post2site.indexing.sitemap.enabled'),
                'indexnow' => config('post2site.indexing.indexnow.enabled') && filled(config('post2site.indexing.indexnow.key')),
                'google_auto_submit' => false,
            ],
            'limits' => [
                'per_page_max' => config('post2site.content.per_page_max'),
                'default_status_on_create' => 'draft',
            ],
            'safety' => [
                'delete_exposed' => false,
                'database_access_exposed' => false,
                'shell_access_exposed' => false,
                'server_operations_exposed' => false,
            ],
        ]);
    }

    public function index(ListPostsRequest $request): JsonResponse
    {
        $paginator = $this->posts->listPosts(
            $request->validated(),
            $request->integer('per_page', 20),
        );

        return response()->json($this->responses->paginated($paginator));
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $post = $this->posts->createPost($request->validated());

        return response()->json($this->responses->envelope($post), 201);
    }

    public function show(string $idOrSlug): JsonResponse
    {
        return response()->json($this->responses->envelope(
            $this->posts->findPostByIdOrSlug($idOrSlug),
        ));
    }

    public function update(UpdatePostRequest $request, string $idOrSlug): JsonResponse
    {
        $post = $this->posts->findPostByIdOrSlug($idOrSlug);
        $data = $request->validated();
        $this->validateContentScopeCombination($post, $data);
        $updated = $this->posts->updatePost($post->id, $data);

        return response()->json($this->responses->envelope($updated));
    }

    public function publish(string $idOrSlug): JsonResponse
    {
        $post = $this->posts->findPostByIdOrSlug($idOrSlug);
        $mode = config('post2site.publishing.mode');

        if ($mode === 'review') {
            $published = $this->posts->markPublished($post->id, new PublishedPostData(
                targetId: $post->id,
                targetType: 'post2site',
                link: '',
                publishedAt: now(),
            ));
            Post2SitePostPublished::dispatch($published);

            return response()->json($this->responses->envelope($published, [
                'sitemap_update_expected' => config('post2site.indexing.sitemap.enabled'),
                'indexnow_queued' => false,
                'google_auto_submit' => false,
            ]));
        }

        if ($mode === 'adapter' && $this->publicationTarget instanceof NullPublicationTarget) {
            throw ValidationException::withMessages([
                'publication_target' => 'A real PublicationTarget binding is required when post2site.publishing.mode is adapter.',
            ]);
        }

        $target = $this->publicationTarget->publish($post);
        $published = $this->posts->markPublished($post->id, $target);
        Post2SitePostPublished::dispatch($published);

        if (config('post2site.indexing.enabled', true) && filled($published->link)) {
            SubmitPublishedPostForIndexing::dispatch($published->id, $published->link)
                ->afterCommit()
                ->onQueue(config('post2site.indexing.queue', 'default'));
        }

        return response()->json($this->responses->envelope($published, [
            'sitemap_update_expected' => config('post2site.indexing.sitemap.enabled'),
            'indexnow_queued' => config('post2site.indexing.indexnow.enabled') && filled($published->link),
            'google_auto_submit' => false,
        ]));
    }

    public function scopeContext(string $contentScope): JsonResponse
    {
        $context = $this->scopes->contextForScope($contentScope);

        return $context
            ? response()->json($context)
            : response()->json(['message' => 'The selected content_scope does not exist.'], 404);
    }

    private function validateContentScopeCombination(object $post, array $data): void
    {
        $nextType = $data['type'] ?? $post->type;
        $nextScope = array_key_exists('content_scope', $data) ? $data['content_scope'] : $post->contentScope;
        $scopedTypes = config('post2site.content.scoped_types', []);

        if (in_array($nextType, $scopedTypes, true) && blank($nextScope)) {
            throw ValidationException::withMessages([
                'content_scope' => 'The content_scope field is required for this content type.',
            ]);
        }

        if (! in_array($nextType, $scopedTypes, true) && filled($nextScope)) {
            throw ValidationException::withMessages([
                'content_scope' => 'The content_scope field is not allowed for this content type.',
            ]);
        }
    }
}
