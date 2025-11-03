<?php
// src/Controller/ThumbnailsController.php

namespace App\Controller;

use App\Entity\Client;
use App\Repository\ClientRepository;
use App\Repository\OrderRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/thumbnails')]
final class ThumbnailsController extends AbstractController
{
    #[Route('', name: 'app_thumbnails_index', methods: ['GET'])]
    public function index(Request $request, ClientRepository $clientsRepo, OrderRepository $ordersRepo): Response
    {
        $perPage = 100;
        $page = max(1, (int)$request->query->get('page', 1));

        $isAdmin  = $this->isGranted('ROLE_ADMIN');
        $isClient = $this->isGranted('ROLE_CLIENT') && !$isAdmin;

        if ($isClient) {
            // Force current user's client; no filtering UI; only their orders
            $user = $this->getUser();
            $selectedClient = (is_object($user) && method_exists($user, 'getClient')) ? $user->getClient() : null;
            if (!$selectedClient instanceof Client) {
                throw $this->createAccessDeniedException('No client linked to this user.');
            }
            $clients = []; // hide selector
        } else {
            // Admin: allow filtering by any client
            $clientParam = trim((string)$request->query->get('client', ''));
            $selectedClient = (ctype_digit($clientParam) && $clientParam !== '')
                ? $clientsRepo->find((int)$clientParam)
                : null;
            $clients = $clientsRepo->createQueryBuilder('c')->orderBy('c.name', 'ASC')->getQuery()->getResult();
        }

        $result = $ordersRepo->paginateOrdersWithAssets($selectedClient, $page, $perPage);

        return $this->render('thumbnails/index.html.twig', [
            'clients'        => $clients,
            'selectedClient' => $selectedClient,
            'orders'         => $result['items'],
            'page'           => $result['page'],
            'hasMore'        => $result['hasMore'],
            'isClientOnly'   => $isClient, // for template logic
        ]);
    }
}
