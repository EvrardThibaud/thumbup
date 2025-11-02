<?php
// src/Controller/DashboardController.php

namespace App\Controller;

use App\Entity\Client;
use App\Enum\OrderStatus;
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
        // If CLIENT (non-admin): keep existing client dashboard only
        if ($this->isGranted('ROLE_CLIENT') && !$this->isGranted('ROLE_ADMIN')) {
            $user   = $this->getUser();
            $client = (is_object($user) && method_exists($user, 'getClient')) ? $user->getClient() : null;
            if (!$client instanceof Client) {
                throw $this->createAccessDeniedException('No client linked to this user.');
            }

            $end   = new \DateTimeImmutable('first day of next month 00:00:00');
            $start = $end->modify('-12 months');

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
                    'title'  => ['display' => true, 'text' => '📈 Orders per month (last 12 months)'],
                    'tooltip'=> ['mode' => 'index', 'intersect' => false],
                ],
                'interaction' => ['mode' => 'index', 'intersect' => false],
                'scales' => [
                    'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
                    'x' => ['ticks' => ['autoSkip' => true, 'maxTicksLimit' => 12]],
                ],
            ]);

            return $this->render('dashboard/index.html.twig', [
                'ordersByMonthChart' => $ordersByMonthChart,
            ]);
        }

        // ADMIN dashboard (with optional client filter)
        $raw = trim((string) $request->query->get('client', ''));
        $selectedClient = (ctype_digit($raw) && $raw !== '') ? $clientsRepo->find((int) $raw) : null;


        $end   = new \DateTimeImmutable('first day of next month 00:00:00');
        $start = $end->modify('-12 months');

        // --- Totals & averages (last 12 months) ---
        // Orders created
        $ordersQb = $orders->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.createdAt >= :start AND o.createdAt < :end')
            ->setParameter('start', $start)->setParameter('end', $end);
        if ($selectedClient instanceof Client) {
            $ordersQb->andWhere('o.client = :c')->setParameter('c', $selectedClient);
        }
        $totalOrders12m = (int) $ordersQb->getQuery()->getSingleScalarResult();

        // Time entries minutes
        $timeQb = $times->createQueryBuilder('t')
            ->select('COALESCE(SUM(t.minutes),0)')
            ->andWhere('t.createdAt >= :start AND t.createdAt < :end')
            ->setParameter('start', $start)->setParameter('end', $end);
        if ($selectedClient instanceof Client) {
            $timeQb->leftJoin('t.relatedOrder', 'to')->andWhere('to.client = :c')->setParameter('c', $selectedClient);
        }
        $totalMinutes12m = (int) $timeQb->getQuery()->getSingleScalarResult();

        // Revenue (delivered orders; sum price in cents)
        $revQb = $orders->createQueryBuilder('o')
            ->select('COALESCE(SUM(o.price),0)')
            ->andWhere('o.paid = :paid')
            ->andWhere('o.updatedAt >= :start AND o.updatedAt < :end')
            ->setParameter('paid', true)
            ->setParameter('start', $start)->setParameter('end', $end);
        if ($selectedClient instanceof Client) {
            $revQb->andWhere('o.client = :c')->setParameter('c', $selectedClient);
        }
        $totalCents12m = (int) $revQb->getQuery()->getSingleScalarResult();

        // Averages over the last 12 months window
        $avg = [
            'orders' => [
                'day'   => round($totalOrders12m / 365, 2),
                'week'  => round($totalOrders12m / 52, 2),
                'month' => round($totalOrders12m / 12, 2),
                'year'  => $totalOrders12m,
            ],
            'minutes' => [
                'day'   => round($totalMinutes12m / 365, 1),
                'week'  => round($totalMinutes12m / 52, 1),
                'month' => round($totalMinutes12m / 12, 1),
                'year'  => $totalMinutes12m,
            ],
            'revenueEuros' => [
                'day'   => round(($totalCents12m/100) / 365, 2),
                'week'  => round(($totalCents12m/100) / 52, 2),
                'month' => round(($totalCents12m/100) / 12, 2),
                'year'  => round(($totalCents12m/100), 2),
            ],
        ];

        // --- Monthly series (last 12 months, labels oldest..newest) ---
        $labels   = [];
        $countPer = [];
        $minsPer  = [];
        $revPer   = [];
        $avgTimePerOrder = [];

        $cursor = new \DateTimeImmutable($start->format('Y-m-01 00:00:00'));
        while ($cursor < $end) {
            $monthStart = $cursor;
            $monthEnd   = $cursor->modify('+1 month');

            $labels[] = $monthStart->format('Y-m');

            // Orders created in month
            $qb1 = $orders->createQueryBuilder('o')
                ->select('COUNT(o.id)')
                ->andWhere('o.createdAt >= :ms AND o.createdAt < :me')
                ->setParameter('ms', $monthStart)->setParameter('me', $monthEnd);
            if ($selectedClient instanceof Client) {
                $qb1->andWhere('o.client = :c')->setParameter('c', $selectedClient);
            }
            $count = (int) $qb1->getQuery()->getSingleScalarResult();
            $countPer[] = $count;

            // Time minutes in month
            $qb2 = $times->createQueryBuilder('t')
                ->select('COALESCE(SUM(t.minutes),0)')
                ->andWhere('t.createdAt >= :ms AND t.createdAt < :me')
                ->setParameter('ms', $monthStart)->setParameter('me', $monthEnd);
            if ($selectedClient instanceof Client) {
                $qb2->leftJoin('t.relatedOrder', 'to2')->andWhere('to2.client = :c')->setParameter('c', $selectedClient);
            }
            $mins = (int) $qb2->getQuery()->getSingleScalarResult();
            $minsPer[] = $mins;

            // Revenue by delivered updated in month (cents)
            $qb3 = $orders->createQueryBuilder('o')
                ->select('COALESCE(SUM(o.price),0)')
                ->andWhere('o.paid = :paid')
                ->andWhere('o.updatedAt >= :ms AND o.updatedAt < :me')
                ->setParameter('paid', true)
                ->setParameter('ms', $monthStart)->setParameter('me', $monthEnd);
            if ($selectedClient instanceof Client) {
                $qb3->andWhere('o.client = :c')->setParameter('c', $selectedClient);
            }
            $cents = (int) $qb3->getQuery()->getSingleScalarResult();
            $revPer[] = round($cents/100, 2);

            // Avg time per thumbnail this month (minutes/order)
            $avgTimePerOrder[] = $count > 0 ? round($mins / $count, 1) : 0.0;

            $cursor = $monthEnd;
        }

        // Build charts
        $ordersByMonthChart = $charts->createChart(Chart::TYPE_LINE);
        $ordersByMonthChart->setData([
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Thumbnails per month',
                'data'  => $countPer,
                'fill' => false,
                'tension' => 0.25,
                'pointRadius' => 3,
                'borderColor' => '#7aa2f7',
            ]],
        ]);

        $timeByMonthChart = $charts->createChart(Chart::TYPE_LINE);
        $timeByMonthChart->setData([
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Time spent per month (min)',
                'data'  => $minsPer,
                'fill' => false,
                'tension' => 0.25,
                'pointRadius' => 3,
                'borderColor' => '#98c379',
            ]],
        ]);

        $avgTimePerOrderChart = $charts->createChart(Chart::TYPE_LINE);
        $avgTimePerOrderChart->setData([
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Average time per thumbnail (min)',
                'data'  => $avgTimePerOrder,
                'fill' => false,
                'tension' => 0.25,
                'pointRadius' => 3,
                'borderColor' => '#e5c07b',
            ]],
        ]);

        $revenueByMonthChart = $charts->createChart(Chart::TYPE_LINE);
        $revenueByMonthChart->setData([
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Revenue per month (€)',
                'data'  => $revPer,
                'fill' => false,
                'tension' => 0.25,
                'pointRadius' => 3,
                'borderColor' => '#d3869b',
            ]],
        ]);

        foreach ([$ordersByMonthChart, $timeByMonthChart, $avgTimePerOrderChart, $revenueByMonthChart] as $chart) {
            $chart->setOptions([
                'responsive' => true,
                'plugins' => [
                    'legend' => ['display' => true, 'position' => 'top'],
                    'tooltip'=> ['mode' => 'index', 'intersect' => false],
                ],
                'interaction' => ['mode' => 'index', 'intersect' => false],
                'scales' => [
                    'y' => ['beginAtZero' => true],
                    'x' => ['ticks' => ['autoSkip' => true, 'maxTicksLimit' => 12]],
                ],
            ]);
        }

        // Clients list for filter
        $clients = $clientsRepo->createQueryBuilder('c')
            ->orderBy('c.name', 'ASC')->getQuery()->getResult();

        return $this->render('dashboard/index.html.twig', [
            'clients' => $clients,
            'selectedClient' => $selectedClient,
            'totalMinutes12m' => $totalMinutes12m,
            'totalOrders12m'  => $totalOrders12m,
            'totalRevenue12m' => round($totalCents12m / 100, 2),
            'avg' => $avg,
            'ordersByMonthChart' => $ordersByMonthChart,
            'timeByMonthChart' => $timeByMonthChart,
            'avgTimePerOrderChart' => $avgTimePerOrderChart,
            'revenueByMonthChart' => $revenueByMonthChart,
        ]);
    }
}
