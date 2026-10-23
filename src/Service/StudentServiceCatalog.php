<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;

/**
 * @phpstan-type Person array{image: string, name: string, role: string, unitCode: string}
 * @phpstan-type UnitHead array{image: string, name: string, role: string, unitCode: string, unit: string, summary: string, responsibilities: string, services: list<string>, photos: list<string>}
 * @phpstan-type StudentService array{code: string, name: string, summary: string, description: string, focus: string, route: string, director: Person, heads: list<UnitHead>}
 */
class StudentServiceCatalog
{
    /** The Vice President sits above the three directorates in the organizational chart. */
    private const VICE_PRESIDENT = ['image' => 'images/vpsas/vice-president.jpg', 'name' => 'Dr. Cesar P. Estrope', 'role' => 'Vice President, Student Affairs & Services', 'unitCode' => 'VPSAS'];

    private const SERVICES = [
        'SDS' => [
            'code' => 'SDS',
            'name' => 'Student Development Services',
            'summary' => 'Grow your leadership, get involved in campus life, and develop your skills through student organizations and sports.',
            'description' => 'Student Development Services (SDS) brings together the units that support student leadership, participation, responsible conduct and sports. It helps students build confidence, teamwork and a sense of responsibility alongside their academic learning.',
            'focus' => 'Leadership, participation and personal growth',
            'route' => 'app_sds',
            'director' => ['image' => 'images/people/ventolero.jpg', 'name' => 'Julio E. Ventolero, MTE, MMM', 'role' => 'Director, Student Development Services', 'unitCode' => 'SDS_OFFICE'],
            'heads' => [
                ['image' => 'images/people/cabanlit.jpg', 'name' => 'Roche Cabanlit', 'role' => 'Head, Student Government', 'unitCode' => 'GOV', 'unit' => 'Student Government', 'summary' => 'Leads the office that supports student representation and student-led initiatives on campus.', 'responsibilities' => 'Guides the student government, supports student representation, and helps student leaders plan and carry out campus activities.', 'services' => ['Student council guidance', 'Leadership programs', 'Student representation']],
                ['image' => 'images/people/bugay.jpg', 'name' => 'Dr. Apollo Marr F. Bugay', 'role' => 'Head, Board of Discipline & League of Student Organization', 'unitCode' => 'DISC', 'unit' => 'Board of Discipline & League of Student Organizations', 'summary' => 'Oversees student conduct concerns and supports recognized student organizations.', 'responsibilities' => 'Handles discipline concerns fairly, promotes responsible conduct, and supports the accreditation and activities of student organizations.', 'services' => ['Discipline concerns', 'Student organization support', 'Conduct guidance']],
                ['image' => 'images/people/torres.jpg', 'name' => 'Donald S. Torres', 'role' => 'Head, Student Publication', 'unitCode' => 'PUB', 'unit' => 'Student Publication', 'summary' => 'Guides the student publication and campus journalism programs.', 'responsibilities' => 'Supports student writers and editors, upholds responsible campus journalism, and helps the publication reach the student body.', 'services' => ['Campus journalism', 'Publication guidance', 'Writing and layout support']],
                ['image' => 'images/people/llera.jpg', 'name' => 'Stella Marie Llera', 'role' => 'Head, Foreign International Student Services', 'unitCode' => 'INTL', 'unit' => 'Foreign International Student Services', 'summary' => 'Assists foreign and international students throughout their stay at NORSU.', 'responsibilities' => 'Helps international students with orientation, documentation and adjusting to university and local life.', 'services' => ['International student orientation', 'Documentation support', 'Cultural adjustment help']],
            ],
        ],
        'SWS' => [
            'code' => 'SWS',
            'name' => 'Student Welfare Services',
            'summary' => 'Find guidance, counselling and opportunities that support your well-being and help you navigate student life.',
            'description' => 'Student Welfare Services (SWS) focuses on student well-being and support throughout university life. Its units bring together counselling, assessment, resources and enhancement, along with student enterprise development, to help students address concerns and explore their potential.',
            'focus' => 'Well-being, guidance and student enterprise',
            'route' => 'app_sws',
            'director' => ['image' => 'images/people/taguba.jpg', 'name' => 'Annie Rose Taguba, RGC, RPM', 'role' => 'Director, Student Welfare Services', 'unitCode' => 'SWS_OFFICE'],
            'heads' => [
                ['image' => 'images/people/de-guzman.jpg', 'name' => 'Dulce Maria Rosario S. De Guzman', 'role' => 'Head, Counseling Assessment, Resources & Enhancement', 'unitCode' => 'CARE', 'unit' => 'CARE: Counseling, Assessment, Resources & Enhancement', 'summary' => 'Leads counselling and assessment services that support student well-being.', 'responsibilities' => 'Provides counselling, guidance and assessment, connects students with resources, and follows up on cases through the CARE service.', 'services' => ['Counselling and guidance', 'Psychological assessment', 'Referrals and follow-ups']],
                ['image' => 'images/people/enquilino.jpg', 'name' => 'Pirl I. Enquilino', 'role' => 'Head, Student Enterprise Development', 'unitCode' => 'ENT', 'unit' => 'Student Enterprise Development', 'summary' => 'Supports students who want to build skills and small enterprises.', 'responsibilities' => 'Encourages entrepreneurship among students through training, mentoring and opportunities to practice enterprise skills.', 'services' => ['Entrepreneurship training', 'Mentoring', 'Student enterprise projects']],
            ],
        ],
        'ISPS' => [
            'code' => 'ISPS',
            'name' => 'Institutional Student Programs & Services',
            'summary' => 'Explore scholarships, student publications, housing and inclusive programs that help you take part in university life.',
            'description' => 'Institutional Student Programs & Services (ISPS) brings together programs that support students with different backgrounds, interests and needs. It covers scholarship and financial assistance, multi-faith services, student publications, international student support, food and housing concerns, and services for students with special needs.',
            'focus' => 'Access, inclusion and everyday student support',
            'route' => 'app_isps',
            'director' => ['image' => 'images/people/bernaldez.jpg', 'name' => 'Dr. Carlou G. Bernaldez', 'role' => 'Director, Institutional Student Programs & Services', 'unitCode' => 'ISPS_OFFICE'],
            'heads' => [
                ['image' => 'images/people/ibero.jpg', 'name' => 'Orly V. Ibero', 'role' => 'Head, Sports Development Program', 'unitCode' => 'SPORT', 'unit' => 'Sports Development Program', 'summary' => 'Leads student sports participation and development.', 'responsibilities' => 'Organizes sports activities and training, supports student athletes, and promotes teamwork, discipline and an active campus life.', 'services' => ['Sports training', 'Inter-campus competitions', 'Student athlete support']],
                ['image' => 'images/people/oliveros.jpg', 'name' => 'Dr. Michael Oliveros', 'role' => 'Head, University Clinic', 'unitCode' => 'CLINIC', 'unit' => 'University Clinic', 'summary' => 'Oversees the university clinic and student health services.', 'responsibilities' => 'Provides basic medical and dental assistance, health monitoring and referrals to protect student health on campus.', 'services' => ['Basic medical assistance', 'Health monitoring', 'Referrals']],
                ['image' => 'images/people/escorial.jpg', 'name' => 'Shiela May L. Escorial', 'role' => 'Head, Scholarship and Financial Assistance', 'unitCode' => 'SCHOL', 'unit' => 'Scholarship and Financial Assistance', 'summary' => 'Manages scholarships and financial aid for students.', 'responsibilities' => 'Informs students about scholarship and grant opportunities, processes applications and helps deserving students continue their studies.', 'services' => ['Scholarship information', 'Application processing', 'Financial aid guidance']],
                ['image' => 'images/people/eliaga.jpg', 'name' => 'Miriam Eliaga', 'role' => 'Head, Multi-Faith Services', 'unitCode' => 'FAITH', 'unit' => 'Multi-Faith Services', 'summary' => 'Leads spiritual and multi-faith programs for the university community.', 'responsibilities' => 'Promotes mutual respect among faiths and offers spiritual activities and support open to students of every belief.', 'services' => ['Spiritual activities', 'Interfaith programs', 'Student spiritual support']],
                ['image' => 'images/people/rizada.jpg', 'name' => 'Ma. Leroz P. Rizada', 'role' => 'Head, Food, Student Housing & Residential', 'unitCode' => 'HOUS', 'unit' => 'Food, Student Housing & Residential', 'summary' => 'Looks after student housing, residential and food services.', 'responsibilities' => 'Supports safe and comfortable student residences and helps students find housing and food services around the university.', 'services' => ['Student housing support', 'Residential concerns', 'Food services']],
                ['image' => 'images/people/alama.jpg', 'name' => 'Efreneth G. Alama', 'role' => 'Head, Services for Students w/ Special Needs', 'unitCode' => 'SSN', 'unit' => 'Services for Students with Special Needs', 'summary' => 'Supports students with special needs so they can learn on equal terms.', 'responsibilities' => 'Coordinates accommodations and assistance for students with special needs and works with offices and faculty to make learning accessible.', 'services' => ['Accessibility accommodations', 'Learning support', 'Coordination with faculty']],
            ],
        ],
    ];

