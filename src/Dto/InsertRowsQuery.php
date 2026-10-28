<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

readonly class InsertRowsQuery
{
    public function __construct(
        #[Assert\Range(min: 1, max: 20)]
        public int $count = 1,
    ) {
    }
}
