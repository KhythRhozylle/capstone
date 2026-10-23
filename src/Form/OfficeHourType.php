<?php

namespace App\Form;

use App\Entity\OfficeHour;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OfficeHourType extends AbstractType
{
    public const DAYS = [
        'Monday' => 1,
        'Tuesday' => 2,
        'Wednesday' => 3,
        'Thursday' => 4,
        'Friday' => 5,
        'Saturday' => 6,
        'Sunday' => 7,
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dayOfWeek', ChoiceType::class, ['label' => 'Day', 'choices' => self::DAYS])
            ->add('opensAt', TimeType::class, ['label' => 'Opens', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('closesAt', TimeType::class, ['label' => 'Closes', 'widget' => 'single_text', 'input' => 'datetime_immutable']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => OfficeHour::class]);
    }
}
