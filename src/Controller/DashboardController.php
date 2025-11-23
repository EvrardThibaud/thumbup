<?php

namespace App\Controller;

use App\Entity\Client;
use App\Repository\ClientRepository;
use App\Repository\OrderRepository;
use App\Repository\TimeEntryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET'])]
    public function index(
        Request $request,
        ClientRepository $clientsRepo,
        OrderRepository $orders,
        TimeEntryRepository $times,
        ChartBuilderInterface $charts
    ): Response {
        // Client dashboard (non admin)
        if ($this->isGranted('ROLE_CLIENT') && !$this->isGranted('ROLE_ADMIN')) {
            /** @var \App\Entity\User|null $user */
            $user   = $this->getUser();
            $client = (is_object($user) && method_exists($user, 'getClient')) ? $user->getClient() : null;

            if (!$client instanceof Client) {
                throw $this->createAccessDeniedException('No client linked to this user.');
            }

            $end = new \DateTimeImmutable('first day of next month 00:00:00');

            $minDue = $orders->createQueryBuilder('o')
                ->select('MIN(o.dueAt)')
                ->andWhere('o.client = :c')
                ->andWhere('o.dueAt IS NOT NULL')
                ->setParameter('c', $client)
                ->getQuery()
                ->getSingleScalarResult();

            if ($minDue) {
                $minDueAt = new \DateTimeImmutable($minDue);
                $start    = new \DateTimeImmutable($minDueAt->format('Y-m-01 00:00:00'));
            } else {
                $start = $end->modify('-12 months');
            }

            $series = $orders->getMonthlyCountsByClient($start, $end, $client);

            $ordersByMonthChart = $charts->createChart(Chart::TYPE_LINE);
            $ordersByMonthChart->setData([
                'labels' => $series['labels'],
                'datasets' => [[
                    'label' => 'Orders per month',
                    'data' => $series['data'],
                    'fill' => false,
                    'tension' => 0.25,
                    'pointRadius' => 4,
                    'pointHoverRadius' => 6,
                    'borderColor' => '#4da3ff',
                ]],
            ]);
            $ordersByMonthChart->setOptions([
                'responsive' => true,
                'plugins' => [
                    'legend' => ['display' => true, 'position' => 'top'],
                    'title' => ['display' => true, 'text' => '📈 Orders per month (since first order)'],
                    'tooltip' => ['mode' => 'index', 'intersect' => false],
                ],
                'interaction' => ['mode' => 'index', 'intersect' => false],
                'scales' => [
                    'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
                    'x' => ['ticks' => ['autoSkip' => true, 'maxTicksLimit' => 12]],
                ],
            ]);

            return $this->render('dashboard/index.html.twig', [
                'ordersByMonthChart' => $ordersByMonthChart,
                'is_admin'           => false,
            ]);
        }

        // Admin dashboard

        $raw            = trim((string) $request->query->get('client', ''));
        $selectedClient = (ctype_digit($raw) && $raw !== '') ? $clientsRepo->find((int) $raw) : null;

        $range = (string) $request->query->get('range', 'year'); // all, year, month, week
        if (!in_array($range, ['all', 'year', 'month', 'week'], true)) {
            $range = 'year';
        }

        $groupBy = in_array($range, ['month', 'week'], true) ? 'day' : 'month';

        $now = new \DateTimeImmutable('now');

        if ($range === 'all') {
            $minQb = $orders->createQueryBuilder('o')
                ->select('MIN(o.dueAt)')
                ->andWhere('o.dueAt IS NOT NULL');

            if ($selectedClient instanceof Client) {
                $minQb->andWhere('o.client = :c')->setParameter('c', $selectedClient);
            }

            $minDue = $minQb->getQuery()->getSingleScalarResult();
            if ($minDue) {
                $start = new \DateTimeImmutable($minDue);
            } else {
                $start = $now->modify('-1 year');
            }
            $end = $now;
        } elseif ($range === 'month') {
            $end   = $now;
            $start = $now->modify('-31 days');
        } elseif ($range === 'week') {
            $end   = $now;
            $start = $now->modify('-7 days');
        } else {
            $end   = $now;
            $start = $now->modify('-1 year');
        }

        $periodLabel = match ($range) {
            'all'   => 'All time',
            'year'  => 'Last year',
            'month' => 'Last month',
            'week'  => 'Last week',
            default => 'Last year',
        };

        // Totals for the selected period

        $ordersQb = $orders->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.dueAt IS NOT NULL')
            ->andWhere('o.dueAt >= :start AND o.dueAt < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        if ($selectedClient instanceof Client) {
            $ordersQb->andWhere('o.client = :c')->setParameter('c', $selectedClient);
        }

        $totalOrders = (int) $ordersQb->getQuery()->getSingleScalarResult();

        $timeQb = $times->createQueryBuilder('t')
            ->select('COALESCE(SUM(t.minutes), 0)')
            ->join('t.relatedOrder', 'to')
            ->andWhere('to.dueAt IS NOT NULL')
            ->andWhere('to.dueAt >= :start AND to.dueAt < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        if ($selectedClient instanceof Client) {
            $timeQb->andWhere('to.client = :c')->setParameter('c', $selectedClient);
        }

        $totalMinutes = (int) $timeQb->getQuery()->getSingleScalarResult();

        $revQb = $orders->createQueryBuilder('o')
            ->select('COALESCE(SUM(o.price), 0)')
            ->andWhere('o.paid = :paid')
            ->andWhere('o.dueAt IS NOT NULL')
            ->andWhere('o.dueAt >= :start AND o.dueAt < :end')
            ->setParameter('paid', true)
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        if ($selectedClient instanceof Client) {
            $revQb->andWhere('o.client = :c')->setParameter('c', $selectedClient);
        }

        $totalCents   = (int) $revQb->getQuery()->getSingleScalarResult();
        $totalRevenue = round($totalCents / 100, 2);

        // Series for charts

        $labels   = [];
        $countPer = [];
        $minsPer  = [];
        $revPer   = [];

        if ($groupBy === 'month') {
            $cursor = new \DateTimeImmutable($start->format('Y-m-01 00:00:00'));
            $limit  = (new \DateTimeImmutable($end->format('Y-m-01 00:00:00')))->modify('+1 month');

            while ($cursor < $limit) {
                $bucketStart = $cursor;
                $bucketEnd   = $cursor->modify('+1 month');

                $labels[] = $bucketStart->format('Y-m');

                $qb1 = $orders->createQueryBuilder('o')
                    ->select('COUNT(o.id)')
                    ->andWhere('o.dueAt IS NOT NULL')
                    ->andWhere('o.dueAt >= :bs AND o.dueAt < :be')
                    ->setParameter('bs', $bucketStart)
                    ->setParameter('be', $bucketEnd);

                if ($selectedClient instanceof Client) {
                    $qb1->andWhere('o.client = :c')->setParameter('c', $selectedClient);
                }

                $countPer[] = (int) $qb1->getQuery()->getSingleScalarResult();

                $qb2 = $times->createQueryBuilder('t')
                    ->select('COALESCE(SUM(t.minutes), 0)')
                    ->join('t.relatedOrder', 'to2')
                    ->andWhere('to2.dueAt IS NOT NULL')
                    ->andWhere('to2.dueAt >= :bs AND to2.dueAt < :be')
                    ->setParameter('bs', $bucketStart)
                    ->setParameter('be', $bucketEnd);

                if ($selectedClient instanceof Client) {
                    $qb2->andWhere('to2.client = :c')->setParameter('c', $selectedClient);
                }

                $minsPer[] = (int) $qb2->getQuery()->getSingleScalarResult();

                $qb3 = $orders->createQueryBuilder('o')
                    ->select('COALESCE(SUM(o.price), 0)')
                    ->andWhere('o.paid = :paid')
                    ->andWhere('o.dueAt IS NOT NULL')
                    ->andWhere('o.dueAt >= :bs AND o.dueAt < :be')
                    ->setParameter('paid', true)
                    ->setParameter('bs', $bucketStart)
                    ->setParameter('be', $bucketEnd);

                if ($selectedClient instanceof Client) {
                    $qb3->andWhere('o.client = :c')->setParameter('c', $selectedClient);
                }

                $cents    = (int) $qb3->getQuery()->getSingleScalarResult();
                $revPer[] = round($cents / 100, 2);

                $cursor = $bucketEnd;
            }
        } else {
            $cursor = new \DateTimeImmutable($start->format('Y-m-d 00:00:00'));
            $limit  = (new \DateTimeImmutable($end->format('Y-m-d 00:00:00')))->modify('+1 day');

            while ($cursor < $limit) {
                $bucketStart = $cursor;
                $bucketEnd   = $cursor->modify('+1 day');

                $labels[] = $bucketStart->format('Y-m-d');

                $qb1 = $orders->createQueryBuilder('o')
                    ->select('COUNT(o.id)')
                    ->andWhere('o.dueAt IS NOT NULL')
                    ->andWhere('o.dueAt >= :bs AND o.dueAt < :be')
                    ->setParameter('bs', $bucketStart)
                    ->setParameter('be', $bucketEnd);

                if ($selectedClient instanceof Client) {
                    $qb1->andWhere('o.client = :c')->setParameter('c', $selectedClient);
                }

                $countPer[] = (int) $qb1->getQuery()->getSingleScalarResult();

                $qb2 = $times->createQueryBuilder('t')
                    ->select('COALESCE(SUM(t.minutes), 0)')
                    ->join('t.relatedOrder', 'to2')
                    ->andWhere('to2.dueAt IS NOT NULL')
                    ->andWhere('to2.dueAt >= :bs AND to2.dueAt < :be')
                    ->setParameter('bs', $bucketStart)
                    ->setParameter('be', $bucketEnd);

                if ($selectedClient instanceof Client) {
                    $qb2->andWhere('to2.client = :c')->setParameter('c', $selectedClient);
                }

                $minsPer[] = (int) $qb2->getQuery()->getSingleScalarResult();

                $qb3 = $orders->createQueryBuilder('o')
                    ->select('COALESCE(SUM(o.price), 0)')
                    ->andWhere('o.paid = :paid')
                    ->andWhere('o.dueAt IS NOT NULL')
                    ->andWhere('o.dueAt >= :bs AND o.dueAt < :be')
                    ->setParameter('paid', true)
                    ->setParameter('bs', $bucketStart)
                    ->setParameter('be', $bucketEnd);

                if ($selectedClient instanceof Client) {
                    $qb3->andWhere('o.client = :c')->setParameter('c', $selectedClient);
                }

                $cents    = (int) $qb3->getQuery()->getSingleScalarResult();
                $revPer[] = round($cents / 100, 2);

                $cursor = $bucketEnd;
            }
        }

        $unitLabel = $groupBy === 'day' ? 'day' : 'month';

        $ordersChart = $charts->createChart(Chart::TYPE_LINE);
        $ordersChart->setData([
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Thumbnails per ' . $unitLabel,
                'data' => $countPer,
                'fill' => false,
                'tension' => 0.25,
                'pointRadius' => 3,
                'borderColor' => '#7aa2f7',
            ]],
        ]);

        $timeChart = $charts->createChart(Chart::TYPE_LINE);
        $timeChart->setData([
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Time spent per ' . $unitLabel . ' (min)',
                'data' => $minsPer,
                'fill' => false,
                'tension' => 0.25,
                'pointRadius' => 3,
                'borderColor' => '#98c379',
            ]],
        ]);

        $revenueChart = $charts->createChart(Chart::TYPE_LINE);
        $revenueChart->setData([
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Revenue per ' . $unitLabel . ' (€)',
                'data' => $revPer,
                'fill' => false,
                'tension' => 0.25,
                'pointRadius' => 3,
                'borderColor' => '#d3869b',
            ]],
        ]);

        // Global cumulative charts

        $firstDue = $orders->createQueryBuilder('o')
            ->select('MIN(o.dueAt)')
            ->andWhere('o.dueAt IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $lastDue = $orders->createQueryBuilder('o')
            ->select('MAX(o.dueAt)')
            ->andWhere('o.dueAt IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $cumOrdersChart  = null;
        $cumRevenueChart = null;

        if ($firstDue && $lastDue) {
            $startCum = (new \DateTimeImmutable($firstDue))->modify('first day of this month 00:00:00');
            $endCum   = (new \DateTimeImmutable($lastDue))->modify('first day of next month 00:00:00');

            $labelsCum      = [];
            $cumOrdersData  = [];
            $cumRevenueData = [];

            $cursor          = $startCum;
            $totalOrdersCum  = 0;
            $totalRevenueCum = 0.0;

            while ($cursor < $endCum) {
                $monthStart = $cursor;
                $monthEnd   = $cursor->modify('+1 month');

                $labelsCum[] = $monthStart->format('Y-m');

                $countMonth = (int) $orders->createQueryBuilder('o')
                    ->select('COUNT(o.id)')
                    ->andWhere('o.dueAt IS NOT NULL')
                    ->andWhere('o.dueAt >= :ms AND o.dueAt < :me')
                    ->setParameter('ms', $monthStart)
                    ->setParameter('me', $monthEnd)
                    ->getQuery()
                    ->getSingleScalarResult();

                $totalOrdersCum  += $countMonth;
                $cumOrdersData[] = $totalOrdersCum;

                $centsMonth = (int) $orders->createQueryBuilder('o')
                    ->select('COALESCE(SUM(o.price), 0)')
                    ->andWhere('o.paid = :paid')
                    ->andWhere('o.dueAt IS NOT NULL')
                    ->andWhere('o.dueAt >= :ms AND o.dueAt < :me')
                    ->setParameter('paid', true)
                    ->setParameter('ms', $monthStart)
                    ->setParameter('me', $monthEnd)
                    ->getQuery()
                    ->getSingleScalarResult();

                $totalRevenueCum   += round($centsMonth / 100, 2);
                $cumRevenueData[] = $totalRevenueCum;

                $cursor = $monthEnd;
            }

            $cumOrdersChart = $charts->createChart(Chart::TYPE_LINE);
            $cumOrdersChart->setData([
                'labels' => $labelsCum,
                'datasets' => [[
                    'label' => 'Cumulative thumbnails (all time)',
                    'data' => $cumOrdersData,
                    'fill' => false,
                    'tension' => 0.25,
                    'pointRadius' => 3,
                    'borderColor' => '#61afef',
                ]],
            ]);

            $cumRevenueChart = $charts->createChart(Chart::TYPE_LINE);
            $cumRevenueChart->setData([
                'labels' => $labelsCum,
                'datasets' => [[
                    'label' => 'Cumulative revenue (all time, €)',
                    'data' => $cumRevenueData,
                    'fill' => false,
                    'tension' => 0.25,
                    'pointRadius' => 3,
                    'borderColor' => '#c678dd',
                ]],
            ]);

            foreach ([$cumOrdersChart, $cumRevenueChart] as $chart) {
                $chart->setOptions([
                    'responsive' => true,
                    'plugins' => [
                        'legend'  => ['display' => true, 'position' => 'top'],
                        'tooltip' => ['mode' => 'index', 'intersect' => false],
                    ],
                    'interaction' => ['mode' => 'index', 'intersect' => false],
                    'scales' => [
                        'y' => ['beginAtZero' => true],
                        'x' => ['ticks' => ['autoSkip' => true, 'maxTicksLimit' => 12]],
                    ],
                ]);
            }
        }

        foreach ([$ordersChart, $timeChart, $revenueChart] as $chart) {
            $chart->setOptions([
                'responsive' => true,
                'plugins' => [
                    'legend'  => ['display' => true, 'position' => 'top'],
                    'tooltip' => ['mode' => 'index', 'intersect' => false],
                ],
                'interaction' => ['mode' => 'index', 'intersect' => false],
                'scales' => [
                    'y' => ['beginAtZero' => true],
                    'x' => ['ticks' => ['autoSkip' => true, 'maxTicksLimit' => 12]],
                ],
            ]);
        }

        $clients = $clientsRepo->createQueryBuilder('c')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('dashboard/index.html.twig', [
            'is_admin'        => true,
            'clients'         => $clients,
            'selectedClient'  => $selectedClient,
            'range'           => $range,
            'periodLabel'     => $periodLabel,
            'totalMinutes'    => $totalMinutes,
            'totalOrders'     => $totalOrders,
            'totalRevenue'    => $totalRevenue,
            'ordersChart'     => $ordersChart,
            'timeChart'       => $timeChart,
            'revenueChart'    => $revenueChart,
            'cumOrdersChart'  => $cumOrdersChart,
            'cumRevenueChart' => $cumRevenueChart,
        ]);
    }
}
