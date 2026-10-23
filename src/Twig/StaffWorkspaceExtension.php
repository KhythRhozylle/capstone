<?php

namespace App\Twig;

use App\Entity\User;
use App\Service\StaffWorkspace;
use Twig\Attribute\AsTwigFunction;

class StaffWorkspaceExtension
{
    public function __construct(private readonly StaffWorkspace $workspace)
    {
    }

    #[AsTwigFunction('staff_workspace')]
    public function current(?User $user): ?array
    {
        if (!$this->workspace->supports($user)) {
            return null;
        }
        $unit = $this->workspace->personalUnit($user);
        $directorate = StaffWorkspace::directorateCode($user);

        return ['code' => $unit?->getCode(), 'name' => $unit?->getName() ?? 'Unassigned office', 'directorate' => $directorate,
            'specialist' => $directorate ? $directorate.' Directorate' : ($unit?->getName() ?? 'Office desk'),
            'care' => $this->workspace->hasCareDesk($user), 'documents' => null !== $directorate || (bool) $unit?->handlesDocuments()];
    }
}
