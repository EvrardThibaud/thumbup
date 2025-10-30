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
use App\Form\OrderFiltersType;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Enum\OrderStatus;
use App\Entity\User;

#[Route('/order')]
final class OrderController extends AbstractController
{
    #[Route('/', name: 'app_order_index', methods: ['GET'])]
    public function index(Request $request, OrderRepository $orders): Response
    {
        $isClient = $this->isGranted('ROLE_CLIENT') && !$this->isGranted('ROLE_ADMIN');

        $form = $this->createForm(OrderFiltersType::class, null, [
            'is_client' => $isClient,
            'method' => 'GET',
        ]);
        $form->handleRequest($request);

        $q       = $form->get('q')->getData();
        $client  = $form->has('client') ? $form->get('client')->getData() : null;
        $clientId= $client ? $client->getId() : null;
        $status  = $form->get('status')->getData();
        $from    = $form->get('from')->getData();
        $to      = $form->get('to')->getData();
        $paidSel = $form->get('paid')->getData(); // null|"yes"|"no"
        $paid    = $paidSel === 'yes' ? true : ($paidSel === 'no' ? false : null);

        if ($isClient) {
            $me = $this->getUser();
            $clientId = $me?->getClient()?->getId();
        }

        $page = (int) $request->query->get('page', 1);
        $limit= 20;
        $sort = (string) $request->query->get('sort', 'updatedAt');
        $dir  = (string) $request->query->get('dir', 'DESC');

        [$items, $total] = $orders->searchPaginated(
            q: $q,
            clientId: $clientId,
            status: $status,
            from: $from,
            to: $to,
            paid: $paid,
            page: $page,
            limit: $limit,
            sort: $sort,
            dir: $dir
        );

        return $this->render('order/index.html.twig', [
            'filters' => $form->createView(),
            'orders'  => $items,
            'total'   => $total,
            'page'    => $page,
            'limit'   => $limit,
            'sort'    => $sort,
            'dir'     => $dir,
            'route'   => 'app_order_index',
            'qs'      => $request->query->all(),
            'is_client' => $isClient,
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

    #[Route('/{id<\d+>}', name: 'app_order_show', methods: ['GET'])]
    public function show(Request $request, Order $order): Response
    {
        $this->denyAccessUnlessGranted('ORDER_VIEW', $order);
        $back = $request->query->get('back'); // string|null
        return $this->render('order/show.html.twig', [
            'order' => $order,
            'back'  => $back,
        ]);
    }

    #[Route('/{id<\d+>}/edit', name: 'app_order_edit', methods: ['GET','POST'])]
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

    #[Route('/{id<\d+>}', name: 'app_order_delete', methods: ['POST'])]
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
    public function export(Request $request, OrderRepository $orders): StreamedResponse
    {
        $isClient = $this->isGranted('ROLE_CLIENT') && !$this->isGranted('ROLE_ADMIN');
    
        // Recrée le formulaire de filtres (GET) pour lire les mêmes champs (order_filters[...])
        $form = $this->createForm(\App\Form\OrderFiltersType::class, null, [
            'is_client' => $isClient,
            'method'    => 'GET',
        ]);
        $form->handleRequest($request);
    
        $q        = $form->get('q')->getData();
        $client   = $form->has('client') ? $form->get('client')->getData() : null;
        $clientId = $client ? $client->getId() : null;
        $statusVal= $form->get('status')->getData(); // string|nullable
        $status   = $statusVal ? \App\Enum\OrderStatus::from($statusVal) : null;
        $from     = $form->get('from')->getData();
        $to       = $form->get('to')->getData();
        $paidSel  = $form->get('paid')->getData();   // null|"yes"|"no"
        $paid     = $paidSel === 'yes' ? true : ($paidSel === 'no' ? false : null);
    
        if ($isClient) {
            $clientId = $this->getUser()?->getClient()?->getId();
        }
    
        $sort = (string) $request->query->get('sort', 'updatedAt');
        $dir  = (string) $request->query->get('dir', 'DESC');
    
        $iterable = $orders->findForExport($q, $clientId, $status, $from, $to, $paid, $sort, $dir);
    
        $response = new StreamedResponse(function () use ($iterable) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID','Client','Title','Price (EUR)','Status','Paid','DueAt','UpdatedAt']);
            foreach ($iterable as $o) {
                fputcsv($out, [
                    $o->getId(),
                    $o->getClient()?->getName(),
                    $o->getTitle(),
                    number_format($o->getPrice()/100, 2, '.', ''),
                    $o->getStatus()->value,
                    $o->isPaid() ? 'yes' : 'no',
                    $o->getDueAt()?->format('Y-m-d H:i'),
                    $o->getUpdatedAt()?->format('Y-m-d H:i'),
                ]);
            }
            fclose($out);
        });
    
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="orders.csv"');
        return $response;
    }

    #[Route('/{id<\d+>}/toggle-paid', name: 'app_order_toggle_paid', methods: ['POST'])]
    public function togglePaid(Request $request, Order $order, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN'); // réservé admin

        if (!$this->isCsrfTokenValid('toggle-paid'.$order->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $order->setPaid(!$order->isPaid());
        $order->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', $order->isPaid() ? 'Marked as paid.' : 'Marked as unpaid.');

        $back = $request->query->get('back') ?: $request->headers->get('referer');
        return $back ? $this->redirect($back) : $this->redirectToRoute('app_order_show', ['id' => $order->getId()]);
    }

    #[Route('/{id<\d+>}/cancel', name: 'app_order_cancel', methods: ['POST'])]
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
