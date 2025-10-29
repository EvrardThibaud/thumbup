<?php

namespace App\Controller;

use App\Entity\TimeEntry;
use App\Form\TimeEntryType;
use App\Repository\TimeEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Order;
use App\Repository\OrderRepository;


#[Route('/admin/time-entry')]
final class TimeEntryController extends AbstractController
{
    #[Route(name: 'app_time_entry_index', methods: ['GET'])]
    public function index(TimeEntryRepository $timeEntryRepository): Response
    {
        return $this->render('time_entry/index.html.twig', [
            'time_entries' => $timeEntryRepository->findAll(),
        ]);
    }

    public function __construct(private OrderRepository $orderRepo) {}

    #[Route('/new', name: 'app_time_entry_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $timeEntry = new TimeEntry();
        $timeEntry->setCreatedAt(new \DateTimeImmutable());

        if ($orderId = $request->query->getInt('orderId')) {
            if ($order = $this->orderRepo->find($orderId)) {
                $timeEntry->setRelatedOrder($order);
            }
        }

        $form = $this->createForm(TimeEntryType::class, $timeEntry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($timeEntry);
            $em->flush();
        
            $order = $timeEntry->getRelatedOrder();
            if ($order) {
                return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
            }
            return $this->redirectToRoute('app_time_entry_index');
        }

        return $this->render('time_entry/new.html.twig', [
            'time_entry' => $timeEntry,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_time_entry_show', methods: ['GET'])]
    public function show(TimeEntry $timeEntry): Response
    {
        return $this->render('time_entry/show.html.twig', [
            'time_entry' => $timeEntry,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_time_entry_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, TimeEntry $timeEntry, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(TimeEntryType::class, $timeEntry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
        
            $order = $timeEntry->getRelatedOrder();
            if ($order) {
                return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
            }
            return $this->redirectToRoute('app_time_entry_index');
        }

        return $this->render('time_entry/edit.html.twig', [
            'time_entry' => $timeEntry,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_time_entry_delete', methods: ['POST'])]
    public function delete(Request $request, TimeEntry $timeEntry, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($timeEntry);
        $entityManager->flush();

        $order = $timeEntry->getRelatedOrder();
        if ($order) {
            return $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
        }
        return $this->redirectToRoute('app_time_entry_index');
    }
}
