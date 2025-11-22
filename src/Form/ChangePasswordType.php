<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Mot de passe actuel
            ->add('currentPassword', PasswordType::class, [
                'label'    => false,
                'mapped'   => false,
                'attr'     => [
                    'autocomplete' => 'current-password',
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Please enter your current password.',
                    ]),
                ],
            ])

            // Nouveau mot de passe
            ->add('newPassword', PasswordType::class, [
                'label'    => false,
                'mapped'   => false,
                'attr'     => [
                    'autocomplete' => 'new-password',
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Please enter a new password.',
                    ]),
                    new Assert\Length([
                        'min'        => 8,
                        'minMessage' => 'Your new password should be at least {{ limit }} characters.',
                    ]),
                    new Assert\Regex([
                        'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).+$/',
                        'message' => 'Password must contain at least 1 uppercase letter, 1 lowercase letter, 1 number and 1 special character.',
                    ]),
                ],
            ])

            // Confirmation
            ->add('confirmNewPassword', PasswordType::class, [
                'label'    => false,
                'mapped'   => false,
                'attr'     => [
                    'autocomplete' => 'new-password',
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Please confirm your new password.',
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
