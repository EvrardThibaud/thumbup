<?php

namespace App\Form;

use App\Entity\Client;
use App\Entity\Order;
use App\Enum\OrderStatus;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class OrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isClient = (bool) $options['is_client'];
        $forEdit  = (bool) $options['for_edit']; // NEW: distinguish create vs edit

        // Champs communs
        $builder
            ->add('title')
            ->add('brief')
            ->add('dueAt', null, ['widget' => 'single_text']);

        if ($isClient) {
            // CLIENT
            // NEW: en création (for_edit=false) le client voit le prix (min 5€); en édition, non.
            if (!$forEdit) {
                $builder->add('price', null, [
                    // price stored in CENTS → 500 = €5
                    'constraints' => [
                        new Assert\PositiveOrZero(),
                        new Assert\GreaterThanOrEqual(500),
                    ],
                    'attr' => ['min' => 5, 'step' => 1],
                    'help' => 'Minimum €5.00',
                ]);
            }
            // Pas de client/status/paid côté client.
            return;
        }

        // ADMIN
        $builder
            ->add('client', EntityType::class, [
                'class' => Client::class,
                'placeholder' => 'Select a client',
                'choice_label' => 'name',
            ])
            ->add('price', null, [
                'constraints' => [new Assert\PositiveOrZero()],
            ])
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'Created'   => OrderStatus::CREATED,
                    'Refused'   => OrderStatus::REFUSED,
                    'Canceled'  => OrderStatus::CANCELED,
                    'To do'     => OrderStatus::ACCEPTED, // admin wording
                    'Doing'     => OrderStatus::DOING,
                    'Delivered' => OrderStatus::DELIVERED,
                    'Finished'  => OrderStatus::FINISHED,   // 👈
                    'Revision'  => OrderStatus::REVISION,
                ],
                'choice_label' => fn ($choice) => match ($choice) {
                    OrderStatus::CREATED   => 'Created',
                    OrderStatus::REFUSED   => 'Refused',
                    OrderStatus::CANCELED  => 'Canceled',
                    OrderStatus::ACCEPTED  => 'To do',
                    OrderStatus::DOING     => 'Doing',
                    OrderStatus::DELIVERED => 'Delivered',
                    OrderStatus::FINISHED     => 'Finished',
                    OrderStatus::REVISION => 'Revision',
                },
            ])
            ->add('paid', CheckboxType::class, [
                'required' => false,
                'label' => 'Paid',
            ])
            ->add('createdAt', null, ['widget' => 'single_text'])
            ->add('updatedAt', null, ['widget' => 'single_text']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Order::class,
            'is_client'  => false,
            'for_edit'   => true, // NEW: edit by default; set to false in create action
        ]);
        $resolver->setAllowedTypes('is_client', 'bool');
        $resolver->setAllowedTypes('for_edit', 'bool');
    }
}
