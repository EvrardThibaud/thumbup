<?php
// src/Controller/PaymentsController.php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/payments')]
final class PaymentsController extends AbstractController
{
    #[Route('', name: 'app_payments_index', methods: ['GET'])]
    public function index(OrderRepository $ordersRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');
        if ($this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $user = $this->getUser();
        $client = (is_object($user) && method_exists($user, 'getClient')) ? $user->getClient() : null;
        if (!$client instanceof Client) {
            throw $this->createAccessDeniedException('No client linked to this user.');
        }

        $orders = $ordersRepo->findBillableUnpaidByClient($client); // unpaid + status in [DELIVERED, REVISION, FINISHED]

        return $this->render('payments/index.html.twig', [
            'orders' => $orders,
        ]);
    }

    #[Route('/pay', name: 'app_payments_pay', methods: ['POST'])]
    public function pay(Request $request, OrderRepository $ordersRepo, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');
        if ($this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('pay-orders', (string)$request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $ids = array_filter(array_map('intval', (array)$request->request->all('orders')));
        if (!$ids) {
            $this->addFlash('warning', 'No orders selected.');
            return $this->redirectToRoute('app_payments_index');
        }

        $user = $this->getUser();
        $client = (is_object($user) && method_exists($user, 'getClient')) ? $user->getClient() : null;
        if (!$client instanceof Client) {
            throw $this->createAccessDeniedException();
        }

        // Re-check ownership + eligibility server-side
        $updated = 0;
        foreach ($ids as $id) {
            /** @var Order|null $o */
            $o = $ordersRepo->find($id);
            if (
                $o instanceof Order
                && $o->getClient() === $client
                && $o->isPaid() === false
                && in_array($o->getStatus(), [OrderStatus::DELIVERED, OrderStatus::REVISION, OrderStatus::FINISHED], true)
            ) {
                $o->setPaid(true);
                $o->setUpdatedAt(new \DateTimeImmutable());
                $updated++;
            }
        }

        if ($updated > 0) {
            $em->flush();
            $this->addFlash('success', sprintf('%d order(s) marked as paid.', $updated));
        } else {
            $this->addFlash('warning', 'No eligible orders to pay.');
        }

        return $this->redirectToRoute('app_payments_index');
    }
}
