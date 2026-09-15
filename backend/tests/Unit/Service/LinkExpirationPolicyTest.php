<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Domain\Entity\Link;
use App\Service\LinkExpirationPolicy;
use PHPUnit\Framework\TestCase;

final class LinkExpirationPolicyTest extends TestCase
{
    private const REFERENCE_NOW = '2026-02-01T12:00:00+00:00';

    public function test_a_recently_updated_link_is_active(): void
    {
        $now = new \DateTimeImmutable(self::REFERENCE_NOW);
        $link = new Link('abc1234', 'https://example.com', $now->modify('-29 days'));

        self::assertFalse((new LinkExpirationPolicy())->isExpired($link, $now));
    }

    public function test_a_link_inactive_for_more_than_thirty_days_is_expired(): void
    {
        $now = new \DateTimeImmutable(self::REFERENCE_NOW);
        $link = new Link('abc1234', 'https://example.com', $now->modify('-31 days'));

        self::assertTrue((new LinkExpirationPolicy())->isExpired($link, $now));
    }

    public function test_a_link_updated_exactly_thirty_days_ago_is_still_active(): void
    {
        $now = new \DateTimeImmutable(self::REFERENCE_NOW);
        $link = new Link('abc1234', 'https://example.com', $now->modify('-30 days'));

        self::assertFalse((new LinkExpirationPolicy())->isExpired($link, $now));
    }

    public function test_evaluating_expiration_does_not_mutate_the_link(): void
    {
        $now = new \DateTimeImmutable(self::REFERENCE_NOW);
        $updatedAt = $now->modify('-31 days');
        $link = new Link('abc1234', 'https://example.com', $updatedAt);

        (new LinkExpirationPolicy())->isExpired($link, $now);

        self::assertSame(0, $link->getClicks());
        self::assertSame($updatedAt, $link->getUpdatedAt());
    }
}
