<?php

declare(strict_types=1);

namespace App\Service;

final class ShortenerBaseUrl
{
    private const SCHEMES = ['http', 'https'];

    private function __construct(private readonly string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $scheme = parse_url($value, PHP_URL_SCHEME);
        $host = parse_url($value, PHP_URL_HOST);
        $query = parse_url($value, PHP_URL_QUERY);
        $fragment = parse_url($value, PHP_URL_FRAGMENT);

        $hasHttpScheme = \is_string($scheme) && \in_array(strtolower($scheme), self::SCHEMES, true);
        $hasHost = \is_string($host) && $host !== '';

        if (!$hasHttpScheme || !$hasHost || $query !== null || $fragment !== null) {
            throw new \InvalidArgumentException(\sprintf(
                'SHORTENER_BASE_URL must be an absolute http or https URL with a non-empty host and no query string or fragment; got "%s".',
                $value,
            ));
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function withSlug(string $slug): string
    {
        return rtrim($this->value, '/') . '/' . $slug;
    }
}
