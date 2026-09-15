<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Domain\Entity\Link;
use App\Repository\LinkRepository;
use App\Tests\Integration\DatabaseSchemaTestCase;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

final class LinkRepositoryTest extends DatabaseSchemaTestCase
{
    public function test_finds_a_link_by_its_slug_and_returns_null_for_unknown_slugs(): void
    {
        $this->persistLink('abc1234', 'https://example.com/one');

        $repository = $this->repository();

        self::assertNotNull($repository->findOneBySlug('abc1234'));
        self::assertSame('https://example.com/one', $repository->findOneBySlug('abc1234')?->getOriginalUrl());
        self::assertNull($repository->findOneBySlug('unknown1'));
    }

    public function test_slug_existence_follows_persistence_state(): void
    {
        $repository = $this->repository();

        self::assertFalse($repository->slugExists('abc1234'));

        $this->persistLink('abc1234', 'https://example.com/one');

        self::assertTrue($repository->slugExists('abc1234'));
    }

    public function test_lists_links_newest_first(): void
    {
        $older = new Link('older12', 'https://example.com/older', new \DateTimeImmutable('2026-01-01T12:00:00+00:00'));
        $newer = new Link('newer12', 'https://example.com/newer', new \DateTimeImmutable('2026-01-02T12:00:00+00:00'));

        $entityManager = $this->entityManager();
        $entityManager->persist($older);
        $entityManager->persist($newer);
        $entityManager->flush();

        $slugs = array_map(
            static fn (Link $link): string => $link->getSlug(),
            $this->repository()->findAllNewestFirst(),
        );

        self::assertSame(['newer12', 'older12'], $slugs);
    }

    public function test_the_database_rejects_duplicate_slugs(): void
    {
        $this->persistLink('abc1234', 'https://example.com/one');

        $entityManager = $this->entityManager();
        $entityManager->persist(new Link('abc1234', 'https://example.com/two'));

        $this->expectException(UniqueConstraintViolationException::class);

        $entityManager->flush();
    }

    public function test_a_new_link_starts_with_zero_clicks_and_equal_timestamps(): void
    {
        $this->persistLink('abc1234', 'https://example.com/one');

        $link = $this->repository()->findOneBySlug('abc1234');

        self::assertSame(0, $link?->getClicks());
        self::assertSame($link?->getCreatedAt(), $link?->getUpdatedAt());
    }

    private function repository(): LinkRepository
    {
        return self::service(LinkRepository::class);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::service('doctrine.orm.default_entity_manager');
    }

    private function persistLink(string $slug, string $originalUrl): void
    {
        $entityManager = $this->entityManager();
        $entityManager->persist(new Link($slug, $originalUrl));
        $entityManager->flush();
    }
}
