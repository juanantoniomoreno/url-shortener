<?php

declare(strict_types=1);

namespace App\Controller;

use App\Message\LinkVisited;
use App\Repository\LinkRepository;
use App\Service\LinkExpirationPolicy;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

final class LinkRedirectController
{
    public function __construct(
        private readonly LinkRepository $repository,
        private readonly LinkExpirationPolicy $expirationPolicy,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function redirect(string $slug): Response
    {
        $link = $this->repository->findOneBySlug($slug);

        if ($link === null) {
            return $this->error('not_found', 'The requested short link does not exist.', Response::HTTP_NOT_FOUND);
        }

        if ($this->expirationPolicy->isExpired($link)) {
            return $this->error('gone', 'This short link has expired.', Response::HTTP_GONE);
        }

        $this->queueVisit($slug);

        return new Response('', Response::HTTP_FOUND, ['Location' => $link->getOriginalUrl()]);
    }

    /**
     * Queues the visit without ever blocking or failing the redirect itself:
     * if the broker is unavailable the failure is logged and the visit is lost,
     * but the visitor still reaches the original URL.
     */
    private function queueVisit(string $slug): void
    {
        try {
            $this->bus->dispatch(new LinkVisited($slug));
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to queue click tracking for {slug}: {reason}.', [
                'slug' => $slug,
                'reason' => $exception->getMessage(),
            ]);
        }
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
