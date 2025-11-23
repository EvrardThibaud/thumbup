<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $opts): void
    {
        $b->add('email', EmailType::class, [
            'label' => 'Email',
        ])->add('plainPassword', PasswordType::class, [
            'mapped' => false,
            'label'  => 'Password',
            'constraints' => [
                new Assert\NotBlank([
                    'message' => 'Please choose a password.',
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

        if (($opts['with_client_fields'] ?? true) === true) {
            $b->add('clientName', TextType::class, [
                'mapped' => false,
                'label'  => 'Client name',
            ])->add('channelUrl', TextType::class, [
                'mapped'   => false,
                'required' => false,
                'label'    => 'Channel URL (optional)',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $r): void
    {
        $r->setDefaults([
            'data_class' => User::class,
            'with_client_fields' => true,
        ]);
    }
}
