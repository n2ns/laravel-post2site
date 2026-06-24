<?php

namespace N2ns\LaravelPost2Site\Integrations\SaasKit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use N2ns\LaravelPost2Site\Contracts\ContentScopeValidator;

class SaasKitContentScopeValidator implements ContentScopeValidator
{
    public function validate(string $kind, string $key): ?string
    {
        if ($kind !== 'product') {
            return 'The content_scope kind must be product.';
        }

        $productClass = config('post2site.integrations.saas_kit.product_model');
        if (! is_string($productClass) || ! class_exists($productClass) || ! is_subclass_of($productClass, Model::class)) {
            return 'A valid SaaS Kit product model is required.';
        }

        /** @var class-string<Model> $productClass */
        $product = new $productClass;
        $query = $productClass::query()->where('code', $key);

        if (Schema::hasColumn($product->getTable(), 'is_active')) {
            $query->where('is_active', true);
        }

        if (defined($productClass.'::SELLABLE_CODES')) {
            $query->whereIn('code', constant($productClass.'::SELLABLE_CODES'));
        }

        return $query->exists() ? null : 'The selected content_scope product does not exist.';
    }
}
