<?php

namespace App\Form;

use App\Entity\Order;
use App\Entity\TimeEntry;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TimeEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('minutes')
            ->add('note', null, ['required' => false])
            ->add('createdAt', null, [
                'widget' => 'single_text',
            ])
            ->add('relatedOrder', EntityType::class, [
                'class' => Order::class,
                'choice_label' => 'title',
                'placeholder' => 'Sélectionner une commande',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TimeEntry::class,
        ]);
    }
}
