<?php

namespace App\Form;

use App\Entity\Client;
use App\Entity\Order;
use App\Enum\OrderStatus;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class OrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isClient = (bool) $options['is_client'];

        if ($isClient) {
            // CLIENT: only title, brief, dueAt (nothing else visible/editable)
            $builder
                ->add('title')
                ->add('brief')
                ->add('dueAt', null, ['widget' => 'single_text']);
            return;
        }

        // ADMIN (or non-client contexts): full form
        $builder
            ->add('title')
            ->add('brief')
            ->add('price', null, [
                // price stored in CENTS in the entity → 500 = €5
                'constraints' => [
                    new Assert\PositiveOrZero(),
                ],
                'help' => null,
            ])
            ->add('dueAt', null, ['widget' => 'single_text'])
            ->add('createdAt', null, ['widget' => 'single_text'])
            ->add('updatedAt', null, ['widget' => 'single_text']);

        // ADMIN: can choose client + status
        $builder
            ->add('client', EntityType::class, [
                'class' => Client::class,
                'placeholder' => 'Select a client',
                'choice_label' => 'name',
            ])
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'To do'     => OrderStatus::CREATED,
                    'Doing'     => OrderStatus::DOING,
                    'Delivered' => OrderStatus::DELIVERED,
                    'Paid'      => OrderStatus::CREATED,
                ],
                'choice_label' => fn ($choice) => match ($choice) {
                    OrderStatus::CREATED => 'To do',
                    OrderStatus::DOING => 'Doing',
                    OrderStatus::DELIVERED => 'Delivered',
                    OrderStatus::CREATED => 'Paid',
                },
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Order::class,
            'is_client'  => false, // custom option
        ]);
    }
}
