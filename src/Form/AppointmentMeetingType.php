<?php

namespace App\Form;

use App\Entity\Appointment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AppointmentMeetingType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('meetingUrl', UrlType::class, [
            'label' => 'Virtual meeting URL',
            'default_protocol' => null,
            'attr' => ['placeholder' => 'https://meet.google.com/...', 'maxlength' => 255],
            'constraints' => [
                new Assert\NotBlank(),
                new Assert\Length(max: 255),
                new Assert\Url(protocols: ['https', 'http']),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Appointment::class]);
    }
}
