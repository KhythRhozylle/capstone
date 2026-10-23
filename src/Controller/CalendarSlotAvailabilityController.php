<?php

namespace App\Controller;

use App\Dto\CalendarSlotAvailability as Slot;
use App\Entity\User;
use App\Repository\OfficeUnavailableRepository;
use App\Service\CalendarSlotAvailability;
use App\Service\StaffWorkspace;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_STAFF')]
class CalendarSlotAvailabilityController extends AbstractController
{
    #[Route('/staff/appointments/calendar/availability', name: 'staff_calendar_slot_availability', methods: ['POST'], format: 'json')]
    public function update(Request $request, #[CurrentUser] User $user, #[MapRequestPayload] Slot $slot, StaffWorkspace $workspace, CalendarSlotAvailability $availability, OfficeUnavailableRepository $periods, ClockInterface $clock): JsonResponse
    {
        $unit = $workspace->supports($user) ? $workspace->personalUnit($user) : null;
        if (!$unit || !$this->isCsrfTokenValid('calendar-slot-availability', $request->headers->get('X-CSRF-Token'))) {
            throw $this->createAccessDeniedException();
        }
        $date = new \DateTimeImmutable($slot->date, new \DateTimeZone('Asia/Manila'));
        if ($date < $clock->now()->setTimezone(new \DateTimeZone('Asia/Manila'))->setTime(0, 0)) {
            return $this->json(['message' => 'Past dates cannot be changed.'], 422);
        }
        $slot->wholeDay
            ? $availability->setWholeDay($unit, $user, $date, $slot->unavailable)
            : $availability->set($unit, $user, $date, $slot->start, $slot->unavailable);

        return $this->json(['periods' => $periods->periods($unit)]);
    }
}
