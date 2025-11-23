<?php
namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Image;

final class OrderAssetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('file', FileType::class, [
            'mapped' => false,
            'multiple' => true,
            'required' => true,
            'attr' => ['accept' => 'image/*'],  
            'constraints' => [
                new All([
                    'constraints' => [
                        new Image([
                            'maxSize' => '20M',
                            'mimeTypesMessage' => 'Please upload image files only.',
                        ]),
                    ],
                ]),
            ],
        ]);
    }
}
