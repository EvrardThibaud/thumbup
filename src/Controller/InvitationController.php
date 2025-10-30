<?php
namespace App\Controller;

use App\Entity\Client;
use App\Entity\Invitation;
use App\Service\TokenFactory;
use Doctrine\ORM\EntityManagerInterface as EM;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class InvitationController extends AbstractController
{
    #[Route('/admin/invite/create/{id}', name: 'app_invite_create', methods: ['POST'])]
    public function create(Client $client, TokenFactory $tokens, EM $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $inv = (new Invitation())
            ->setClient($client)
            ->setToken($tokens->generate(24))
            // optionnel :
            // ->setExpiresAt((new \DateTimeImmutable())->modify('+14 days'))
        ;

        $em->persist($inv);
        $em->flush();

        $this->addFlash('success', 'Invitation créée.');
        return $this->redirectToRoute('app_client_show', ['id' => $client->getId()]);
    }

    #[Route('/admin/invite/revoke/{id}', name: 'app_invite_revoke', methods: ['POST'])]
    public function revoke(Invitation $inv, EM $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $clientId = $inv->getClient()->getId();
        $em->remove($inv);
        $em->flush();

        $this->addFlash('success', 'Invitation révoquée.');
        return $this->redirectToRoute('app_client_show', ['id' => $clientId]);
    }
}
