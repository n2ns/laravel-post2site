<?php

namespace N2ns\LaravelPost2Site\Integrations\Canvas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use N2ns\LaravelPost2Site\Contracts\PublicationTarget;
use N2ns\LaravelPost2Site\Data\PostData;
use N2ns\LaravelPost2Site\Data\PublishedPostData;
use N2ns\LaravelPost2Site\Support\PublicUrlPattern;

class CanvasPublicationTarget implements PublicationTarget
{
    public function publish(PostData $post): PublishedPostData
    {
        $postClass = $this->modelClass('post_model');
        $authorId = $this->authorId();

        return DB::transaction(function () use ($post, $postClass, $authorId): PublishedPostData {
            /** @var Model $canvasPost */
            $canvasPost = $postClass::query()->firstOrNew([
                'slug' => $post->slug,
                'user_id' => $authorId,
            ]);

            if (! $canvasPost->exists && ! $canvasPost->getKey()) {
                $canvasPost->setAttribute($canvasPost->getKeyName(), (string) Str::uuid());
            }

            $canvasPost->setAttribute('slug', $post->slug);
            $canvasPost->setAttribute('title', $post->title);
            $canvasPost->setAttribute('summary', $post->excerpt);
            $canvasPost->setAttribute('body', $post->content);
            $canvasPost->setAttribute('published_at', $post->publishedAt ?? now());
            $canvasPost->setAttribute('featured_image', $post->thumbnail);
            $canvasPost->setAttribute('user_id', $authorId);
            $canvasPost->save();

            return new PublishedPostData(
                targetId: $canvasPost->getKey(),
                targetType: $canvasPost::class,
                link: $this->publicUrl($post),
                publishedAt: $canvasPost->getAttribute('published_at') ?? now(),
            );
        });
    }

    /**
     * @return class-string<Model>
     */
    private function modelClass(string $key): string
    {
        $class = config("post2site.integrations.canvas.{$key}");

        if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            throw ValidationException::withMessages([
                "post2site.integrations.canvas.{$key}" => 'A valid Canvas Eloquent model class is required.',
            ]);
        }

        return $class;
    }

    private function authorId(): int|string
    {
        $configuredId = config('post2site.integrations.canvas.author_id');
        if (is_string($configuredId) && $configuredId !== '') {
            return $configuredId;
        }

        if (is_numeric($configuredId)) {
            return (int) $configuredId;
        }

        $userClass = $this->modelClass('user_model');
        $query = $userClass::query();
        $email = config('post2site.integrations.canvas.author_email');

        if (is_string($email) && $email !== '') {
            $query->where('email', $email);
        }

        $user = $query->first();
        if (! $user) {
            throw ValidationException::withMessages([
                'post2site.integrations.canvas.author_id' => 'Configure POST2SITE_CANVAS_USER_ID or create a default Canvas user before publishing.',
            ]);
        }

        return $user->getKey();
    }

    private function publicUrl(PostData $post): string
    {
        return PublicUrlPattern::build(
            (string) config('post2site.integrations.canvas.public_url_pattern', '/blog/{slug}'),
            $post->locale,
            $post->slug,
            $post->contentScope,
        );
    }
}
