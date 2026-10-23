<?php

namespace App\Controller;

use App\Entity\DocumentRequest;
use App\Entity\SasUnit;
use App\Entity\User;
use App\Form\StaffDocumentType;
use App\Repository\DocumentRequestRepository;
use App\Repository\SasUnitRepository;
use App\Repository\UserRepository;
use App\Service\DocumentRecords;
use App\Service\StaffScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_STAFF')]
class StaffDocumentController extends AbstractController
{
    #[Route('/staff/documents/new', name: 'staff_document_new', methods: ['GET', 'POST'])]
    #[Route('/staff/documents/{id}/edit', name: 'staff_document_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function form(Request $request, #[CurrentUser] User $user, DocumentRequestRepository $documents, UserRepository $users, SasUnitRepository $units, DocumentRecords $records, ?int $id = null): Response
    {
        $scope = StaffScope::for($user);
        $document = null === $id ? new DocumentRequest() : $this->load($documents, $id, $scope);
        $students = array_values(array_filter($users->findByRole('ROLE_STUDENT'), static fn (User $student): bool => null !== $student->getCampus() && (null === $scope->campus || $scope->campus->getId() === $student->getCampus()->getId())));
        $offices = array_values(array_filter($units->findBy([], ['name' => 'ASC']), static fn (SasUnit $unit): bool => $scope->unrestricted || ($scope->directorateCode ? $unit->getDirectorate()->getCode() === $scope->directorateCode && 'VPSAS' !== $unit->getCode() : $scope->unit?->getId() === $unit->getId())));
        $previous = null === $id ? null : $document->getStatus()->label();
        $form = $this->createForm(StaffDocumentType::class, $document, ['students' => $students, 'units' => $offices]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if (!$scope->allows($document->getStudent()->getCampus(), $document->getUnit())) {
                throw $this->createAccessDeniedException();
            }
            $records->save($document, $user, $previous);
            $this->addFlash('success', null === $id ? 'Document created.' : 'Document updated.');

            return $this->redirectToRoute('staff_document_show', ['id' => $document->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('staff/documents/form.html.twig', ['form' => $form, 'document' => $document, 'editing' => null !== $id]);
    }

    #[Route('/staff/documents/{id}/delete', name: 'staff_document_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request, #[CurrentUser] User $user, DocumentRequestRepository $documents, DocumentRecords $records): Response
    {
        $document = $this->load($documents, $id, StaffScope::for($user));
        if (!$this->isCsrfTokenValid('document-delete-'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $records->delete($document, $user);
        $this->addFlash('success', 'Document deleted.');

        return $this->redirectToRoute('staff_documents', status: Response::HTTP_SEE_OTHER);
    }

    private function load(DocumentRequestRepository $documents, int $id, StaffScope $scope): DocumentRequest
    {
        $document = $documents->find($id) ?? throw $this->createNotFoundException();
        if (!$scope->allows($document->getCampus(), $document->getUnit())) {
            throw $this->createAccessDeniedException();
        }

        return $document;
    }
}
