<?php

namespace App\Form;

use App\Entity\DocumentRequest;
use App\Entity\SasUnit;
use App\Entity\User;
use App\Enum\DocumentType;
use App\Enum\RequestStatus;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class StaffDocumentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('student', EntityType::class, ['constraints' => [new Assert\NotNull()], 'setter' => static function (DocumentRequest $document, ?User $value): void { if (null !== $value) { $document->setStudent($value); } }, 'class' => User::class, 'choices' => $options['students'], 'choice_label' => static fn (User $student): string => $student->getFullName().' ('.$student->getEmail().')', 'placeholder' => 'Choose a student'])
            ->add('unit', EntityType::class, ['constraints' => [new Assert\NotNull()], 'setter' => static function (DocumentRequest $document, ?SasUnit $value): void { if (null !== $value) { $document->setUnit($value); } }, 'class' => SasUnit::class, 'choices' => $options['units'], 'choice_label' => 'name', 'label' => 'Issuing office', 'placeholder' => 'Choose an office'])
            ->add('documentType', EnumType::class, ['constraints' => [new Assert\NotNull()], 'setter' => static function (DocumentRequest $document, ?DocumentType $value): void { if (null !== $value) { $document->setDocumentType($value); } }, 'class' => DocumentType::class, 'choice_label' => static fn (DocumentType $type): string => $type->label()])
            ->add('purpose', TextareaType::class, ['empty_data' => '', 'attr' => ['rows' => 4]])
            ->add('status', EnumType::class, ['constraints' => [new Assert\NotNull()], 'setter' => static function (DocumentRequest $document, ?RequestStatus $value): void { if (null !== $value) { $document->setStatus($value); } }, 'class' => RequestStatus::class, 'choice_label' => static fn (RequestStatus $status): string => $status->label()]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => DocumentRequest::class]);
        $resolver->setRequired(['students', 'units']);
        $resolver->setAllowedTypes('students', 'array');
        $resolver->setAllowedTypes('units', 'array');
    }
}
