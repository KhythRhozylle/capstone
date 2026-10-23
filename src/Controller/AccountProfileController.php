<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\OfficeProfilePhotoType;
use App\Form\ProfileType;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\OfficeProfileContext;
use App\Service\OfficeProfilePhotoStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class AccountProfileController extends AbstractController
{
    #[Route('/profile', name: 'account_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request, FormFactoryInterface $forms, OfficeProfileContext $profiles, OfficeProfilePhotoStorage $photos, #[CurrentUser] User $user): Response
    {
        if ($profiles->supports($user)) {
            return $this->redirectToRoute('vpsas_profile');
        }
        $inline = 'office-profile-panel' === $request->headers->get('Turbo-Frame');
        $form = $forms->createNamed($inline ? 'account_photo_inline' : 'account_photo', OfficeProfilePhotoType::class, null, [
            'action' => $this->generateUrl('account_profile'),
            'csrf_token_id' => 'account_photo_'.$user->getId(),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $photos->storeAccountPhoto($user, $form->get('photo')->getData());

                return $this->redirectToRoute('account_profile', status: Response::HTTP_SEE_OTHER);
            } catch (FileException) {
                $form->addError(new FormError('The photo could not be saved. Please select it again and retry.'));
            }
        }

        return $this->render('account/_profile_frame.html.twig', [
            'photo_form' => $form->createView(),
            'inline' => true,
        ], new Response(status: $form->isSubmitted() ? 422 : 200, headers: ['Cache-Control' => 'private, no-store']));
    }
    #[Route('/profile/edit', name: 'account_profile_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, FormFactoryInterface $forms, EntityManagerInterface $manager, #[CurrentUser] User $user): Response
    {
        // Keep submitted values separate from the signed-in identity until validation succeeds.
        $details = ['firstName' => $user->getFirstName(), 'lastName' => $user->getLastName()];
        $profile = $user->getStudentProfile();
        $form = $this->createForm(ProfileType::class, $details, [
            'profile' => $profile,
            'action' => $this->generateUrl('account_profile_edit'),
            'csrf_token_id' => 'account_details_'.$user->getId(),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user->setFirstName((string) $form->get('firstName')->getData());
            $user->setLastName((string) $form->get('lastName')->getData());
            if (null !== $profile) {
                $profile->setContactNumber((string) $form->get('contactNumber')->getData());
                $profile->setProgram((string) $form->get('program')->getData());
                $profile->setCollege((string) $form->get('college')->getData());
                $profile->setYearLevel((string) $form->get('yearLevel')->getData());
            }
            $manager->flush();
            $photoForm = $forms->createNamed('account_photo_inline', OfficeProfilePhotoType::class, null, [
                'action' => $this->generateUrl('account_profile'),
                'csrf_token_id' => 'account_photo_'.$user->getId(),
            ]);

            return $this->render('account/_profile_frame.html.twig', [
                'photo_form' => $photoForm->createView(), 'inline' => true, 'saved' => true,
            ], new Response(headers: ['Cache-Control' => 'private, no-store']));
        }

        return $this->render('account/_edit_frame.html.twig', ['form' => $form->createView()],
            new Response(status: $form->isSubmitted() ? 422 : 200, headers: ['Cache-Control' => 'private, no-store']));
    }

}
