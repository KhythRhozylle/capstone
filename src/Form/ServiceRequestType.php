<?php

namespace App\Form;

use App\Entity\SasUnit;
use App\Entity\ServiceRequest;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ServiceRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('unit', EntityType::class, [
                'class' => SasUnit::class,
                'choice_label' => 'name',
                'label' => 'SAS unit',
            ])
            ->add('subject', TextType::class)
            ->add('description', TextareaType::class, ['attr' => ['rows' => 5]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ServiceRequest::class]);
    }
}
