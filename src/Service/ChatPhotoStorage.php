<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ChatPhotoStorage
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/var/chat-photos')] private readonly string $directory,
    ) {
    }

    public function store(UploadedFile $photo): string
    {
        $extension = match ($photo->getMimeType()) {
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
            default => throw new \InvalidArgumentException('Unsupported chat photo type.'),
        };
        $filename = bin2hex(random_bytes(16)).'.'.$extension;
        $photo->move($this->directory, $filename);

        return $filename;
    }

    public function path(string $filename): string
    {
        if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/D', $filename)) {
            throw new FileNotFoundException('Chat photo');
        }

        return $this->directory.'/'.$filename;
    }

    public function remove(string $filename): void
    {
        $path = $this->path($filename);
        if (is_file($path)) {
            unlink($path);
        }
    }
}
