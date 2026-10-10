<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

class ReferenceCodeGenerator
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function generate(string $prefix, object $entity): string
    {
        $id = method_exists($entity, 'getId') ? $entity->getId() : null;
        if (!\is_int($id)) {
            $this->entityManager->flush();
            $id = method_exists($entity, 'getId') ? $entity->getId() : 0;
        }

        return \sprintf('%s-%s-%05d', $prefix, (new \DateTimeImmutable())->format('Y'), (int) $id);
    }
}
