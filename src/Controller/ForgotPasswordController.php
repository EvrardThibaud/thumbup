<?php

namespace App\Controller;

use App\Entity\PasswordResetToken;
use App\Form\ResetPasswordType;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ForgotPasswordController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private PasswordResetTokenRepository $tokenRepository,
        private EntityManagerInterface $em,
        private MailerInterface $mailer
    ) {
    }

    #[Route('/forgot-password', name: 'app_forgot_password_request', methods: ['GET', 'POST'])]
    public function request(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email', ''));
            $session = $request->getSession();

            if ($email !== '') {
                $session->set('password_reset.email', $email);
                $session->set('password_reset.last_request', time());

                $user = $this->userRepository->findOneBy(['email' => $email]);
                if ($user) {
                    $this->createAndSendToken($user);
                }
            }

            return $this->redirectToRoute('app_forgot_password_check_email');
        }

        return $this->render('security/forgot_password_request.html.twig');
    }

    #[Route('/forgot-password/check-email', name: 'app_forgot_password_check_email', methods: ['GET'])]
    public function checkEmail(Request $request): Response
    {
        $session = $request->getSession();
        $email = $session->get('password_reset.email');

        if (!$email) {
            return $this->redirectToRoute('app_forgot_password_request');
        }

        $lastRequest = (int) $session->get('password_reset.last_request', 0);
        $now = time();
        $secondsRemaining = max(0, 60 - ($now - $lastRequest));

        return $this->render('security/forgot_password_check_email.html.twig', [
            'email' => $email,
            'seconds_remaining' => $secondsRemaining,
        ]);
    }

    #[Route('/forgot-password/resend', name: 'app_forgot_password_resend', methods: ['POST'])]
    public function resend(Request $request): Response
    {
        $session = $request->getSession();
        $email = $session->get('password_reset.email');

        if (!$email) {
            return $this->redirectToRoute('app_forgot_password_request');
        }

        $lastRequest = (int) $session->get('password_reset.last_request', 0);
        $now = time();

        if ($now - $lastRequest < 60) {
            $this->addFlash('warning', 'Please wait a bit before requesting another email.');
            return $this->redirectToRoute('app_forgot_password_check_email');
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if ($user) {
            $session->set('password_reset.last_request', $now);
            $this->createAndSendToken($user);
        }

        $this->addFlash('success', 'A new password reset email has been sent (if the address exists).');

        return $this->redirectToRoute('app_forgot_password_check_email');
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function reset(
        string $token,
        Request $request,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $resetToken = $this->tokenRepository->findOneBy(['token' => $token]);

        if (!$resetToken || $resetToken->isExpired() || $resetToken->isUsed()) {
            $this->addFlash('danger', 'This reset link is invalid or has expired.');
            return $this->redirectToRoute('app_forgot_password_request');
        }

        $user = $resetToken->getUser();
        if (!$user) {
            $this->addFlash('danger', 'Unable to reset password for this account.');
            return $this->redirectToRoute('app_forgot_password_request');
        }

        $form = $this->createForm(ResetPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            $hashed = $passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashed);

            $resetToken->setUsedAt(new \DateTimeImmutable());

            $this->em->flush();

            $this->addFlash('success', 'Your password has been reset. You can now sign in.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'resetForm' => $form->createView(),
        ]);
    }

    private function createAndSendToken($user): void
    {
        $existingTokens = $this->tokenRepository->findBy(['user' => $user, 'usedAt' => null]);
        foreach ($existingTokens as $t) {
            $t->setUsedAt(new \DateTimeImmutable());
        }

        $tokenValue = bin2hex(random_bytes(32));

        $resetToken = new PasswordResetToken();
        $resetToken
            ->setUser($user)
            ->setToken($tokenValue)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setExpiresAt(new \DateTimeImmutable('+1 hour'));

        $this->em->persist($resetToken);
        $this->em->flush();

        $resetUrl = $this->generateUrl(
            'app_reset_password',
            ['token' => $tokenValue],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new Email())
            ->from('thibaud.evrard@outlook.com')
            ->to($user->getEmail())
            ->subject('ThumbUp password reset')
            ->text("You requested a password reset on ThumbUp.\n\nUse this link to set a new password:\n$resetUrl\n\nIf you did not request this, you can ignore this email.")
            ->html(
                '<p>You requested a password reset on <strong>ThumbUp</strong>.</p>' .
                '<p><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES) . '">Click here to set a new password</a></p>' .
                '<p>If you did not request this, you can ignore this email.</p>'
            );

        $this->mailer->send($email);
    }
}
