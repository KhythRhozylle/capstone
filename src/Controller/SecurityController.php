<?php

namespace App\Controller;

use App\Entity\StudentProfile;
use App\Entity\User;
use App\Form\RegistrationType;
use App\Repository\CampusRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, #[CurrentUser] ?User $user): Response
    {
        if ($user instanceof User) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('Logout is handled by the firewall.');
    }

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        CampusRepository $campusRepository,
    ): Response {
        if ($this->getUser() instanceof User) {
            return $this->redirectToRoute('app_home');
        }

        if ($campusRepository->count([]) === 0) {
            $this->addFlash('warning', 'Campus data is not loaded yet. Ask an administrator to run database fixtures before student registration.');

            return $this->redirectToRoute('app_login');
        }

        $user = new User();
        $user->setRoles(['ROLE_STUDENT']);
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $profile = new StudentProfile();
            $profile->setUser($user);
            $profile->setStudentNumber((string) $form->get('studentNumber')->getData());
            $profile->setProgram((string) $form->get('program')->getData());
            $profile->setCollege((string) $form->get('college')->getData());
            $profile->setYearLevel((string) $form->get('yearLevel')->getData());
            $profile->setContactNumber((string) $form->get('contactNumber')->getData());
            $user->setStudentProfile($profile);

            $plain = (string) $form->get('plainPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $plain));

            $entityManager->persist($profile);
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Your student account was created. You may sign in now.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/register.html.twig', [
            'form' => $form,
        ]);
    }
}
