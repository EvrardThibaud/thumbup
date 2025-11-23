<?php

namespace App\Form;

use App\Entity\Client;
use App\Entity\User;
use App\Repository\ClientRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
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
                'label'    => 'Role',
                'expanded' => true,
                'multiple' => false,
                'choices'  => [
                    'Admin'  => 'ROLE_ADMIN',
                    'Client' => 'ROLE_CLIENT',
                ],
                'help' => 'User must be either admin or client.',
            ])

            ->add('client', EntityType::class, [
                'class'       => Client::class,
                'required'    => false,
                'placeholder' => 'No linked client',
                'choice_label'=> 'name',
                'autocomplete'=> true,
                'label'       => 'Linked client',
                'query_builder' => function (ClientRepository $cr) use ($options) {
                    $qb = $cr->createQueryBuilder('c')
                        ->leftJoin(User::class, 'u', 'WITH', 'u.client = c')
                        ->where('u.id IS NULL');

                    if (($options['current_user'] ?? null) instanceof User && $options['current_user']->getClient()) {
                        $qb->orWhere('c = :curr')->setParameter('curr', $options['current_user']->getClient());
                    }

                    return $qb->orderBy('c.name', 'ASC');
                },
            ]);

        $builder->get('roles')->addModelTransformer(
            new CallbackTransformer(
                function (?array $rolesArray): ?string {
                    if (!$rolesArray || count($rolesArray) === 0) {
                        return null;
                    }
                    return $rolesArray[0];
                },
                function (?string $roleString): array {
                    return $roleString ? [$roleString] : [];
                }
            )
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'      => User::class,
            'current_user'    => null,
            'require_password'=> false,
        ]);
    }
}
