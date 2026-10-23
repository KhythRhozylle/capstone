<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

readonly class AvailabilityQuery
{
    public function __construct(#[Assert\Range(min: 2020, max: 2100)] public ?int $year = null, #[Assert\Range(min: 1, max: 12)] public ?int $month = null) {}
}
