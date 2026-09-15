<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Domain\Entity\Link;

final class LinkRedirectControllerTest extends LinkApiTestCase
{
    private function persistLink(string $slug, string $url): void
    {
        $entityManager = $this->service('doctrine.orm.default_entity_manager');
        $entityManager->persist(new Link($slug, $url));
        $entityManager->flush();
    }

    private function setUpdatedAt(Link $link, \DateTimeImmutable $updatedAt): void
    {
        $reflection = new \ReflectionProperty(Link::class, 'updatedAt');
        $reflection->setValue($link, $updatedAt);
    }

    public function test_active_redirect_returns_302_to_original_url(): void
    {
        $this->persistLink('active1', 'https://example.com/redirect');

        $client = $this->jsonBrowser();
        $client->request('GET', '/active1');
        $response = $client->getResponse();

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('https://example.com/redirect', $response->headers->get('Location'));
    }

    public function test_unknown_slug_returns_404(): void
    {
        $client = $this->jsonBrowser();
        $client->request('GET', '/unknown1');

        $response = $client->getResponse();
        self::assertSame(404, $response->getStatusCode());
        $this->assertErrorShape($response->getContent());
    }

    public function test_expired_link_returns_410(): void
    {
        $entityManager = $this->service('doctrine.orm.default_entity_manager');
        $link = new Link('expired1', 'https://example.com/expired');
        $entityManager->persist($link);
        $entityManager->flush();

        $this->setUpdatedAt($link, new \DateTimeImmutable('-31 days'));
        $entityManager->flush();

        $client = $this->jsonBrowser();
        $client->request('GET', '/expired1');

        $response = $client->getResponse();
        self::assertSame(410, $response->getStatusCode());
        $this->assertErrorShape($response->getContent());
    }
}
