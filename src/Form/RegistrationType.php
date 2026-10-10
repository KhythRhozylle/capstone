<?php

namespace App\Form;

use App\Entity\Campus;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class)
            ->add('lastName', TextType::class)
            ->add('email', EmailType::class)
            ->add('campus', EntityType::class, [
                'class' => Campus::class,
                'choice_label' => 'name',
            ])
            ->add('studentNumber', TextType::class, ['mapped' => false, 'constraints' => [new Assert\NotBlank()]])
            ->add('program', TextType::class, ['mapped' => false, 'constraints' => [new Assert\NotBlank()]])
            ->add('college', TextType::class, ['mapped' => false, 'constraints' => [new Assert\NotBlank()]])
            ->add('yearLevel', TextType::class, ['mapped' => false, 'constraints' => [new Assert\NotBlank()]])
            ->add('contactNumber', TelType::class, ['mapped' => false, 'constraints' => [new Assert\NotBlank()]])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => ['label' => 'Password'],
                'second_options' => ['label' => 'Repeat password'],
                'constraints' => [new Assert\NotBlank(), new Assert\Length(min: 8)],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
