<?php

namespace N2ns\LaravelPost2Site\Contracts;

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

interface Post2SiteAdapter
{
    public function capabilities(): array;

    public function siteContext(): array;

    public function editorialPolicy(): array;

    public function inventory(array $query): InventoryResult;

    public function inventoryStats(array $query): array;

    public function findDuplicates(array $payload): DuplicateResult;

    public function validateContentPayload(DraftContext $context, array $contentPayload, string $mode): ValidationResult;

    /**
     * @return list<string>
     */
    public function extractAssetRefs(array $contentPayload): array;

    public function storeSelectedAsset(AssetUpload $upload, ClientContext $clientContext, ?DraftContext $draftContext = null): AssetResult;

    public function previewDraft(DraftContext $context): PreviewResult;

    public function publishDraft(DraftContext $context, PublishRequest $request): PublishResult;
}
