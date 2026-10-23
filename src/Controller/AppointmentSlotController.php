<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\AppointmentRepository;
use App\Repository\CampusRepository;
use App\Repository\SasUnitRepository;
use App\Service\AppointmentAvailability;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Lists the bookable time slots of a unit for one day. Used by the booking and reschedule forms. */
#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class AppointmentSlotController extends AbstractController
{
    #[Route('/appointment-slots', name: 'appointment_slots', methods: ['GET'])]
    public function slots(
        Request $request,
        SasUnitRepository $units,
        CampusRepository $campuses,
        AppointmentRepository $appointments,
        AppointmentAvailability $availability,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $unit = $units->find($request->query->getInt('unit'));
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $request->query->get('date'), new \DateTimeZone(AppointmentAvailability::TIMEZONE));
        if (!$unit || !$unit->handlesAppointments() || !$day) {
            return $this->json(['message' => 'Choose a unit and a date.', 'slots' => []], 422);
        }

        $campus = $this->isGranted('ROLE_STAFF') && $request->query->has('campus')
            ? $campuses->find($request->query->getInt('campus'))
            : $user->getCampus();
        if (!$campus) {
            return $this->json(['message' => 'No campus is assigned to this account.', 'slots' => []], 422);
        }

        $exclude = null;
        if ($request->query->getInt('exclude')) {
            $candidate = $appointments->find($request->query->getInt('exclude'));
            if ($candidate && ($candidate->getStudent()->getId() === $user->getId() || $this->isGranted('ROLE_STAFF'))) {
                $exclude = $candidate;
            }
        }

        $slots = $availability->slotsFor($unit, $campus, $day, $exclude);

        return $this->json([
            'message' => $slots ? null : \sprintf('%s has no office hours on this day.', $unit->getName()),
            'slots' => $slots,
        ]);
    }
}
