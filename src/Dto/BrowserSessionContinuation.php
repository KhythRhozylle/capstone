<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

readonly class BrowserSessionContinuation
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 8192)]
        #[Assert\Regex(pattern: '~\A/(?!/)[^#\x00-\x20\x7f\\\\]*\z~')]
        public string $target = '/',
    ) {
    }
}
