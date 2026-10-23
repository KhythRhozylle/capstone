<?php

namespace App\Twig;

use App\Entity\User;
use App\Enum\OfficeProfile;
use App\Service\OfficeProfileContext;
use Twig\Attribute\AsTwigFunction;

class OfficeProfileExtension
{
    public function __construct(private readonly OfficeProfileContext $profiles)
    {
    }

    #[AsTwigFunction('office_profile')]
    public function current(?User $user): ?array
    {
        if (!$this->profiles->supports($user)) {
            return null;
        }
        $profile = $this->profiles->current($user);

        return ['key' => $profile->value, 'name' => $profile->label(), 'title' => $profile->title(), 'initials' => $profile->initials(), 'photo' => $user->getProfilePhoto($profile->value)];
    }

    #[AsTwigFunction('office_profile_choices')]
    public function choices(): array
    {
        return array_map(static fn (OfficeProfile $profile) => ['key' => $profile->value, 'name' => $profile->label()], OfficeProfile::cases());
    }
}
