<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $profile = $options['profile'];
        $builder
            ->add('firstName', TextType::class)
            ->add('lastName', TextType::class)
            ->add('contactNumber', TelType::class, [
                'mapped' => false,
                'data' => $profile?->getContactNumber(),
            ])
            ->add('program', TextType::class, [
                'mapped' => false,
                'data' => $profile?->getProgram(),
            ])
            ->add('college', TextType::class, [
                'mapped' => false,
                'data' => $profile?->getCollege(),
            ])
            ->add('yearLevel', TextType::class, [
                'mapped' => false,
                'data' => $profile?->getYearLevel(),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'profile' => null,
        ]);
        $resolver->setAllowedTypes('profile', ['null', \App\Entity\StudentProfile::class]);
    }
}