    /** Campus views fill any of a unit's six photo slots that have no picture of their own yet. */
    private const FALLBACK_PHOTOS = ['images/login-building.jpg', 'images/newlogin.jpeg'];

    private const PHOTO_COUNT = 6;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    /** @return array<string, StudentService> */
    public function all(): array
    {
        $services = self::SERVICES;
        foreach ($services as $code => $service) {
            foreach ($service['heads'] as $index => $head) {
                $services[$code]['heads'][$index]['photos'] = $this->photosFor(pathinfo($head['image'], \PATHINFO_FILENAME));
            }
        }

        return $services;
    }

    /**
     * Unit photos live in public/images/units/<head-slug>/ (any jpg, png or webp, sorted by name).
     *
     * @return list<string>
     */
    private function photosFor(string $slug): array
    {
        $relativeDirectory = 'images/units/'.$slug;
        $photos = [];

        if (is_dir($this->projectDir.'/public/'.$relativeDirectory)) {
            $files = Finder::create()->files()->depth('== 0')->name('/\.(jpe?g|png|webp)$/i')->sortByName()->in($this->projectDir.'/public/'.$relativeDirectory);
            foreach ($files as $file) {
                $photos[] = $relativeDirectory.'/'.$file->getFilename();
            }
        }

        for ($i = \count($photos); $i < self::PHOTO_COUNT; ++$i) {
            $photos[] = self::FALLBACK_PHOTOS[$i % \count(self::FALLBACK_PHOTOS)];
        }

        return \array_slice($photos, 0, self::PHOTO_COUNT);
    }

