<?php

namespace App\Form;

use App\Entity\Campus;
use App\Entity\OfficeDocumentRecord;
use App\Enum\DocumentRecordStatus;
use App\Repository\CampusRepository;
use App\Entity\User;
use App\Service\OfficeProfileContext;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OfficeDocumentRecordType extends AbstractType
{
    public function __construct(private readonly Security $security, private readonly OfficeProfileContext $profiles, private readonly \App\Repository\OfficeDocumentColumnRepository $columns)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $official = $this->security->getUser();
        $actorName = $official instanceof User ? $this->profiles->actorName($official) : '';
        $record = $builder->getData();
        $receivedBy = null === $record->getId() ? $actorName : $record->getReceivedBy();
        $releasedBy = $record->getReleasedBy();
        $builder->setAttribute('actor_name', $actorName);
        $builder->setAttribute('new_record', null === $record->getId());
        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event) use ($actorName, $receivedBy, $releasedBy): void {
            $data = $event->getData();
            if (!is_array($data)) {
                return;
            }
            $data['receivedBy'] = $receivedBy;
            $releasing = !empty($data['releasedAt']) || 'released' === ($data['status'] ?? null);
            $data['releasedBy'] = $releasedBy ?: ($releasing ? $actorName : '');
            $event->setData($data);
        });
        $builder
            ->add('campus', EntityType::class, [
                'class' => Campus::class,
                'choice_label' => 'name',
                'query_builder' => static fn (CampusRepository $repository) => $repository->createQueryBuilder('c')->orderBy('c.name', 'ASC'),
                'placeholder' => 'Choose a campus',
            ])
            ->add('particular', TextareaType::class, ['label' => 'Particular', 'empty_data' => '', 'attr' => ['rows' => 3, 'maxlength' => 2000]])
            ->add('amount', MoneyType::class, ['currency' => 'PHP', 'input' => 'string', 'scale' => 2, 'empty_data' => '0', 'attr' => ['inputmode' => 'decimal']])
            ->add('receivedAt', DateType::class, ['label' => 'Date received', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('receivedBy', TextType::class, ['label' => 'Received by', 'empty_data' => '', 'attr' => ['maxlength' => 180, 'readonly' => true]])
            ->add('releasedAt', DateType::class, ['label' => 'Date released', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'required' => false])
            ->add('releasedBy', TextType::class, ['label' => 'Released by', 'required' => false, 'attr' => ['maxlength' => 180, 'readonly' => true]])
            ->add('status', EnumType::class, ['class' => DocumentRecordStatus::class, 'choice_label' => static fn (DocumentRecordStatus $status): string => $status->label()]);
        $columns = $this->columns->forUnit($record->getUnit());
        foreach ($columns as $column) {
            $builder->add('column_'.$column->getId(), TextType::class, [
                'label' => $column->getLabel(),
                'mapped' => false,
                'required' => false,
                'data' => $record->getExtraData()[$column->getId()] ?? '',
                'constraints' => [new \Symfony\Component\Validator\Constraints\Length(max: 2000)],
                'attr' => ['maxlength' => 2000],
            ]);
        }
        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event) use ($columns, $record): void {
            $values = $record->getExtraData();
            foreach ($columns as $column) {
                $values[$column->getId()] = $event->getForm()->get('column_'.$column->getId())->getData() ?? '';
            }
            $record->setExtraData($values);
        });
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $attributes = $view->vars['attr'];
        $attributes['data-controller'] = trim(($attributes['data-controller'] ?? '').' office-document-identity');
        $attributes['data-action'] = trim(($attributes['data-action'] ?? '').' change@document->office-document-identity#update office-profile:changed@window->office-document-identity#profileChanged');
        $attributes['data-office-document-identity-actor-value'] = $form->getConfig()->getAttribute('actor_name');
        $attributes['data-office-document-identity-new-value'] = $form->getConfig()->getAttribute('new_record') ? 'true' : 'false';
        $view->vars['attr'] = $attributes;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => OfficeDocumentRecord::class, 'csrf_token_id' => 'office_document_record']);
    }
}
