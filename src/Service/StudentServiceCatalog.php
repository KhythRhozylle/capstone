<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;

/**
 * @phpstan-type GalleryImage array{image: string, alt: string, title: string}
 * @phpstan-type Person array{image: string, name: string, role: string}
 * @phpstan-type Offering array{code: string, slug: string, name: string, description: string, images: list<GalleryImage>}
 * @phpstan-type StudentService array{code: string, name: string, summary: string, description: string, focus: string, route: string, director: Person, heads: list<Person>, offerings: list<Offering>, slides: list<GalleryImage>}
 */
class StudentServiceCatalog
{
    private const SLIDES = [
        [
            'image' => 'images/login-building.jpg',
            'alt' => 'Blue architectural illustration of a university building with a curved facade and glass windows.',
            'title' => 'Campus building illustration',
        ],
        [
            'image' => 'images/newlogin.jpeg',
            'alt' => 'Elevated view of campus buildings surrounding a courtyard at sunset.',
            'title' => 'Campus courtyard',
        ],
    ];

    private const SERVICES = [
        'SDS' => [
            'code' => 'SDS',
            'name' => 'Student Development Services',
            'summary' => 'Grow your leadership, get involved in campus life, and develop your skills through student organizations and sports.',
            'description' => 'Student Development Services (SDS) brings together the units that support student leadership, participation, responsible conduct and sports. It helps students build confidence, teamwork and a sense of responsibility alongside their academic learning.',
            'focus' => 'Leadership, participation and personal growth',
            'route' => 'app_sds',
            'director' => ['image' => 'images/people/ventolero.jpg', 'name' => 'Julio E. Ventolero, MTE, MMM', 'role' => 'Director, Student Development Services'],
            'heads' => [
                ['image' => 'images/people/cabanlit.jpg', 'name' => 'Roche Cabanlit', 'role' => 'Head, Student Government'],
                ['image' => 'images/people/bugay.jpg', 'name' => 'Dr. Apollo Marr F. Bugay', 'role' => 'Head, Board of Discipline & League of Student Organization'],
                ['image' => 'images/people/torres.jpg', 'name' => 'Donald S. Torres', 'role' => 'Head, Student Publication'],
                ['image' => 'images/people/llera.jpg', 'name' => 'Stella Marie Llera', 'role' => 'Head, Foreign International Student Services'],
            ],
            'offerings' => [
                [
                    'code' => 'OSAS',
                    'name' => 'University OSAS',
                    'description' => 'A starting point for student affairs inquiries and help finding the SAS unit that fits your concern.',
                ],
                [
                    'code' => 'GOV',
                    'name' => 'Student Government',
                    'description' => 'Student representation, leadership and opportunities to take part in campus initiatives.',
                ],
                [
                    'code' => 'DISC',
                    'name' => 'Board of Discipline & Campus Student Organization',
                    'description' => 'Support for responsible student conduct, discipline concerns and participation in campus student organizations.',
                ],
                [
                    'code' => 'SPORT',
                    'name' => 'Sports Development Program',
                    'description' => 'Student sports participation and development, with an emphasis on teamwork, discipline and an active campus life.',
                ],
            ],
        ],
        'SWS' => [
            'code' => 'SWS',
            'name' => 'Student Welfare Services',
            'summary' => 'Find guidance, counselling and opportunities that support your well-being and help you navigate student life.',
            'description' => 'Student Welfare Services (SWS) focuses on student well-being and support throughout university life. Its units bring together counselling, assessment, resources and enhancement, along with student enterprise development, to help students address concerns and explore their potential.',
            'focus' => 'Well-being, guidance and student enterprise',
            'route' => 'app_sws',
            'director' => ['image' => 'images/people/taguba.jpg', 'name' => 'Annie Rose Taguba, RGC, RPM', 'role' => 'Director, Student Welfare Services'],
            'heads' => [
                ['image' => 'images/people/de-guzman.jpg', 'name' => 'Dulce Maria Rosario S. De Guzman', 'role' => 'Head, Counseling Assessment, Resources & Enhancement'],
                ['image' => 'images/people/enquilino.jpg', 'name' => 'Pirl I. Enquilino', 'role' => 'Head, Student Enterprise Development'],
            ],
            'offerings' => [
                [
                    'code' => 'CARE',
                    'name' => 'CARE — Counselling, Assessment, Resources & Enhancement',
                    'description' => 'Guidance and counselling support for personal or academic concerns, with assessment, referrals and follow-ups through the CARE service.',
                ],
                [
                    'code' => 'ENT',
                    'name' => 'Student Enterprise Development',
                    'description' => 'Support for exploring student enterprise ideas and developing practical entrepreneurial skills.',
                ],
            ],
        ],
        'ISPS' => [
            'code' => 'ISPS',
            'name' => 'Institutional Student Programs & Services',
            'summary' => 'Explore scholarships, student publications, housing and inclusive programs that help you take part in university life.',
            'description' => 'Institutional Student Programs & Services (ISPS) brings together programs that support students with different backgrounds, interests and needs. It covers scholarship and financial assistance, multi-faith services, student publications, international student support, food and housing concerns, and services for students with special needs.',
            'focus' => 'Access, inclusion and everyday student support',
            'route' => 'app_isps',
            'director' => ['image' => 'images/people/bernaldez.jpg', 'name' => 'Dr. Carlou G. Bernaldez', 'role' => 'Director, Institutional Student Programs & Services'],
            'heads' => [
                ['image' => 'images/people/ibero.jpg', 'name' => 'Orly V. Ibero', 'role' => 'Head, Sports Development Program'],
                ['image' => 'images/people/oliveros.jpg', 'name' => 'Dr. Michael Oliveros', 'role' => 'Head, University Clinic'],
                ['image' => 'images/people/escorial.jpg', 'name' => 'Shiela May L. Escorial', 'role' => 'Head, Scholarship and Financial Assistance'],
                ['image' => 'images/people/eliaga.jpg', 'name' => 'Miriam Eliaga', 'role' => 'Head, Multi-Faith Services'],
                ['image' => 'images/people/rizada.jpg', 'name' => 'Ma. Leroz P. Rizada', 'role' => 'Head, Food, Student Housing & Residential'],
                ['image' => 'images/people/alama.jpg', 'name' => 'Efreneth G. Alama', 'role' => 'Head, Services for Students w/ Special Needs'],
            ],
            'offerings' => [
                [
                    'code' => 'SCHOL',
                    'name' => 'Scholarship and Financial Assistance',
                    'description' => 'Information and assistance with scholarship or financial support inquiries. Ask the unit about available opportunities and their requirements.',
                ],
                [
                    'code' => 'FAITH',
                    'name' => 'Multi-Faith Services',
                    'description' => 'Support for students’ faith-related needs and participation in a diverse university community.',
                ],
                [
                    'code' => 'PUB',
                    'name' => 'Student Publication',
                    'description' => 'A place for student journalism, writing and participation in campus publications.',
                ],
                [
                    'code' => 'INTL',
                    'name' => 'Foreign / International Student Services',
                    'description' => 'Information and support for foreign and international students navigating university life.',
                ],
                [
                    'code' => 'HOUS',
                    'name' => 'Food, Student Housing & Residential',
                    'description' => 'Help with questions or concerns about student food services, housing and residential life.',
                ],
                [
                    'code' => 'SSN',
                    'name' => 'Services for Students with Special Needs',
                    'description' => 'A starting point for discussing accessibility and support needs so students can participate in university life.',
                ],
            ],
        ],
    ];

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
            $services[$code]['slides'] = self::SLIDES;
            foreach ($service['offerings'] as $index => $offering) {
                $services[$code]['offerings'][$index]['slug'] = strtolower($offering['code']);
                $services[$code]['offerings'][$index]['images'] = $this->galleryImages($code, $offering['code'], $offering['name']);
            }
        }

        return $services;
    }

    /** @return StudentService|null */
    public function get(string $code): ?array
    {
        return $this->all()[strtoupper($code)] ?? null;
    }

    /** @return list<GalleryImage> */
    private function galleryImages(string $directorateCode, string $unitCode, string $unitName): array
    {
        $relativeDirectory = 'images/services/'.strtolower($directorateCode).'/'.strtolower($unitCode);
        $directory = $this->projectDir.'/public/'.$relativeDirectory;
        $images = [];

        if (is_dir($directory)) {
            $files = Finder::create()->files()->depth('== 0')->name('/\.(jpe?g|png|webp)$/i')->sortByName()->in($directory);
            foreach ($files as $file) {
                $images[] = [
                    'image' => $relativeDirectory.'/'.$file->getFilename(),
                    'alt' => 'Photo from the '.$unitName.' gallery.',
                    'title' => $unitName.' photo '.(\count($images) + 1),
                ];
            }
        }

        // Campus views keep each gallery usable until its own service photos are supplied.
        return $images ?: self::SLIDES;
    }
}
