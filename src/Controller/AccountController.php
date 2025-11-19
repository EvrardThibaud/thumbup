<?php
// src/Controller/AccountController.php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/account')]
final class AccountController extends AbstractController
{
    #[Route('', name: 'app_account', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var User $user */
        $user = $this->getUser();

        return $this->render('account/index.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/edit', name: 'app_account_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var User $user */
        $user   = $this->getUser();
        $client = $user?->getClient();

        // Password form
        $passwordForm = $this->createForm(ChangePasswordType::class, null, [
            'attr' => [
                'autocomplete' => 'off',
                'data-turbo'   => 'false',
            ],
        ]);
        $passwordForm->handleRequest($request);

        if ($request->isMethod('POST')) {
            $action = (string) $request->request->get('_action', '');

            // --- PROFILE UPDATE ---
            if ($action === 'profile') {
                if (!$client) {
                    throw $this->createAccessDeniedException('No client linked to this account.');
                }

                if (!$this->isCsrfTokenValid('edit_profile', (string) $request->request->get('_token'))) {
                    throw $this->createAccessDeniedException('Invalid CSRF token.');
                }

                $username   = trim((string) $request->request->get('username', ''));
                $channelUrl = trim((string) $request->request->get('channel_url', ''));

                if ($username === '') {
                    $this->addFlash('danger', 'Username cannot be empty.');
                    return $this->redirectToRoute('app_account_edit');
                }

                if (mb_strlen($username) > 255) {
                    $this->addFlash('danger', 'Username is too long (max 255 characters).');
                    return $this->redirectToRoute('app_account_edit');
                }

                if ($channelUrl !== '' && mb_strlen($channelUrl) > 255) {
                    $this->addFlash('danger', 'Channel URL is too long (max 255 characters).');
                    return $this->redirectToRoute('app_account_edit');
                }

                if ($channelUrl !== '' && !preg_match('#^https?://#i', $channelUrl)) {
                    $channelUrl = 'https://' . $channelUrl;
                }

                $client->setName($username);
                $client->setChannelUrl($channelUrl !== '' ? $channelUrl : null);

                $em->flush();

                $this->addFlash('success', 'Profile updated.');
                return $this->redirectToRoute('app_account_edit');
            }

            // --- PASSWORD UPDATE ---
            if ($action === 'password' && $passwordForm->isSubmitted()) {
                $currentPlain = (string) $passwordForm->get('currentPassword')->getData();
                $newPlain     = (string) $passwordForm->get('newPassword')->getData();
                $confirmPlain = (string) $passwordForm->get('confirmNewPassword')->getData();

                // 1) Check current password
                if (!$hasher->isPasswordValid($user, $currentPlain)) {
                    $passwordForm->get('currentPassword')->addError(
                        new FormError('Current password is incorrect.')
                    );
                }

                // 2) New passwords must match
                if ($newPlain !== '' && $confirmPlain !== '' && $newPlain !== $confirmPlain) {
                    $passwordForm->get('confirmNewPassword')->addError(
                        new FormError('The two new passwords must match.')
                    );
                }

                // 3) New password must be different
                if ($newPlain !== '' && $newPlain === $currentPlain) {
                    $passwordForm->get('newPassword')->addError(
                        new FormError('The new password must be different from the current password.')
                    );
                }

                if ($passwordForm->isSubmitted() && $passwordForm->isValid()) {
                    $user->setPassword(
                        $hasher->hashPassword($user, $newPlain)
                    );
                    $em->flush();

                    $this->addFlash('success', 'Password updated successfully.');

                    return $this->redirectToRoute('app_account_edit');
                }
            }
        }

        return $this->render('account/edit.html.twig', [
            'user'         => $user,
            'passwordForm' => $passwordForm->createView(),
        ]);
    }
}
