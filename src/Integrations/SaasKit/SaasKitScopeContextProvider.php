<?php

namespace N2ns\LaravelPost2Site\Integrations\SaasKit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use N2ns\LaravelPost2Site\Contracts\ScopeContextProvider;

class SaasKitScopeContextProvider implements ScopeContextProvider
{
    public function availableScopes(): array
    {
        $productClass = $this->productModelClass();
        if ($productClass === null) {
            return [];
        }

        $query = $this->activeProductsQuery($productClass);

        return $query->orderBy('code')
            ->get(['code'])
            ->map(fn (Model $product): array => $this->contextFromProduct($product))
            ->values()
            ->all();
    }

    public function contextForScope(string $contentScope): ?array
    {
        if (! str_starts_with($contentScope, 'product:')) {
            return null;
        }

        $productClass = $this->productModelClass();
        if ($productClass === null) {
            return null;
        }

        $code = substr($contentScope, strlen('product:'));
        $product = $this->activeProductsQuery($productClass)->where('code', $code)->first();

        return $product ? $this->contextFromProduct($product) : null;
    }

    /**
     * @return class-string<Model>|null
     */
    private function productModelClass(): ?string
    {
        $productClass = config('post2site.integrations.saas_kit.product_model');

        return is_string($productClass) && class_exists($productClass) && is_subclass_of($productClass, Model::class)
            ? $productClass
            : null;
    }

    /**
     * @param  class-string<Model>  $productClass
     */
    private function activeProductsQuery(string $productClass): mixed
    {
        $product = new $productClass;
        $query = $productClass::query();

        if (Schema::hasColumn($product->getTable(), 'is_active')) {
            $query->where('is_active', true);
        }

        if (defined($productClass.'::SELLABLE_CODES')) {
            $query->whereIn('code', constant($productClass.'::SELLABLE_CODES'));
        }

        return $query;
    }

    private function contextFromProduct(Model $product): array
    {
        $code = (string) $product->getAttribute('code');
        $name = method_exists($product, 'getLocalized')
            ? $product->getLocalized('name', config('post2site.integrations.saas_kit.default_locale', 'en'))
            : null;

        return [
            'content_scope' => 'product:'.$code,
            'code' => $code,
            'name' => $name ?: ($product->getAttribute('name') ?: $code),
            'canonical_url' => rtrim((string) config('app.url'), '/').'/'.$code,
            'docs_url' => rtrim((string) config('app.url'), '/').'/'.$code.'/guides',
        ];
    }
}
