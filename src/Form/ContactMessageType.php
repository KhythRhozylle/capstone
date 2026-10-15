<?php

namespace App\Form;

use App\Dto\ContactMessage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContactMessageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Full name', 'attr' => ['placeholder' => 'Juan Dela Cruz', 'autocomplete' => 'name']])
            ->add('email', EmailType::class, ['label' => 'Email', 'attr' => ['placeholder' => 'you@norsu.edu.ph', 'autocomplete' => 'email']])
            ->add('phone', TelType::class, ['label' => 'Phone number', 'required' => false, 'attr' => ['placeholder' => '0917 123 4567', 'autocomplete' => 'tel']])
            ->add('message', TextareaType::class, ['label' => 'Message', 'attr' => ['placeholder' => 'How can we help?', 'rows' => 5]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ContactMessage::class]);
    }
}
