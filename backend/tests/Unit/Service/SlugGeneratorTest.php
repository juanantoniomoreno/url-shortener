<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Repository\LinkRepository;
use App\Service\SlugGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SlugGeneratorTest extends TestCase
{
    private LinkRepository&MockObject $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(LinkRepository::class);
    }

    public function test_generates_the_seven_character_candidate_when_it_is_free(): void
    {
        $this->repository->method('slugExists')->willReturn(false);
        $generator = new SlugGenerator($this->repository, $this->queuedCandidates(['abc1234']));

        self::assertSame('abc1234', $generator->generate());
    }

    public function test_generated_slug_from_the_random_seam_matches_the_expected_format(): void
    {
        $this->repository->method('slugExists')->willReturn(false);
        $generator = new SlugGenerator($this->repository);

        self::assertMatchesRegularExpression('/^[a-zA-Z0-9]{7}$/', $generator->generate());
    }

    public function test_rejects_a_reserved_candidate_and_uses_the_next_one(): void
    {
        $this->repository->method('slugExists')->willReturn(false);
        $generator = new SlugGenerator($this->repository, $this->queuedCandidates(['api', 'abc1234']));

        self::assertSame('abc1234', $generator->generate());
    }

    public function test_retries_when_a_candidate_collides_with_a_persisted_slug(): void
    {
        $this->repository->method('slugExists')->willReturnCallback(
            static fn (string $slug): bool => $slug === 'abc1234',
        );
        $generator = new SlugGenerator($this->repository, $this->queuedCandidates(['abc1234', 'zzz9999']));

        self::assertSame('zzz9999', $generator->generate());
    }

    public function test_fails_after_ten_unsuccessful_attempts(): void
    {
        $this->repository->method('slugExists')->willReturn(true);
        $attempts = 0;
        $generator = new SlugGenerator(
            $this->repository,
            static function () use (&$attempts): string {
                ++$attempts;

                return 'abc1234';
            },
        );

        try {
            $generator->generate();
            self::fail('Expected a RuntimeException after exhausting the generation attempts.');
        } catch (\RuntimeException $exception) {
            self::assertSame(10, $attempts);
            self::assertSame('Unable to generate a unique slug after 10 attempts.', $exception->getMessage());
        }
    }

    /**
     * @param list<string> $candidates
     *
     * @return \Closure(): string
     */
    private function queuedCandidates(array $candidates): \Closure
    {
        return static function () use (&$candidates): string {
            $candidate = array_shift($candidates);

            if ($candidate === null) {
                throw new \LogicException('Test candidate queue exhausted.');
            }

            return $candidate;
        };
    }
}
