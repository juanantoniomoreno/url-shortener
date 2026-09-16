<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Domain\Entity\Link;
use App\Message\LinkVisited;
use App\MessageHandler\LinkVisitedHandler;
use App\Repository\LinkRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class LinkVisitedHandlerTest extends TestCase
{
    private LinkRepository&MockObject $repository;
    private EntityManagerInterface&MockObject $entityManager;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(LinkRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function test_increments_clicks_and_refreshes_updated_at_for_a_persisted_link(): void
    {
        $link = new Link('abc1234', 'https://example.com/article');
        $updatedAtBefore = $link->getUpdatedAt();

        $this->repository->expects(self::once())->method('findOneBySlug')->with('abc1234')->willReturn($link);
        $this->entityManager->expects(self::once())->method('flush');

        $this->handler()->handle(new LinkVisited('abc1234'));

        self::assertSame(1, $link->getClicks());
        self::assertGreaterThan($updatedAtBefore, $link->getUpdatedAt());
    }

    public function test_increments_clicks_from_the_current_count_for_repeat_visits(): void
    {
        $link = new Link('abc1234', 'https://example.com/article');
        $link->markVisited();
        self::assertSame(1, $link->getClicks());

        $this->repository->method('findOneBySlug')->willReturn($link);
        $this->entityManager->expects(self::once())->method('flush');

        $this->handler()->handle(new LinkVisited('abc1234'));

        self::assertSame(2, $link->getClicks());
    }

    public function test_missing_link_is_ignored_without_creating_or_modifying_anything(): void
    {
        $this->repository->expects(self::once())->method('findOneBySlug')->with('ghost99')->willReturn(null);
        $this->entityManager->expects(self::never())->method('flush');
        $this->entityManager->expects(self::never())->method('persist');

        $this->handler()->handle(new LinkVisited('ghost99'));
    }

    public function test_missing_link_no_op_is_logged_for_diagnostics(): void
    {
        $this->repository->method('findOneBySlug')->willReturn(null);
        $this->entityManager->expects(self::never())->method('flush');
        $this->logger->expects(self::once())->method('debug')->with(
            self::stringContains('missing link'),
            self::equalTo(['slug' => 'ghost99']),
        );

        $this->handler()->handle(new LinkVisited('ghost99'));
    }

    private function handler(): LinkVisitedHandler
    {
        return new LinkVisitedHandler($this->repository, $this->entityManager, $this->logger);
    }
}
