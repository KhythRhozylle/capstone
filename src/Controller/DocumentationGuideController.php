<?php

namespace App\Controller;

use App\Entity\DocumentationGuide;
use App\Entity\User;
use App\Form\DocumentationGuideType;
use App\Repository\DocumentationGuideRepository;
use App\Security\DocumentationGuideVoter;
use App\Service\DocumentationGuidePublisher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/documentation')]
#[IsGranted(DocumentationGuideVoter::VIEW)]
class DocumentationGuideController extends AbstractController
{
    #[Route('', name: 'documentation_index', methods: ['GET'])]
    public function index(DocumentationGuideRepository $guides): Response
    {
        return $this->render('documentation/index.html.twig', [
            'guides' => $guides->findBy([], ['updatedAt' => 'DESC', 'id' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'documentation_new', methods: ['GET', 'POST'])]
    #[IsGranted(DocumentationGuideVoter::MANAGE)]
    public function create(Request $request, #[CurrentUser] User $editor, DocumentationGuidePublisher $publisher): Response
    {
        return $this->editForm(new DocumentationGuide(), $request, $editor, $publisher);
    }

    #[Route('/{id}', name: 'documentation_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(DocumentationGuide $guide): Response
    {
        return $this->render('documentation/show.html.twig', ['guide' => $guide]);
    }

    #[Route('/{id}/edit', name: 'documentation_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(DocumentationGuideVoter::MANAGE)]
    public function edit(DocumentationGuide $guide, Request $request, #[CurrentUser] User $editor, DocumentationGuidePublisher $publisher): Response
    {
        return $this->editForm($guide, $request, $editor, $publisher);
    }

    #[Route('/{id}/delete', name: 'documentation_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(DocumentationGuideVoter::MANAGE)]
    public function delete(DocumentationGuide $guide, Request $request, DocumentationGuidePublisher $publisher): Response
    {
        if (!$this->isCsrfTokenValid('delete_guide_'.$guide->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        $publisher->delete($guide);
        $this->addFlash('success', 'Documentation guide deleted.');

        return $this->redirectToRoute('documentation_index', status: Response::HTTP_SEE_OTHER);
    }

    private function editForm(DocumentationGuide $guide, Request $request, User $editor, DocumentationGuidePublisher $publisher): Response
    {
        $form = $this->createForm(DocumentationGuideType::class, $guide);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $publisher->save($guide, $editor);
            $this->addFlash('success', 'Documentation guide published.');

            return $this->redirectToRoute('documentation_show', ['id' => $guide->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('documentation/form.html.twig', ['guide' => $guide, 'form' => $form]);
    }
}
