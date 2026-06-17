<?php

namespace N2ns\LaravelPost2Site\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use N2ns\LaravelPost2Site\Contracts\PublicationTarget;
use N2ns\LaravelPost2Site\Data\PostData;
use N2ns\LaravelPost2Site\Data\PublishedPostData;
use N2ns\LaravelPost2Site\Support\PublicUrlPattern;

class ConfigurablePublicationTarget implements PublicationTarget
{
    public function publish(PostData $post): PublishedPostData
    {
        $config = config('post2site.publishing.target', []);
        $modelClass = $config['model'] ?? null;

        if (! is_string($modelClass) || ! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            throw ValidationException::withMessages([
                'publishing.target.model' => 'A valid Eloquent model class is required for configurable publishing.',
            ]);
        }

        /** @var class-string<Model> $modelClass */
        $model = $this->findOrNew($modelClass, $post, $config['lookup'] ?? []);
        $this->assignFields($model, $post, $config['fields'] ?? []);
        $this->assignTranslations($model, $post, $config['translations'] ?? []);
        $model->save();

        return new PublishedPostData(
            targetId: $model->getKey(),
            targetType: $model::class,
            link: $this->resolveUrl($model, $post, $config['url'] ?? []),
            publishedAt: $model->getAttribute('published_at') ?? now(),
        );
    }

    private function findOrNew(string $modelClass, PostData $post, array $lookup): Model
    {
        $attributes = [];

        foreach ($lookup as $postField => $column) {
            if (! is_string($column) || $column === '') {
                throw ValidationException::withMessages([
                    'publishing.target.lookup' => 'Lookup mappings must point to non-empty column names.',
                ]);
            }

            $attributes[$column] = $this->postValue($post, $postField);
        }

        if ($attributes === []) {
            throw ValidationException::withMessages([
                'publishing.target.lookup' => 'At least one lookup mapping is required for configurable publishing.',
            ]);
        }

        return $modelClass::query()->firstOrNew($attributes);
    }

    private function assignFields(Model $model, PostData $post, array $fields): void
    {
        foreach ($fields as $postField => $mapping) {
            if (is_string($mapping)) {
                $model->setAttribute($mapping, $this->postValue($post, $postField));

                continue;
            }

            if (is_array($mapping) && isset($mapping['column']) && is_string($mapping['column']) && $mapping['column'] !== '') {
                $value = Arr::get($mapping, 'value');
                $model->setAttribute($mapping['column'], $value === 'now' ? now() : $value);

                continue;
            }

            throw ValidationException::withMessages([
                "publishing.target.fields.{$postField}" => 'Field mappings must be a column name or an array with a non-empty column value.',
            ]);
        }
    }

    private function assignTranslations(Model $model, PostData $post, array $translations): void
    {
        $fields = $translations['fields'] ?? [];

        if (($translations['driver'] ?? null) === 'spatie_translatable' && method_exists($model, 'setTranslation')) {
            foreach ($fields as $postField => $column) {
                if (! is_string($column) || $column === '') {
                    throw ValidationException::withMessages([
                        "publishing.target.translations.fields.{$postField}" => 'Translation field mappings must point to non-empty column names.',
                    ]);
                }

                $model->setTranslation($column, $post->locale, $this->postValue($post, $postField) ?? '');
            }

            return;
        }

        foreach ($fields as $postField => $column) {
            if (! is_string($column) || $column === '') {
                throw ValidationException::withMessages([
                    "publishing.target.translations.fields.{$postField}" => 'Translation field mappings must point to non-empty column names.',
                ]);
            }

            $model->setAttribute($column, $this->postValue($post, $postField));
        }
    }

    private function resolveUrl(Model $model, PostData $post, array $config): string
    {
        $pattern = $config['pattern'] ?? config('post2site.public_url.pattern', '/{slug}');

        return PublicUrlPattern::build(
            $pattern,
            $post->locale,
            (string) ($model->getAttribute('slug') ?? $post->slug),
            $post->contentScope,
        );
    }

    private function postValue(PostData $post, string $field): mixed
    {
        return match ($field) {
            'id' => $post->id,
            'slug' => $post->slug,
            'type' => $post->type,
            'content_scope' => $post->contentScope,
            'locale' => $post->locale,
            'title' => $post->title,
            'excerpt' => $post->excerpt,
            'content' => $post->content,
            'thumbnail' => $post->thumbnail,
            default => null,
        };
    }
}
