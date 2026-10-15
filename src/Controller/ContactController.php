<?php

namespace App\Controller;

use App\Dto\ContactMessage;
use App\Form\ContactMessageType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

class ContactController extends AbstractController
{
    /**
     * @param array{address: string, office_hours: string, email: string, phones: list<array{label: string, number: string}>} $contact
     */
    #[Route('/contact', name: 'app_contact')]
    public function index(
        Request $request,
        MailerInterface $mailer,
        #[Autowire(param: 'app.contact')] array $contact,
    ): Response {
        $inquiry = new ContactMessage();
        $form = $this->createForm(ContactMessageType::class, $inquiry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = (new Email())
                ->from(new Address($contact['email'], 'NORSU-SAS Insight'))
                ->to($contact['email'])
                ->replyTo(new Address($inquiry->email, $inquiry->name))
                ->subject('Website inquiry from '.$inquiry->name)
                ->text(\sprintf("Name: %s\nEmail: %s\nPhone: %s\n\n%s", $inquiry->name, $inquiry->email, $inquiry->phone ?: '-', $inquiry->message));

            try {
                $mailer->send($email);
                $this->addFlash('success', 'Thank you! Your message has been sent. We will get back to you soon.');

                return $this->redirectToRoute('app_contact');
            } catch (TransportExceptionInterface) {
                $form->addError(new FormError('We could not send your message right now. Please try again later or call us.'));
            }
        }

        return $this->render('contact/index.html.twig', [
            'form' => $form,
            'contact' => $contact,
        ]);
    }
}
