<?php

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Enum\OrderStatus;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /**
     * @return array{0: Order[], 1: int}
     */
    public function searchPaginated(
        ?string $q,
        ?int $clientId,
        ?OrderStatus $status,
        ?\DateTimeInterface $from,
        ?\DateTimeInterface $to,
        ?bool $paid,
        int $page,
        int $limit,
        string $sort,
        string $dir
    ): array {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.client', 'c')->addSelect('c')
            ->leftJoin('o.assets', 'a')
            ->addSelect('COUNT(a.id) AS HIDDEN assetsCount');

        if ($q)          { $qb->andWhere('o.title LIKE :q')->setParameter('q', '%'.$q.'%'); }
        if ($clientId)   { $qb->andWhere('c.id = :cid')->setParameter('cid', $clientId); }
        if ($status)     { $qb->andWhere('o.status = :st')->setParameter('st', $status->value); }
        if ($from)       { $qb->andWhere('o.createdAt >= :from')->setParameter('from', $from); }
        if ($to)         { $qb->andWhere('o.createdAt <= :to')->setParameter('to', $to); }
        if ($paid !== null) { $qb->andWhere('o.paid = :paid')->setParameter('paid', $paid); }

        // Group pour pouvoir compter les assets
        $qb->groupBy('o.id, c.id');

        $sortMap = [
            'id'        => 'o.id',
            'title'     => 'o.title',
            'client'    => 'c.name',
            'price'     => 'o.price',
            'status'    => 'o.status',
            'dueAt'     => 'o.dueAt',
            'createdAt' => 'o.createdAt',
            'updatedAt' => 'o.updatedAt',
            'assets'    => 'assetsCount', // <-- nouveau
        ];
        $qb->orderBy($sortMap[$sort] ?? 'o.updatedAt', strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC');

        // Count propre (sans groupBy)
        $countQb = clone $qb;
        $count = (int) $countQb
            ->resetDQLPart('orderBy')
            ->resetDQLPart('groupBy')
            ->select('COUNT(DISTINCT o.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb->setFirstResult(($page-1)*$limit)->setMaxResults($limit)->getQuery()->getResult();

        return [$items, $count];
    }

    /**
     * @return iterable<Order>
     */
    public function findForExport(
        ?string $q,
        ?int $clientId,
        ?OrderStatus $status,
        ?\DateTimeInterface $from,
        ?\DateTimeInterface $to,
        ?bool $paid,
        string $sort,
        string $dir
    ): iterable {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.client', 'c')->addSelect('c')
            ->leftJoin('o.assets', 'a')
            ->addSelect('COUNT(a.id) AS HIDDEN assetsCount');

        if ($q)          { $qb->andWhere('o.title LIKE :q')->setParameter('q', '%'.$q.'%'); }
        if ($clientId)   { $qb->andWhere('c.id = :cid')->setParameter('cid', $clientId); }
        if ($status)     { $qb->andWhere('o.status = :st')->setParameter('st', $status->value); }
        if ($from)       { $qb->andWhere('o.createdAt >= :from')->setParameter('from', $from); }
        if ($to)         { $qb->andWhere('o.createdAt <= :to')->setParameter('to', $to); }
        if ($paid !== null) { $qb->andWhere('o.paid = :paid')->setParameter('paid', $paid); }

        $qb->groupBy('o.id, c.id');

        $sortMap = [
            'id'        => 'o.id',
            'title'     => 'o.title',
            'client'    => 'c.name',
            'price'     => 'o.price',
            'status'    => 'o.status',
            'dueAt'     => 'o.dueAt',
            'createdAt' => 'o.createdAt',
            'updatedAt' => 'o.updatedAt',
            'assets'    => 'assetsCount', // <-- nouveau
        ];
        $qb->orderBy($sortMap[$sort] ?? 'o.updatedAt', strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC');

        return $qb->getQuery()->toIterable();
    }

    public function dueByClient(): array
    {
        // map [clientId => dueCents] ; due = DELIVERED && NOT paid
        $rows = $this->createQueryBuilder('o')
            ->innerJoin('o.client', 'c')
            ->select('c.id AS clientId')
            ->addSelect('COALESCE(SUM(CASE WHEN o.status = :deliv AND o.paid = :false THEN o.price ELSE 0 END), 0) AS dueCents')
            ->groupBy('c.id')
            ->setParameter('deliv', \App\Enum\OrderStatus::DELIVERED->value)
            ->setParameter('false', false)
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $r) { $map[(int)$r['clientId']] = (int)$r['dueCents']; }
        return $map;
    }

    public function dueAndPaidForClient(int $clientId): array
    {
        // due = DELIVERED && NOT paid ; paid = paid == true (quel que soit le statut)
        $r = $this->createQueryBuilder('o')
            ->innerJoin('o.client', 'c')
            ->select('COALESCE(SUM(CASE WHEN o.status = :deliv AND o.paid = :false THEN o.price ELSE 0 END), 0) AS dueCents')
            ->addSelect('COALESCE(SUM(CASE WHEN o.paid = :true THEN o.price ELSE 0 END), 0) AS paidCents')
            ->andWhere('c.id = :cid')
            ->setParameter('cid', $clientId)
            ->setParameter('deliv', \App\Enum\OrderStatus::DELIVERED->value)
            ->setParameter('false', false)
            ->setParameter('true', true)
            ->getQuery()
            ->getSingleResult();

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
