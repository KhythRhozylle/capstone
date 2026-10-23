<?php

namespace App\Service;

use App\Entity\DocumentationGuide;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class DocumentationGuidePublisher
{
    public function __construct(private readonly EntityManagerInterface $manager)
    {
    }

    public function save(DocumentationGuide $guide, User $editor): void
    {
        if (null === $guide->getId()) {
            $guide->publish($editor);
            $this->manager->persist($guide);
        } else {
            $guide->recordUpdate($editor);
        }
        $this->manager->flush();
    }

    public function delete(DocumentationGuide $guide): void
    {
        $this->manager->remove($guide);
        $this->manager->flush();
    }
}
