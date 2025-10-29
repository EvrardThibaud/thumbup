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
        int $limit = 20,
        string $sort = 'updatedAt',
        string $dir = 'DESC'
    ): array {
        $map = [
            'id'        => 'o.id',
            'title'     => 'o.title',
            'client'    => 'c.name',
            'price'     => 'o.price',
            'status'    => 'o.status',
            'dueAt'     => 'o.dueAt',
            'createdAt' => 'o.createdAt',
            'updatedAt' => 'o.updatedAt',
        ];
        $sortExpr = $map[$sort] ?? 'o.updatedAt';
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        // --- liste paginée
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.client', 'c')->addSelect('c');

        if ($q) {
            $like = '%'.mb_strtolower($q).'%';
            $qb->andWhere('LOWER(o.title) LIKE :q OR LOWER(o.brief) LIKE :q2')
            ->setParameter('q', $like)
            ->setParameter('q2', $like);
        }
        if ($clientId) {
            $qb->andWhere('c.id = :clientId')->setParameter('clientId', $clientId);
        }
        if ($status) {
            // Si souci avec ORM/DBAL, remplacer par ->setParameter('status', $status->value)
            $qb->andWhere('o.status = :status')->setParameter('status', $status);
        }
        if ($from) {
            $from = (new \DateTimeImmutable($from->format('Y-m-d')))->setTime(0, 0);
            $qb->andWhere('o.createdAt >= :from')->setParameter('from', $from);
        }
        if ($to) {
            $to = (new \DateTimeImmutable($to->format('Y-m-d')))->setTime(23, 59, 59);
            $qb->andWhere('o.createdAt <= :to')->setParameter('to', $to);
        }

        $qb->orderBy($sortExpr, $dir)
        ->setFirstResult(max(0, ($page - 1) * $limit))
        ->setMaxResults($limit);

        $items = $qb->getQuery()->getResult();

        // --- total avec les mêmes filtres (sans order/limit)
        $countQb = $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->leftJoin('o.client', 'c');

        if ($q) {
            $like = '%'.mb_strtolower($q).'%';
            $countQb->andWhere('LOWER(o.title) LIKE :q_c OR LOWER(o.brief) LIKE :q2_c')
                    ->setParameter('q_c', $like)
                    ->setParameter('q2_c', $like);
        }
        if ($clientId) {
            $countQb->andWhere('c.id = :clientId_c')->setParameter('clientId_c', $clientId);
        }
        if ($status) {
            $countQb->andWhere('o.status = :status_c')->setParameter('status_c', $status);
            // ou $status->value selon ton mapping
        }
        if ($from) {
            $from = (new \DateTimeImmutable($from->format('Y-m-d')))->setTime(0, 0);
            $countQb->andWhere('o.createdAt >= :from_c')->setParameter('from_c', $from);
        }
        if ($to) {
            $to = (new \DateTimeImmutable($to->format('Y-m-d')))->setTime(23, 59, 59);
            $countQb->andWhere('o.createdAt <= :to_c')->setParameter('to_c', $to);
        }

        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        return ['items' => $items, 'total' => $total, 'page' => $page, 'limit' => $limit];
    }

    public function findForExport(
        ?string $q,
        ?int $clientId,
        ?\App\Enum\OrderStatus $status,
        ?\DateTimeInterface $from,
        ?\DateTimeInterface $to,
        string $sort = 'updatedAt',
        string $dir = 'DESC'
    ): iterable {
        $map = [
            'id'=>'o.id','title'=>'o.title','client'=>'c.name','price'=>'o.price',
            'status'=>'o.status','dueAt'=>'o.dueAt','createdAt'=>'o.createdAt','updatedAt'=>'o.updatedAt',
        ];
        $sortExpr = $map[$sort] ?? 'o.updatedAt';
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
    
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.client','c')->addSelect('c');
    
        if ($q) {
            $like = '%'.mb_strtolower($q).'%';
            $qb->andWhere('LOWER(o.title) LIKE :q OR LOWER(o.brief) LIKE :q2')
               ->setParameter('q',$like)->setParameter('q2',$like);
        }
        if ($clientId) { $qb->andWhere('c.id = :cid')->setParameter('cid',$clientId); }
        if ($status)   { $qb->andWhere('o.status = :st')->setParameter('st',$status); }
        if ($from)     { $f=(new \DateTimeImmutable($from->format('Y-m-d')))->setTime(0,0);
                         $qb->andWhere('o.createdAt >= :from')->setParameter('from',$f); }
        if ($to)       { $t=(new \DateTimeImmutable($to->format('Y-m-d')))->setTime(23,59,59);
                         $qb->andWhere('o.createdAt <= :to')->setParameter('to',$t); }
    
        return $qb->orderBy($sortExpr,$dir)->getQuery()->toIterable();
    }

    public function dueByClient(): array
    {
        // map [clientId => dueCents]
        $rows = $this->createQueryBuilder('o')
            ->innerJoin('o.client', 'c')
            ->select('c.id AS clientId')
            ->addSelect('COALESCE(SUM(CASE WHEN o.status = :deliv THEN o.price ELSE 0 END), 0) AS dueCents')
            ->groupBy('c.id')
            ->setParameter('deliv', \App\Enum\OrderStatus::DELIVERED->value)
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $r) { $map[(int)$r['clientId']] = (int)$r['dueCents']; }
        return $map;
    }

    public function dueAndPaidForClient(int $clientId): array
    {
        $r = $this->createQueryBuilder('o')
            ->innerJoin('o.client', 'c')
            ->select('COALESCE(SUM(CASE WHEN o.status = :deliv THEN o.price ELSE 0 END), 0) AS dueCents')
            ->addSelect('COALESCE(SUM(CASE WHEN o.status = :paid  THEN o.price ELSE 0 END), 0) AS paidCents')
            ->andWhere('c.id = :cid')
            ->setParameter('cid', $clientId)
            ->setParameter('deliv', \App\Enum\OrderStatus::DELIVERED->value)
            ->setParameter('paid',  \App\Enum\OrderStatus::PAID->value)
            ->getQuery()
            ->getSingleResult(); // scalars

        return [
            'dueCents'  => (int)$r['dueCents'],
            'paidCents' => (int)$r['paidCents'],
        ];
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
