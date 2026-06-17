<?php

namespace N2ns\LaravelPost2Site\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use N2ns\LaravelPost2Site\Contracts\IndexingNotifier;
use N2ns\LaravelPost2Site\Data\IndexingPlan;
use N2ns\LaravelPost2Site\Models\Post2SiteIndexingSubmission;
use N2ns\LaravelPost2Site\Models\Post2SitePost;

class SubmitPublishedPostForIndexing implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string|int $postId,
        public readonly string $link,
    ) {}

    public function uniqueId(): string
    {
        return $this->link;
    }

    public function uniqueFor(): int
    {
        return (int) config('post2site.indexing.dedupe_minutes', 10) * 60;
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(IndexingNotifier $notifier): void
    {
        $post = Post2SitePost::query()->find($this->postId);
        $host = parse_url($this->link, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return;
        }

        $recentCutoff = now()->subMinutes((int) config('post2site.indexing.dedupe_minutes', 10));
        $recentSubmissionExists = Post2SiteIndexingSubmission::query()
            ->where('url', $this->link)
            ->where('driver', 'indexnow')
            ->where('last_submitted_at', '>=', $recentCutoff)
            ->whereIn('status', ['queued', 'accepted'])
            ->exists();

        if ($recentSubmissionExists) {
            return;
        }

        $result = $notifier->notify(new IndexingPlan(
            url: $this->link,
            host: $host,
            postId: $this->postId,
            contentScope: $post?->content_scope,
            publishedAt: $post?->published_at,
        ));

        Post2SiteIndexingSubmission::query()->create([
            'post_id' => $this->postId,
            'url' => $this->link,
            'driver' => $result->driver,
            'status' => $result->status,
            'http_status' => $result->httpStatus,
            'response_body' => $result->responseBody,
            'attempts' => 1,
            'last_submitted_at' => now(),
        ]);
    }
}
