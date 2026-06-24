<?php

namespace N2ns\LaravelPost2Site\Integrations\SaasKit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use N2ns\LaravelPost2Site\Contracts\PublicationTarget;
use N2ns\LaravelPost2Site\Data\PostData;
use N2ns\LaravelPost2Site\Data\PublishedPostData;

class SaasKitPublicationTarget implements PublicationTarget
{
    public function publish(PostData $post): PublishedPostData
    {
        $blogPostClass = $this->modelClass('blog_post_model');
        $translationClass = $this->modelClass('blog_post_translation_model');
        $defaultLocale = $this->defaultLocale();

        return DB::transaction(function () use ($post, $blogPostClass, $translationClass, $defaultLocale): PublishedPostData {
            /** @var Model $blogPost */
            $blogPost = $blogPostClass::query()->firstOrNew(['slug' => $post->slug]);

            $blogPost->setAttribute('user_id', $blogPost->getAttribute('user_id') ?: $this->authorId());
            $blogPost->setAttribute('type', $post->type ?: config('post2site.integrations.saas_kit.default_type', 'technical'));
            $blogPost->setAttribute('content_scope', $post->contentScope);
            $blogPost->setAttribute('status', 'published');
            $blogPost->setAttribute('slug', $post->slug);
            $blogPost->setAttribute('thumbnail', $post->thumbnail);
            $blogPost->setAttribute('published_at', $blogPost->getAttribute('published_at') ?: now());

            if ($post->locale === $defaultLocale || ! $blogPost->exists) {
                $blogPost->setAttribute('title', $post->title);
                $blogPost->setAttribute('content', $post->content);
                $blogPost->setAttribute('excerpt', $post->excerpt);
            }

            $blogPost->save();

            $translationClass::query()->updateOrCreate(
                [
                    'blog_post_id' => $blogPost->getKey(),
                    'locale' => $post->locale,
                ],
                [
                    'title' => $post->title,
                    'content' => $post->content,
                    'excerpt' => $post->excerpt,
                ],
            );

            return new PublishedPostData(
                targetId: $blogPost->getKey(),
                targetType: $blogPost::class,
                link: $this->publicUrl($blogPost, $post),
                publishedAt: $blogPost->getAttribute('published_at') ?? now(),
            );
        });
    }

    /**
     * @return class-string<Model>
     */
    private function modelClass(string $key): string
    {
        $class = config("post2site.integrations.saas_kit.{$key}");

        if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            throw ValidationException::withMessages([
                "post2site.integrations.saas_kit.{$key}" => 'A valid SaaS Kit Eloquent model class is required.',
            ]);
        }

        return $class;
    }

    private function authorId(): int|string
    {
        $configuredId = config('post2site.integrations.saas_kit.author_id');
        if (is_numeric($configuredId)) {
            return (int) $configuredId;
        }

        $userClass = $this->modelClass('user_model');
        $query = $userClass::query();
        $email = config('post2site.integrations.saas_kit.author_email');

        if (is_string($email) && $email !== '') {
            $query->where('email', $email);
        }

        $user = $query->first();
        if (! $user) {
            throw ValidationException::withMessages([
                'post2site.integrations.saas_kit.author_id' => 'Configure POST2SITE_SAAS_KIT_AUTHOR_ID or create a default author user before publishing.',
            ]);
        }

        return $user->getKey();
    }

    private function publicUrl(Model $blogPost, PostData $post): string
    {
        if (method_exists($blogPost, 'publicUrl')) {
            $url = $blogPost->publicUrl($post->locale);
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return (new SaasKitPublicUrlResolver)->resolve($post) ?? '';
    }

    private function defaultLocale(): string
    {
        return (string) config('post2site.integrations.saas_kit.default_locale', 'en');
    }
}
