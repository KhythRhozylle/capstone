<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\OfficeProfile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class OfficeProfilePhotoStorage
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
        #[Autowire('%kernel.project_dir%/public/uploads/profile-photos')] private readonly string $directory,
    ) {
    }

    public function store(User $user, OfficeProfile $profile, UploadedFile $file): void
    {
        $this->storeForKey($user, $profile->value, $file);
    }

    public function storeAccountPhoto(User $user, UploadedFile $file): void
    {
        $this->storeForKey($user, 'account', $file);
    }

    private function storeForKey(User $user, string $profile, UploadedFile $file): void
    {
        $extension = match ($file->getMimeType()) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
            default => throw new \InvalidArgumentException('Unsupported profile photo type.'),
        };
        $filename = $user->getId().'-'.$profile.'-'.bin2hex(random_bytes(16)).'.'.$extension;
        $old = $user->getProfilePhoto($profile);
        $file->move($this->directory, $filename);
        try {
            $user->setProfilePhoto($profile, $filename);
            $this->manager->flush();
        } catch (\Throwable $error) {
            $user->setProfilePhoto($profile, $old);
            unlink($this->directory.'/'.$filename);
            throw $error;
        }
        if (null !== $old && preg_match('/^'.preg_quote($user->getId().'-'.$profile.'-', '/').'[a-f0-9]{32}\.(jpg|png|webp)$/D', $old) && is_file($this->directory.'/'.$old)) {
            unlink($this->directory.'/'.$old);
        }
    }
}
