<?php

namespace App\Form;

use App\Entity\OfficeUnavailable;
use App\Repository\OfficeUnavailableRepository;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class OfficeUnavailableType extends AbstractType
{
    public function __construct(private readonly OfficeUnavailableRepository $unavailable)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [];
        for ($minute = 0; $minute <= 1440; $minute += 30) {
            $label = 1440 === $minute ? '12:00 AM (next day)' : (new \DateTimeImmutable('today'))->setTime(intdiv($minute, 60), $minute % 60)->format('g:i A');
            $choices[$label] = $minute;
        }
        $builder->add('startMinute', ChoiceType::class, ['label' => 'From', 'choices' => array_filter($choices, static fn (int $m) => $m < 1440), 'required' => false, 'empty_data' => '0', 'placeholder' => false]);
        $builder->add('endMinute', ChoiceType::class, ['label' => 'Until', 'choices' => array_filter($choices, static fn (int $m) => $m > 0), 'required' => false, 'empty_data' => '1440', 'placeholder' => false]);
        $builder->add('day', DateType::class, ['label' => 'Unavailable date', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'constraints' => [new Assert\NotBlank(), new Assert\GreaterThanOrEqual(new \DateTimeImmutable('today'))]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => OfficeUnavailable::class, 'constraints' => [new Assert\Callback(function (OfficeUnavailable $entry, ExecutionContextInterface $context): void {
            if ($entry->getEndMinute() <= $entry->getStartMinute()) {
                $context->buildViolation('The end time must be after the start time.')->atPath('endMinute')->addViolation();
            } elseif ($this->unavailable->overlaps($entry->getUnit(), $entry->getDay(), $entry->getStartMinute(), $entry->getEndMinute())) {
                $context->buildViolation('This period is already unavailable.')->addViolation();
            }
        })]]);
    }
}
