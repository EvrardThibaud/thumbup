<?php

namespace App\Form;

use App\Entity\Client;
use App\Entity\Order;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use App\Enum\OrderStatus;

class OrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title')
            ->add('brief')
            ->add('price')
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'To do'      => OrderStatus::TODO,
                    'Doing'      => OrderStatus::DOING,
                    'Delivered'  => OrderStatus::DELIVERED,
                    'Paid'       => OrderStatus::PAID,
                ],
                'choice_label' => fn ($choice) => match($choice) {
                    OrderStatus::TODO => 'À faire',
                    OrderStatus::DOING => 'En cours',
                    OrderStatus::DELIVERED => 'Livré',
                    OrderStatus::PAID => 'Payé',
                },
            ])
            ->add('dueAt', null, [
                'widget' => 'single_text',
            ])
            ->add('createdAt', null, [
                'widget' => 'single_text',
            ])
            ->add('updatedAt', null, [
                'widget' => 'single_text',
            ])
            ->add('client', EntityType::class, [
                'class' => Client::class,
                'choice_label' => 'name',
                'placeholder' => 'Sélectionner un client',
                'autocomplete' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Order::class,
        ]);
    }
}
