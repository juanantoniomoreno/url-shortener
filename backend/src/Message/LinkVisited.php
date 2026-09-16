<?php

declare(strict_types=1);

namespace App\Message;

/**
 * A click recorded by the redirect endpoint, processed asynchronously by the worker.
 */
final readonly class LinkVisited
{
    public function __construct(
        private readonly string $slug,
    ) {
    }

    public function getSlug(): string
    {
        return $this->slug;
    }
}
