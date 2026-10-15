<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class ContactMessage
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    public ?string $name = null;

    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    #[Assert\Length(max: 30)]
    #[Assert\Regex(pattern: '/^[0-9+()\-\s]*$/', message: 'Use digits, spaces, "+", "-" and parentheses only.')]
    public ?string $phone = null;

    #[Assert\NotBlank]
    #[Assert\Length(min: 10, max: 3000)]
    public ?string $message = null;
}
