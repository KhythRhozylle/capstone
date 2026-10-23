<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class OfficeProfilePhotoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('photo', FileType::class, [
            'label' => 'Profile photo',
            'mapped' => false,
            'attr' => ['accept' => 'image/jpeg,image/png,image/webp'],
            'help' => 'JPG, PNG or WebP. Up to 2 MB and 6000 × 6000 pixels.',
            'constraints' => [new Assert\NotNull(), new Assert\Image(maxSize: '2M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'], maxWidth: 6000, maxHeight: 6000)],
        ]);
    }
}
