<?php

namespace App\Form;

use App\Entity\CareCase;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CareRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('reason', TextareaType::class, [
            'label' => 'Why are you requesting counseling or CARE support?',
            'attr' => ['rows' => 6],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CareCase::class]);
    }
}
