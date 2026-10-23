<?php

namespace App\Service;

use App\Entity\StatusHistory;
use App\Entity\User;
use App\Repository\StatusHistoryRepository;
use Doctrine\ORM\EntityManagerInterface;

class TrackingService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly StatusHistoryRepository $historyRepository,
        private readonly OfficeProfileContext $profiles,
        private readonly StaffWorkspace $workspace,
    ) {
    }

    public function record(object $entity, ?string $from, string $to, ?User $actor = null, ?string $note = null): void
    {
        $id = method_exists($entity, 'getId') ? $entity->getId() : null;
        if (!\is_int($id)) {
            $this->entityManager->flush();
            $id = method_exists($entity, 'getId') ? $entity->getId() : 0;
        }

        $history = new StatusHistory($entity::class, (int) $id, $from, $to, $actor, $note);
        if ($this->profiles->supports($actor)) {
            $history->setActorName($this->profiles->current($actor)->label());
        } elseif ($this->workspace->supports($actor)) {
            $history->setActorName($actor->getFullName());
        }
        $this->entityManager->persist($history);
    }

    /** @return list<StatusHistory> */
    public function history(object $entity): array
    {
        $id = method_exists($entity, 'getId') ? $entity->getId() : 0;

        return $this->historyRepository->forEntity($entity::class, (int) $id);
    }
}
