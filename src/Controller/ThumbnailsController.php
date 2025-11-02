<?php
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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $perPage = 3;
        $page = max(1, (int)$request->query->get('page', 1));

        $clientParam = trim((string)$request->query->get('client', ''));
        $selectedClient = (ctype_digit($clientParam) && $clientParam !== '')
            ? $clientsRepo->find((int)$clientParam)
            : null;

        $result  = $ordersRepo->paginateOrdersWithAssets($selectedClient, $page, $perPage);
        $clients = $clientsRepo->createQueryBuilder('c')->orderBy('c.name', 'ASC')->getQuery()->getResult();

        return $this->render('thumbnails/index.html.twig', [
            'clients'        => $clients,
            'selectedClient' => $selectedClient,
            'orders'         => $result['items'],
            'page'           => $result['page'],
            'hasMore'        => $result['hasMore'],
        ]);
    }
}
