<?php

namespace App\Form;

use App\Entity\Appointment;
use App\Entity\SasUnit;
use App\Enum\AppointmentMode;
use App\Repository\SasUnitRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints as Assert;

class AppointmentType extends AbstractType
{
    /** What the visit is about; helps the official prepare before the appointment. */
    public const TOPICS = [
        'Academic or class concern',
        'Scholarship or financial assistance',
        'Student organization or activity',
        'Discipline or conduct',
        'Health or well-being',
        'Documents or requirements',
        'Other',
    ];

    public function __construct(private readonly UrlGeneratorInterface $urls)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Asia/Manila'));

        $builder
            ->add('unit', EntityType::class, [
                'class' => SasUnit::class,
                'choice_label' => 'name',
                'query_builder' => static function (SasUnitRepository $repo) use ($options) {
                    $query = $repo->createQueryBuilder('u')
                        ->andWhere('u.handlesAppointments = true')
                        ->orderBy('u.name', 'ASC');
                    if ($options['locked_unit'] instanceof SasUnit) {
                        $query->andWhere('u = :locked')->setParameter('locked', $options['locked_unit']);
                    }

                    return $query;
                },
                'label' => 'SAS unit',
                'attr' => ['data-slot-picker-target' => 'unit'],
            ])
            ->add('mode', EnumType::class, [
                'class' => AppointmentMode::class,
                'choice_label' => static fn (AppointmentMode $mode) => $mode->label(),
            ])
            ->add('scheduledAt', DateTimeType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'view_timezone' => 'Asia/Manila',
                'attr' => ['min' => $today->format('Y-m-d').'T00:00', 'data-slot-picker-target' => 'when', 'data-booking-calendar-target' => 'when'],
                'help' => 'Pick a date, then choose one of the open time slots shown below it.',
                'constraints' => [new Assert\GreaterThanOrEqual(
                    value: $today,
                    message: 'Please choose today or a future date for your appointment.',
                )],
            ])
            ->add('purpose', TextareaType::class, [
                'label' => $options['detailed'] ? 'What would you like to discuss?' : 'Purpose',
                'attr' => ['rows' => 4],
            ]);

        if ($options['detailed']) {
            $builder
                ->add('topic', ChoiceType::class, [
                    'choices' => array_combine(self::TOPICS, self::TOPICS),
                    'placeholder' => 'Choose a topic',
                    'label' => 'Topic',
                    'constraints' => [new Assert\NotBlank(message: 'Please choose what your visit is about.')],
                ])
                ->add('contactNumber', TelType::class, [
                    'required' => false,
                    'label' => 'Contact number (optional)',
                    'help' => 'The office may use this if the schedule changes.',
                    'attr' => ['autocomplete' => 'tel'],
                ])
                ->add('accommodationNote', TextareaType::class, [
                    'required' => false,
                    'label' => 'Anything the office should prepare or accommodate? (optional)',
                    'attr' => ['rows' => 3, 'maxlength' => 1000],
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Appointment::class,
            'detailed' => false,
            'locked_unit' => null,
            'validation_groups' => ['Default', 'scheduling'],
            'attr' => [
                'data-controller' => 'slot-picker',
                'data-slot-picker-url-value' => $this->urls->generate('appointment_slots'),
            ],
        ]);
        $resolver->setAllowedTypes('detailed', 'bool');
        $resolver->setAllowedTypes('locked_unit', ['null', SasUnit::class]);
    }
}
