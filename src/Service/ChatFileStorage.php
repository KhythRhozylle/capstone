<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ChatFileStorage
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/var/chat-files')] private readonly string $directory,
    ) {
    }

    public function store(UploadedFile $file): string
    {
        $filename = bin2hex(random_bytes(16)).'.bin';
        $file->move($this->directory, $filename);

        return $filename;
    }

    public function originalName(UploadedFile $file): string
    {
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $name = preg_replace('/[\x00-\x1f\x7f]/u', '', $name) ?? '';

        return trim($name, '. ') ?: 'file';
    }

    public function path(string $filename): string
    {
        if (!preg_match('/^[a-f0-9]{32}\.bin$/D', $filename)) {
            throw new FileNotFoundException('Chat file');
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
