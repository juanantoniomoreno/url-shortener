<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Entity\Link;
use App\Repository\LinkRepository;
use App\Service\LinkExpirationPolicy;
use App\Service\SlugGenerator;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class LinkController
{
    public function __construct(
        private readonly LinkRepository $repository,
        private readonly SlugGenerator $slugGenerator,
        private readonly EntityManagerInterface $entityManager,
        private readonly LinkExpirationPolicy $expirationPolicy,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function create(Request $request): JsonResponse
    {
        $payload = $this->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $url = $payload['url'] ?? null;
        if (!\is_string($url) || !$this->isValidUrl($url)) {
            return $this->error('invalid_url', 'The URL must be an absolute HTTP or HTTPS URL.', Response::HTTP_BAD_REQUEST);
        }

        $customSlug = $payload['slug'] ?? null;
        if ($customSlug !== null && !\is_string($customSlug)) {
            return $this->error('invalid_slug', 'The slug must be a string.', Response::HTTP_BAD_REQUEST);
        }

        $slug = $this->resolveSlug($customSlug);
        if ($slug instanceof JsonResponse) {
            return $slug;
        }

        $link = new Link($slug, $url);
        $this->entityManager->persist($link);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            return $this->error('slug_conflict', 'The requested slug is already in use.', Response::HTTP_CONFLICT);
        }

        return new JsonResponse($this->serializeLink($link), Response::HTTP_CREATED);
    }

    public function list(): JsonResponse
    {
        $links = $this->repository->findAllNewestFirst();

        return new JsonResponse(array_map($this->serializeLink(...), $links));
    }

    private function resolveSlug(?string $customSlug): string|JsonResponse
    {
        if ($customSlug === null) {
            try {
                return $this->slugGenerator->generate();
            } catch (\RuntimeException $exception) {
                return $this->error('slug_generation_failed', $exception->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        if ($customSlug === '' || !$this->isValidCustomSlug($customSlug)) {
            return $this->error('invalid_slug', 'The slug may only contain letters, numbers, hyphens, and underscores, must be 64 characters or fewer, and must not be a reserved name.', Response::HTTP_BAD_REQUEST);
        }

        if ($this->repository->slugExists($customSlug)) {
            return $this->error('slug_conflict', 'The requested slug is already in use.', Response::HTTP_CONFLICT);
        }

        return $customSlug;
    }

    private function decodeJson(Request $request): array|JsonResponse
    {
        $content = $request->getContent();

        if ($content === '' || $content === false) {
            return [];
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->error('invalid_json', 'The request body must be valid JSON.', Response::HTTP_BAD_REQUEST);
        }

        if (!\is_array($decoded)) {
            return $this->error('invalid_json', 'The request body must be a JSON object.', Response::HTTP_BAD_REQUEST);
        }

        return $decoded;
    }

    private function isValidUrl(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        if ($scheme === null || $host === null) {
            return false;
        }

        if (!\in_array(strtolower($scheme), ['http', 'https'], true)) {
            return false;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function isValidCustomSlug(string $slug): bool
    {
        if (strlen($slug) > 64) {
            return false;
        }

        if (preg_match('/^[a-zA-Z0-9_-]+$/', $slug) !== 1) {
            return false;
        }

        return !\in_array(strtolower($slug), SlugGenerator::RESERVED_SLUGS, true);
    }

    private function serializeLink(Link $link): array
    {
        $slug = $link->getSlug();

        return [
            'slug' => $slug,
            'url' => $link->getOriginalUrl(),
            'shortUrl' => $this->urlGenerator->generate('link_redirect', ['slug' => $slug], UrlGeneratorInterface::ABSOLUTE_URL),
            'clicks' => $link->getClicks(),
            'createdAt' => $link->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $link->getUpdatedAt()->format(\DateTimeInterface::ATOM),
            'isExpired' => $this->expirationPolicy->isExpired($link),
        ];
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
