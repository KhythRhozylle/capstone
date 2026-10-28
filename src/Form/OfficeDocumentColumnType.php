<?php

namespace App\Form;

use App\Entity\OfficeDocumentColumn;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OfficeDocumentColumnType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('label', TextType::class, ['label' => 'Column name', 'empty_data' => '', 'attr' => ['maxlength' => 80, 'placeholder' => 'e.g. Reference number']]);
        $builder->add('count', ChoiceType::class, ['label' => 'Number of columns', 'choices' => array_combine(range(1, 10), range(1, 10)), 'mapped' => false, 'data' => 1, 'empty_data' => '1', 'help' => 'Multiple columns use numbered names, for example Reference 1, Reference 2.']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => OfficeDocumentColumn::class, 'csrf_token_id' => 'office_document_column']);
    }
}
