<?php

namespace App\Tests\Controller;

use App\Entity\Appointment;
use App\Entity\User;
use App\Enum\AppointmentStatus;
use App\Repository\AppointmentRepository;
use App\Repository\UserRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Read-only HTTP coverage using the demo accounts in the existing test database.
 * No fixtures are loaded and no forms are submitted by this suite.
 */
class WorkspacePagesTest extends WebTestCase
{
    #[DataProvider('sidebarPages')]
    public function testSidebarPagesRenderTheirSharedWorkspace(string $email, string $path, ?string $expectedElement): void
    {
        $client = $this->authenticatedClient($email);
        $crawler = $client->request('GET', $path);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.app-shell[data-controller~="app-shell"]');
        self::assertSelectorExists('.topbar');
        self::assertSelectorExists('.sidebar .side-nav');
        self::assertCount(1, $crawler->filter('main.content h1'));
        self::assertNotSame('', trim($crawler->filter('main.content h1')->text()));
        self::assertCount(1, $crawler->filter('.side-nav a[aria-current="page"]'));
        self::assertSame($path, $crawler->filter('.side-nav a[aria-current="page"]')->attr('href'));

        if (null !== $expectedElement) {
            self::assertSelectorExists('main.content '.$expectedElement);
        }
    }

    public static function sidebarPages(): iterable
    {
        yield 'staff dashboard' => ['osas@norsu.edu.ph', '/staff', '.metric-card'];
        yield 'staff appointments' => ['osas@norsu.edu.ph', '/staff/appointments', 'table.data-table'];
        yield 'staff calendar' => ['osas@norsu.edu.ph', '/staff/appointments/calendar', 'a[href="/staff/appointments"]'];
        yield 'staff service requests' => ['osas@norsu.edu.ph', '/staff/services', 'table.data-table'];
        yield 'staff concerns' => ['osas@norsu.edu.ph', '/staff/concerns', 'table.data-table'];
        yield 'staff documents' => ['osas@norsu.edu.ph', '/staff/documents', 'table.data-table'];
        yield 'staff walk-in queue' => ['osas@norsu.edu.ph', '/staff/queue', 'form[method="post"]'];
        yield 'staff announcements' => ['osas@norsu.edu.ph', '/staff/announcements', 'a[href="/staff/announcements/new"]'];
        yield 'student dashboard' => ['student@norsu.edu.ph', '/portal', '.metric-card'];
        yield 'student profile' => ['student@norsu.edu.ph', '/portal/profile', 'form[method="post"]'];
        yield 'student appointments' => ['student@norsu.edu.ph', '/portal/appointments', 'table.data-table'];
        yield 'student walk-in queue' => ['student@norsu.edu.ph', '/portal/queue', 'form[method="post"] select[name="unit"]'];
        yield 'student service requests' => ['student@norsu.edu.ph', '/portal/services', 'table.data-table'];
        yield 'student concerns' => ['student@norsu.edu.ph', '/portal/concerns', 'table.data-table'];
        yield 'student CARE counseling' => ['student@norsu.edu.ph', '/portal/care', 'table.data-table'];
        yield 'student documents' => ['student@norsu.edu.ph', '/portal/documents', 'table.data-table'];
        yield 'student announcements' => ['student@norsu.edu.ph', '/portal/announcements', null];
        yield 'student notifications' => ['student@norsu.edu.ph', '/portal/notifications', null];
        yield 'guidance dashboard' => ['care@norsu.edu.ph', '/care', 'table.data-table'];
        yield 'guidance cases' => ['care@norsu.edu.ph', '/care/cases', 'table.data-table'];
        yield 'VPSAS dashboard' => ['vpsas@norsu.edu.ph', '/vpsas', 'table.data-table'];
    }

