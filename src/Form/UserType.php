<?php
// src/Form/UserType.php

namespace App\Form;

use App\Entity\Client;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Roles',
                'expanded' => false,
                'multiple' => true,
                'choices' => [
                    'Admin'  => 'ROLE_ADMIN',
                    'Client' => 'ROLE_CLIENT',
                ],
                'help' => 'Select at least one role.',
            ])
            ->add('client', EntityType::class, [
                'class' => Client::class,
                'required' => false,
                'placeholder' => 'No linked client',
                'choice_label' => 'name',
                'autocomplete' => true,
                'label' => 'Linked client',
                // N'afficher que les clients non liés… + le client déjà lié à CE user
                'query_builder' => function (\App\Repository\ClientRepository $cr) use ($options) {
                    $qb = $cr->createQueryBuilder('c')
                        ->leftJoin(\App\Entity\User::class, 'u', 'WITH', 'u.client = c')
                        ->where('u.id IS NULL');
            
                    if (($options['current_user'] ?? null) instanceof \App\Entity\User && $options['current_user']->getClient()) {
                        $qb->orWhere('c = :curr')->setParameter('curr', $options['current_user']->getClient());
                    }
            
                    return $qb->orderBy('c.name', 'ASC');
                },
            ])            
            ->add('plainPassword', PasswordType::class, [
                'label' => 'New password',
                'mapped' => false,
                'required' => $options['require_password'],
                'attr' => ['autocomplete' => 'new-password'],
                'help' => $options['require_password'] ? null : 'Leave empty to keep current password.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'current_user' => null,
            'require_password' => false,
        ]);
    }
}
