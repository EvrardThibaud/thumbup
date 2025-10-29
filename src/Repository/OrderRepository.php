<?php

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function searchPaginated(
        ?string $q,
        ?int $clientId,
        ?\App\Enum\OrderStatus $status,
        ?\DateTimeInterface $from,
        ?\DateTimeInterface $to,
        int $page = 1,
        int $limit = 20
    ): array {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.client', 'c')->addSelect('c')
            ->orderBy('o.updatedAt', 'DESC');
    
        if ($q) {
            $qb->andWhere('LOWER(o.title) LIKE :q OR LOWER(o.brief) LIKE :q2')
               ->setParameter('q', '%'.mb_strtolower($q).'%')
               ->setParameter('q2', '%'.mb_strtolower($q).'%');
        }
        if ($clientId) {
            $qb->andWhere('c.id = :clientId')->setParameter('clientId', $clientId);
        }
        if ($status) {
            $qb->andWhere('o.status = :status')->setParameter('status', $status);
            // si souci ORM3 avec enums, utiliser ->setParameter('status', $status->value)
        }
        if ($from) {
            $from = (new \DateTimeImmutable($from->format('Y-m-d')))->setTime(0,0);
            $qb->andWhere('o.createdAt >= :from')->setParameter('from', $from);
        }
        if ($to) {
            $to = (new \DateTimeImmutable($to->format('Y-m-d')))->setTime(23,59,59);
            $qb->andWhere('o.createdAt <= :to')->setParameter('to', $to);
        }
    
        $offset = max(0, ($page - 1) * $limit);
        $qb->setFirstResult($offset)->setMaxResults($limit);
    
        $items = $qb->getQuery()->getResult();
    
        // total (sans pagination)
        $countQb = clone $qb;
        $countQb->resetDQLPart('orderBy')->setFirstResult(null)->setMaxResults(null)
            ->select('COUNT(o.id)');
        $total = (int) $countQb->getQuery()->getSingleScalarResult();
    
        return ['items' => $items, 'total' => $total, 'page' => $page, 'limit' => $limit];
    }
    

    //    /**
    //     * @return Order[] Returns an array of Order objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('o.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Order
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
