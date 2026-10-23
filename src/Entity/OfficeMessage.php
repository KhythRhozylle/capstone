<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Index(columns: ['sender_id', 'recipient_id', 'created_at'])]
class OfficeMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $sender;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $recipient;

    #[ORM\Column(length: 180)]
    private string $senderName;

    #[ORM\Column(length: 2000)]
    #[Assert\Length(max: 2000)]
    private string $body = '';

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $photoFilename = null;

    #[Assert\Image(maxSize: '2M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'], maxWidth: 6000, maxHeight: 6000)]
    private ?UploadedFile $photoUpload = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $attachmentFilename = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $attachmentName = null;

    #[ORM\Column(nullable: true)]
    private ?int $attachmentSize = null;

    #[Assert\File(maxSize: '2M', filenameMaxLength: 255)]
    private ?UploadedFile $attachmentUpload = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $sender, User $recipient, string $senderName)
    {
        $this->sender = $sender;
        $this->recipient = $recipient;
        $this->senderName = $senderName;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPhotoFilename(): ?string
    {
        return $this->photoFilename;
    }

    public function setPhotoFilename(?string $filename): void
    {
        $this->photoFilename = $filename;
    }

    public function getPhotoUpload(): ?UploadedFile
    {
        return $this->photoUpload;
    }

    public function setPhotoUpload(?UploadedFile $photo): void
    {
        $this->photoUpload = $photo;
    }

    public function getAttachmentFilename(): ?string
    {
        return $this->attachmentFilename;
    }

    public function getAttachmentName(): ?string
    {
        return $this->attachmentName;
    }

    public function getAttachmentSize(): ?int
    {
        return $this->attachmentSize;
    }

    public function setAttachment(?string $filename, ?string $name = null, ?int $size = null): void
    {
        $this->attachmentFilename = $filename;
        $this->attachmentName = $name;
        $this->attachmentSize = $size;
    }

    public function getAttachmentUpload(): ?UploadedFile
    {
        return $this->attachmentUpload;
    }

    public function setAttachmentUpload(?UploadedFile $file): void
    {
        $this->attachmentUpload = $file;
    }

    #[Assert\Callback]
    public function validateContent(ExecutionContextInterface $context): void
    {
        if ('' === $this->body && null === $this->photoUpload && null === $this->photoFilename && null === $this->attachmentUpload && null === $this->attachmentFilename) {
            $context->buildViolation('Write a message or select a photo or file.')->atPath('body')->addViolation();
        }
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function markRead(): void
    {
        $this->readAt ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getSender(): User
    {
        return $this->sender;
    }

    public function getRecipient(): User
    {
        return $this->recipient;
    }

    public function getSenderName(): string
    {
        return $this->senderName;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): void
    {
        $this->body = trim($body);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
