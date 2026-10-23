<?php

namespace App\Controller;

use App\Entity\OfficeActivity;
use App\Dto\ActivityQuery;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use App\Entity\User;
use App\Enum\OfficeProfile;
use App\Form\OfficeProfilePhotoType;
use App\Form\OfficeProfileSwitchType;
use App\Service\OfficeProfileContext;
use App\Service\OfficeProfilePhotoStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/vpsas/profile')]
#[IsGranted('ROLE_VPSAS')]
class OfficeProfileController extends AbstractController
{
    public function __construct(private readonly OfficeProfileContext $profiles)
    {
    }

    #[Route('', name: 'vpsas_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request, #[CurrentUser] User $user, OfficeProfilePhotoStorage $photos): Response
    {
        $this->denyAccessUnlessGrantedAccount($user);
        $profile = $this->profiles->current($user);
        $form = $this->createForm(OfficeProfilePhotoType::class, null, ['action' => $this->generateUrl('vpsas_profile'), 'csrf_token_id' => 'office_photo_'.$user->getId().'_'.$profile->value]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $photos->store($user, $profile, $form->get('photo')->getData());
                $this->addFlash('success', 'Profile photo updated for '.$profile->label().'.');

                return $this->redirectToRoute('vpsas_profile', status: Response::HTTP_SEE_OTHER);
            } catch (FileException) {
                $form->addError(new FormError('The photo could not be saved. Please try again.'));
            }
        }

        return $this->render('office-profile-panel' === $request->headers->get('Turbo-Frame') ? 'vpsas/_profile_frame.html.twig' : 'vpsas/profile.html.twig', ['form' => $form, 'selected' => $profile], new Response(status: $form->isSubmitted() ? 422 : 200));
    }

    #[Route('/switch', name: 'vpsas_profile_switch', methods: ['POST'])]
    public function switch(Request $request, #[CurrentUser] User $user): Response
    {
        $this->denyAccessUnlessGrantedAccount($user);
        $form = $this->createForm(OfficeProfileSwitchType::class);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            throw $this->createAccessDeniedException('Invalid profile selection.');
        }
        $this->profiles->select($user, OfficeProfile::from($form->get('profile')->getData()));

        return $this->redirectToRoute('vpsas_profile', status: Response::HTTP_SEE_OTHER);
    }

    #[Route('/activity', name: 'vpsas_profile_activity', methods: ['GET'])]
    public function activity(Request $request, #[CurrentUser] User $user, EntityManagerInterface $manager, #[MapQueryString] ActivityQuery $query = new ActivityQuery()): Response
    {
        $this->denyAccessUnlessGrantedAccount($user);

        return $this->render('office-profile-panel' === $request->headers->get('Turbo-Frame') ? 'vpsas/_profile_activity_frame.html.twig' : 'vpsas/profile_activity.html.twig', ['activities' => $manager->getRepository(OfficeActivity::class)->findBy(['accountId' => $user->getId()], ['id' => 'DESC'], 50, ($query->page - 1) * 50), 'page' => $query->page, 'pages' => max(1, (int) ceil($manager->getRepository(OfficeActivity::class)->count(['accountId' => $user->getId()]) / 50))]);
    }

    #[Route('/menu', name: 'vpsas_profile_menu', methods: ['GET'])]
    public function menu(#[CurrentUser] User $user): Response
    {
        $this->denyAccessUnlessGrantedAccount($user);

        return $this->render('vpsas/_profile_menu_frame.html.twig');
    }

    private function denyAccessUnlessGrantedAccount(User $user): void
    {
        if (!$this->profiles->supports($user)) {
            throw $this->createAccessDeniedException();
        }
    }
}