    public function testStaffCanNavigateToEverySidebarDestination(): void
    {
        $client = $this->authenticatedClient('osas@norsu.edu.ph');
        $crawler = $client->request('GET', '/staff');
        $paths = $crawler->filter('.side-nav a')->each(static fn ($link): string => $link->attr('href'));

        self::assertSame([
            '/staff',
            '/staff/appointments',
            '/staff/appointments/calendar',
            '/staff/services',
            '/staff/concerns',
            '/staff/documents',
            '/staff/queue',
            '/staff/announcements',
        ], $paths);

        foreach ($paths as $path) {
            $client->click($crawler->filter('.side-nav a[href="'.$path.'"]')->link());
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('.side-nav a[href="'.$path.'"][aria-current="page"]');
            $crawler = $client->getCrawler();
        }
    }

    public function testDashboardCountsAllAppointmentsWhileShowingOnlyRecentRows(): void
    {
        $client = $this->authenticatedClient('osas@norsu.edu.ph');
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'osas@norsu.edu.ph']);
        $appointments = [];
        $idProperty = new \ReflectionProperty(Appointment::class, 'id');
        for ($i = 1; $i <= 12; ++$i) {
            $appointment = (new Appointment())
                ->setStudent($user)
                ->setCampus($user->getCampus())
                ->setUnit($user->getStaffUnit())
                ->setStatus(AppointmentStatus::Approved)
                ->setReferenceCode('APT-TEST-'.$i);
            $idProperty->setValue($appointment, $i);
            $appointments[] = $appointment;
        }
        $repository = $this->createMock(AppointmentRepository::class);
        $repository->expects(self::once())->method('findForStaff')->willReturn($appointments);
        static::getContainer()->set(AppointmentRepository::class, $repository);

        $crawler = $client->request('GET', '/staff');

        self::assertResponseIsSuccessful();
        self::assertSame('12', $crawler->filter('.metric-card[href="/staff/appointments"] .metric-value')->text());
        self::assertCount(5, $crawler->filter('.dashboard-bottom table.data-table tbody tr'));
        self::assertSelectorTextContains('.metric-card[href="/staff/appointments"] .metric-caption', '12 open');
    }

    #[DataProvider('requestForms')]
    public function testRequestFormsRetainTheirControlsAndActiveSidebar(string $email, string $path, string $section, string $fieldName): void
    {
        $client = $this->authenticatedClient($email);
        $client->request('GET', $path);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.side-nav a[href="'.$section.'"][aria-current="page"]');
        self::assertSelectorExists('main.content form[method="post"] [name="'.$fieldName.'"]');
        self::assertSelectorExists('main.content form[method="post"] input[type="hidden"]');
        self::assertSelectorExists('main.content form[method="post"] button[type="submit"]');
    }

    public static function requestForms(): iterable
    {
        yield 'book appointment' => ['student@norsu.edu.ph', '/portal/appointments/new', '/portal/appointments', 'appointment[purpose]'];
        yield 'request service' => ['student@norsu.edu.ph', '/portal/services/new', '/portal/services', 'service_request[subject]'];
        yield 'file concern' => ['student@norsu.edu.ph', '/portal/concerns/new', '/portal/concerns', 'concern[subject]'];
        yield 'request document' => ['student@norsu.edu.ph', '/portal/documents/new', '/portal/documents', 'document_request[purpose]'];
        yield 'request counseling' => ['student@norsu.edu.ph', '/portal/care/new', '/portal/care', 'care_request[reason]'];
        yield 'publish announcement' => ['osas@norsu.edu.ph', '/staff/announcements/new', '/staff/announcements', 'announcement[title]'];
    }

    #[DataProvider('protectedWorkspaces')]
    public function testAnonymousVisitorsMustSignIn(string $path): void
    {
        $client = static::createClient();
        $client->request('GET', $path);

        self::assertResponseRedirects('http://localhost/login');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action="/login"] input[name="_password"]');
    }

    public static function protectedWorkspaces(): iterable
    {
        yield 'staff' => ['/staff'];
        yield 'student' => ['/portal'];
        yield 'guidance' => ['/care'];
        yield 'executive' => ['/vpsas'];
    }

    private function authenticatedClient(string $email): KernelBrowser
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user, 'The test database must contain the existing demo account '.$email.'.');
        $client->loginUser($user);

        return $client;
    }
}
