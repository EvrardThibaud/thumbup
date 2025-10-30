<?php

namespace App\Controller;

use App\Entity\Order;
use App\Form\OrderType;
use App\Repository\OrderRepository;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Form\OrderFilterType;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Enum\OrderStatus;
use App\Entity\User;

#[Route('/order')]
final class OrderController extends AbstractController
{
    #[Route(name: 'app_order_index', methods: ['GET'])]
    public function index(Request $request, OrderRepository $repo, ClientRepository $clientRepo): Response
    {
        $form = $this->createForm(OrderFilterType::class);
        $form->handleRequest($request);
        $data = $form->getData() ?? [];

        $q      = $data['q']      ?? null;
        $client = $data['client'] ?? null;
        $status = $data['status'] ?? null;
        $from   = $data['from']   ?? null;
        $to     = $data['to']     ?? null;

        $clientId = $client?->getId();

        // 👉 Si ROLE_CLIENT : impose le client et pré-remplit le champ du form
        $isClient = $this->isGranted('ROLE_CLIENT') && !$this->isGranted('ROLE_ADMIN');
        if ($isClient) {
            /** @var \App\Entity\User|null $user */
            $user = $this->getUser();

            if (!$user instanceof \App\Entity\User || null === $user->getClient()) {
                throw $this->createAccessDeniedException();
            }

            $userClient = $user->getClient();
            $clientId   = $userClient->getId();

            if (!$form->isSubmitted()) {
                $form->get('client')->setData($userClient);
            }
        }

        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = 10;
        $sort  = (string) $request->query->get('sort', 'updatedAt');
        $dir   = (string) $request->query->get('dir', 'DESC');

        $result = $repo->searchPaginated($q, $clientId, $status, $from, $to, $page, $limit, $sort, $dir);

        return $this->render('order/index.html.twig', [
            'orders'  => $result['items'],
            'total'   => $result['total'],
            'page'    => $result['page'],
            'limit'   => $result['limit'],
            'filters' => $form->createView(),
            'sort'    => $sort,
            'dir'     => strtoupper($dir),
            'is_client' => $isClient, // 👈 pour le Twig
        ]);
    }
    
    #[Route('/new', name: 'app_order_new', methods: ['GET','POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $order = new Order();
        $now = new \DateTimeImmutable();
        $order->setCreatedAt($now);
        $order->setUpdatedAt($now);
        $order->setStatus(\App\Enum\OrderStatus::CREATED); // défaut
        $order->setPaid(false); // défaut

        $isClient = $this->isGranted('ROLE_CLIENT') && !$this->isGranted('ROLE_ADMIN');

        // Si CLIENT : lier automatiquement son Client (champ non visible)
        if ($isClient) {
            /** @var \App\Entity\User|null $user */
            $user = $this->getUser();
            if (!$user instanceof \App\Entity\User || null === $user->getClient()) {
                throw $this->createAccessDeniedException();
            }
            $order->setClient($user->getClient());
        }

        // NEW: for_edit = false en création pour afficher "price" au client (avec min 5€)
        $form = $this->createForm(\App\Form\OrderType::class, $order, [
            'is_client' => $isClient,
            'for_edit'  => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // Filets de sécurité côté serveur
            if ($isClient) {
                // Le client ne peut pas modifier ces champs sensibles
                $order->setStatus(\App\Enum\OrderStatus::CREATED);
                $order->setPaid(false);
                // Min 5€ déjà validé par le FormType, mais on revérifie au cas où
                if ($order->getPrice() < 500) {
                    $form->get('price')->addError(new \Symfony\Component\Form\FormError('Minimum €5.00'));
                    return $this->render('order/new.html.twig', [
                        'order' => $order,
                        'form'  => $form,
                        'back'  => $request->query->get('back') ?: $request->headers->get('referer'),
                    ]);
                }
            }

            $em->persist($order);
            $em->flush();

            $back = $request->request->get('back') ?: $request->query->get('back');
            return $back
                ? $this->redirect($back)
                : $this->redirectToRoute('app_order_index');
        }

        $back = $request->query->get('back') ?: $request->headers->get('referer');
        return $this->render('order/new.html.twig', [
            'order' => $order,
            'form'  => $form,
            'back'  => $back,
        ]);
    }

