<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\AnnouncementRepository;
use App\Repository\CampusRepository;
use App\Repository\SasDirectorateRepository;
use App\Repository\UserRepository;
use App\Service\StudentServiceCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class HomeController extends AbstractController
{
    /** Staff roles shown in the "Team" section, most senior first. */
    private const TEAM_ROLES = [
        'ROLE_VPSAS' => 'Vice President, Student Affairs & Services',
        'ROLE_DIRECTOR' => 'Director',
        'ROLE_VPSAS_SECRETARIAT' => 'Secretary',
        'ROLE_GUIDANCE' => 'Guidance Counselor',
        'ROLE_STAFF' => 'SAS Staff',
    ];

    /** Photos used for campuses that have no picture of their own yet. */
    private const FALLBACK_CAMPUS_IMAGES = ['images/newlogin.jpeg', 'images/login-building.jpg'];

    #[Route('/', name: 'app_home')]
    public function index(
        #[CurrentUser] ?User $user,
        CampusRepository $campuses,
        SasDirectorateRepository $directorates,
        AnnouncementRepository $announcements,
        UserRepository $users,
        StudentServiceCatalog $serviceCatalog,
        #[Autowire('%kernel.project_dir%')] string $projectDir,
    ): Response {
        if ($user instanceof User) {
            if ($user->isStudent()) {
                return $this->redirectToRoute('portal_dashboard');
            }

            if ($this->isGranted('ROLE_VPSAS')) {
                return $this->redirectToRoute('vpsas_dashboard');
            }

            if ($this->isGranted('ROLE_GUIDANCE')) {
                return $this->redirectToRoute('care_dashboard');
            }

            if ($user->isStaff()) {
                return $this->redirectToRoute('staff_dashboard');
            }
        }

        $campusList = $campuses->findBy([], ['id' => 'ASC']);

        // A campus picture is public/images/campuses/<code>.jpg (e.g. main.jpg, bais1.jpg).
        $slides = [];
        foreach ($campusList as $i => $campus) {
            $own = 'images/campuses/'.strtolower($campus->getCode()).'.jpg';
            $slides[] = [
                'campus' => $campus,
                'image' => is_file($projectDir.'/public/'.$own) ? $own : self::FALLBACK_CAMPUS_IMAGES[$i % \count(self::FALLBACK_CAMPUS_IMAGES)],
            ];
        }

        return $this->render('home/index.html.twig', [
            'campuses' => $campusList,
            'slides' => $slides,
            'directorates' => $directorates->findBy([], ['id' => 'ASC']),
            'announcements' => $announcements->latestUniversityWide(3),
            'student_services' => $serviceCatalog->all(),
            'team' => $this->buildTeam($users->findStaff(), $projectDir),
        ]);
    }

    /**
     * @param list<User> $staff
     *
     * @return list<array{user: User, title: string, portrait: ?array{image: string, width: float, left: float, top: float}}>
     */
    private function buildTeam(array $staff, string $projectDir): array
    {
        $rank = array_flip(array_keys(self::TEAM_ROLES));
        // These windows show the named portraits already present in the SAS organizational chart.
        $portraits = [
            'cesar estrope' => ['x' => 600, 'y' => 591, 'size' => 200],
            'annie rose taguba' => ['x' => 600, 'y' => 920, 'size' => 200],
            'dianne gutual-futalan' => ['x' => 1120, 'y' => 676, 'size' => 148],
        ];
        $portraitImage = 'images/sas-org-chart.jpg';
        $portraitImageExists = is_file($projectDir.'/public/'.$portraitImage);
        $team = [];
        foreach ($staff as $member) {
            foreach (self::TEAM_ROLES as $role => $title) {
                if (\in_array($role, $member->getRoles(), true)) {
                    $crop = $portraits[strtolower($member->getFullName())] ?? null;
                    $portrait = $portraitImageExists && null !== $crop ? [
                        'image' => $portraitImage,
                        'width' => 1400 / $crop['size'] * 100,
                        'left' => -$crop['x'] / $crop['size'] * 100,
                        'top' => -$crop['y'] / $crop['size'] * 100,
                    ] : null;
                    if ('ROLE_DIRECTOR' === $role && null !== $member->getStaffUnit()) {
                        $title .= ', '.$member->getStaffUnit()->getDirectorate()->getName();
                    }
                    $team[] = ['user' => $member, 'title' => $title, 'portrait' => $portrait, 'rank' => $rank[$role]];
                    break;
                }
            }
        }

        usort($team, static fn (array $a, array $b) => $a['rank'] <=> $b['rank']
            ?: strcmp(strtolower($a['user']->getFullName()), strtolower($b['user']->getFullName()))
            ?: strcmp($a['user']->getEmail(), $b['user']->getEmail()));

        return array_map(
            static fn (array $member) => ['user' => $member['user'], 'title' => $member['title'], 'portrait' => $member['portrait']],
            \array_slice($team, 0, 3),
        );
    }
}
