<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $opts): void
    {
        $b->add('email', EmailType::class, [
            'label' => 'Email',
        ])->add('plainPassword', PasswordType::class, [
            'mapped' => false,
            'label'  => 'Password',
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
