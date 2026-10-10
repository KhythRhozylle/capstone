<?php

namespace App\Form;

use App\Entity\DocumentRequest;
use App\Entity\SasUnit;
use App\Enum\DocumentType;
use App\Repository\SasUnitRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DocumentRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('unit', EntityType::class, [
                'class' => SasUnit::class,
                'choice_label' => 'name',
                'query_builder' => static fn (SasUnitRepository $repo) => $repo->createQueryBuilder('u')
                    ->andWhere('u.handlesDocuments = true')
                    ->orderBy('u.name', 'ASC'),
                'label' => 'Issuing SAS unit',
            ])
            ->add('documentType', EnumType::class, [
                'class' => DocumentType::class,
                'choice_label' => static fn (DocumentType $type) => $type->label(),
            ])
            ->add('purpose', TextareaType::class, ['attr' => ['rows' => 4]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => DocumentRequest::class]);
    }
}
