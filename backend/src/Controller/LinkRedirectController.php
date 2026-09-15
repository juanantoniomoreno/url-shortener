<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\LinkRepository;
use App\Service\LinkExpirationPolicy;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class LinkRedirectController
{
    public function __construct(
        private readonly LinkRepository $repository,
        private readonly LinkExpirationPolicy $expirationPolicy,
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

        return new Response('', Response::HTTP_FOUND, ['Location' => $link->getOriginalUrl()]);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
