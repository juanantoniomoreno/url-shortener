<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\Integration\DatabaseSchemaTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

abstract class LinkApiTestCase extends DatabaseSchemaTestCase
{
    protected function jsonBrowser(): KernelBrowser
    {
        $browser = self::createBrowser();
        $browser->setServerParameter('HTTP_ACCEPT', 'application/json');
        $browser->setServerParameter('CONTENT_TYPE', 'application/json');

        return $browser;
    }

    protected function decodeResponse(?string $content): array
    {
        self::assertNotNull($content);
        self::assertJson($content);

        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    protected function assertErrorResponse(KernelBrowser $client, int $statusCode, string $errorCode): void
    {
        $response = $client->getResponse();
        self::assertSame($statusCode, $response->getStatusCode());

        $payload = $this->decodeResponse($response->getContent());
        self::assertSame($errorCode, $payload['error']['code']);
        self::assertArrayHasKey('message', $payload['error']);
    }

    protected function assertErrorShape(?string $content): void
    {
        $payload = $this->decodeResponse($content);
        self::assertArrayHasKey('error', $payload);
        self::assertArrayHasKey('code', $payload['error']);
        self::assertArrayHasKey('message', $payload['error']);
    }
}
