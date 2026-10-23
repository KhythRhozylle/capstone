<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $profile = $options['profile'];
        $builder
            ->add('firstName', TextType::class, ['constraints' => [new Assert\NotBlank(), new Assert\Length(max: 120)]])
            ->add('lastName', TextType::class, ['constraints' => [new Assert\NotBlank(), new Assert\Length(max: 120)]]);
        if (null === $profile) {
            return;
        }
        $builder
            ->add('contactNumber', TelType::class, [
                'mapped' => false,
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 32)],
                'data' => $profile?->getContactNumber(),
            ])
            ->add('program', TextType::class, [
                'mapped' => false,
                'data' => $profile?->getProgram(),
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 180)],
            ])
            ->add('college', TextType::class, [
                'mapped' => false,
                'data' => $profile?->getCollege(),
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 180)],
            ])
            ->add('yearLevel', TextType::class, [
                'mapped' => false,
                'data' => $profile?->getYearLevel(),
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 32)],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'profile' => null,
        ]);
        $resolver->setAllowedTypes('profile', ['null', \App\Entity\StudentProfile::class]);
    }
}
