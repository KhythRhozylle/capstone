<?php

namespace App\Service;

use App\Entity\ChatThreadPreference;
use App\Entity\OfficeActivity;
use App\Entity\StatusHistory;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

class LegacyAccountCleanup
{
    private const DEMOS = [
        'student@norsu.edu.ph' => ['Ana', 'Villanueva'],
        'student.bais@norsu.edu.ph' => ['Carlo', 'Reyes'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $manager,
        #[Autowire('%kernel.project_dir%/var/backups')] private readonly string $backupDirectory,
    ) {
    }

    /** @return array{remove: list<User>, merge: list<array{from: User, into: User}>} */
    public function plan(): array
    {
        $remove = [];
        foreach (self::DEMOS as $email => [$first, $last]) {
            $user = $this->manager->getRepository(User::class)->findOneBy(['email' => $email, 'firstName' => $first, 'lastName' => $last]);
            if ($user && $user->isStudent()) {
                $remove[] = $user;
            }
        }
        $merge = [];
        $canonical = $this->manager->getRepository(User::class)->findOneBy(['email' => 'vpsas@norsu.edu.ph']);
        $alias = $this->manager->getRepository(User::class)->findOneBy(['email' => 'vp.sas@norsu.edu.ph', 'firstName' => 'Cesar', 'lastName' => 'Estrope']);
        if ($alias && $canonical && in_array('ROLE_VPSAS', $alias->getRoles(), true) && in_array('ROLE_VPSAS', $canonical->getRoles(), true)) {
            $merge[] = ['from' => $alias, 'into' => $canonical];
        }

        return ['remove' => $remove, 'merge' => $merge];
    }

    public function apply(): ?string
    {
        $plan = $this->plan();
        if (!$plan['remove'] && !$plan['merge']) {
            return null;
        }
        return $this->manager->wrapInTransaction(function () use ($plan): string {
            $backup = $this->backup();
            foreach ($plan['merge'] as ['from' => $from, 'into' => $into]) {
                $this->merge($from, $into);
            }
            $visited = new \SplObjectStorage();
            foreach ($plan['remove'] as $user) {
                $this->removeRelated($user, $visited);
            }

            return $backup;
        });
    }

    private function backup(): string
    {
        $connection = $this->manager->getConnection();
        $tables = [];
        foreach ($connection->createSchemaManager()->listTableNames() as $table) {
            $tables[$table] = $connection->createQueryBuilder()->select('*')->from($connection->quoteSingleIdentifier($table))->executeQuery()->fetchAllAssociative();
        }
        $path = $this->backupDirectory.'/accounts-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(5)).'.snapshot';
        (new Filesystem())->dumpFile($path, serialize(['createdAt' => gmdate(DATE_ATOM), 'tables' => $tables]));

        return $path;
    }

    private function merge(User $from, User $into): void
    {
        // Preferences may overlap between the old and current office identities.
        $preferenceMetadata = $this->manager->getClassMetadata(ChatThreadPreference::class);
        $seen = [];
        $updates = [];
        $preferences = $this->manager->getRepository(ChatThreadPreference::class)->findAll();
        $usesAlias = static fn (ChatThreadPreference $preference) => $preferenceMetadata->getFieldValue($preference, 'owner')->getId() === $from->getId() || $preferenceMetadata->getFieldValue($preference, 'contact')->getId() === $from->getId();
        usort($preferences, static fn (ChatThreadPreference $left, ChatThreadPreference $right) => $usesAlias($left) <=> $usesAlias($right));
        foreach ($preferences as $preference) {
            $owner = $preferenceMetadata->getFieldValue($preference, 'owner');
            $contact = $preferenceMetadata->getFieldValue($preference, 'contact');
            $owner = $owner->getId() === $from->getId() ? $into : $owner;
            $contact = $contact->getId() === $from->getId() ? $into : $contact;
            $key = $owner->getId().':'.$contact->getId();
            if ($owner->getId() === $contact->getId() || isset($seen[$key])) {
                $this->manager->remove($preference);
                continue;
            }
            $seen[$key] = true;
            $updates[] = [$preference, $owner, $contact];
        }
        $this->manager->flush();
        foreach ($updates as [$preference, $owner, $contact]) {
            $preferenceMetadata->setFieldValue($preference, 'owner', $owner);
            $preferenceMetadata->setFieldValue($preference, 'contact', $contact);
        }
        $this->manager->flush();
        foreach ($this->manager->getMetadataFactory()->getAllMetadata() as $metadata) {
            foreach ($metadata->getAssociationNames() as $field) {
                if ($metadata->getAssociationTargetClass($field) !== User::class || $metadata->isAssociationInverseSide($field) || !$metadata->isSingleValuedAssociation($field)) {
                    continue;
                }
                foreach ($this->manager->getRepository($metadata->getName())->findBy([$field => $from]) as $record) {
                    $metadata->setFieldValue($record, $field, $into);
                }
            }
        }
        $activityMetadata = $this->manager->getClassMetadata(OfficeActivity::class);
        foreach ($this->manager->getRepository(OfficeActivity::class)->findBy(['accountId' => $from->getId()]) as $activity) {
            $activityMetadata->setFieldValue($activity, 'accountId', $into->getId());
        }
        $this->manager->flush();
        $this->manager->remove($from);
        $this->manager->flush();
    }

    /** @param \SplObjectStorage<object, null> $visited */
    private function removeRelated(object $entity, \SplObjectStorage $visited): void
    {
        if ($visited->contains($entity)) {
            return;
        }
        $visited->attach($entity);
        $class = $this->manager->getClassMetadata($entity::class)->getName();
        foreach ($this->manager->getMetadataFactory()->getAllMetadata() as $metadata) {
            foreach ($metadata->getAssociationNames() as $field) {
                if ($metadata->getAssociationTargetClass($field) !== $class || $metadata->isAssociationInverseSide($field) || !$metadata->isSingleValuedAssociation($field)) {
                    continue;
                }
                $association = $metadata->getAssociationMapping($field);
                $nullable = $association->joinColumns[0]->nullable ?? true;
                foreach ($this->manager->getRepository($metadata->getName())->findBy([$field => $entity]) as $record) {
                    if ($visited->contains($record)) {
                        continue;
                    }
                    if ($nullable) {
                        $metadata->setFieldValue($record, $field, null);
                    } else {
                        $this->removeRelated($record, $visited);
                    }
                }
            }
        }
        if ($entity instanceof User) {
            foreach ($this->manager->getRepository(OfficeActivity::class)->findBy(['accountId' => $entity->getId()]) as $activity) {
                $this->manager->remove($activity);
            }
        }
        $id = $this->manager->getClassMetadata($class)->getIdentifierValues($entity)['id'] ?? null;
        if (null !== $id && !$entity instanceof StatusHistory) {
            foreach ($this->manager->getRepository(StatusHistory::class)->findBy(['entityType' => $class, 'entityId' => $id]) as $history) {
                $this->manager->remove($history);
            }
        }
        $this->manager->remove($entity);
    }
}
