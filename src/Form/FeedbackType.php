<?php

namespace App\Form;

use App\Entity\Feedback;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FeedbackType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('rating', ChoiceType::class, [
                'label' => 'Your rating',
                'expanded' => true,
                'choices' => ['5 - Excellent' => 5, '4 - Good' => 4, '3 - Fair' => 3, '2 - Poor' => 2, '1 - Very poor' => 1],
                'placeholder' => false,
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'Comments (optional)',
                'required' => false,
                'attr' => ['rows' => 3, 'maxlength' => 1000, 'placeholder' => 'Tell us what went well or what we can improve.'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Feedback::class]);
    }
}
