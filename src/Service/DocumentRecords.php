<?php

namespace App\Service;

use App\Entity\DocumentRequest;
use App\Entity\User;
use App\Enum\RequestStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

class DocumentRecords
{
    public function __construct(private readonly EntityManagerInterface $manager, private readonly ReferenceCodeGenerator $codes, private readonly TrackingService $tracking, private readonly ClockInterface $clock)
    {
    }

    public function save(DocumentRequest $document, User $actor, ?string $previousStatus): void
    {
        $this->manager->wrapInTransaction(function () use ($document, $actor, $previousStatus): void {
            $new = null === $document->getId();
            $document->setCampus($document->getStudent()->getCampus());
            $document->setReleasedAt(RequestStatus::Released === $document->getStatus() ? ($document->getReleasedAt() ?? $this->clock->now()) : null);
            if ($new) {
                $document->setReferenceCode('DOC-'.substr($document->getVerificationToken(), 0, 24));
                $this->manager->persist($document);
                $this->manager->flush();
                $document->setReferenceCode($this->codes->generate('DOC', $document));
            }
            $this->tracking->record($document, $previousStatus, $document->getStatus()->label(), $actor, $new ? 'Document created by official.' : 'Document details updated by official.');
        });
    }

    public function delete(DocumentRequest $document, User $actor): void
    {
        $this->manager->wrapInTransaction(function () use ($document, $actor): void {
            $this->tracking->record($document, $document->getStatus()->label(), 'Deleted', $actor, 'Deleted document '.$document->getReferenceCode().'.');
            $this->manager->remove($document);
        });
    }
}
