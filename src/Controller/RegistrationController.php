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
use App\Security\UserAuthenticator;

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

        // Si invitation: vérifier qu'aucun user n'est déjà lié à ce client
        $linkedAlready = false;
        if ($inv !== null) {
            $clientForInv = $inv->getClient();
            if ($clientForInv) {
                $linkedAlready = (bool) $em->getRepository(User::class)->findOneBy(['client' => $clientForInv]);
            }
        }

        $user = new User();

        // Sans invitation, on affiche les champs clientName/channelUrl
        $form = $this->createForm(RegistrationFormType::class, $user, [
            'with_client_fields' => ($inv === null),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Rôle par défaut
            $user->setRoles(['ROLE_CLIENT']);

            // Hash PW
            $plain = (string) $form->get('plainPassword')->getData();
            $user->setPassword($hasher->hashPassword($user, $plain));

            if ($inv !== null) {
                // Cas INSCRIPTION via invitation
                if ($linkedAlready) {
                    $this->addFlash('danger', 'This client is already linked to another account.');
                    return $this->redirectToRoute('app_login');
                }

                $client = $inv->getClient();
                if (!$client) {
                    $this->addFlash('danger', 'Invalid invitation (missing client).');
                    return $this->redirectToRoute('app_register');
                }

                // Lier le user au client de l’invitation
                $user->setClient($client);

                // Marquer l’invitation comme utilisée
                $inv->setUsedAt(new \DateTimeImmutable());
                $em->persist($inv);

                // (Optionnel) Nettoyer autres invitations non utilisées de ce client
                $others = $em->getRepository(\App\Entity\Invitation::class)->findBy(['client' => $client]);
                foreach ($others as $o) {
                    if ($o !== $inv && !$o->getUsedAt()) {
                        $em->remove($o);
                    }
                }
            } else {
                // Cas INSCRIPTION sans invitation → créer un nouveau Client
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

                $client = (new Client())
                    ->setName($name)
                    ->setChannelUrl($url ?: null);

                $em->persist($client);
                $user->setClient($client);
            }

            $em->persist($user);
            $em->flush();

            // Auto-login
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
