<?php
namespace App\Form;

use App\Entity\Client;
use App\Enum\OrderStatus;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SearchType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class OrderFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $o): void
    {
        $b
        ->add('q', SearchType::class, [
            'label' => 'Search',
            'required' => false,
        ])
        ->add('client', EntityType::class, [
            'label' => 'Client',
            'class' => Client::class,
            'choice_label' => 'name',
            'required' => false,
            'autocomplete' => true,
        ])
        ->add('status', ChoiceType::class, [
            'label' => 'Status',
            'required' => false,
            'choices' => [
                'To do' => OrderStatus::CREATED,
                'Doing' => OrderStatus::DOING,
                'Delivered' => OrderStatus::DELIVERED,
                'Paid' => OrderStatus::CREATED,
            ],
        ])
        ->add('from', DateType::class, [
            'label' => 'From',
            'widget' => 'single_text',
            'required' => false,
        ])
        ->add('to', DateType::class, [
            'label' => 'To',
            'widget' => 'single_text',
            'required' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'csrf_protection' => false, // GET, no CSRF
        ]);
    }
}
