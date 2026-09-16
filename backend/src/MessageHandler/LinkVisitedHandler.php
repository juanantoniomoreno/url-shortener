<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\LinkVisited;
use App\Repository\LinkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Consumes one accepted visit and records it on the referenced link.
 *
 * A missing link (deleted between dispatch and consumption) is acknowledged
 * without failing the message; the no-op is logged for diagnosis.
 */
#[AsMessageHandler(method: 'handle')]
final class LinkVisitedHandler
{
    public function __construct(
        private readonly LinkRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(LinkVisited $message): void
    {
        $link = $this->repository->findOneBySlug($message->getSlug());

        if ($link === null) {
            $this->logger->debug('Ignored a visit for a missing link.', [
                'slug' => $message->getSlug(),
            ]);

            return;
        }

        $link->markVisited();
        $this->entityManager->flush();
    }
}
