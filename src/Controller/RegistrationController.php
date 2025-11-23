<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\User;
use App\Entity\EmailVerificationToken;
use App\Form\RegistrationFormType;
use App\Repository\InvitationRepository;
use Doctrine\ORM\EntityManagerInterface as EM;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET','POST'])]
    public function register(
        Request $request,
        InvitationRepository $invitations,
        UserPasswordHasherInterface $hasher,
        EM $em,
        MailerInterface $mailer
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }
        $token = (string) $request->query->get('invite', '');
        $inv   = $token ? $invitations->findUsableByToken($token) : null;

        $linkedAlready = false;
        if ($inv !== null) {
            $clientForInv = $inv->getClient();
            if ($clientForInv) {
                $linkedAlready = (bool) $em->getRepository(User::class)->findOneBy(['client' => $clientForInv]);
            }
        }

        $user = new User();

        $form = $this->createForm(RegistrationFormType::class, $user, [
            'with_client_fields' => ($inv === null),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setRoles(['ROLE_CLIENT']);

            $plain = (string) $form->get('plainPassword')->getData();
            $user->setPassword($hasher->hashPassword($user, $plain));
            $user->setTimezone('Europe/Paris'); 

            if ($inv !== null) {
                if ($linkedAlready) {
                    $this->addFlash('danger', 'This client is already linked to another account.');
                    return $this->redirectToRoute('app_login');
                }

                $client = $inv->getClient();
                if (!$client) {
                    $this->addFlash('danger', 'Invalid invitation (missing client).');
                    return $this->redirectToRoute('app_register');
                }

                $user->setClient($client);

                $inv->setUsedAt(new \DateTimeImmutable());
                $em->persist($inv);

                $others = $em->getRepository(\App\Entity\Invitation::class)->findBy(['client' => $client]);
                foreach ($others as $o) {
                    if ($o !== $inv && !$o->getUsedAt()) {
                        $em->remove($o);
                    }
                }
            } else {
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

            $session = $request->getSession();
            $session->set('verify_email', $user->getEmail());
            $session->set('verify_email_last_sent', time());

            $this->createAndSendVerificationToken($user, $em, $mailer);

            return $this->redirectToRoute('app_register_check_email');
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form->createView(),
            'inviteToken'      => $token ?: null,
            'invitation'       => $inv,
            'invitedClient'    => $inv?->getClient(),
        ]);
    }

    #[Route('/register/check-email', name: 'app_register_check_email', methods: ['GET'])]
    public function checkEmail(Request $request): Response
    {
        $session = $request->getSession();
        $email = $session->get('verify_email');

        if (!$email) {
            return $this->redirectToRoute('app_register');
        }

        $last = (int) $session->get('verify_email_last_sent', 0);
        $now = time();
        $secondsRemaining = max(0, 60 - ($now - $last));

        return $this->render('security/register_check_email.html.twig', [
            'email' => $email,
            'seconds_remaining' => $secondsRemaining,
        ]);
    }

    #[Route('/register/resend-verification', name: 'app_register_resend_verification', methods: ['POST'])]
    public function resendVerification(Request $request, EM $em, MailerInterface $mailer): Response
    {
        $session = $request->getSession();
        $email = $session->get('verify_email');

        if (!$email) {
            return $this->redirectToRoute('app_register');
        }

        $last = (int) $session->get('verify_email_last_sent', 0);
        $now = time();

        if ($now - $last < 60) {
            $this->addFlash('warning', 'Please wait a bit before requesting another email.');
            return $this->redirectToRoute('app_register_check_email');
        }

        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($user) {
            $session->set('verify_email_last_sent', $now);
            $this->createAndSendVerificationToken($user, $em, $mailer);
            $this->addFlash('success', 'A new verification email has been sent (if the address exists).');
        }

        return $this->redirectToRoute('app_register_check_email');
    }

    #[Route('/verify-email/{token}', name: 'app_verify_email', methods: ['GET'])]
    public function verifyEmail(string $token, EM $em): Response
    {
        $repo = $em->getRepository(EmailVerificationToken::class);
        /** @var EmailVerificationToken|null $tokenEntity */
        $tokenEntity = $repo->findOneBy(['token' => $token]);

        if (!$tokenEntity || $tokenEntity->isExpired() || $tokenEntity->isUsed()) {
            $this->addFlash('danger', 'This verification link is invalid or has expired.');
            return $this->redirectToRoute('app_login');
        }

        $user = $tokenEntity->getUser();
        if (!$user) {
            $this->addFlash('danger', 'Unable to verify this account.');
            return $this->redirectToRoute('app_login');
        }

        $tokenEntity->setUsedAt(new \DateTimeImmutable());
        $user->setIsVerified(true);

        $em->flush();

        return $this->render('security/email_verified.html.twig');
    }

    private function createAndSendVerificationToken(User $user, EM $em, MailerInterface $mailer): void
    {
        $repo = $em->getRepository(EmailVerificationToken::class);
        $existing = $repo->findBy(['user' => $user, 'usedAt' => null]);

        foreach ($existing as $t) {
            $t->setUsedAt(new \DateTimeImmutable());
        }

        $value = bin2hex(random_bytes(32));

        $token = new EmailVerificationToken();
        $token
            ->setUser($user)
            ->setToken($value)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setExpiresAt(new \DateTimeImmutable('+3 days'));

        $em->persist($token);
        $em->flush();

        $url = $this->generateUrl(
            'app_verify_email',
            ['token' => $value],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new Email())
            ->from('thibaud.evrard@outlook.com')
            ->to($user->getEmail())
            ->subject('Confirm your ThumbUp account')
            ->text("Welcome to ThumbUp!\n\nPlease confirm your email by visiting this link:\n$url\n\nIf you did not create an account, you can ignore this email.")
            ->html(
                '<p>Welcome to <strong>ThumbUp</strong> 👋</p>' .
                '<p>Please confirm your email address by clicking the button below:</p>' .
                '<p><a href="' . htmlspecialchars($url, ENT_QUOTES) . '" class="btn btn-primary">Confirm my account</a></p>' .
                '<p class="small" style="color:#aaa;">If the button doesn’t work, copy this link into your browser:<br>' .
                htmlspecialchars($url, ENT_QUOTES) . '</p>'
            );

        $mailer->send($email);
    }
}
