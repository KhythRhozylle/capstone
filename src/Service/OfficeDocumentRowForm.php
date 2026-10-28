<?php

namespace App\Service;

use App\Entity\OfficeDocumentRecord;
use App\Form\OfficeDocumentRecordType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class OfficeDocumentRowForm
{
    public function __construct(private readonly FormFactoryInterface $forms, private readonly UrlGeneratorInterface $urls)
    {
    }

    public function create(OfficeDocumentRecord $record, ?string $rowKey = null): FormInterface
    {
        $id = $record->getId();
        if (null !== $rowKey && !preg_match('/^[a-f0-9]{16}$/D', $rowKey)) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Invalid row key.');
        }
        $name = 'office_record_'.($id ?? ('new'.($rowKey ? '_'.$rowKey : '')));

        return $this->forms->createNamed($name, OfficeDocumentRecordType::class, $record, [
            'action' => $this->urls->generate(null === $id ? 'office_document_new' : 'office_document_edit', array_filter(['id' => $id, 'inline' => 1, 'row' => null === $id ? $rowKey : null], static fn ($value) => null !== $value)),
            'attr' => ['id' => $name, 'data-action' => 'submit->document-sheet#save', 'data-turbo' => 'false'],
        ]);
    }
}
