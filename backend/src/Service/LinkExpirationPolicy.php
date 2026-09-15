<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Entity\Link;

final class LinkExpirationPolicy
{
    private const INACTIVITY_DAYS = 30;

    /**
     * A link is expired when its last activity is strictly older than thirty days.
     *
     * This rule is derived state only: it MUST NOT mutate the link or the database.
     */
    public function isExpired(Link $link, ?\DateTimeImmutable $now = null): bool
    {
        $now ??= new \DateTimeImmutable();

        return $link->getUpdatedAt() < $now->modify(\sprintf('-%d days', self::INACTIVITY_DAYS));
    }
}
