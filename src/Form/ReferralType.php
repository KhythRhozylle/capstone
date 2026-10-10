<?php

namespace App\Form;

use App\Entity\SasUnit;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReferralType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('unit', EntityType::class, [
                'class' => SasUnit::class,
                'choice_label' => 'name',
                'label' => 'Refer to unit',
            ])
            ->add('note', TextareaType::class, [
                'required' => false,
                'attr' => ['rows' => 3],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
