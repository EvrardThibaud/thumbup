<?php

namespace App\Controller;

use App\Entity\Order;
use App\Form\OrderType;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Form\OrderFiltersType;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Domain\OrderWorkflow;
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
        $limit= 10;
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
    public function new(Request $request, EntityManagerInterface $em, \App\Repository\OrderRepository $ordersRepo): Response
    {
        $order = new \App\Entity\Order();
        $now = new \DateTimeImmutable();
        $order->setCreatedAt($now);
        $order->setUpdatedAt($now);
        $order->setStatus(\App\Enum\OrderStatus::CREATED);
        $order->setPaid(false);

        $isClient = $this->isGranted('ROLE_CLIENT') && !$this->isGranted('ROLE_ADMIN');

        $minDueAtAttr = null;
        $lastPriceCents = null;

        if ($isClient) {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            if (!$user || null === $user->getClient()) { throw $this->createAccessDeniedException(); }
            $order->setClient($user->getClient());
            $order->setDueAt($now->modify('+2 days'));
            $minDueAtAttr = $now->format('Y-m-d\TH:i');

            $last = $ordersRepo->createQueryBuilder('o')
                ->andWhere('o.client = :c')->setParameter('c', $user->getClient())
                ->orderBy('o.id', 'DESC')->setMaxResults(1)
                ->getQuery()->getOneOrNullResult();
            if ($last) { $lastPriceCents = max(500, (int)$last->getPrice()); }
        }

        $form = $this->createForm(\App\Form\OrderType::class, $order, [
            'is_client' => $isClient,
            'for_edit'  => false,
            'min_due_at'=> $minDueAtAttr,
        ]);
        $form->handleRequest($request);

        // Sync price from pretty input -> hidden cents (server truth)
        if ($form->isSubmitted()) {
            $pretty = $request->request->get('pretty_price');
            if ($pretty !== null) {
                $normalized = str_replace(['€',' '], '', (string)$pretty);
                $normalized = str_replace(',', '.', $normalized);
                if (is_numeric($normalized)) {
                    $cents = (int) round(((float)$normalized) * 100);
                    $order->setPrice(max(500, $cents));
                }
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            if ($isClient) {
                $order->setStatus(\App\Enum\OrderStatus::CREATED);
                $order->setPaid(false);

                if ($order->getDueAt() && $order->getDueAt() < $now) {
                    $form->get('dueAt')->addError(new \Symfony\Component\Form\FormError('Due date must be in the future.'));
                }
                if ($order->getPrice() < 500) {
                    $form->get('price')->addError(new \Symfony\Component\Form\FormError('Minimum €5.00'));
                }
                if (!$form->isValid()) {
                    return $this->render('order/new.html.twig', [
                        'order' => $order,
                        'form'  => $form,
                        'back'  => $request->query->get('back') ?: $request->headers->get('referer'),
                        'lastPriceCents' => $lastPriceCents,
                        'due_min_help'   => true,
                    ]);
                }
            }

            // attachments
            /** @var \Symfony\Component\HttpFoundation\File\UploadedFile[] $files */
            $files = $form->get('attachments')->getData() ?: [];
            foreach ($files as $file) {
                if (!$file) continue;
                $mime = $file->getMimeType() ?: '';
                if (!in_array($mime, ['application/pdf','image/png','image/jpeg','image/webp'], true)) continue;

                $asset = new \App\Entity\OrderAsset();   // ← fichiers client
                $asset->setOrder($order);
                $asset->setFile($file);                  // mapping "order_asset"
                $em->persist($asset);
            }

            $em->persist($order);
            $em->flush();

            $back = $request->request->get('back') ?: $request->query->get('back');
            return $back ? $this->redirect($back) : $this->redirectToRoute('app_order_index');
        }

        $back = $request->query->get('back') ?: $request->headers->get('referer');
        return $this->render('order/new.html.twig', [
            'order' => $order,
            'form'  => $form,
            'back'  => $back,
            'lastPriceCents' => $lastPriceCents,
            'due_min_help'   => (bool)$minDueAtAttr,
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

    #[Route('/{id}/edit', name: 'app_order_edit', methods: ['GET','POST'])]
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

    #[Route('/{id}/toggle-paid', name: 'app_order_toggle_paid', methods: ['POST'])]
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

    #[Route('/{id}/accept', name: 'app_order_accept', methods: ['POST'])]
    public function accept(Request $request, Order $order, OrderWorkflow $wf, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('wf-accept'.$order->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try { $wf->accept($order); $em->flush(); $this->addFlash('success','Order accepted.'); }
        catch (\LogicException) { $this->addFlash('warning','Transition not allowed.'); }
        return $this->redirect($request->query->get('back') ?: $request->headers->get('referer') ?: $this->generateUrl('app_order_show',['id'=>$order->getId()]));
    }

    #[Route('/{id}/refuse', name: 'app_order_refuse', methods: ['POST'])]
    public function refuse(Request $request, Order $order, OrderWorkflow $wf, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('wf-refuse'.$order->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try { $wf->refuse($order); $em->flush(); $this->addFlash('success','Order refused.'); }
        catch (\LogicException) { $this->addFlash('warning','Transition not allowed.'); }
        return $this->redirect($request->query->get('back') ?: $request->headers->get('referer') ?: $this->generateUrl('app_order_show',['id'=>$order->getId()]));
    }

    #[Route('/{id}/start', name: 'app_order_start', methods: ['POST'])]
    public function start(Request $request, Order $order, OrderWorkflow $wf, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('wf-start'.$order->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try { $wf->start($order); $em->flush(); $this->addFlash('success','Work started.'); }
        catch (\LogicException) { $this->addFlash('warning','Transition not allowed.'); }
        return $this->redirect($request->query->get('back') ?: $request->headers->get('referer') ?: $this->generateUrl('app_order_show',['id'=>$order->getId()]));
    }

    #[Route('/{id}/deliver', name: 'app_order_deliver', methods: ['POST'])]
    public function deliver(Request $request, Order $order, OrderWorkflow $wf, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('wf-deliver'.$order->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try { $wf->deliver($order); $em->flush(); $this->addFlash('success','Order delivered.'); }
        catch (\LogicException) { $this->addFlash('warning','Transition not allowed.'); }
        return $this->redirect($request->query->get('back') ?: $request->headers->get('referer') ?: $this->generateUrl('app_order_show',['id'=>$order->getId()]));
    }

    // Cancel (client) — si tu as déjà une action, tu peux la simplifier pour réutiliser le workflow :
    #[Route('/{id}/cancel', name: 'app_order_cancel', methods: ['POST'])]
    public function cancel(Request $request, Order $order, OrderWorkflow $wf, EntityManagerInterface $em): Response
    {
        // Proprio client OU admin (mais UI côté client)
        $this->denyAccessUnlessGranted('ORDER_EDIT', $order);
        if (!$this->isCsrfTokenValid('wf-cancel'.$order->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        try { $wf->cancel($order); $em->flush(); $this->addFlash('success','Order canceled.'); }
        catch (\LogicException) { $this->addFlash('warning','This order can no longer be canceled.'); }
        return $this->redirect($request->query->get('back') ?: $request->headers->get('referer') ?: $this->generateUrl('app_order_show',['id'=>$order->getId()]));
    }

    #[Route('/{id}/finish', name: 'app_order_finish', methods: ['POST'])]
    public function finish(Request $request, Order $order, OrderWorkflow $wf, EntityManagerInterface $em): Response
    {
        // Admin OU client propriétaire (via voter)
        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->denyAccessUnlessGranted('ORDER_EDIT', $order);
        }
        if (!$this->isCsrfTokenValid('wf-finish'.$order->getId(), (string)$request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        try { $wf->finish($order); $em->flush(); $this->addFlash('success', 'Order marked as finished.'); }
        catch (\LogicException) { $this->addFlash('warning', 'Transition not allowed.'); }

        return $this->redirect($request->query->get('back')
            ?: $request->headers->get('referer')
            ?: $this->generateUrl('app_order_show', ['id' => $order->getId()]));
    }

    #[Route('/{id}/request-revision', name: 'app_order_request_revision', methods: ['POST'])]
    public function requestRevision(Request $request, Order $order, OrderWorkflow $wf, EntityManagerInterface $em): Response
    {
        // Admin OU client propriétaire (via voter)
        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->denyAccessUnlessGranted('ORDER_EDIT', $order);
        }
        if (!$this->isCsrfTokenValid('wf-revision'.$order->getId(), (string)$request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        try { $wf->requestRevision($order); $em->flush(); $this->addFlash('warning', 'Revision requested.'); }
        catch (\LogicException) { $this->addFlash('warning', 'Transition not allowed.'); }

        return $this->redirect($request->query->get('back')
            ?: $request->headers->get('referer')
            ?: $this->generateUrl('app_order_show', ['id' => $order->getId()]));
    }
}
