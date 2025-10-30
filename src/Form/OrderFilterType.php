<?php

namespace App\Form;

use App\Entity\Client;
use App\Enum\OrderStatus;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class OrderFiltersType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isClient = (bool) $options['is_client'];

        $builder
            ->add('q', SearchType::class, ['required' => false, 'label' => 'Search'])
            ->add('status', ChoiceType::class, [
                'required' => false,
                'placeholder' => 'Any status',
                'choices' => [
                    'Created' => OrderStatus::CREATED->value,
                    'Refused' => OrderStatus::REFUSED->value,
                    'Canceled' => OrderStatus::CANCELED->value,
                    'Accepted / To do' => OrderStatus::ACCEPTED->value,
                    'Doing' => OrderStatus::DOING->value,
                    'Delivered' => OrderStatus::DELIVERED->value,
                ],
            ])
            ->add('paid', ChoiceType::class, [
                'required' => false,
                'placeholder' => 'Any',
                'label' => 'Paid',
                'choices' => [
                    'Yes' => 'yes',
                    'No'  => 'no',
                ],
            ])
            ->add('from', DateTimeType::class, ['required' => false, 'widget' => 'single_text', 'label' => 'From'])
            ->add('to',   DateTimeType::class, ['required' => false, 'widget' => 'single_text', 'label' => 'To']);

        if (!$isClient) {
            $builder->add('client', EntityType::class, [
                'class' => Client::class,
                'required' => false,
                'placeholder' => 'Any client',
                'choice_label' => 'name',
            ]);
        } else {
            $builder->add('client', EntityType::class, [
                'class' => Client::class,
                'required' => false,
                'disabled' => true,
                'choice_label' => 'name',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
            'is_client' => false,
        ]);
    }
}
