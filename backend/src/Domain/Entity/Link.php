<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\LinkRepository::class)]
#[ORM\Table(name: 'link')]
#[ORM\UniqueConstraint(name: 'uniq_link_slug', columns: ['slug'])]
#[ORM\Index(name: 'idx_link_created_at', columns: ['created_at'])]
class Link
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $slug;

    #[ORM\Column(type: 'text')]
    private string $originalUrl;

    #[ORM\Column(options: ['default' => 0])]
    private int $clicks = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $slug, string $originalUrl, ?\DateTimeImmutable $now = null)
    {
        $now ??= new \DateTimeImmutable();

        $this->slug = $slug;
        $this->originalUrl = $originalUrl;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getOriginalUrl(): string
    {
        return $this->originalUrl;
    }

    public function getClicks(): int
    {
        return $this->clicks;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function markVisited(?\DateTimeImmutable $visitedAt = null): void
    {
        ++$this->clicks;
        $this->updatedAt = $visitedAt ?? new \DateTimeImmutable();
    }
}
