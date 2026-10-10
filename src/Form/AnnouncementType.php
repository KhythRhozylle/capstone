<?php

namespace App\Form;

use App\Entity\Announcement;
use App\Entity\Campus;
use App\Enum\AnnouncementScope;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AnnouncementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class)
            ->add('scope', EnumType::class, [
                'class' => AnnouncementScope::class,
                'choice_label' => static fn (AnnouncementScope $scope) => $scope->label(),
            ])
            ->add('campus', EntityType::class, [
                'class' => Campus::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'All campuses (university-wide)',
            ])
            ->add('body', TextareaType::class, ['attr' => ['rows' => 8]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Announcement::class]);
    }
}
