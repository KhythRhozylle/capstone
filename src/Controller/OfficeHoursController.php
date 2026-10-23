<?php

namespace App\Controller;

use App\Entity\OfficeHour;
use App\Entity\SasUnit;
use App\Entity\User;
use App\Form\OfficeHourType;
use App\Repository\OfficeHourRepository;
use App\Repository\SasUnitRepository;
use App\Service\StaffScope;
use App\Service\StaffWorkspace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/** Lets staff define when their SAS unit accepts appointments. */
#[Route('/staff/office-hours')]
class OfficeHoursController extends AbstractController
{
    public function __construct(private readonly StaffWorkspace $workspace)
    {
    }

    #[Route('', name: 'staff_office_hours', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        SasUnitRepository $unitRepository,
        OfficeHourRepository $hours,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user,
    ): Response {
        $units = $this->manageableUnits($user, $unitRepository);
        if (!$units) {
            $this->addFlash('info', 'Your account is not linked to a SAS unit that takes appointments.');

            return $this->redirectToRoute('staff_dashboard');
        }

        $unit = $units[0];
        foreach ($units as $candidate) {
            if ($candidate->getId() === $request->query->getInt('unit')) {
                $unit = $candidate;
            }
        }

        $form = $this->createForm(OfficeHourType::class, new OfficeHour($unit));
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($form->getData());
            $entityManager->flush();
            $this->addFlash('success', 'Office hours saved.');

            return $this->redirectToRoute('staff_office_hours', ['unit' => $unit->getId()], Response::HTTP_SEE_OTHER);
        }

        $byDay = array_fill_keys(range(1, 7), []);
        $recorded = $hours->forUnit($unit);
        foreach ($recorded as $hour) {
            $byDay[$hour->getDayOfWeek()][] = $hour;
        }

        return $this->render('staff/office_hours/index.html.twig', [
            'units' => $units,
            'unit' => $unit,
            'form' => $form,
            'byDay' => $byDay,
            'usingDefaults' => !$recorded,
            'days' => array_flip(OfficeHourType::DAYS),
        ], new Response(status: $form->isSubmitted() && !$form->isValid() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/delete', name: 'staff_office_hours_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        SasUnitRepository $unitRepository,
        OfficeHourRepository $hours,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user,
    ): Response {
        $hour = $hours->find($id);
        $allowed = array_map(static fn (SasUnit $u) => $u->getId(), $this->manageableUnits($user, $unitRepository));
        if (!$hour || !\in_array($hour->getUnit()->getId(), $allowed, true)) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('office-hour-delete-'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $unitId = $hour->getUnit()->getId();
        $entityManager->remove($hour);
        $entityManager->flush();
        $this->addFlash('success', 'Office hours removed.');

        return $this->redirectToRoute('staff_office_hours', ['unit' => $unitId], Response::HTTP_SEE_OTHER);
    }

    /** @return list<SasUnit> */
    private function manageableUnits(User $user, SasUnitRepository $units): array
    {
        if ($this->workspace->supports($user)) {
            $office = $this->workspace->personalUnit($user);
            return $office ? [$office] : [];
        }
        $scope = StaffScope::for($user);
        if ($scope->unit) {
            return 'VPSAS' !== $scope->unit->getCode() && $scope->unit->handlesAppointments() ? [$scope->unit] : [];
        }
        if (!$scope->unrestricted) {
            return [];
        }

        return array_values(array_filter($units->findBy(['handlesAppointments' => true], ['name' => 'ASC']), static fn (SasUnit $unit) => 'VPSAS' !== $unit->getCode()));
    }
}
