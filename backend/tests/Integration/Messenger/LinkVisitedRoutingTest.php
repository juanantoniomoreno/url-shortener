<?php

declare(strict_types=1);

namespace App\Tests\Integration\Messenger;

use App\Message\LinkVisited;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

/**
 * Records envelopes routed to it, without contacting any real broker.
 */
final class RecordingSender implements SenderInterface
{
    /** @var list<Envelope> */
    public array $sent = [];

    public function send(Envelope $envelope): Envelope
    {
        $this->sent[] = $envelope;

        return $envelope;
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        throw new \BadMethodCallException('Decoding is not expected in routing tests.');
    }
}

final class LinkVisitedRoutingTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
        parent::tearDown();
    }

    public function test_link_visited_is_routed_to_the_async_transport(): void
    {
        $sender = new RecordingSender();
        self::getContainer()->set('messenger.transport.async', $sender);

        $bus = self::getContainer()->get('messenger.bus.default');
        $bus->dispatch(new LinkVisited('abc1234'));

        self::assertCount(1, $sender->sent);
        self::assertInstanceOf(LinkVisited::class, $sender->sent[0]->getMessage());
        self::assertSame('abc1234', $sender->sent[0]->getMessage()->getSlug());
    }
}
