<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Repository\OrderRepository;
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
    public function app(OrderRepository $orderRepo): Response
    {
        $createdOrders  = [];
        $acceptedOrders = [];

        if ($this->isGranted('ROLE_ADMIN')) {
            $createdOrders = $orderRepo->createQueryBuilder('o')
                ->andWhere('o.status = :created')
                ->setParameter('created', OrderStatus::CREATED)
                ->orderBy('o.dueAt', 'ASC')
                ->addOrderBy('o.id', 'DESC')
                ->getQuery()
                ->getResult();

            $acceptedOrders = $orderRepo->createQueryBuilder('o2')
                ->andWhere('o2.status = :accepted')
                ->setParameter('accepted', OrderStatus::ACCEPTED)
                ->orderBy('o2.dueAt', 'ASC')
                ->addOrderBy('o2.id', 'DESC')
                ->getQuery()
                ->getResult();
        }

        return $this->render('home/index.html.twig', [
            'createdOrders'  => $createdOrders,
            'acceptedOrders' => $acceptedOrders,
        ]);
    }
}
