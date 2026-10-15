<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class ContactControllerTest extends WebTestCase
{
    public function testContactPageDisplaysTheFormAndOfficeDetails(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/contact');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Get In Touch');
        self::assertSelectorExists('.landing-nav a[href="/contact"][aria-current="page"]');
        self::assertSelectorExists('.contact-details a[href="mailto:sas@norsu.edu.ph"]');
        self::assertSelectorTextContains('.contact-details', 'Monday to Friday');
        self::assertCount(3, $crawler->filter('.contact-list a[href^="tel:"]'));
        self::assertSelectorExists('form.contact-card[method="post"] input[name="contact_message[_token]"]');
        self::assertSelectorExists('form.contact-card[aria-labelledby="contact-form-title"]');
        self::assertSelectorTextContains('#contact-form-title', 'Send a message');
        self::assertSelectorExists('label[for="contact_message_name"]');
        self::assertSelectorExists('input[name="contact_message[name]"][required]');
        self::assertSelectorExists('input[name="contact_message[email]"][type="email"][required]');
        self::assertSelectorExists('input[name="contact_message[phone]"][type="tel"]:not([required])');
        self::assertSelectorExists('textarea[name="contact_message[message]"][required]');
    }

    #[DataProvider('phoneNumbers')]
    public function testValidMessageIsSentWithTheSenderAsReplyTo(?string $phone): void
    {
        $client = static::createClient();
        $client->disableReboot();

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->with(self::callback(static function (Email $email) use ($phone): bool {
            self::assertSame('sas@norsu.edu.ph', $email->getFrom()[0]->getAddress());
            self::assertSame('sas@norsu.edu.ph', $email->getTo()[0]->getAddress());
            self::assertSame('juan@example.com', $email->getReplyTo()[0]->getAddress());
            self::assertSame('Juan Dela Cruz', $email->getReplyTo()[0]->getName());
            self::assertSame('Website inquiry from Juan Dela Cruz', $email->getSubject());
            self::assertStringContainsString('Phone: '.($phone ?? '-'), $email->getTextBody());
            self::assertStringContainsString('Please help me book a guidance appointment.', $email->getTextBody());

            return true;
        }));
        static::getContainer()->set(MailerInterface::class, $mailer);
        $client->request('GET', '/contact');

        $message = self::validMessage();
        $message['contact_message[phone]'] = $phone ?? '';
        $client->submitForm('Submit', $message);

        self::assertResponseRedirects('/contact');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.flash', 'Your message has been sent.');
        self::assertSelectorExists('input[name="contact_message[name]"]:not([value])');
    }

    public static function phoneNumbers(): iterable
    {
        yield 'with a phone number' => ['0917 123 4567'];
        yield 'without a phone number' => [null];
    }

    public function testInvalidMessageShowsValidationErrorsWithoutSendingEmail(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');
        static::getContainer()->set(MailerInterface::class, $mailer);
        $client->request('GET', '/contact');

        $client->submitForm('Submit', [
            'contact_message[name]' => '',
            'contact_message[email]' => 'invalid-address',
            'contact_message[phone]' => 'call me',
            'contact_message[message]' => 'Short',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.contact-card', 'This value should not be blank.');
        self::assertSelectorTextContains('.contact-card', 'This value is not a valid email address.');
        self::assertSelectorTextContains('.contact-card', 'Use digits, spaces');
        self::assertSelectorTextContains('.contact-card', '10 characters or more');
        self::assertSelectorExists('input[name="contact_message[email]"][value="invalid-address"]');
        self::assertSelectorExists('.cf-errors[role="alert"]');
        self::assertSelectorExists('input[name="contact_message[email]"][aria-invalid="true"][aria-describedby="contact_message_email-errors"]');
        self::assertSelectorTextContains('#contact_message_email-errors', 'This value is not a valid email address.');
    }

    public function testCrossOriginSubmissionIsRejectedWithoutSendingEmail(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');
        static::getContainer()->set(MailerInterface::class, $mailer);
        $client->request('GET', '/contact');

        $client->submitForm('Submit', self::validMessage(), 'POST', [
            'HTTP_ORIGIN' => 'https://unrelated.example',
            'HTTP_REFERER' => 'https://unrelated.example/contact',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.contact-card', 'The CSRF token is invalid.');
    }

    public function testMailFailureKeepsTheMessageAndDisplaysRetryInstructions(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willThrowException(new TransportException('Delivery unavailable.'));
        static::getContainer()->set(MailerInterface::class, $mailer);
        $client->request('GET', '/contact');

        $client->submitForm('Submit', self::validMessage());

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.contact-card', 'Please try again later or call us.');
        self::assertSelectorExists('input[name="contact_message[name]"][value="Juan Dela Cruz"]');
        self::assertSelectorTextContains('textarea[name="contact_message[message]"]', 'Please help me book a guidance appointment.');
    }

    /**
     * @return array<string, string>
     */
    private static function validMessage(): array
    {
        return [
            'contact_message[name]' => 'Juan Dela Cruz',
            'contact_message[email]' => 'juan@example.com',
            'contact_message[phone]' => '0917 123 4567',
            'contact_message[message]' => 'Please help me book a guidance appointment.',
        ];
    }
}
