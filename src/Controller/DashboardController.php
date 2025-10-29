<?php

namespace App\Controller;

use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use App\Repository\TimeEntryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

#[Route('/admin')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'admin_dashboard')]
    public function index(
        OrderRepository $orders,
        TimeEntryRepository $times,
        ChartBuilderInterface $charts
    ): Response {
        // KPI 1: miniatures du mois (créées ce mois)
        $startMonth = (new \DateTimeImmutable('first day of this month 00:00:00'));
        $now = new \DateTimeImmutable('now');
        $miniaturesMois = (int) $orders->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.createdAt >= :start AND o.createdAt <= :now')
            ->setParameter('start', $startMonth)
            ->setParameter('now', $now)
            ->getQuery()->getSingleScalarResult();

        // KPI 2: temps total semaine (en min)
        $monday = new \DateTimeImmutable('monday this week 00:00:00');
        $minutesSemaine = (int) $times->createQueryBuilder('t')
            ->select('COALESCE(SUM(t.minutes),0)')
            ->andWhere('t.createdAt >= :monday AND t.createdAt <= :now')
            ->setParameter('monday', $monday)
            ->setParameter('now', $now)
            ->getQuery()->getSingleScalarResult();

        // KPI 3: CA estimé (orders livrés) en € (price est en centimes)
        $caCents = (int) $orders->createQueryBuilder('o')
            ->select('COALESCE(SUM(o.price),0)')
            ->andWhere('o.status = :delivered')
            ->setParameter('delivered', OrderStatus::DELIVERED)
            ->getQuery()->getSingleScalarResult();
        $caEuros = $caCents / 100;

        // Graphe: orders par mois (6 derniers mois)
        $labels = [];
        $data = [];
        for ($i = 5; $i >= 0; $i--) {
            $first = (new \DateTimeImmutable("first day of -$i month 00:00:00"));
            $last  = (new \DateTimeImmutable("last day of -$i month 23:59:59"));
            $count = (int) $orders->createQueryBuilder('o')
                ->select('COUNT(o.id)')
                ->andWhere('o.createdAt BETWEEN :first AND :last')
                ->setParameter('first', $first)
                ->setParameter('last', $last)
                ->getQuery()->getSingleScalarResult();
            $labels[] = $first->format('M Y');
            $data[] = $count;
        }

        $ordersByMonthChart = $charts->createChart(Chart::TYPE_BAR);
        $ordersByMonthChart->setData([
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Miniatures / mois',
                'data' => $data,
            ]],
        ]);

        return $this->render('dashboard/index.html.twig', [
            'miniaturesMois' => $miniaturesMois,
            'minutesSemaine' => $minutesSemaine,
            'caEuros' => $caEuros,
            'ordersByMonthChart' => $ordersByMonthChart,
        ]);
    }
}
