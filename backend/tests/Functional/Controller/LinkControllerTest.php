<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Domain\Entity\Link;
use App\Service\SlugGenerator;

final class LinkControllerTest extends LinkApiTestCase
{
    public function test_create_link_with_generated_slug_returns_201_and_documented_fields(): void
    {
        $client = $this->jsonBrowser();
        $client->request('POST', '/api/links', [], [], [], json_encode(['url' => 'https://example.com/article'], JSON_THROW_ON_ERROR));

        $response = $client->getResponse();
        self::assertSame(201, $response->getStatusCode());

        $payload = $this->decodeResponse($response->getContent());
        self::assertMatchesRegularExpression('/^[a-zA-Z0-9]{7}$/', $payload['slug']);
        self::assertSame('https://example.com/article', $payload['url']);
        self::assertStringEndsWith('/' . $payload['slug'], $payload['shortUrl']);
        self::assertSame(0, $payload['clicks']);
        self::assertFalse($payload['isExpired']);
        self::assertArrayHasKey('createdAt', $payload);
        self::assertArrayHasKey('updatedAt', $payload);
        self::assertSame($payload['createdAt'], $payload['updatedAt']);
    }

    public function test_create_link_with_custom_slug_returns_requested_slug(): void
    {
        $client = $this->jsonBrowser();
        $client->request('POST', '/api/links', [], [], [], json_encode([
            'url' => 'https://example.com/custom',
            'slug' => 'my-link_1',
        ], JSON_THROW_ON_ERROR));

        $response = $client->getResponse();
        self::assertSame(201, $response->getStatusCode());

        $payload = $this->decodeResponse($response->getContent());
        self::assertSame('my-link_1', $payload['slug']);
        self::assertSame('https://example.com/custom', $payload['url']);
        self::assertStringEndsWith('/my-link_1', $payload['shortUrl']);
    }

    public function test_create_rejects_missing_url(): void
    {
        $client = $this->jsonBrowser();
        $client->request('POST', '/api/links', [], [], [], json_encode([], JSON_THROW_ON_ERROR));

        $this->assertErrorResponse($client, 400, 'invalid_url');
    }

    public function test_create_rejects_non_http_url(): void
    {
        $client = $this->jsonBrowser();
        $client->request('POST', '/api/links', [], [], [], json_encode(['url' => 'ftp://example.com'], JSON_THROW_ON_ERROR));

        $this->assertErrorResponse($client, 400, 'invalid_url');
    }

    public function test_create_rejects_relative_url(): void
    {
        $client = $this->jsonBrowser();
        $client->request('POST', '/api/links', [], [], [], json_encode(['url' => '/some/path'], JSON_THROW_ON_ERROR));

        $this->assertErrorResponse($client, 400, 'invalid_url');
    }

    public function test_create_rejects_malformed_json(): void
    {
        $client = $this->jsonBrowser();
        $client->request('POST', '/api/links', [], [], [], '{not json');

        $this->assertErrorResponse($client, 400, 'invalid_json');
    }

    public function test_create_rejects_invalid_custom_slug_format(): void
    {
        $client = $this->jsonBrowser();
        $client->request('POST', '/api/links', [], [], [], json_encode([
            'url' => 'https://example.com',
            'slug' => 'my slug!',
        ], JSON_THROW_ON_ERROR));

        $this->assertErrorResponse($client, 400, 'invalid_slug');
    }

    public function test_create_rejects_reserved_custom_slug(): void
    {
        $client = $this->jsonBrowser();
        $client->request('POST', '/api/links', [], [], [], json_encode([
            'url' => 'https://example.com',
            'slug' => 'api',
        ], JSON_THROW_ON_ERROR));

        $this->assertErrorResponse($client, 400, 'invalid_slug');
    }

    public function test_create_rejects_empty_custom_slug(): void
    {
        $client = $this->jsonBrowser();
        $client->request('POST', '/api/links', [], [], [], json_encode([
            'url' => 'https://example.com',
            'slug' => '',
        ], JSON_THROW_ON_ERROR));

        $this->assertErrorResponse($client, 400, 'invalid_slug');
    }

    public function test_create_rejects_slug_conflict_with_409(): void
    {
        $client = $this->jsonBrowser();
        $client->request('POST', '/api/links', [], [], [], json_encode([
            'url' => 'https://example.com/first',
            'slug' => 'conflict',
        ], JSON_THROW_ON_ERROR));
        self::assertSame(201, $client->getResponse()->getStatusCode());

        $client->request('POST', '/api/links', [], [], [], json_encode([
            'url' => 'https://example.com/second',
            'slug' => 'conflict',
        ], JSON_THROW_ON_ERROR));

        $response = $client->getResponse();
        self::assertSame(409, $response->getStatusCode());

        $payload = $this->decodeResponse($response->getContent());
        self::assertSame('slug_conflict', $payload['error']['code']);
    }

    public function test_list_links_returns_newest_first(): void
    {
        $entityManager = $this->service('doctrine.orm.default_entity_manager');
        $older = new Link('older12', 'https://example.com/older', new \DateTimeImmutable('2026-01-01T12:00:00+00:00'));
        $newer = new Link('newer12', 'https://example.com/newer', new \DateTimeImmutable('2026-01-02T12:00:00+00:00'));
        $entityManager->persist($older);
        $entityManager->persist($newer);
        $entityManager->flush();

        $client = $this->jsonBrowser();
        $client->request('GET', '/api/links');

        $response = $client->getResponse();
        self::assertSame(200, $response->getStatusCode());

        $payload = $this->decodeResponse($response->getContent());
        self::assertCount(2, $payload);
        self::assertSame(['newer12', 'older12'], array_map(static fn (array $item): string => $item['slug'], $payload));
        self::assertArrayHasKey('shortUrl', $payload[0]);
        self::assertArrayHasKey('isExpired', $payload[0]);
    }

    public function test_list_returns_empty_array_when_no_links_exist(): void
    {
        $client = $this->jsonBrowser();
        $client->request('GET', '/api/links');

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertSame([], $this->decodeResponse($client->getResponse()->getContent()));
    }

    public function test_reserved_generated_slugs_are_never_persisted(): void
    {
        $client = $this->jsonBrowser();
        $client->request('POST', '/api/links', [], [], [], json_encode(['url' => 'https://example.com'], JSON_THROW_ON_ERROR));

        $payload = $this->decodeResponse($client->getResponse()->getContent());
        self::assertNotContains($payload['slug'], SlugGenerator::RESERVED_SLUGS);
    }

    public function test_api_routes_are_not_shadowed_by_the_catch_all_redirect_route(): void
    {
        $client = $this->jsonBrowser();
        $client->request('GET', '/api/links');

        self::assertSame(200, $client->getResponse()->getStatusCode());
    }
}
