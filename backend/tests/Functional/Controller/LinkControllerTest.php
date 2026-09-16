<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Domain\Entity\Link;
use App\Message\LinkVisited;
use App\Service\SlugGenerator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Test double that records every dispatched message instead of routing it.
 */
final class RecordingMessageBusSpy implements MessageBusInterface
{
    /** @var list<object> */
    public array $dispatched = [];

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->dispatched[] = $message;

        return new Envelope($message, $stamps);
    }
}

/**
 * Test double that simulates an unreachable broker on every dispatch attempt.
 */
final class FailingMessageBusSpy implements MessageBusInterface
{
    public int $dispatchAttempts = 0;

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        ++$this->dispatchAttempts;

        throw new \RuntimeException('The message broker is unreachable.');
    }
}

/**
 * Functional coverage for the create/list API and the dispatch side of redirects.
 */
final class LinkControllerTest extends LinkApiTestCase
{
    private function overrideMessageBus(MessageBusInterface $bus): void
    {
        self::getContainer()->set('messenger.bus.default', $bus);
        self::getContainer()->set('message_bus', $bus);
    }

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

    public function test_active_redirect_dispatches_one_link_visited_message(): void
    {
        $this->persistLinkForTracking('tracked', 'https://example.com/tracked');

        $bus = new RecordingMessageBusSpy();
        $client = $this->jsonBrowser();
        $client->disableReboot();
        $this->overrideMessageBus($bus);

        $client->request('GET', '/tracked');

        $response = $client->getResponse();
        self::assertSame(302, $response->getStatusCode());

        self::assertCount(1, $bus->dispatched);
        self::assertInstanceOf(LinkVisited::class, $bus->dispatched[0]);
        self::assertSame('tracked', $bus->dispatched[0]->getSlug());
    }

    public function test_unknown_slug_redirect_does_not_dispatch_link_visited(): void
    {
        $bus = new RecordingMessageBusSpy();
        $client = $this->jsonBrowser();
        $client->disableReboot();
        $this->overrideMessageBus($bus);

        $client->request('GET', '/unknown1');

        self::assertSame(404, $client->getResponse()->getStatusCode());
        self::assertSame([], $bus->dispatched);
    }

    public function test_expired_link_redirect_does_not_dispatch_link_visited(): void
    {
        $entityManager = $this->service('doctrine.orm.default_entity_manager');
        $link = new Link('expired2', 'https://example.com/expired');
        $entityManager->persist($link);
        $entityManager->flush();

        $reflection = new \ReflectionProperty(Link::class, 'updatedAt');
        $reflection->setValue($link, new \DateTimeImmutable('-31 days'));
        $entityManager->flush();

        $bus = new RecordingMessageBusSpy();
        $client = $this->jsonBrowser();
        $client->disableReboot();
        $this->overrideMessageBus($bus);

        $client->request('GET', '/expired2');

        self::assertSame(410, $client->getResponse()->getStatusCode());
        self::assertSame([], $bus->dispatched);
    }

    public function test_broker_publish_failure_is_logged_and_does_not_break_the_redirect(): void
    {
        $this->persistLinkForTracking('resilient', 'https://example.com/resilient');

        $bus = new FailingMessageBusSpy();
        $client = $this->jsonBrowser();
        $client->disableReboot();
        $this->overrideMessageBus($bus);

        $testHandler = new \Monolog\Handler\TestHandler();
        $logger = self::getContainer()->get('monolog.logger');
        $logger->pushHandler($testHandler);

        $client->request('GET', '/resilient');

        $response = $client->getResponse();
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('https://example.com/resilient', $response->headers->get('Location'));
        self::assertSame(1, $bus->dispatchAttempts);
        self::assertTrue($testHandler->hasErrorThatContains('click tracking'));
    }

    private function persistLinkForTracking(string $slug, string $url): void
    {
        $entityManager = $this->service('doctrine.orm.default_entity_manager');
        $entityManager->persist(new Link($slug, $url));
        $entityManager->flush();
    }
}
