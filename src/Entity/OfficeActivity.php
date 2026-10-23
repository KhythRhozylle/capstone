<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(columns: ['account_id', 'occurred_at'])]
class OfficeActivity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $accountId;

    #[ORM\Column(length: 20)]
    private string $profileKey;

    #[ORM\Column(length: 180)]
    private string $actorName;

    #[ORM\Column(length: 12)]
    private string $method;

    #[ORM\Column(length: 2048)]
    private string $path;

    #[ORM\Column(length: 255)]
    private string $route;

    #[ORM\Column]
    private int $statusCode;

    #[ORM\Column]
    private \DateTimeImmutable $occurredAt;

    public function getUserLabel(): string
    {
        return match ($this->profileKey) {
            'cesar' => 'Dr. Estrope',
            'mark' => 'Sir Mark',
            'dianne' => 'Ma’am Dianne',
            default => $this->actorName,
        };
    }

    public function getActionLabel(): string
    {
        if ($this->statusCode >= 400) {
            return 403 === $this->statusCode ? 'Denied' : 'Failed';
        }
        if ('app_login' === $this->route) {
            return 'Logged In';
        }
        if ('app_logout' === $this->route) {
            return 'Logged Out';
        }
        if ('vpsas_profile_switch' === $this->route) {
            return 'Switched';
        }
        if (in_array($this->method, ['GET', 'HEAD'], true)) {
            return 'Viewed';
        }
        if (str_contains($this->route, 'delete')) {
            return 'Deleted';
        }
        if ('vpsas_profile' === $this->route) {
            return 'Uploaded';
        }
        if ('vpsas_chat_thread' === $this->route) {
            return 'Sent';
        }

        return str_contains($this->route, 'new') ? 'Created' : 'Updated';
    }

    public function getDescription(): string
    {
        if ($this->statusCode >= 400) {
            return (403 === $this->statusCode ? 'Access was denied' : 'The action could not be completed').'.';
        }
        if ('app_login' === $this->route) {
            return 'Signed in to the system';
        }
        if ('app_logout' === $this->route) {
            return 'Signed out of the system';
        }
        if ('vpsas_profile_switch' === $this->route) {
            return 'Switched to '.$this->actorName.'’s profile';
        }
        $resource = match (true) {
            str_contains($this->route, 'documentation') => 'documentation guide',
            str_contains($this->route, 'announcement') => 'announcement',
            str_contains($this->route, 'availability') => 'office availability',
            str_contains($this->route, 'appointment') => 'appointment',
            str_contains($this->route, 'office_hours') => 'office hours',
            str_contains($this->route, 'feedback') => 'student feedback',
            str_contains($this->route, 'document') => 'document request',
            str_contains($this->route, 'concern') => 'student concern',
            str_contains($this->route, 'chat') => 'chat conversation',
            str_contains($this->route, 'activity') => 'activity log',
            str_contains($this->route, 'profile') => 'profile',
            str_contains($this->route, 'dashboard') => 'dashboard',
            default => 'record',
        };
        if (in_array($this->method, ['GET', 'HEAD'], true)) {
            return 'Viewed '.$resource;
        }

        return match ($this->route) {
            'vpsas_profile' => 'Uploaded profile picture',
            'vpsas_chat_thread' => 'Sent a chat message',
            'vpsas_availability' => 'Marked the VPSAS office unavailable',
            'staff_availability' => 'Marked the office unavailable',
            'vpsas_availability_delete', 'staff_availability_delete' => 'Restored office availability',
            'staff_announcement_new' => 'Added a new announcement',
            default => match ($this->getActionLabel()) {
                'Deleted' => 'Deleted a '.$resource,
                'Created' => 'Added a new '.$resource,
                default => 'Updated '.$resource,
            },
        };
    }

    public function getActorName(): string
    {
        return $this->actorName;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
