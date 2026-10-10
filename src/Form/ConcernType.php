<?php

namespace App\Form;

use App\Entity\Concern;
use App\Enum\ConcernType as ConcernTypeEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConcernType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', EnumType::class, [
                'class' => ConcernTypeEnum::class,
                'choice_label' => static fn (ConcernTypeEnum $type) => $type->label(),
                'label' => 'Kind',
            ])
            ->add('subject', TextType::class)
            ->add('respondents', TextType::class, [
                'required' => false,
                'label' => 'Person / office involved (for complaints)',
            ])
            ->add('description', TextareaType::class, ['attr' => ['rows' => 6]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Concern::class]);
    }
}
