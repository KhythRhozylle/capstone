<?php

namespace App\Service;

use App\Entity\Campus;
use App\Entity\QueueTicket;
use App\Entity\SasUnit;
use App\Entity\User;
use App\Enum\QueueStatus;
use App\Repository\QueueTicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Lock\LockFactory;

class QueueService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly QueueTicketRepository $tickets,
        private readonly LockFactory $lockFactory,
    ) {
    }

    public function issue(Campus $campus, SasUnit $unit, string $displayName, ?User $student = null): QueueTicket
    {
        $lock = $this->lockFactory->createLock(\sprintf('queue-%d-%d', $campus->getId(), $unit->getId()));
        $lock->acquire(true);

        try {
            $ticket = new QueueTicket();
            $ticket->setCampus($campus);
            $ticket->setUnit($unit);
            $ticket->setDisplayName($displayName);
            $ticket->setStudent($student);
            $ticket->setNumber($this->tickets->nextNumber($campus, $unit));
            $this->entityManager->persist($ticket);
            $this->entityManager->flush();

            return $ticket;
        } finally {
            $lock->release();
        }
    }

    public function callNext(Campus $campus, SasUnit $unit): ?QueueTicket
    {
        $lock = $this->lockFactory->createLock(\sprintf('queue-call-%d-%d', $campus->getId(), $unit->getId()));
        $lock->acquire(true);

        try {
            $ticket = $this->tickets->nextWaiting($campus, $unit);
            if (!$ticket instanceof QueueTicket) {
                return null;
            }
            $ticket->setStatus(QueueStatus::Called);
            $ticket->setCalledAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            return $ticket;
        } finally {
            $lock->release();
        }
    }
}
