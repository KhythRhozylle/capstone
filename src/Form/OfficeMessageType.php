<?php

namespace App\Form;

use App\Entity\OfficeMessage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OfficeMessageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('body', TextareaType::class, ['label' => 'Message', 'required' => false, 'empty_data' => '', 'attr' => ['rows' => 3, 'maxlength' => 2000]]);
        $builder->add('photo', FileType::class, ['label' => 'Attach a photo', 'property_path' => 'photoUpload', 'required' => false, 'attr' => ['accept' => 'image/jpeg,image/png,image/webp']]);
        $builder->add('file', FileType::class, ['label' => 'Attach a file', 'property_path' => 'attachmentUpload', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => OfficeMessage::class]);
    }
}
