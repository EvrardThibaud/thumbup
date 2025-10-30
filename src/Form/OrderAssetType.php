<?php
namespace App\Form;

use App\Entity\OrderAsset;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class OrderAssetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('file', FileType::class, [
            'label' => 'Upload thumbnail',
            'mapped' => false, // on mappe manuellement vers setFile()
            'required' => true,
            'constraints' => [
                new Assert\File([
                    'maxSize' => '10M',
                    'mimeTypes' => ['image/png','image/jpeg','image/webp'],
                    'mimeTypesMessage' => 'PNG, JPG or WEBP only (max 10MB).',
                ]),
            ],
            'attr' => ['accept' => 'image/png,image/jpeg,image/webp'],
        ]);
    }
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => OrderAsset::class]);
    }
}
