<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

readonly class CalendarSlotAvailability
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Date]
        public string $date,
        #[Assert\Range(min: 0, max: 1380)]
        #[Assert\DivisibleBy(60)]
        public int $start,
        public bool $unavailable,
        public bool $wholeDay = false,
    ) {
    }
}
