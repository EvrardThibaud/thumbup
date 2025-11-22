<?php
// src/Form/ResetPasswordType.php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Validator\Constraints as Assert;

class ResetPasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Exemple à appliquer sur ton form de reset (plainPassword RepeatedType)

        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'first_options'  => [
                'label' => 'New password',
            ],
            'second_options' => [
                'label' => 'Repeat password',
            ],
            'invalid_message' => 'The password fields must match.',
            'mapped' => false,
            'constraints' => [
                new Assert\NotBlank([
                    'message' => 'Please enter a new password.',
                ]),
                new Assert\Length([
                    'min' => 8,
                    'minMessage' => 'Your password must be at least {{ limit }} characters long.',
                ]),
                new Assert\Regex([
                    'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).+$/',
                    'message' => 'Password must contain at least 1 uppercase letter, 1 lowercase letter, 1 number and 1 special character.',
                ]),
            ],
        ]);

    }
}