    #[Route('/{id}', name: 'app_order_show', methods: ['GET'])]
    public function show(Request $request, Order $order): Response
    {
        $this->denyAccessUnlessGranted('ORDER_VIEW', $order);
        $back = $request->query->get('back'); // string|null
        return $this->render('order/show.html.twig', [
            'order' => $order,
            'back'  => $back,
        ]);
    }


    #[Route('/{id}/edit', name: 'app_order_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Order $order, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ORDER_EDIT', $order);

        $user = $this->getUser();
        $isClient = $this->isGranted('ROLE_CLIENT')
            && method_exists($user, 'getClient')
            && $user->getClient() === $order->getClient();

        $back = $request->query->get('back') ?: $request->headers->get('referer');

        $form = $this->createForm(OrderType::class, $order, [
            'is_client' => $isClient, // ← restrict visible/editable fields for clients
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            if ($back) {
                return $this->redirect($back);
            }
            return $this->redirectToRoute('app_order_index');
        }

        return $this->render('order/edit.html.twig', [
            'order' => $order,
            'form'  => $form,
            'back'  => $back,
            'is_client' => $isClient,
        ]);
    }

    #[Route('/{id}', name: 'app_order_delete', methods: ['POST'])]
    public function delete(Request $request, Order $order, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ORDER_EDIT', $order);
        $back = $request->request->get('back'); // via POST
        if ($this->isCsrfTokenValid('delete'.$order->getId(), $request->request->get('_token'))) {
            $em->remove($order);
            $em->flush();
        }
        return $back
            ? $this->redirect($back)
            : $this->redirectToRoute('app_order_index');
    }

    #[Route('/admin/order/export', name: 'app_order_export', methods: ['GET'])]
    public function export(Request $request, OrderRepository $repo): StreamedResponse
    {
        // Reprendre les filtres (lecture safe champ par champ)
        $form = $this->createForm(\App\Form\OrderFilterType::class);
        $form->handleRequest($request);

        $q       = $form->has('q')      ? $form->get('q')->getData()      : null;
        $client  = $form->has('client') ? $form->get('client')->getData() : null; // Client|null
        $status  = $form->has('status') ? $form->get('status')->getData() : null; // Enum|null
        $from    = $form->has('from')   ? $form->get('from')->getData()   : null;
        $to      = $form->has('to')     ? $form->get('to')->getData()     : null;

        $clientId = $client?->getId();

        $sort = (string) $request->query->get('sort', 'updatedAt');
        $dir  = (string) $request->query->get('dir', 'DESC');

        $iter = $repo->findForExport($q, $clientId, $status, $from, $to, $sort, $dir);

        $response = new StreamedResponse(function () use ($iter) {
            $out = fopen('php://output', 'w');
            // En-têtes
            fputcsv($out, ['ID', 'Title', 'Client', 'Price(€)', 'Status', 'Due', 'Created', 'Updated']);
            // Lignes
            foreach ($iter as $o) {
                fputcsv($out, [
                    $o->getId(),
                    $o->getTitle(),
                    $o->getClient()?->getName() ?? '',
                    number_format($o->getPrice() / 100, 2, ',', ' '),
                    $o->getStatus()->value,
                    $o->getDueAt()?->format('Y-m-d H:i') ?? '',
                    $o->getCreatedAt()?->format('Y-m-d H:i') ?? '',
                    $o->getUpdatedAt()?->format('Y-m-d H:i') ?? '',
                ]);
            }
            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="orders.csv"');

        return $response;
    }

    #[Route('/{id}/cancel', name: 'app_order_cancel', methods: ['POST'])]
    public function cancel(Request $request, \App\Entity\Order $order, \Doctrine\ORM\EntityManagerInterface $em): \Symfony\Component\HttpFoundation\Response
    {
        // Autorisations: le client propriétaire peut éditer; l’admin aussi (mais en pratique seul le client verra le bouton)
        $this->denyAccessUnlessGranted('ORDER_EDIT', $order);

        // CSRF
        if (!$this->isCsrfTokenValid('cancel'.$order->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        // Règle métier: Cancel autorisé uniquement si status = CREATED
        if ($order->getStatus() !== \App\Enum\OrderStatus::CREATED) {
            $this->addFlash('warning', 'This order can no longer be canceled.');
        } else {
            $order->setStatus(\App\Enum\OrderStatus::CANCELED);
            $order->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', 'Order canceled.');
        }

        $back = $request->query->get('back') ?: $request->headers->get('referer');
        return $back
            ? $this->redirect($back)
            : $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
    }

}
