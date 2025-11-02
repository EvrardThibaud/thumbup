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
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        // Page d'IDs triées (orders ayant ≥1 asset)
        $idQb = $this->createQueryBuilder('o')
            ->select('o.id')
            ->andWhere('EXISTS (
                SELECT 1 FROM App\Entity\OrderAsset oa
                WHERE oa.order = o
            )')
            ->orderBy('o.updatedAt', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage + 1);

        if ($client instanceof Client) {
            $idQb->andWhere('o.client = :client')->setParameter('client', $client);
        }

        $rawIds = $idQb->getQuery()->getScalarResult();
        $ids = array_map(static fn($r) => (int)$r['id'], $rawIds);

        $hasMore = count($ids) > $perPage;
        if ($hasMore) {
            array_pop($ids); // garde exactement $perPage
        }

        if (!$ids) {
            return ['items' => [], 'hasMore' => false, 'page' => $page, 'perPage' => $perPage];
        }

        // Chargement des orders + client + assets pour ces IDs (même tri)
        $qb = $this->createQueryBuilder('o')
            ->leftJoin('o.client', 'c')->addSelect('c')
            ->leftJoin('o.assets', 'a')->addSelect('a')
            ->andWhere('o.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('o.updatedAt', 'DESC')
            ->addOrderBy('o.id', 'DESC');

        $items = $qb->getQuery()->getResult();

        return [
            'items'   => $items,
            'hasMore' => $hasMore,
            'page'    => $page,
            'perPage' => $perPage,
        ];
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

    public function getMonthlyCountsByClient(\DateTimeInterface $start, \DateTimeInterface $end, Client $client): array
    {
        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.client = :client')
            ->andWhere('o.createdAt >= :start')
            ->andWhere('o.createdAt < :end')
            ->setParameter('client', $client)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('o.createdAt', 'ASC');

        $orders = $qb->getQuery()->getResult();

        // Build month buckets from start..end (inclusive start, exclusive end)
        $cursor = new \DateTimeImmutable($start->format('Y-m-01 00:00:00'));
        $endMonth = new \DateTimeImmutable($end->format('Y-m-01 00:00:00'));

        $labels = [];
        $indexByKey = [];
        while ($cursor <= $endMonth) {
            $key = $cursor->format('Y-m');
            $indexByKey[$key] = count($labels);
            $labels[] = $key;
            $cursor = $cursor->modify('+1 month');
        }

        $data = array_fill(0, count($labels), 0);

        foreach ($orders as $order) {
            /** @var Order $order */
            $key = $order->getCreatedAt()->format('Y-m');
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
