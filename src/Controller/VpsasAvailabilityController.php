<?php

namespace App\Controller;

use App\Dto\AvailabilityQuery;
use App\Entity\OfficeUnavailable;
use App\Entity\User;
use App\Form\OfficeUnavailableType;
use App\Repository\OfficeUnavailableRepository;
use App\Repository\SasUnitRepository;
use App\Service\StaffWorkspace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class VpsasAvailabilityController extends AbstractController
{
    #[Route('/appointments/vpsas-availability', name: 'vpsas_availability', defaults: ['code' => 'VPSAS'], methods: ['GET', 'POST'])]
    #[Route('/appointments/availability/{code}', name: 'staff_availability', requirements: ['code' => '[A-Z0-9_]+'], methods: ['GET', 'POST'])]
    public function index(Request $request, #[CurrentUser] User $user, SasUnitRepository $units, OfficeUnavailableRepository $unavailable, StaffWorkspace $workspace, EntityManagerInterface $manager, ClockInterface $clock, string $code = 'VPSAS', #[MapQueryString] AvailabilityQuery $query = new AvailabilityQuery()): Response
    {
        $unit = $units->findOneBy(['code' => $code]) ?? throw $this->createNotFoundException('This office is not configured.');
        $availabilityRoute = 'VPSAS' === $code ? 'vpsas_availability' : 'staff_availability';
        $availabilityParams = 'VPSAS' === $code ? [] : ['code' => $code];
        $owner = $workspace->owns($user, $unit);
        if ($request->isMethod('POST') && !$owner) { throw $this->createAccessDeniedException(); }
        $form = null;
        if ($owner) {
            $form = $this->createForm(OfficeUnavailableType::class, new OfficeUnavailable($unit, $user), ['action' => $this->generateUrl($availabilityRoute, $availabilityParams)]);
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $manager->persist($form->getData());
                $manager->flush();
                $this->addFlash('success', $unit->getName().' is unavailable during the selected period. Students cannot book overlapping slots.');
                return $this->redirectToRoute($availabilityRoute, $availabilityParams, 303);
            }
        }
        $today = $clock->now()->setTimezone(new \DateTimeZone('Asia/Manila'));
        $month = $today->setDate($query->year ?? (int) $today->format('Y'), $query->month ?? (int) $today->format('n'), 1)->setTime(0, 0);
        $start = $month->modify('-'.((int) $month->format('N') - 1).' days');
        $dates = [];
        for ($i = 0; $i < 42; ++$i) { $dates[] = $start->modify('+'.$i.' days'); }
        return $this->render('vpsas/availability.html.twig', ['unit' => $unit, 'availabilityRoute' => $availabilityRoute, 'availabilityParams' => $availabilityParams, 'owner' => $owner, 'form' => $form?->createView(), 'dates' => $dates, 'month' => $month, 'publicPeriods' => $unavailable->findBy(['unit' => $unit], ['day' => 'ASC', 'startMinute' => 'ASC']), 'blocked' => $unavailable->dates($unit), 'entries' => $owner ? $unavailable->findBy(['owner' => $user, 'unit' => $unit], ['day' => 'ASC']) : []], new Response(status: $form?->isSubmitted() && !$form->isValid() ? 422 : 200));
    }

    #[Route('/appointments/availability', name: 'office_availability_directory', methods: ['GET'])]
    public function directory(SasUnitRepository $units): Response
    {
        return $this->render('staff/availability_directory.html.twig', ['units' => $units->findBy(['handlesAppointments' => true], ['name' => 'ASC'])]);
    }

    #[Route('/appointments/vpsas-availability/{id}/delete', name: 'vpsas_availability_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[Route('/appointments/availability/{id}/delete', name: 'staff_availability_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request, #[CurrentUser] User $user, StaffWorkspace $workspace, OfficeUnavailableRepository $entries, EntityManagerInterface $manager): Response
    {
        $entry = $entries->find($id) ?? throw $this->createNotFoundException();
        if (!$workspace->owns($user, $entry->getUnit()) || $entry->getOwner()->getId() !== $user->getId() || !$this->isCsrfTokenValid('availability-delete-'.$id, $request->request->getString('_token'))) { throw $this->createAccessDeniedException(); }
        $manager->remove($entry);
        $manager->flush();
        return $this->redirectToRoute('VPSAS' === $entry->getUnit()->getCode() ? 'vpsas_availability' : 'staff_availability', 'VPSAS' === $entry->getUnit()->getCode() ? [] : ['code' => $entry->getUnit()->getCode()], 303);
    }
}
