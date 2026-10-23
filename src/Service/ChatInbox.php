<?php

namespace App\Service;

use App\Entity\ChatThreadPreference;
use App\Entity\OfficeMessage;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class ChatInbox
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly UserRepository $users,
    ) {
    }

    /** @return list<array{person: User, last: ?OfficeMessage, unreadCount: int}> */
    public function search(User $user, string $query): array
    {
        $builder = $this->users->createQueryBuilder('u')
            ->andWhere('u.id != :self')->setParameter('self', $user->getId());
        if ('' !== trim($query)) {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower(trim($query))).'%';
            $builder->andWhere("LOWER(CONCAT(CONCAT(u.firstName, ' '), u.lastName)) LIKE :term ESCAPE '!' OR LOWER(u.email) LIKE :term ESCAPE '!'")
                ->setParameter('term', $term);
        }
        $people = $builder->orderBy('u.firstName', 'ASC')->addOrderBy('u.lastName', 'ASC')
            ->addOrderBy('u.id', 'ASC')->setMaxResults(50)->getQuery()->getResult();

        return $this->withUnreadCounts($user, array_map(static fn (User $person) => ['person' => $person, 'last' => null], $people));
    }

    /** @return list<array{person: User, last: OfficeMessage, unreadCount: int}> */
    public function conversations(User $user, string $view): array
    {
        $latest = $this->manager->createQuery('SELECT m, s, r FROM App\Entity\OfficeMessage m JOIN m.sender s JOIN m.recipient r
            WHERE (m.sender = :self OR m.recipient = :self) AND NOT EXISTS (
                SELECT n.id FROM App\Entity\OfficeMessage n WHERE n.id > m.id AND
                ((n.sender = m.sender AND n.recipient = m.recipient) OR (n.sender = m.recipient AND n.recipient = m.sender))
            ) ORDER BY m.createdAt DESC, m.id DESC')->setParameter('self', $user)->getResult();
        $archived = array_column($this->manager->createQuery('SELECT IDENTITY(p.contact) AS contact FROM App\Entity\ChatThreadPreference p WHERE p.owner = :self AND p.archived = true')
            ->setParameter('self', $user)->getScalarResult(), 'contact');
        $rows = [];
        foreach ($latest as $message) {
            $person = $message->getSender()->getId() === $user->getId() ? $message->getRecipient() : $message->getSender();
            $isArchived = in_array($person->getId(), $archived);
            if (('archive' === $view) !== $isArchived) {
                continue;
            }
            $rows[] = ['person' => $person, 'last' => $message];
        }

        return $this->withUnreadCounts($user, $rows);
    }

    /**
     * @param list<array{person: User, last: ?OfficeMessage}> $rows
     *
     * @return list<array{person: User, last: ?OfficeMessage, unreadCount: int}>
     */
    private function withUnreadCounts(User $user, array $rows): array
    {
        if ([] === $rows) {
            return [];
        }
        $contacts = array_map(static fn (array $row) => $row['person']->getId(), $rows);
        $counts = $this->manager->createQuery('SELECT IDENTITY(m.sender) AS sender, COUNT(m.id) AS received FROM App\Entity\OfficeMessage m WHERE m.recipient = :self AND m.sender IN (:contacts) AND m.readAt IS NULL
            AND NOT EXISTS (SELECT reply.id FROM App\Entity\OfficeMessage reply WHERE reply.sender = :self AND reply.recipient = m.sender AND reply.id > m.id)
            GROUP BY m.sender')
            ->setParameter('self', $user)->setParameter('contacts', $contacts)->getScalarResult();
        $bySender = array_column($counts, 'received', 'sender');

        return array_map(static fn (array $row) => [...$row, 'unreadCount' => (int) ($bySender[$row['person']->getId()] ?? 0)], $rows);
    }

    /** @return list<OfficeMessage> */
    public function openConversation(User $user, User $contact): array
    {
        $messages = $this->manager->createQuery('SELECT m FROM App\Entity\OfficeMessage m WHERE (m.sender = :self AND m.recipient = :other) OR (m.sender = :other AND m.recipient = :self) ORDER BY m.createdAt ASC, m.id ASC')
            ->setParameter('self', $user)->setParameter('other', $contact)->getResult();
        $changed = false;
        foreach ($messages as $message) {
            if ($message->getRecipient()->getId() === $user->getId() && null === $message->getReadAt()) {
                $message->markRead();
                $changed = true;
            }
        }
        if ($changed) {
            $this->manager->flush();
        }

        return $messages;
    }

    public function isArchived(User $user, User $contact): bool
    {
        return $this->manager->getRepository(ChatThreadPreference::class)->findOneBy(['owner' => $user, 'contact' => $contact])?->isArchived() ?? false;
    }

    public function toggleArchive(User $user, User $contact): void
    {
        $preference = $this->manager->getRepository(ChatThreadPreference::class)->findOneBy(['owner' => $user, 'contact' => $contact]) ?? new ChatThreadPreference($user, $contact);
        $preference->setArchived(!$preference->isArchived());
        $this->manager->persist($preference);
        $this->manager->flush();
    }
}
