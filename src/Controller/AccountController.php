<?php

namespace App\Controller;

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

        return $this->render('account/index.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/password', name: 'app_account_password', methods: ['GET', 'POST'])]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $form = $this->createForm(ChangePasswordType::class, null, [
            'attr' => [
                'autocomplete' => 'off',
                'data-turbo'   => 'false', // pour éviter l’erreur Turbo
            ],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $currentPlain  = (string) $form->get('currentPassword')->getData();
            $newPlain      = (string) $form->get('newPassword')->getData();
            $confirmPlain  = (string) $form->get('confirmNewPassword')->getData();

            // 1) Vérifier l’ancien mot de passe
            if (!$hasher->isPasswordValid($user, $currentPlain)) {
                $form->get('currentPassword')->addError(
                    new FormError('Current password is incorrect.')
                );
            }

            // 2) Vérifier que les deux nouveaux mots de passe sont identiques
            if ($newPlain !== '' && $confirmPlain !== '' && $newPlain !== $confirmPlain) {
                $form->get('confirmNewPassword')->addError(
                    new FormError('The two new passwords must match.')
                );
            }

            // 3) Vérifier que le nouveau n’est pas identique à l’ancien
            if ($newPlain !== '' && $newPlain === $currentPlain) {
                $form->get('newPassword')->addError(
                    new FormError('The new password must be different from the current password.')
                );
            }

            // Si tout est OK côté contraintes + nos erreurs custom
            if ($form->isValid()) {
                $user->setPassword(
                    $hasher->hashPassword($user, $newPlain)
                );
                $em->flush();

                $this->addFlash('success', 'Password updated successfully.');

                return $this->redirectToRoute('app_account');
            }
        }

        return $this->render('account/password.html.twig', [
            'passwordForm' => $form->createView(),
        ]);
    }
}