    /**
     * The officials students can book, in organizational chart order: the Vice President, then each
     * directorate's director followed by that directorate's unit heads.
     *
     * @return array{vicePresident: Person, directorates: list<array{code: string, name: string, route: string, director: Person, heads: list<UnitHead>}>}
     */
    public function officials(): array
    {
        $directorates = [];
        foreach ($this->all() as $service) {
            $directorates[] = [
                'code' => $service['code'],
                'name' => $service['name'],
                'route' => $service['route'],
                'director' => $service['director'],
                'heads' => $service['heads'],
            ];
        }

        return ['vicePresident' => self::VICE_PRESIDENT, 'directorates' => $directorates];
    }

    /**
     * The official who takes appointments for a SAS unit, or null when the unit has no listed official.
     *
     * @return Person|null
     */
    public function officialForUnit(string $unitCode): ?array
    {
        $officials = $this->officials();
        if ($officials['vicePresident']['unitCode'] === $unitCode) {
            return $officials['vicePresident'];
        }
        foreach ($officials['directorates'] as $directorate) {
            foreach ([$directorate['director'], ...$directorate['heads']] as $person) {
                if ($person['unitCode'] === $unitCode) {
                    return $person;
                }
            }
        }

        return null;
    }

    /** @return StudentService|null */
    public function get(string $code): ?array
    {
        return $this->all()[strtoupper($code)] ?? null;
    }
}
