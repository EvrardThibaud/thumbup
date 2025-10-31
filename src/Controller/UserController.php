<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/users')]
final class UserController extends AbstractController
{
    #[Route('', name: 'app_user_index', methods: ['GET'])]
    public function index(UserRepository $users, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = 20;
        $qb = $users->createQueryBuilder('u')
            ->leftJoin('u.client', 'c')->addSelect('c')
            ->orderBy('u.createdAt', 'DESC');
        $total = (int) (clone $qb)->select('COUNT(u.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();
        $items = $qb->setFirstResult(($page-1)*$limit)->setMaxResults($limit)->getQuery()->getResult();

        return $this->render('user/index.html.twig', [
            'users' => $items, 'total' => $total, 'page' => $page, 'limit' => $limit,
        ]);
    }

    #[Route('/new', name: 'app_user_new', methods: ['GET','POST'])]
    public function new(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['require_password' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = (string) $form->get('plainPassword')->getData();
            $user->setPassword($hasher->hashPassword($user, $plain));
            $em->persist($user);
            $em->flush();
            $this->addFlash('success', 'User created.');
            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('user/new.html.twig', ['form' => $form, 'user' => $user]);
    }

    #[Route('/{id}', name: 'app_user_show', methods: ['GET'])]
    public function show(User $user): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        return $this->render('user/show.html.twig', ['user' => $user]);
    }

    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET','POST'])]
    public function edit(User $user, Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(UserType::class, $user, ['require_password' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = (string) $form->get('plainPassword')->getData();
            if ($plain !== '') {
                $user->setPassword($hasher->hashPassword($user, $plain));
            }
            $em->flush();
            $this->addFlash('success', 'User updated.');
            $back = $request->query->get('back') ?: $request->headers->get('referer');
            return $back ? $this->redirect($back) : $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
        }

        return $this->render('user/edit.html.twig', ['form' => $form, 'user' => $user]);
    }

    #[Route('/{id}', name: 'app_user_delete', methods: ['POST'])]
    public function delete(User $user, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('delete-user'.$user->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $em->remove($user);
        $em->flush();
        $this->addFlash('success', 'User deleted.');
        return $this->redirectToRoute('app_user_index');
    }
}
