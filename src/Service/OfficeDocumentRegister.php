<?php

namespace App\Service;

use App\Entity\OfficeDocumentRecord;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class OfficeDocumentRegister
{
    public function __construct(
        private readonly StaffWorkspace $workspace,
        private readonly EntityManagerInterface $manager,
        private readonly TrackingService $tracking,
        private readonly ClockInterface $clock,
        private readonly OfficeProfileContext $profiles,
    ) {
    }

    public function create(User $official): OfficeDocumentRecord
    {
        $unit = $this->workspace->supports($official) ? $this->workspace->personalUnit($official) : $official->getStaffUnit();
        if (null === $unit) {
            throw new AccessDeniedException('An assigned office is required to receive documents.');
        }
        $record = new OfficeDocumentRecord($unit);
        $record->setCampus($official->getCampus());
        $record->setReceivedAt($this->clock->now()->setTimezone(new \DateTimeZone('Asia/Manila'))->setTime(0, 0));
        $record->setReceivedBy($this->profiles->actorName($official));

        return $record;
    }

    public function assertAccess(OfficeDocumentRecord $record, User $official): void
    {
        $scope = StaffScope::for($official);
        // The selected campus is the document's origin, not the receiving office's campus.
        $officeScope = new StaffScope(null, $scope->unit, $scope->unrestricted, $scope->directorateCode);
        if ((!$scope->unrestricted && null === $scope->unit && null === $scope->directorateCode)
            || !$officeScope->allows($record->getCampus(), $record->getUnit())) {
            throw new AccessDeniedException();
        }
    }

    public function save(OfficeDocumentRecord $record, User $official, ?string $previousStatus): void
    {
        $this->assertAccess($record, $official);
        $this->manager->wrapInTransaction(function () use ($record, $official, $previousStatus): void {
            $new = null === $record->getId();
            $this->manager->persist($record);
            $this->tracking->record($record, $previousStatus, $record->getStatus()->label(), $official, $new ? 'Office document received.' : 'Office document record updated.');
        });
    }

    public function delete(OfficeDocumentRecord $record, User $official): void
    {
        $this->assertAccess($record, $official);
        $this->manager->wrapInTransaction(function () use ($record, $official): void {
            $this->tracking->record($record, $record->getStatus()->label(), 'Deleted', $official, 'Office document record deleted.');
            $this->manager->remove($record);
        });
    }
}
