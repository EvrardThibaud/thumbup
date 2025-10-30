<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\InvitationRepository;
use Doctrine\ORM\EntityManagerInterface as EM;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use App\Security\UserAuthenticator; // ton authenticator (généré par make:security)

final class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET','POST'])]
    public function register(
        Request $request,
        InvitationRepository $invitations,
        UserPasswordHasherInterface $hasher,
        EM $em,
        UserAuthenticatorInterface $authenticatorManager,
        UserAuthenticator $formAuthenticator
    ): Response {
        $token = (string) $request->query->get('invite', '');
        $inv   = $token ? $invitations->findUsableByToken($token) : null;

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user, [
            'with_client_fields' => $inv === null,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setRoles(['ROLE_CLIENT']);
            $user->setPassword(
                $hasher->hashPassword($user, (string) $form->get('plainPassword')->getData())
            );

            if ($inv) {
                // Lier au Client existant via invitation
                $user->setClient($inv->getClient());
                $inv->setUsedAt(new \DateTimeImmutable());
                $em->persist($inv);
            } else {
                // Créer un nouveau Client automatiquement
                $name = trim((string) $form->get('clientName')->getData());
                $url  = (string) $form->get('channelUrl')->getData();
                if ($name === '') {
                    $this->addFlash('danger', 'Client name is required.');
                    return $this->render('security/register.html.twig', [
                        'registrationForm' => $form->createView(),
                        'inviteToken'      => null,
                        'invitation'       => null,
                        'invitedClient'    => null,
                    ]);
                }
                $client = (new Client())->setName($name)->setChannelUrl($url);
                $em->persist($client);
                $user->setClient($client);
            }

            $em->persist($user);
            $em->flush();

            // 🔒 Auto-login + redirection (selon onAuthenticationSuccess)
            return $authenticatorManager->authenticateUser(
                $user,
                $formAuthenticator,
                $request
            );
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form->createView(),
            'inviteToken'      => $token ?: null,
            'invitation'       => $inv,
            'invitedClient'    => $inv?->getClient(),
        ]);
    }
}
