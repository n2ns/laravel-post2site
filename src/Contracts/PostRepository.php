<?php

namespace N2ns\LaravelPost2Site\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;
use N2ns\LaravelPost2Site\Data\PostData;
use N2ns\LaravelPost2Site\Data\PublishedPostData;

interface PostRepository
{
    /** @return LengthAwarePaginator<int, PostData> */
    public function listPosts(array $filters, int $perPage = 20): LengthAwarePaginator;

    public function createPost(array $data): PostData;

    public function findPostByIdOrSlug(string $idOrSlug): PostData;

    public function updatePost(string|int $id, array $data): PostData;

    public function markPublished(string|int $id, PublishedPostData $published): PostData;
}
