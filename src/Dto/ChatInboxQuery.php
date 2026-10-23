<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

readonly class ChatInboxQuery
{
    public function __construct(
        #[Assert\Length(max: 120)] public string $q = '',
        #[Assert\Choice(choices: ['chat', 'people', 'archive'])] public string $view = 'chat',
    ) {
    }
}
