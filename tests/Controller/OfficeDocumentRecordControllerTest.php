<?php

namespace App\Tests\Controller;

use App\DataFixtures\AppFixtures;
use App\Entity\Campus;
use App\Entity\OfficeDocumentRecord;
use App\Entity\User;
use App\Enum\DocumentRecordStatus;
use App\Service\StaffWorkspace;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OfficeDocumentRecordControllerTest extends WebTestCase
{
    private array $databaseEnvironment;
    private KernelBrowser $client;
    private EntityManagerInterface $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->databaseEnvironment = [$_ENV['DATABASE_URL'] ?? null, $_SERVER['DATABASE_URL'] ?? null];
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
        $this->client = self::createClient();
        $this->client->disableReboot();
        $this->manager = self::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($this->manager))->createSchema($this->manager->getMetadataFactory()->getAllMetadata());
        self::getContainer()->get(AppFixtures::class)->load($this->manager);
        $this->login('studentgovernment@norsu.edu.ph');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach (['_ENV', '_SERVER'] as $index => $global) {
            if (null === $this->databaseEnvironment[$index]) {
                unset($GLOBALS[$global]['DATABASE_URL']);
            } else {
                $GLOBALS[$global]['DATABASE_URL'] = $this->databaseEnvironment[$index];
            }
        }
    }

    public function testDropdownsContainTheCampusListAndExactlyFourStatuses(): void
    {
        $crawler = $this->client->request('GET', '/staff/document-records/new');
        self::assertResponseIsSuccessful();
        self::assertCount($this->manager->getRepository(Campus::class)->count([]), $crawler->filter('select[name="office_document_record[campus]"] option')->reduce(static fn ($option) => '' !== $option->attr('value')));
        self::assertSame(['Pending', 'Approved', 'For releasing', 'Released'], $crawler->filter('select[name="office_document_record[status]"] option')->each(static fn ($option) => $option->text()));
        self::assertSelectorNotExists('select[name*="student"]');
        self::assertSelectorExists('.side-nav a[href="/staff/documents"][aria-current="page"]');
    }

    public function testOfficialRecordsEditsPrintsExportsAndDeletesAnOfficeDocument(): void
    {
        $this->submit();
        self::assertResponseStatusCodeSame(303);
        $record = $this->manager->getRepository(OfficeDocumentRecord::class)->findOneBy([]);
        self::assertNotNull($record);
        $id = $record->getId();
        self::assertSame('BAIS1', $record->getCampus()->getCode());
        self::assertSame('1234.50', $record->getAmount());
        self::assertSame('2026-10-20', $record->getReceivedAt()->format('Y-m-d'));
        self::assertSame('Roche Cabanlit', $record->getReceivedBy());
        self::assertSame(DocumentRecordStatus::Pending, $record->getStatus());
        $this->client->request('GET', '/staff/documents');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.office-document-table', 'Office certification payment');
        self::assertSelectorTextContains('.office-document-table', 'Bais Campus I');
        $this->client->request('GET', '/staff/document-records/'.$id.'/edit');
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Save changes', [
            'office_document_record[status]' => 'released',
            'office_document_record[releasedAt]' => '2026-10-22',
            'office_document_record[releasedBy]' => 'Releasing Officer',
            'office_document_record[particular]' => 'Updated office certification',
        ]);
        self::assertResponseStatusCodeSame(303);
        $record = $this->manager->find(OfficeDocumentRecord::class, $id);
        self::assertSame(DocumentRecordStatus::Released, $record->getStatus());
        self::assertSame('2026-10-22', $record->getReleasedAt()->format('Y-m-d'));
        self::assertSame('Roche Cabanlit', $record->getReleasedBy());
        foreach (['/staff/document-records/'.$id.'/print', '/staff/document-records/print'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('table', 'Updated office certification');
            self::assertSelectorTextContains('table', 'Roche Cabanlit');
            self::assertSelectorExists('[data-print-document]');
        }
        foreach (['/staff/document-records/'.$id.'/pdf', '/staff/document-records/pdf'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertResponseHeaderSame('Content-Type', 'application/pdf');
            self::assertStringStartsWith('%PDF-', $this->client->getResponse()->getContent());
            self::assertStringContainsString('%%EOF', $this->client->getResponse()->getContent());
            self::assertStringContainsString('attachment;', $this->client->getResponse()->headers->get('Content-Disposition'));
        }
        $this->client->request('POST', '/staff/document-records/'.$id.'/delete', ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        $crawler = $this->client->request('GET', '/staff/document-records/'.$id);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Updated office certification');
        $token = $crawler->filter('form[action$="/delete"] input[name="_token"]')->attr('value');
        $this->client->request('POST', '/staff/document-records/'.$id.'/delete', ['_token' => $token]);
        self::assertResponseRedirects('/staff/documents', 303);
        self::assertSame(0, $this->manager->getRepository(OfficeDocumentRecord::class)->count([]));
        $this->client->request('GET', '/staff/document-records/'.$id);
        self::assertResponseStatusCodeSame(404);
    }

    public function testEveryStatusCanBeSaved(): void
    {
        foreach (DocumentRecordStatus::cases() as $status) {
            $this->submit(['status' => $status->value, 'releasedAt' => '2026-10-22', 'releasedBy' => 'Releasing Officer']);
            self::assertResponseStatusCodeSame(303);
        }
        self::assertSame(4, $this->manager->getRepository(OfficeDocumentRecord::class)->count([]));
    }

    public function testInvalidRecordsAreRejectedWithoutSaving(): void
    {
        foreach ([
            ['particular' => ''],
            ['amount' => '-0.01'],
            ['amount' => 'not money'],
            ['amount' => '10000000000'],
            ['receivedAt' => ''],
            ['campus' => '999999'],
            ['status' => 'processing'],
            ['status' => 'released', 'releasedAt' => '', 'releasedBy' => ''],
            ['releasedAt' => '2026-10-19', 'releasedBy' => 'Too early'],
        ] as $changes) {
            $this->submit($changes, false);
            self::assertResponseStatusCodeSame(422, json_encode($changes));
            self::assertSame(0, $this->manager->getRepository(OfficeDocumentRecord::class)->count([]));
        }
    }

    public function testOtherOfficesAndStudentsCannotAccessTheRecordsOrExports(): void
    {
        $this->login('studententerprise@norsu.edu.ph');
        $this->submit(['particular' => 'Foreign office secret']);
        self::assertResponseStatusCodeSame(303);
        $id = $this->manager->getRepository(OfficeDocumentRecord::class)->findOneBy([])->getId();
        $this->login('studentgovernment@norsu.edu.ph');
        foreach (['/staff/documents', '/staff/document-records/print'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertStringNotContainsString('Foreign office secret', $this->client->getResponse()->getContent());
        }
        foreach (['', '/edit', '/print', '/pdf'] as $suffix) {
            $this->client->request('GET', '/staff/document-records/'.$id.$suffix);
            self::assertResponseStatusCodeSame(403);
        }
        $this->client->request('POST', '/staff/document-records/'.$id.'/delete');
        self::assertResponseStatusCodeSame(403);
        $campus = $this->manager->getRepository(Campus::class)->findOneBy(['code' => 'MAIN']);
        $student = (new User())->setEmail('register-student@example.test')->setFirstName('Student')->setLastName('Test')->setPassword('unused')->setCampus($campus)->setRoles(['ROLE_STUDENT']);
        $this->manager->persist($student);
        $this->manager->flush();
        $this->client->loginUser($student);
        foreach (['/staff/document-records/new', '/staff/document-records/'.$id, '/staff/document-records/print', '/staff/document-records/pdf'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseStatusCodeSame(403);
        }
    }

    public function testDirectorsAndVpsasCanRecordDocumentsForTheirOffices(): void
    {
        foreach (['sds@norsu.edu.ph', 'sws@norsu.edu.ph', 'isps@norsu.edu.ph', 'vpsas@norsu.edu.ph', 'care@norsu.edu.ph'] as $email) {
            $official = $this->login($email);
            $expected = self::getContainer()->get(StaffWorkspace::class)->personalUnit($official)->getId();
            $this->submit(['particular' => $email]);
            self::assertResponseStatusCodeSame(303);
            $record = $this->manager->getRepository(OfficeDocumentRecord::class)->findOneBy(['particular' => $email]);
            self::assertSame($expected, $record->getUnit()->getId());
            $this->client->request('GET', '/staff/document-records/'.$record->getId());
            self::assertResponseIsSuccessful();
        }
    }

    public function testOfficialsCanAddEditAndDeleteRowsDirectlyFromTheMonitoringTable(): void
    {
        $campus = $this->manager->getRepository(Campus::class)->findOneBy(['code' => 'BAIS1']);
        $crawler = $this->client->request('GET', '/staff/documents');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller="document-sheet"]');
        self::assertSelectorNotExists('.office-document-table button[type="submit"]');
        $form = $crawler->filter('form[name="office_record_new"]')->form([
            'office_record_new[campus]' => $campus->getId(),
            'office_record_new[particular]' => 'Inline register entry',
            'office_record_new[amount]' => '250.50',
            'office_record_new[receivedAt]' => '2026-10-20',
            'office_record_new[receivedBy]' => 'Inline Receiver',
            'office_record_new[status]' => 'pending',
        ]);
        $this->client->submit($form, [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $response = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertStringContainsString('Inline register entry', $response['html']);
        self::assertStringContainsString('data-new="false"', $response['html']);
        self::assertStringContainsString('data-new="true"', $response['newRow']);
        $record = $this->manager->getRepository(OfficeDocumentRecord::class)->findOneBy(['particular' => 'Inline register entry']);
        self::assertNotNull($record);
        $id = $record->getId();
        $crawler = $this->client->request('GET', '/staff/documents', server: ['HTTP_ACCEPT' => 'text/html']);
        $form = $crawler->filter('tr[data-record-id="'.$id.'"]')->filter('form')->form([
            'office_record_'.$id.'[particular]' => 'Edited directly in the sheet',
            'office_record_'.$id.'[status]' => 'approved',
        ]);
        $this->client->submit($form, [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        $record = $this->manager->find(OfficeDocumentRecord::class, $id);
        self::assertSame('Edited directly in the sheet', $record->getParticular());
        self::assertSame(DocumentRecordStatus::Approved, $record->getStatus());
        $response = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertNull($response['newRow']);
        $crawler = $this->client->request('GET', '/staff/documents', server: ['HTTP_ACCEPT' => 'text/html']);
        $delete = $crawler->filter('tr[data-record-id="'.$id.'"] button[data-action="document-sheet#remove"]');
        $this->client->request('POST', $delete->attr('data-url'), ['_token' => $delete->attr('data-token')], server: ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString('{"deleted":true}', $this->client->getResponse()->getContent());
        self::assertSame(0, $this->manager->getRepository(OfficeDocumentRecord::class)->count([]));
    }

    public function testInlineRowErrorsPreserveInputAndDoNotSaveInvalidOrUnprotectedRequests(): void
    {
        $crawler = $this->client->request('GET', '/staff/documents');
        $form = $crawler->filter('form[name="office_record_new"]')->form([
            'office_record_new[particular]' => 'Keep this entry',
            'office_record_new[receivedAt]' => '',
        ]);
        $this->client->submit($form, [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(422);
        $response = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertStringContainsString('Keep this entry', $response['html']);
        self::assertStringContainsString('This value should not be null.', $response['html']);
        self::assertSame(0, $this->manager->getRepository(OfficeDocumentRecord::class)->count([]));
        $crawler = $this->client->request('GET', '/staff/documents', server: ['HTTP_ACCEPT' => 'text/html']);
        $form = $crawler->filter('form[name="office_record_new"]')->form([
            'office_record_new[particular]' => 'Invalid token entry',
            'office_record_new[_token]' => 'invalid',
        ]);
        $this->client->submit($form, [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->manager->getRepository(OfficeDocumentRecord::class)->count([]));
    }

    public function testSharedAccountUsesTheActiveProfileAndPreservesReceiptAndReleaseNames(): void
    {
        $this->login('vpsas@norsu.edu.ph');
        $profiles = \App\Enum\OfficeProfile::cases();
        foreach ($profiles as $index => $receiver) {
            $releaser = $profiles[($index + 1) % count($profiles)];
            $editor = $profiles[($index + 2) % count($profiles)];
            $this->switchProfile($receiver->label());
            $crawler = $this->client->request('GET', '/staff/documents');
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('input[name="office_record_new[receivedBy]"][readonly][value="'.$receiver->label().'"]');
            $form = $crawler->filter('form[name="office_record_new"]')->form([
                'office_record_new[particular]' => 'Received by '.$receiver->value,
                'office_record_new[receivedBy]' => 'Forged receiver',
            ]);
            $this->client->submit($form, [], ['HTTP_ACCEPT' => 'application/json']);
            self::assertResponseIsSuccessful();
            $record = $this->manager->getRepository(OfficeDocumentRecord::class)->findOneBy(['particular' => 'Received by '.$receiver->value]);
            $id = $record->getId();
            self::assertSame($receiver->label(), $record->getReceivedBy());
            self::assertNull($record->getReleasedBy());
            $this->switchProfile($releaser->label());
            $crawler = $this->client->request('GET', '/staff/documents', server: ['HTTP_ACCEPT' => 'text/html']);
            $form = $crawler->filter('tr[data-record-id="'.$id.'"]')->filter('form')->form([
                'office_record_'.$id.'[status]' => 'released',
                'office_record_'.$id.'[releasedAt]' => '2026-10-24',
                'office_record_'.$id.'[receivedBy]' => 'Changed receiver',
                'office_record_'.$id.'[releasedBy]' => 'Forged releaser',
            ]);
            $this->client->submit($form, [], ['HTTP_ACCEPT' => 'application/json']);
            self::assertResponseIsSuccessful();
            $record = $this->manager->find(OfficeDocumentRecord::class, $id);
            self::assertSame($receiver->label(), $record->getReceivedBy());
            self::assertSame($releaser->label(), $record->getReleasedBy());
            $this->switchProfile($editor->label());
            $this->client->request('GET', '/staff/document-records/'.$id.'/edit');
            $this->client->submitForm('Save changes', ['office_document_record[amount]' => '20.00']);
            self::assertResponseStatusCodeSame(303);
            $record = $this->manager->find(OfficeDocumentRecord::class, $id);
            self::assertSame($receiver->label(), $record->getReceivedBy());
            self::assertSame($releaser->label(), $record->getReleasedBy());
            $this->client->request('GET', '/staff/document-records/'.$id.'/print');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('table', $receiver->label());
            self::assertSelectorTextContains('table', $releaser->label());
        }
    }

    public function testAutosaveUsesSessionTokensAfterTheStatelessDoubleSubmitStrategyWasUsed(): void
    {
        $crawler = $this->client->request('GET', '/staff/documents');
        $session = $this->client->getRequest()->getSession();
        $session->set('csrf-token', 3);
        $session->save();
        $token = $crawler->filter('input[name="office_record_new[_token]"]')->attr('value');
        self::assertNotSame('csrf-token', $token);
        $form = $crawler->filter('form[name="office_record_new"]')->form(['office_record_new[particular]' => 'Saved without a double-submit cookie']);
        $this->client->submit($form, [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->manager->getRepository(OfficeDocumentRecord::class)->count([]));
        $crawler = $this->client->request('GET', '/staff/documents');
        $form = $crawler->filter('form[name="office_record_new"]')->form(['office_record_new[particular]' => 'Rejected token', 'office_record_new[_token]' => 'invalid']);
        $this->client->submit($form, [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(1, $this->manager->getRepository(OfficeDocumentRecord::class)->count([]));
    }

    public function testInsertRowReturnsIndependentRowsThatCanBothAutosave(): void
    {
        $names = [];
        foreach (['First inserted row', 'Second inserted row'] as $particular) {
            $this->client->request('GET', '/staff/document-records/row');
            self::assertResponseIsSuccessful();
            $data = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
            $crawler = new \Symfony\Component\DomCrawler\Crawler('<table><tbody>'.$data['html'].'</tbody></table>', 'http://localhost/staff/documents');
            $name = $crawler->filter('form')->attr('name');
            $names[] = $name;
            $form = $crawler->filter('form')->form([$name.'[particular]' => $particular]);
            $this->client->submit($form, [], ['HTTP_ACCEPT' => 'application/json']);
            self::assertResponseIsSuccessful();
        }
        self::assertNotSame($names[0], $names[1]);
        self::assertSame(2, $this->manager->getRepository(OfficeDocumentRecord::class)->count([]));
    }

    public function testInsertedColumnsAndValuesPersistAndAppearInPrintAndPdf(): void
    {
        $crawler = $this->client->request('GET', '/staff/documents');
        self::assertSelectorExists('.sheet-insert button[data-action="document-sheet#addRow"]');
        self::assertSelectorExists('.sheet-insert button[data-action="document-sheet#showColumn"]');
        $this->client->submit($crawler->selectButton('Insert column')->form(['office_document_column[label]' => 'Reference number']), [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(201);
        $column = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $id = $column['id'];
        $crawler = $this->client->request('GET', '/staff/documents', server: ['HTTP_ACCEPT' => 'text/html']);
        self::assertSelectorTextContains('th[data-column-id="'.$id.'"]', 'Reference number');
        $form = $crawler->filter('form[name="office_record_new"]')->form(['office_record_new[particular]' => 'Custom column record', 'office_record_new[column_'.$id.']' => 'REF-2026-001']);
        $this->client->submit($form, [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        $record = $this->manager->getRepository(OfficeDocumentRecord::class)->findOneBy(['particular' => 'Custom column record']);
        self::assertSame('REF-2026-001', $record->getExtraData()[$id]);
        $crawler = $this->client->request('GET', '/staff/documents');
        self::assertSelectorExists('input[name="office_record_'.$record->getId().'[column_'.$id.']"][value="REF-2026-001"]');
        $form = $crawler->filter('form[name="office_record_'.$record->getId().'"]')->form(['office_record_'.$record->getId().'[column_'.$id.']' => 'REF-UPDATED']);
        $this->client->submit($form, [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/staff/document-records/print');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Reference number');
        self::assertSelectorTextContains('table', 'REF-UPDATED');
        $this->client->request('GET', '/staff/document-records/pdf');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertStringStartsWith('%PDF-', $this->client->getResponse()->getContent());
    }

    public function testColumnNamesAreValidatedProtectedAndScopedToTheOffice(): void
    {
        foreach (['', str_repeat('x', 81)] as $label) {
            $crawler = $this->client->request('GET', '/staff/documents');
            $form = $crawler->selectButton('Insert column')->form(['office_document_column[label]' => $label]);
            $this->client->submit($form, [], ['HTTP_ACCEPT' => 'application/json']);
            self::assertResponseStatusCodeSame(422);
        }
        $crawler = $this->client->request('GET', '/staff/documents');
        $form = $crawler->selectButton('Insert column')->form(['office_document_column[label]' => 'Secret office field', 'office_document_column[_token]' => 'invalid']);
        $this->client->submit($form, [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(422);
        $crawler = $this->client->request('GET', '/staff/documents');
        $form = $crawler->selectButton('Insert column')->form(['office_document_column[label]' => 'Secret office field']);
        $this->client->submit($form, [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(201);
        $crawler = $this->client->request('GET', '/staff/documents');
        $form = $crawler->selectButton('Insert column')->form(['office_document_column[label]' => 'Secret office field']);
        $this->client->submit($form, [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(1, $this->manager->getRepository(\App\Entity\OfficeDocumentColumn::class)->count([]));
        $this->login('studententerprise@norsu.edu.ph');
        $this->client->request('GET', '/staff/documents');
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('Secret office field', $this->client->getResponse()->getContent());
    }

    public function testOfficialsCanChooseHowManyRowsAndColumnsToInsert(): void
    {
        $crawler = $this->client->request('GET', '/staff/documents');
        self::assertCount(20, $crawler->filter('select[data-row-count] option'));
        self::assertCount(10, $crawler->filter('select[name="office_document_column[count]"] option'));
        $this->client->request('GET', '/staff/document-records/row?count=3');
        self::assertResponseIsSuccessful();
        $response = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertCount(3, $response['rows']);
        $names = [];
        foreach ($response['rows'] as $html) {
            $row = new \Symfony\Component\DomCrawler\Crawler('<table><tbody>'.$html.'</tbody></table>');
            $names[] = $row->filter('form')->attr('name');
        }
        self::assertCount(3, array_unique($names));
        self::assertSame(0, $this->manager->getRepository(OfficeDocumentRecord::class)->count([]));
        foreach ([0, 21] as $count) {
            $this->client->request('GET', '/staff/document-records/row?count='.$count);
            self::assertResponseStatusCodeSame(422);
        }
        $crawler = $this->client->request('GET', '/staff/documents');
        $form = $crawler->selectButton('Insert column')->form(['office_document_column[label]' => 'Batch reference', 'office_document_column[count]' => '3']);
        $this->client->submit($form, [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(201);
        $response = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['Batch reference 1', 'Batch reference 2', 'Batch reference 3'], array_column($response['columns'], 'label'));
        self::assertSame(3, $this->manager->getRepository(\App\Entity\OfficeDocumentColumn::class)->count([]));
        $this->client->request('GET', '/staff/documents');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.office-document-table thead', 'Batch reference 3');
    }

    public function testColumnBatchesRejectInvalidCountsAndConflictsWithoutPartiallyInserting(): void
    {
        foreach ([0, 11] as $count) {
            $crawler = $this->client->request('GET', '/staff/documents');
            $form = $crawler->selectButton('Insert column')->form(['office_document_column[label]' => 'Invalid count']);
            $values = $form->getPhpValues();
            $values['office_document_column']['count'] = (string) $count;
            $this->client->request('POST', $form->getUri(), $values, server: ['HTTP_ACCEPT' => 'application/json']);
            self::assertResponseStatusCodeSame(422);
        }
        $crawler = $this->client->request('GET', '/staff/documents');
        $form = $crawler->selectButton('Insert column')->form(['office_document_column[label]' => 'Existing 2']);
        $this->client->submit($form, [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(201);
        $crawler = $this->client->request('GET', '/staff/documents');
        $form = $crawler->selectButton('Insert column')->form(['office_document_column[label]' => 'Existing', 'office_document_column[count]' => '3']);
        $this->client->submit($form, [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(1, $this->manager->getRepository(\App\Entity\OfficeDocumentColumn::class)->count([]));
        self::assertNull($this->manager->getRepository(\App\Entity\OfficeDocumentColumn::class)->findOneBy(['label' => 'Existing 1']));
    }

    private function switchProfile(string $name): void
    {
        $this->client->request('GET', '/vpsas', server: ['HTTP_ACCEPT' => 'text/html']);
        $this->client->submitForm($name);
        self::assertResponseRedirects('/vpsas/profile', 303);
    }

    private function login(string $email): User
    {
        $official = $this->manager->getRepository(User::class)->findOneBy(['email' => $email]);
        $this->client->loginUser($official);

        return $official;
    }

    private function submit(array $changes = [], bool $validate = true): void
    {
        $campus = $this->manager->getRepository(Campus::class)->findOneBy(['code' => 'BAIS1']);
        $values = array_replace(['campus' => (string) $campus->getId(), 'particular' => 'Office certification payment', 'amount' => '1234.50', 'receivedAt' => '2026-10-20', 'receivedBy' => 'Receiving Officer', 'releasedAt' => '', 'releasedBy' => '', 'status' => 'pending'], $changes);
        $crawler = $this->client->request('GET', '/staff/document-records/new');
        self::assertResponseIsSuccessful();
        if (!$validate) {
            $values['_token'] = $crawler->filter('input[name="office_document_record[_token]"]')->attr('value');
            $this->client->request('POST', '/staff/document-records/new', ['office_document_record' => $values]);

            return;
        }
        $fields = [];
        foreach ($values as $key => $value) {
            $fields['office_document_record['.$key.']'] = $value;
        }
        $this->client->submitForm('Save record', $fields);
    }
}
