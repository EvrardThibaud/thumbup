<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\YoutubeChannel;
use App\Form\ClientType;
use App\Repository\ClientRepository;
use App\Repository\InvitationRepository;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/admin/client')]
final class ClientController extends AbstractController
{
    #[Route('/', name: 'app_client_index', methods: ['GET'])]
    public function index(
        ClientRepository $clientsRepo,
        UserRepository $usersRepo,
        OrderRepository $orders
    ): Response {
        $clients = $clientsRepo->findAll();

        $linkedMap = [];
        if (!empty($clients)) {
            $users = $usersRepo->createQueryBuilder('u')
                ->where('u.client IN (:clients)')
                ->setParameter('clients', $clients)
                ->getQuery()
                ->getResult();

            foreach ($users as $u) {
                $cid = $u->getClient()?->getId();
                if ($cid) {
                    $linkedMap[$cid] = $u;
                }
            }
        }

        $dueMap = $orders->dueByClient();

        return $this->render('client/index.html.twig', [
            'clients'   => $clients,
            'dueMap'    => $dueMap,
            'linkedMap' => $linkedMap,
        ]);
    }

    #[Route('/new', name: 'app_client_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $client = new Client();
        $form   = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($client);
            $entityManager->flush();

            return $this->redirectToRoute('app_client_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('client/new.html.twig', [
            'client' => $client,
            'form'   => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_client_show', methods: ['GET'])]
    public function show(
        Client $client,
        OrderRepository $orderRepo,
        Request $request,
        UserRepository $users,
        InvitationRepository $invites,
    ): Response {
        $this->denyAccessUnlessGranted('CLIENT_VIEW', $client);

        $linkedUser = $users->findOneBy(['client' => $client]);
        $totals     = $orderRepo->dueAndPaidForClient($client->getId());

        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = 10;
        $sort  = (string) $request->query->get('sort', 'updatedAt');
        $dir   = (string) $request->query->get('dir', 'DESC');

        $result = $orderRepo->searchPaginated(
            q: null,
            clientId: $client->getId(),
            status: null,
            from: null,
            to: null,
            paid: null,
            page: $page,
            limit: $limit,
            sort: $sort,
            dir: $dir
        );

        [$orders, $total] = $result;

        $inviteLink      = null;
        $lastInvitation  = $invites->findOneBy(
            ['client' => $client],
            ['createdAt' => 'DESC']
        );

        if ($lastInvitation && $lastInvitation->isUsable()) {
            $inviteLink = $this->generateUrl(
                'app_register',
                ['invite' => $lastInvitation->getToken()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        }

        return $this->render('client/show.html.twig', [
            'client'     => $client,
            'dueCents'   => $totals['dueCents'],
            'paidCents'  => $totals['paidCents'],
            'orders'     => $orders,
            'total'      => $total,
            'page'       => $page,
            'limit'      => $limit,
            'sort'       => $sort,
            'dir'        => strtoupper($dir),
            'linkedUser' => $linkedUser,
            'inviteLink' => $inviteLink,
        ]);
    }

    #[Route('/admin/client/{id}/edit', name: 'app_client_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Client $client,
        EntityManagerInterface $em,
        UserRepository $users
    ): Response {
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $channelsPayload = (array) $request->request->all('channels');

            $existing = [];
            foreach ($client->getYoutubeChannels() as $ch) {
                $existing[$ch->getId()] = $ch;
            }

            $position = 0;

            foreach ($channelsPayload as $row) {
                $id   = isset($row['id']) ? (int) $row['id'] : null;
                $name = trim((string) ($row['name'] ?? ''));
                $url  = trim((string) ($row['url'] ?? ''));

                if ($name === '' && $url === '') {
                    if ($id && isset($existing[$id])) {
                        $em->remove($existing[$id]);
                        unset($existing[$id]);
                    }
                    continue;
                }

                if ($url !== '' && !preg_match('#^https?://#i', $url)) {
                    $url = 'https://' . $url;
                }

                if ($id && isset($existing[$id])) {
                    $channel = $existing[$id];
                    unset($existing[$id]);
                } else {
                    $channel = new YoutubeChannel();
                    $channel->setClient($client);
                    $em->persist($channel);
                }

                $channel->setName($name !== '' ? $name : null);
                $channel->setUrl($url);
                $channel->setPosition($position++);
            }

            foreach ($existing as $toRemove) {
                $em->remove($toRemove);
            }

            $em->flush();

            $this->addFlash('success', 'Client updated.');

            return $this->redirectToRoute('app_client_show', ['id' => $client->getId()]);
        }

        $linkedUser = $users->findOneBy(['client' => $client]);

        return $this->render('client/edit.html.twig', [
            'client'     => $client,
            'form'       => $form,
            'linkedUser' => $linkedUser,
        ]);
    }

    #[Route('/{id}', name: 'app_client_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Client $client,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isCsrfTokenValid('delete' . $client->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($client);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_client_index', [], Response::HTTP_SEE_OTHER);
    }
}
