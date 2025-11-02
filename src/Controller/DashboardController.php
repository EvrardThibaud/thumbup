<?php

namespace App\Controller;

use App\Entity\Client;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use App\Repository\TimeEntryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET'])]
    public function index(
        OrderRepository $orders,
        TimeEntryRepository $times,
        ChartBuilderInterface $charts
    ): Response {
        $vars = [];

        // --- Client dashboard data (line chart last 12 months) ---
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

            $vars['ordersByMonthChart'] = $ordersByMonthChart;
        }

        // --- Admin dashboard data (KPIs + recent orders table) ---
        if ($this->isGranted('ROLE_ADMIN')) {
            $now        = new \DateTimeImmutable('now');
            $startMonth = new \DateTimeImmutable('first day of this month 00:00:00');
            $monday     = new \DateTimeImmutable('monday this week 00:00:00');

            $miniaturesMois = (int) $orders->createQueryBuilder('o')
                ->select('COUNT(o.id)')
                ->andWhere('o.createdAt >= :start AND o.createdAt <= :now')
                ->setParameter('start', $startMonth)
                ->setParameter('now', $now)
                ->getQuery()->getSingleScalarResult();

            $minutesSemaine = (int) $times->createQueryBuilder('t')
                ->select('COALESCE(SUM(t.minutes),0)')
                ->andWhere('t.createdAt >= :monday AND t.createdAt <= :now')
                ->setParameter('monday', $monday)
                ->setParameter('now', $now)
                ->getQuery()->getSingleScalarResult();

            $caCents = (int) $orders->createQueryBuilder('o')
                ->select('COALESCE(SUM(o.price),0)')
                ->andWhere('o.status = :delivered')
                ->setParameter('delivered', OrderStatus::DELIVERED)
                ->getQuery()->getSingleScalarResult();

            $labels = [];
            $data   = [];
            for ($i = 5; $i >= 0; $i--) {
                $first = new \DateTimeImmutable("first day of -$i month 00:00:00");
                $last  = new \DateTimeImmutable("last day of -$i month 23:59:59");
                $count = (int) $orders->createQueryBuilder('o')
                    ->select('COUNT(o.id)')
                    ->andWhere('o.createdAt BETWEEN :first AND :last')
                    ->setParameter('first', $first)
                    ->setParameter('last', $last)
                    ->getQuery()->getSingleScalarResult();
                $labels[] = $first->format('M Y');
                $data[]   = $count;
            }

            $ordersByMonthChart = $charts->createChart(Chart::TYPE_BAR);
            $ordersByMonthChart->setData([
                'labels' => $labels,
                'datasets' => [[
                    'label' => 'Thumbnails per month',
                    'data' => $data,
                ]],
            ]);

            // Recent orders (last 10)
            $recentOrders = $orders->createQueryBuilder('o')
                ->leftJoin('o.client', 'c')->addSelect('c')
                ->orderBy('o.updatedAt', 'DESC')
                ->addOrderBy('o.id', 'DESC')
                ->setMaxResults(10)
                ->getQuery()->getResult();

            $vars += [
                'miniaturesMois'    => $miniaturesMois,
                'minutesSemaine'    => $minutesSemaine,
                'caEuros'           => $caCents / 100,
                'ordersByMonthChart'=> $ordersByMonthChart,
                'orders'            => $recentOrders,
            ];
        }

        return $this->render('dashboard/index.html.twig', $vars);
    }
}
