<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\LinkRepository;

final class SlugGenerator
{
    public const RESERVED_SLUGS = ['api', 'admin', 'dashboard'];

    private const SLUG_LENGTH = 7;
    private const MAX_ATTEMPTS = 10;
    private const ALPHABET = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * @param \Closure(): string|null $candidateProvider deterministic candidate seam for tests
     */
    public function __construct(
        private readonly LinkRepository $repository,
        private readonly ?\Closure $candidateProvider = null,
    ) {
    }

    /**
     * @throws \RuntimeException when no unique, non-reserved candidate is found within ten attempts
     */
    public function generate(): string
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; ++$attempt) {
            $candidate = $this->nextCandidate();

            if (\in_array($candidate, self::RESERVED_SLUGS, true)) {
                continue;
            }

            if (!$this->repository->slugExists($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException(\sprintf('Unable to generate a unique slug after %d attempts.', self::MAX_ATTEMPTS));
    }

    private function nextCandidate(): string
    {
        if ($this->candidateProvider !== null) {
            return ($this->candidateProvider)();
        }

        $alphabet = self::ALPHABET;
        $candidate = '';

        for ($position = 0; $position < self::SLUG_LENGTH; ++$position) {
            $candidate .= $alphabet[random_int(0, \strlen($alphabet) - 1)];
        }

        return $candidate;
    }
}
