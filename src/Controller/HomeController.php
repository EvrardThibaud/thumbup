<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Repository\OrderRepository;
use App\Repository\ThumbnailRepository;
use App\Enum\OrderStatus;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'site_landing', methods: ['GET'])]
    public function landing(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }
        return $this->render('home/landing.html.twig');
    }

     #[Route('/app', name: 'app_home', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function app(
        OrderRepository $orderRepo,
        ThumbnailRepository $thumbRepo
    ): Response {
        $createdOrders   = [];
        $acceptedOrders  = [];
        $clientThumbnails = [];

        if ($this->isGranted('ROLE_ADMIN')) {
            // Orders en status CREATED
            $createdOrders = $orderRepo->createQueryBuilder('o')
                ->andWhere('o.status = :created')
                ->setParameter('created', OrderStatus::CREATED)
                ->orderBy('o.dueAt', 'ASC')
                ->addOrderBy('o.id', 'DESC')
                ->getQuery()
                ->getResult();

            // Orders en status ACCEPTED (to do)
            $acceptedOrders = $orderRepo->createQueryBuilder('o2')
                ->andWhere('o2.status = :accepted')
                ->setParameter('accepted', OrderStatus::ACCEPTED)
                ->orderBy('o2.dueAt', 'ASC')
                ->addOrderBy('o2.id', 'DESC')
                ->getQuery()
                ->getResult();
        }

        // Vue client : 6 dernières thumbnails liées à ses orders
        if ($this->isGranted('ROLE_CLIENT') && !$this->isGranted('ROLE_ADMIN')) {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $client = $user?->getClient();

            if ($client) {
                $clientThumbnails = $thumbRepo->createQueryBuilder('t')
                    ->join('t.order', 'o')
                    ->andWhere('o.client = :client')
                    ->setParameter('client', $client)
                    ->orderBy('t.createdAt', 'DESC')
                    ->setMaxResults(6)
                    ->getQuery()
                    ->getResult();
            }
        }

        return $this->render('home/index.html.twig', [
            'createdOrders'    => $createdOrders,
            'acceptedOrders'   => $acceptedOrders,
            'clientThumbnails' => $clientThumbnails,
        ]);
    }
}
