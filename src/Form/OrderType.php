<?php
// src/Form/OrderType.php — FIX submit (no HTML5 block): use HiddenType for price, remove price_euros field

namespace App\Form;

use App\Entity\Client;
use App\Entity\Order;
use App\Enum\OrderStatus;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class OrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $opt): void
    {
        $isClient = (bool)$opt['is_client'];
        $minDueAt = $opt['min_due_at']; // 'Y-m-d\TH:i' or null
        $userTz = $options['user_timezone'] ?? 'Europe/Paris';

        $b->add('title', TextType::class, [
                'label' => '📝 Title',
                'required' => true,
                'attr' => ['placeholder' => 'e.g., “MrBeast – Haunted House thumbnail”', 'autocomplete' => 'off'],
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max:255)],
            ])
          ->add('brief', TextareaType::class, [
                'label' => '📄 Brief',
                'required' => true,
                'attr' => ['placeholder' => 'Concept, colors, references, overlay text…', 'rows' => 6],
                'constraints' => [new Assert\NotBlank()],
            ])
          ->add('dueAt', DateTimeType::class, [
                'label' => '⏰ Due at',
                'widget' => 'single_text',
                'view_timezone' => $userTz,
                'required' => true,
                'html5' => true,
                'attr' => $minDueAt ? ['min' => $minDueAt] : [],
            ])
          // REAL stored price in cents — hidden so HTML5 "required" won't block submit
          ->add('price', HiddenType::class, [
                'constraints' => [new Assert\NotBlank(), new Assert\GreaterThanOrEqual(500)],
            ])
          // Optional attachments
          ->add('attachments', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => '📎 Attach files (PDF or images)',
                'multiple' => true,
                'attr' => ['accept' => 'application/pdf,image/png,image/jpeg,image/webp'],
                'constraints' => [
                    new Assert\All([
                        new Assert\File(maxSize: '25M', mimeTypes: [
                            'application/pdf', 'image/png', 'image/jpeg', 'image/webp',
                        ]),
                    ]),
                ],
            ]);

        if (!$isClient) {
            $b->add('client', EntityType::class, [
                    'class' => Client::class,
                    'label' => '👤 Client',
                    'placeholder' => 'Select a client…',
                    'required' => true,
                ])
              ->add('status', ChoiceType::class, [
                    'label' => '🏷️ Status',
                    'required' => true,
                    'choices' => [
                        'Created' => OrderStatus::CREATED,
                        'Refused' => OrderStatus::REFUSED,
                        'Canceled' => OrderStatus::CANCELED,
                        'Accepted' => OrderStatus::ACCEPTED,
                        'Doing' => OrderStatus::DOING,
                        'Delivered' => OrderStatus::DELIVERED,
                        'Revision' => OrderStatus::REVISION,
                        'Finished' => OrderStatus::FINISHED,
                    ],
                ])
              ->add('paid', CheckboxType::class, [
                    'label' => 'Paid',
                    'required' => false,
                ]);
        }
    }

    public function configureOptions(OptionsResolver $r): void
    {
        $r->setDefaults([
            'data_class' => Order::class,
            'user_timezone' => 'Europe/Paris',
            'is_client'  => false,
            'for_edit'   => false,
            'min_due_at' => null,
        ]);
        $r->setAllowedTypes('is_client', 'bool');
        $r->setAllowedTypes('for_edit', 'bool');
        $r->setAllowedTypes('min_due_at', ['null','string']);
    }
}
