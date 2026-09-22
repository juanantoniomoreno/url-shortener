<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\ShortenerBaseUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ShortenerBaseUrlTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function validBaseUrls(): iterable
    {
        yield 'plain http' => ['http://short.test'];
        yield 'plain https' => ['https://short.test'];
        yield 'path segment' => ['https://example.com/shortener'];
        yield 'trailing slash' => ['https://short.test/'];
        yield 'uppercase scheme' => ['HTTPS://short.test'];
    }

    #[DataProvider('validBaseUrls')]
    public function test_a_well_formed_base_url_is_accepted(string $value): void
    {
        self::assertSame($value, ShortenerBaseUrl::fromString($value)->value());
    }

    /** @return iterable<string, array{string}> */
    public static function malformedBaseUrls(): iterable
    {
        yield 'empty string' => [''];
        yield 'missing scheme' => ['short.test'];
        yield 'missing host' => ['https://'];
        yield 'empty host before path' => ['https:///path'];
        yield 'non-http scheme' => ['ftp://short.test'];
        yield 'query string' => ['https://short.test/?q=1'];
        yield 'fragment' => ['https://short.test/#frag'];
    }

    #[DataProvider('malformedBaseUrls')]
    public function test_a_malformed_base_url_is_rejected_naming_the_variable_and_the_expected_shape(string $value): void
    {
        try {
            ShortenerBaseUrl::fromString($value);
            self::fail('Expected an InvalidArgumentException for ' . var_export($value, true) . '.');
        } catch (\InvalidArgumentException $exception) {
            $message = $exception->getMessage();
            self::assertStringContainsString('SHORTENER_BASE_URL', $message);
            self::assertStringContainsString('http', $message);
            if ($value !== '') {
                self::assertStringContainsString($value, $message);
            }
        }
    }

    public function test_with_slug_appends_the_slug_to_a_plain_base_url(): void
    {
        $baseUrl = ShortenerBaseUrl::fromString('https://short.test');

        self::assertSame('https://short.test/abc1234', $baseUrl->withSlug('abc1234'));
    }

    public function test_with_slug_trims_a_trailing_slash(): void
    {
        $baseUrl = ShortenerBaseUrl::fromString('https://short.test/');

        self::assertSame('https://short.test/abc1234', $baseUrl->withSlug('abc1234'));
    }

    public function test_with_slug_preserves_an_optional_path_segment(): void
    {
        $baseUrl = ShortenerBaseUrl::fromString('https://example.com/shortener');

        self::assertSame('https://example.com/shortener/abc1234', $baseUrl->withSlug('abc1234'));
    }
}
