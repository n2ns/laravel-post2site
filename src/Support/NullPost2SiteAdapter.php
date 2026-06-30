<?php

namespace N2ns\LaravelPost2Site\Support;

use Illuminate\Validation\ValidationException;
use N2ns\LaravelPost2Site\Contracts\Post2SiteAdapter;
use N2ns\LaravelPost2Site\Data\AssetResult;
use N2ns\LaravelPost2Site\Data\AssetUpload;
use N2ns\LaravelPost2Site\Data\ClientContext;
use N2ns\LaravelPost2Site\Data\DraftContext;
use N2ns\LaravelPost2Site\Data\DuplicateResult;
use N2ns\LaravelPost2Site\Data\InventoryResult;
use N2ns\LaravelPost2Site\Data\PreviewResult;
use N2ns\LaravelPost2Site\Data\PublishRequest;
use N2ns\LaravelPost2Site\Data\PublishResult;
use N2ns\LaravelPost2Site\Data\ValidationResult;

class NullPost2SiteAdapter implements Post2SiteAdapter
{
    public function capabilities(): array
    {
        return [
            'host_profile' => 'generic',
            'host_schema' => [
                'content_fields' => [],
                'inventory' => [
                    'filterable_fields' => ['q'],
                    'summary_fields' => [],
                    'stats_dimensions' => [],
                ],
            ],
        ];
    }

    public function siteContext(): array
    {
        return ['summary' => null, 'facts' => [], 'host_metadata' => []];
    }

    public function editorialPolicy(): array
    {
        return [
            'policy_version' => 'generic',
            'content_model' => [
                'required_fields' => [],
                'optional_fields' => [],
                'field_paths' => [],
            ],
            'source_rules' => [],
            'cta_rules' => [],
            'prohibited_claims' => [],
            'publish_blockers' => [],
            'host_metadata' => [],
        ];
    }

    public function inventory(array $query): InventoryResult
    {
        return new InventoryResult;
    }

    public function inventoryStats(array $query): array
    {
        return ['dimensions' => [], 'host_metadata' => []];
    }

    public function findDuplicates(array $payload): DuplicateResult
    {
        return new DuplicateResult;
    }

    public function validateContentPayload(DraftContext $context, array $contentPayload, string $mode): ValidationResult
    {
        return new ValidationResult(publishable: true);
    }

    public function extractAssetRefs(array $contentPayload): array
    {
        return [];
    }

    public function storeSelectedAsset(AssetUpload $upload, ClientContext $clientContext, ?DraftContext $draftContext = null): AssetResult
    {
        return new AssetResult(
            assetId: 'asset_'.str_replace('.', '', uniqid('', true)),
            purpose: $upload->purpose,
            url: null,
            contentType: $upload->contentType,
            metadata: $upload->metadata,
        );
    }

    public function previewDraft(DraftContext $context): PreviewResult
    {
        return new PreviewResult(previewUrl: null);
    }

    public function publishDraft(DraftContext $context, PublishRequest $request): PublishResult
    {
        throw ValidationException::withMessages([
            'adapter' => 'Bind a host Post2SiteAdapter before publishing.',
        ]);
    }
}
