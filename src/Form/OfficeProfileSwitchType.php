<?php

namespace App\Form;

use App\Enum\OfficeProfile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OfficeProfileSwitchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $choices = [];
        foreach (OfficeProfile::cases() as $profile) {
            $choices[$profile->label()] = $profile->value;
        }
        $builder->add('profile', ChoiceType::class, ['choices' => $choices]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['csrf_token_id' => 'switch_office_profile']);
    }
}
