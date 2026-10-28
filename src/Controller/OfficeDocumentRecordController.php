<?php

namespace App\Controller;

use App\Entity\OfficeDocumentRecord;
use App\Entity\User;
use App\Form\OfficeDocumentRecordType;
use App\Repository\OfficeDocumentRecordRepository;
use App\Service\DocumentRegisterPdf;
use App\Service\OfficeDocumentRegister;
use App\Service\TrackingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_STAFF')]
class OfficeDocumentRecordController extends AbstractController
{
    public function __construct(private readonly \App\Repository\OfficeDocumentColumnRepository $columns)
    {
    }

    #[Route('/staff/document-records/row', name: 'office_document_row', methods: ['GET'])]
    public function row(#[CurrentUser] User $user, OfficeDocumentRegister $register, \App\Service\OfficeDocumentRowForm $rowForms, #[\Symfony\Component\HttpKernel\Attribute\MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] \App\Dto\InsertRowsQuery $query = new \App\Dto\InsertRowsQuery()): Response
    {
        $rows = [];
        $columns = $this->columns->forOfficial($user);
        for ($i = 0; $i < $query->count; ++$i) {
            $record = $register->create($user);
            $form = $rowForms->create($record, bin2hex(random_bytes(8)));
            $rows[] = $this->renderView('staff/document_records/_row.html.twig', ['record' => $record, 'form' => $form->createView(), 'columns' => $columns]);
        }

        return $this->json(['html' => implode('', $rows), 'rows' => $rows]);
    }

    #[Route('/staff/document-records/columns/new', name: 'office_document_column_new', methods: ['POST'])]
    public function column(Request $request, #[CurrentUser] User $user, OfficeDocumentRegister $register, \Doctrine\ORM\EntityManagerInterface $manager, \Symfony\Component\Validator\Validator\ValidatorInterface $validator): Response
    {
        $column = new \App\Entity\OfficeDocumentColumn($register->create($user)->getUnit());
        $form = $this->createForm(\App\Form\OfficeDocumentColumnType::class, $column);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }

            return $this->json(['message' => implode(' ', $errors) ?: 'Enter a column name.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $count = (int) $form->get('count')->getData();
        $batch = [];
        for ($i = 1; $i <= $count; ++$i) {
            $entry = new \App\Entity\OfficeDocumentColumn($column->getUnit());
            $entry->setLabel(1 === $count ? $column->getLabel() : $column->getLabel().' '.$i);
            $errors = $validator->validate($entry);
            if (count($errors)) {
                $messages = [];
                foreach ($errors as $error) {
                    $messages[] = $error->getMessage();
                }

                return $this->json(['message' => implode(' ', $messages)], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $batch[] = $entry;
        }
        $manager->wrapInTransaction(static function () use ($manager, $batch): void {
            foreach ($batch as $entry) {
                $manager->persist($entry);
            }
        });
        $columns = array_map(static fn (\App\Entity\OfficeDocumentColumn $entry): array => ['id' => $entry->getId(), 'label' => $entry->getLabel(), 'unitId' => $entry->getUnit()->getId()], $batch);

        return $this->json($columns[0] + ['columns' => $columns], Response::HTTP_CREATED);
    }

    #[Route('/staff/document-records/new', name: 'office_document_new', methods: ['GET', 'POST'])]
    #[Route('/staff/document-records/{id}/edit', name: 'office_document_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function form(Request $request, #[CurrentUser] User $user, OfficeDocumentRecordRepository $records, OfficeDocumentRegister $register, \App\Service\OfficeDocumentRowForm $rowForms, ?int $id = null): Response
    {
        $record = null === $id ? $register->create($user) : $this->load($id, $user, $records, $register);
        $previous = null === $id ? null : $record->getStatus()->label();
        $inline = $request->query->getBoolean('inline');
        $form = $inline ? $rowForms->create($record, $request->query->get('row')) : $this->createForm(OfficeDocumentRecordType::class, $record);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $register->save($record, $user, $previous);
            if ($inline && 'json' === $request->getPreferredFormat()) {
                $saved = $rowForms->create($record)->createView();
                $blank = null === $id ? $register->create($user) : null;

                return $this->json([
                    'html' => $this->renderView('staff/document_records/_row.html.twig', ['record' => $record, 'form' => $saved, 'columns' => $this->columns->forOfficial($user)]),
                    'newRow' => $blank ? $this->renderView('staff/document_records/_row.html.twig', ['record' => $blank, 'form' => $rowForms->create($blank)->createView(), 'columns' => $this->columns->forOfficial($user)]) : null,
                ]);
            }
            if ($inline) {
                return $this->redirectToRoute('staff_documents', status: Response::HTTP_SEE_OTHER);
            }
            $this->addFlash('success', null === $id ? 'Office document recorded.' : 'Office document updated.');

            return $this->redirectToRoute('office_document_show', ['id' => $record->getId()], Response::HTTP_SEE_OTHER);
        }

        if ($inline && $form->isSubmitted() && 'json' === $request->getPreferredFormat()) {
            return $this->json(['html' => $this->renderView('staff/document_records/_row.html.twig', ['record' => $record, 'form' => $form->createView(), 'columns' => $this->columns->forOfficial($user)])], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->render('staff/document_records/form.html.twig', ['form' => $form, 'record' => $record, 'editing' => null !== $id]);
    }

    #[Route('/staff/document-records/{id}', name: 'office_document_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, #[CurrentUser] User $user, OfficeDocumentRecordRepository $records, OfficeDocumentRegister $register, TrackingService $tracking): Response
    {
        $record = $this->load($id, $user, $records, $register);

        return $this->render('staff/document_records/show.html.twig', ['record' => $record, 'history' => $tracking->history($record), 'columns' => $this->columns->forUnit($record->getUnit())]);
    }

    #[Route('/staff/document-records/{id}/delete', name: 'office_document_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request, #[CurrentUser] User $user, OfficeDocumentRecordRepository $records, OfficeDocumentRegister $register): Response
    {
        $record = $this->load($id, $user, $records, $register);
        if (!$this->isCsrfTokenValid('office-document-delete-'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $register->delete($record, $user);
        if ('json' === $request->getPreferredFormat()) {
            return $this->json(['deleted' => true]);
        }
        $this->addFlash('success', 'Office document deleted.');

        return $this->redirectToRoute('staff_documents', status: Response::HTTP_SEE_OTHER);
    }

    #[Route('/staff/document-records/print', name: 'office_documents_print', defaults: ['pdf' => false], methods: ['GET'])]
    #[Route('/staff/document-records/pdf', name: 'office_documents_pdf', defaults: ['pdf' => true], methods: ['GET'])]
    #[Route('/staff/document-records/{id}/print', name: 'office_document_print', requirements: ['id' => '\d+'], defaults: ['pdf' => false], methods: ['GET'])]
    #[Route('/staff/document-records/{id}/pdf', name: 'office_document_pdf', requirements: ['id' => '\d+'], defaults: ['pdf' => true], methods: ['GET'])]
    public function export(bool $pdf, #[CurrentUser] User $user, OfficeDocumentRecordRepository $records, OfficeDocumentRegister $register, DocumentRegisterPdf $renderer, ?int $id = null): Response
    {
        $rows = null === $id ? $records->forOfficial($user) : [$this->load($id, $user, $records, $register)];
        if (!$pdf) {
            return $this->render('staff/document_records/print.html.twig', ['records' => $rows, 'pdf' => false, 'columns' => $this->columns->forOfficial($user)]);
        }

        return new Response($renderer->render($rows, $this->columns->forOfficial($user)), headers: [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, null === $id ? 'office-document-register.pdf' : 'office-document-'.$id.'.pdf'),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function load(int $id, User $user, OfficeDocumentRecordRepository $records, OfficeDocumentRegister $register): OfficeDocumentRecord
    {
        $record = $records->find($id) ?? throw $this->createNotFoundException();
        $register->assertAccess($record, $user);

        return $record;
    }
}
