<?php

namespace App\Form;

use App\Entity\CareFollowUp;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FollowUpType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dueAt', DateTimeType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('notes', TextareaType::class, ['attr' => ['rows' => 3]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CareFollowUp::class]);
    }
}
