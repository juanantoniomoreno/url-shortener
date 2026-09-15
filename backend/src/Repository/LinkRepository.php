<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Entity\Link;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Link>
 */
class LinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Link::class);
    }

    public function findOneBySlug(string $slug): ?Link
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    public function slugExists(string $slug): bool
    {
        $count = $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(link.id)')
            ->from(Link::class, 'link')
            ->where('link.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getSingleScalarResult();

        return 0 < (int) $count;
    }

    /**
     * @return list<Link>
     */
    public function findAllNewestFirst(): array
    {
        return $this->createQueryBuilder('link')
            ->orderBy('link.createdAt', 'DESC')
            ->addOrderBy('link.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
