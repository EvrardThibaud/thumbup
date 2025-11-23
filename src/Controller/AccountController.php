<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\YoutubeChannel;
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

        $passwordForm = $this->createForm(ChangePasswordType::class, null, [
            'attr' => [
                'autocomplete' => 'off',
                'data-turbo'   => 'false',
            ],
        ]);
        $passwordForm->handleRequest($request);

        if ($request->isMethod('POST')) {
            $action = (string) $request->request->get('_action', '');

            if ($action === 'profile') {
                if (!$client) {
                    throw $this->createAccessDeniedException('No client linked to this account.');
                }

                if (!$this->isCsrfTokenValid('edit_profile', (string) $request->request->get('_token'))) {
                    throw $this->createAccessDeniedException('Invalid CSRF token.');
                }

                $username     = trim((string) $request->request->get('username', ''));
                $channelsData = $request->request->all('channels') ?? [];

                if ($username === '') {
                    $this->addFlash('danger', 'Username cannot be empty.');
                    return $this->redirectToRoute('app_account_edit');
                }

                if (mb_strlen($username) > 255) {
                    $this->addFlash('danger', 'Username is too long (max 255 characters).');
                    return $this->redirectToRoute('app_account_edit');
                }

                $existingChannels = [];
                foreach ($client->getYoutubeChannels() as $ch) {
                    if (null !== $ch->getId()) {
                        $existingChannels[$ch->getId()] = $ch;
                    }
                }

                $seenIds  = [];
                $position = 0;

                foreach ($channelsData as $row) {
                    $id   = isset($row['id']) ? (int) $row['id'] : null;
                    $name = trim((string) ($row['name'] ?? ''));
                    $url  = trim((string) ($row['url'] ?? ''));

                    if ($name === '' && $url === '') {
                        continue;
                    }

                    if ($url !== '' && !preg_match('#^https?://#i', $url)) {
                        $url = 'https://' . $url;
                    }

                    if ($name !== '' && mb_strlen($name) > 255) {
                        $this->addFlash('danger', 'Channel title is too long (max 255 characters).');
                        return $this->redirectToRoute('app_account_edit');
                    }

                    if ($url !== '' && mb_strlen($url) > 255) {
                        $this->addFlash('danger', 'Channel URL is too long (max 255 characters).');
                        return $this->redirectToRoute('app_account_edit');
                    }

                    if ($url === '') {
                        $this->addFlash('danger', 'Channel URL cannot be empty if a title is provided.');
                        return $this->redirectToRoute('app_account_edit');
                    }

                    if ($id && isset($existingChannels[$id])) {
                        $channel   = $existingChannels[$id];
                        $seenIds[] = $id;
                    } else {
                        $channel = new YoutubeChannel();
                        $channel->setClient($client);
                        $em->persist($channel);
                    }

                    $channel
                        ->setName($name !== '' ? $name : $url)
                        ->setUrl($url)
                        ->setPosition($position++);
                }

                foreach ($client->getYoutubeChannels() as $ch) {
                    $id = $ch->getId();
                    if ($id !== null && !in_array($id, $seenIds, true)) {
                        $client->removeYoutubeChannel($ch);
                        $em->remove($ch);
                    }
                }

                $client->setName($username);

                $em->flush();

                $this->addFlash('success', 'Profile updated.');
                return $this->redirectToRoute('app_account_edit');
            }

            if ($action === 'password' && $passwordForm->isSubmitted()) {
                $currentPlain = (string) $passwordForm->get('currentPassword')->getData();
                $newPlain     = (string) $passwordForm->get('newPassword')->getData();
                $confirmPlain = (string) $passwordForm->get('confirmNewPassword')->getData();

                if (!$hasher->isPasswordValid($user, $currentPlain)) {
                    $passwordForm->get('currentPassword')->addError(
                        new FormError('Current password is incorrect.')
                    );
                }

                if ($newPlain !== '' && $confirmPlain !== '' && $newPlain !== $confirmPlain) {
                    $passwordForm->get('confirmNewPassword')->addError(
                        new FormError('The two new passwords must match.')
                    );
                }

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
