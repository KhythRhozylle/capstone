<?php

namespace App\Form;

use App\Entity\Appointment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints as Assert;

/** Moves an existing appointment to another open slot of the same unit. */
class AppointmentRescheduleType extends AbstractType
{
    public function __construct(private readonly UrlGeneratorInterface $urls)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Asia/Manila'));

        $builder
            ->add('scheduledAt', DateTimeType::class, [
                'label' => 'New date and time',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'view_timezone' => 'Asia/Manila',
                'attr' => ['min' => $today->format('Y-m-d').'T00:00', 'data-slot-picker-target' => 'when'],
                'constraints' => [new Assert\GreaterThanOrEqual(
                    value: $today,
                    message: 'Please choose today or a future date for your appointment.',
                )],
            ])
            ->add('note', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Reason (optional)',
                'constraints' => [new Assert\Length(max: 255)],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Appointment::class,
            'validation_groups' => ['Default', 'scheduling'],
            'attr' => ['data-controller' => 'slot-picker', 'data-slot-picker-url-value' => $this->urls->generate('appointment_slots')],
        ]);
    }
}
