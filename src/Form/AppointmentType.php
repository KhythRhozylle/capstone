<?php

namespace App\Form;

use App\Entity\Appointment;
use App\Entity\SasUnit;
use App\Enum\AppointmentMode;
use App\Repository\SasUnitRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AppointmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('unit', EntityType::class, [
                'class' => SasUnit::class,
                'choice_label' => 'name',
                'query_builder' => static fn (SasUnitRepository $repo) => $repo->createQueryBuilder('u')
                    ->andWhere('u.handlesAppointments = true')
                    ->orderBy('u.name', 'ASC'),
                'label' => 'SAS unit',
            ])
            ->add('mode', EnumType::class, [
                'class' => AppointmentMode::class,
                'choice_label' => static fn (AppointmentMode $mode) => $mode->label(),
            ])
            ->add('scheduledAt', DateTimeType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('meetingUrl', UrlType::class, [
                'required' => false,
                'label' => 'Virtual meeting URL',
            ])
            ->add('purpose', TextareaType::class, [
                'attr' => ['rows' => 4],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Appointment::class,
        ]);
    }
}
