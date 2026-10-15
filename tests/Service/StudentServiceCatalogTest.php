<?php

namespace App\Tests\Service;

use App\Service\StudentServiceCatalog;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class StudentServiceCatalogTest extends KernelTestCase
{
    public function testEachUnitLoadsOnlyItsOwnGalleryPhotosInFilenameOrder(): void
    {
        $kernel = self::bootKernel();
        $fixtureRoot = $kernel->getProjectDir().'/var/gallery-test-'.bin2hex(random_bytes(8));
        $osas = $fixtureRoot.'/public/images/services/sds/osas';
        $scholarship = $fixtureRoot.'/public/images/services/isps/schol';
        mkdir($osas, 0777, true);
        mkdir($scholarship, 0777, true);

        $source = $kernel->getProjectDir().'/public/images/login-building.jpg';
        $files = [$osas.'/02-campus.JPG', $osas.'/01-campus.jpg', $scholarship.'/01-campus.webp', $osas.'/notes.txt'];

        try {
            foreach (\array_slice($files, 0, 3) as $file) {
                copy($source, $file);
            }
            file_put_contents($files[3], 'This is not a gallery photo.');

            $catalog = new StudentServiceCatalog($fixtureRoot);
            $services = $catalog->all();
            self::assertSame(
                ['images/services/sds/osas/01-campus.jpg', 'images/services/sds/osas/02-campus.JPG'],
                array_column($services['SDS']['offerings'][0]['images'], 'image'),
            );
            self::assertSame(
                ['images/services/isps/schol/01-campus.webp'],
                array_column($services['ISPS']['offerings'][0]['images'], 'image'),
            );
            self::assertSame(
                ['images/login-building.jpg', 'images/newlogin.jpeg'],
                array_column($services['SDS']['offerings'][1]['images'], 'image'),
            );
            self::assertSame('OSAS', $services['SDS']['offerings'][0]['code']);
            self::assertSame('osas', $services['SDS']['offerings'][0]['slug']);
            self::assertStringContainsString('University OSAS', $services['SDS']['offerings'][0]['images'][0]['alt']);
        } finally {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            // Remove only the explicit directories created for this test, from leaves to root.
            foreach ([$osas, \dirname($osas), $scholarship, \dirname($scholarship), $fixtureRoot.'/public/images/services', $fixtureRoot.'/public/images', $fixtureRoot.'/public', $fixtureRoot] as $directory) {
                if (is_dir($directory)) {
                    rmdir($directory);
                }
            }
        }
    }
}
