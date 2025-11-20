<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\Client;
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

    public function paginateOrdersWithAssets(?Client $client, int $page, int $perPage): array
{
    $qb = $this->createQueryBuilder('o')
        ->innerJoin('o.assets', 'a')        // OrderAsset = client files
        ->addSelect('a')
        ->groupBy('o.id')
        ->orderBy('o.updatedAt', 'DESC')
        ->addOrderBy('o.id', 'DESC');

    if ($client) {
        $qb->andWhere('o.client = :c')->setParameter('c', $client);
    }

    $first = max(0, ($page - 1) * $perPage);
    $rows  = $qb->setFirstResult($first)
                ->setMaxResults($perPage + 1)
                ->getQuery()
                ->getResult();

    $hasMore = \count($rows) > $perPage;
    if ($hasMore) { array_pop($rows); }

    return ['items' => $rows, 'page' => $page, 'hasMore' => $hasMore];
}

    public function paginateOrdersWithThumbnails(?Client $client, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('o')
            ->innerJoin('o.thumbnails', 't')    // Thumbnail = final images (admin)
            ->addSelect('t')
            ->groupBy('o.id')
            ->orderBy('o.updatedAt', 'DESC')
            ->addOrderBy('o.id', 'DESC');

        if ($client) {
            $qb->andWhere('o.client = :c')->setParameter('c', $client);
        }

        $first = max(0, ($page - 1) * $perPage);
        $rows  = $qb->setFirstResult($first)
                    ->setMaxResults($perPage + 1)
                    ->getQuery()
                    ->getResult();

        $hasMore = \count($rows) > $perPage;
        if ($hasMore) { array_pop($rows); }

        return ['items' => $rows, 'page' => $page, 'hasMore' => $hasMore];
    }

    

    /**
     * @return iterable<Order>
     */
    public function findForExport(
        ?string $q,
        ?int $clientId,
        ?\App\Enum\OrderStatus $status,
        ?\DateTimeInterface $from,
        ?\DateTimeInterface $to,
        ?bool $paid,
        string $sort,
        string $dir
    ): iterable {
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.client', 'c')
            ->addSelect('c');
    
        if ($q) {
            $qb->andWhere('o.title LIKE :q OR o.brief LIKE :q')
               ->setParameter('q', '%'.$q.'%');
        }
    
        if ($clientId) {
            $qb->andWhere('c.id = :cid')
               ->setParameter('cid', $clientId);
        }
    
        if ($status) {
            $qb->andWhere('o.status = :st')
               ->setParameter('st', $status->value);
        }
    
        if ($from) {
            $qb->andWhere('o.updatedAt >= :from')
               ->setParameter('from', $from);
        }
    
        if ($to) {
            $qb->andWhere('o.updatedAt <= :to')
               ->setParameter('to', $to);
        }
    
        if ($paid !== null) {
            $qb->andWhere('o.paid = :paid')
               ->setParameter('paid', $paid);
        }
    
        // sécurisation du tri
        $allowedSort = [
            'id'        => 'o.id',
            'title'     => 'o.title',
            'client'    => 'c.name',
            'price'     => 'o.price',
            'status'    => 'o.status',
            'paid'      => 'o.paid',
            'dueAt'     => 'o.dueAt',
            'updatedAt' => 'o.updatedAt',
            'createdAt' => 'o.createdAt',
        ];
        $sortExpr = $allowedSort[$sort] ?? 'o.updatedAt';
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
    
        $qb->orderBy($sortExpr, $dir);
    
        // ⚠️ IMPORTANT: pas de fetch join vers o.assets ici
        // et pas de distinct chelou
        // du coup on peut streamer avec toIterable()
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
        $r = $this->createQueryBuilder('o')
            ->innerJoin('o.client', 'c')
            ->select('COALESCE(SUM(CASE WHEN o.status IN (:st) AND o.paid = :false THEN o.price ELSE 0 END), 0) AS dueCents')
            ->addSelect('COALESCE(SUM(CASE WHEN o.paid = :true THEN o.price ELSE 0 END), 0) AS paidCents')
            ->andWhere('c.id = :cid')
            ->setParameter('cid', $clientId)
            ->setParameter('st', [
                \App\Enum\OrderStatus::DELIVERED->value,
                \App\Enum\OrderStatus::FINISHED->value,
                \App\Enum\OrderStatus::REVISION->value,
            ])
            ->setParameter('false', false)
            ->setParameter('true', true)
            ->getQuery()
            ->getSingleResult();

        return [
            'dueCents'  => (int) $r['dueCents'],
            'paidCents' => (int) $r['paidCents'],
        ];
    }

    public function findBillableUnpaidByClient(Client $client): array
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.client', 'c')->addSelect('c')
            ->andWhere('o.client = :client')->setParameter('client', $client)
            ->andWhere('o.paid = :paid')->setParameter('paid', false)
            ->andWhere('o.status IN (:st)')
            ->setParameter('st', [OrderStatus::DELIVERED, OrderStatus::REVISION, OrderStatus::FINISHED])
            ->orderBy('o.updatedAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->getQuery()->getResult();
    }

    public function getMonthlyCountsByClient(\DateTimeInterface $start, \DateTimeInterface $end, Client $client): array
    {
        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.client = :client')
            ->andWhere('o.dueAt IS NOT NULL')
            ->andWhere('o.dueAt >= :start')
            ->andWhere('o.dueAt < :end')
            ->setParameter('client', $client)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('o.dueAt', 'ASC');

        $orders = $qb->getQuery()->getResult();

        // Build month buckets from start..end (inclusive start, exclusive end)
        $cursor   = new \DateTimeImmutable($start->format('Y-m-01 00:00:00'));
        $endMonth = new \DateTimeImmutable($end->format('Y-m-01 00:00:00'));

        $labels     = [];
        $indexByKey = [];

        while ($cursor < $endMonth) {
            $key = $cursor->format('Y-m');
            $indexByKey[$key] = \count($labels);
            $labels[] = $key;
            $cursor = $cursor->modify('+1 month');
        }

        $data = array_fill(0, \count($labels), 0);

        foreach ($orders as $order) {
            /** @var Order $order */
            $dueAt = $order->getDueAt();
            if (!$dueAt) {
                continue;
            }
            $key = $dueAt->format('Y-m');
            if (isset($indexByKey[$key])) {
                $data[$indexByKey[$key]]++;
            }
        }

        return ['labels' => $labels, 'data' => $data];
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
