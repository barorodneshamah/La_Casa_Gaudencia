<?php

namespace App\Controller;

use App\Entity\ContactMessage;
use App\Entity\ContactReply;
use App\Form\ContactMessageType;
use App\Repository\ContactMessageRepository;
use App\Repository\PaymentRepository;
use App\Repository\ReservationRepository;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ContactMessageController extends AbstractController
{

    #[Route('/contact', name: 'app_contact')]
    public function contact(Request $request, EntityManagerInterface $em): Response
    {
        $contactMessage = new ContactMessage();
        $form = $this->createForm(ContactMessageType::class, $contactMessage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($contactMessage->getSubject() === 'Other') {
                $custom = trim((string) $form->get('otherSubject')->getData());
                $contactMessage->setSubject($custom ?: 'Other');
            }
            $contactMessage->setIpAddress($request->getClientIp());
            $em->persist($contactMessage);
            $em->flush();

            $this->addFlash('contact_success', 'Thank you! We will get back to you within 24 hours.');
            return $this->redirectToRoute('app_contact');
        }

        return $this->render('contact/index.html.twig', [
            'contactForm' => $form->createView()
        ]);
    }

    #[Route('/dashboard/messages', name: 'app_contact_message_index')]
    public function index(Request $request, ContactMessageRepository $repo): Response
    {
        $status = $request->query->get('status', 'all');
        $search = $request->query->get('search', '');

        if ($search) {
            $messages = $repo->search($search);
        } elseif ($status !== 'all') {
            $messages = $repo->findByStatus($status);
        } else {
            $messages = $repo->findAllOrdered();
        }

        return $this->render('contact_message/index.html.twig', [
            'messages'      => $messages,
            'stats'         => $repo->getStats(),
            'currentStatus' => $status,
            'searchQuery'   => $search,
            'unread_count'  => $repo->countUnread(),
        ]);
    }

    #[Route('/dashboard/messages/{id}', name: 'app_contact_message_show')]
    public function show(ContactMessage $message, EntityManagerInterface $em, ContactMessageRepository $repo): Response
    {
        
        $message->markAsRead();
        $em->flush();

        return $this->render('contact_message/show.html.twig', [
            'message'      => $message,
            'unread_count' => $repo->countUnread(),
        ]);
    }

    #[IsGranted('ROLE_STAFF')]
    #[Route('/dashboard/messages/{id}/reply', name: 'app_contact_message_reply', methods: ['POST'])]
    public function reply(
        ContactMessage $message,
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer,
        NotificationService $notificationService,
        UserRepository $userRepository
    ): Response {
        $replyText = trim($request->request->get('reply_message', ''));

        if (empty($replyText)) {
            $this->addFlash('error', 'Reply message cannot be empty.');
            return $this->redirectToRoute('app_contact_message_show', ['id' => $message->getId()]);
        }

        // Save reply to database
        $reply = new ContactReply();
        $reply->setContactMessage($message);
        $reply->setRepliedBy($this->getUser());
        $reply->setReplyMessage($replyText);

        // Update message status to 'replied'
        $message->setStatus('replied');

        $em->persist($reply);
        try {
            $em->flush();
        } catch (\Throwable) {
            $this->addFlash('error', 'Failed to save reply. Please try again.');
            return $this->redirectToRoute('app_contact_message_show', ['id' => $message->getId()]);
        }

        // Push notification — send once per reply chain, collapse duplicates on device
        if ($message->getPushSentAt() === null) {
            $user = $userRepository->findOneBy(['email' => $message->getEmail()]);
            if ($user && $user->getFcmToken()) {
                try {
                    $notificationService->sendToDevice(
                        $user->getFcmToken(),
                        'Reply to your message',
                        "Re: {$message->getSubject()}",
                        ['type' => 'contact_reply', 'messageId' => (string) $message->getId()],
                        'contact_reply_' . $message->getId()
                    );
                    $message->markPushSent();
                    $em->flush();
                } catch (\Throwable) {
                }
            }
        }

        // Send email to the original sender
        try {
            $email = (new Email())
                ->from('barorodneshamah@gmail.com')
                ->to($message->getEmail())
                ->subject('Re: ' . $message->getSubject())
                ->text(
                    "Hello {$message->getFullName()},\n\n" .
                    $replyText .
                    "\n\n---\nThis is a reply to your message submitted with subject: \"{$message->getSubject()}\""
                );
            $mailer->send($email);
            $this->addFlash('success', 'Reply sent and saved successfully.');
        } catch (\Exception) {
            $this->addFlash('success', 'Reply saved to database. Note: email delivery failed — check your mailer configuration.');
        }

        return $this->redirectToRoute('app_contact_message_show', ['id' => $message->getId()]);
    }

    #[Route('/dashboard/messages/{id}/status', name: 'app_contact_message_status', methods: ['POST'])]
    public function updateStatus(ContactMessage $message, Request $request, EntityManagerInterface $em): Response
    {
        $status = $request->request->get('status');

        if (in_array($status, ['unread', 'read', 'replied', 'archived'])) {
            $message->setStatus($status);
            if ($status === 'read' && !$message->getReadAt()) {
                $message->setReadAt(new \DateTimeImmutable());
            }
            $em->flush();
            $this->addFlash('success', 'Status updated successfully.');
        }

        return $this->redirectToRoute('app_contact_message_show', ['id' => $message->getId()]);
    }

    #[Route('/dashboard/messages/{id}/notes', name: 'app_contact_message_notes', methods: ['POST'])]
    public function updateNotes(ContactMessage $message, Request $request, EntityManagerInterface $em): Response
    {
        $notes = $request->request->get('notes');
        $message->setNotes($notes);
        $em->flush();

        $this->addFlash('success', 'Notes saved successfully.');
        return $this->redirectToRoute('app_contact_message_show', ['id' => $message->getId()]);
    }

    #[Route('/dashboard/api/unread-count', name: 'app_contact_unread_count', methods: ['GET'])]
    public function unreadCount(
        ContactMessageRepository $repo,
        ReservationRepository $reservationRepo,
        PaymentRepository $paymentRepo
    ): JsonResponse {
        $count = $repo->countUnread()
            + $reservationRepo->count(['status' => 'PENDING'])
            + $paymentRepo->count(['status' => 'PENDING']);
        return new JsonResponse(['count' => $count]);
    }

    #[Route('/dashboard/messages/{id}/delete', name: 'app_contact_message_delete', methods: ['POST'])]
    public function delete(ContactMessage $message, EntityManagerInterface $em): Response
    {
        $em->remove($message);
        $em->flush();

        $this->addFlash('success', 'Message deleted successfully.');
        return $this->redirectToRoute('app_contact_message_index');
    }

    #[Route('/dashboard/messages/bulk-action', name: 'app_contact_message_bulk', methods: ['POST'])]
    public function bulkAction(Request $request, ContactMessageRepository $repo, EntityManagerInterface $em): Response
    {
        $action = $request->request->get('action');
        $ids    = $request->request->all('messages');

        if (empty($ids)) {
            $this->addFlash('error', 'No messages selected.');
            return $this->redirectToRoute('app_contact_message_index');
        }

        foreach ($ids as $id) {
            $message = $repo->find($id);
            if ($message) {
                switch ($action) {
                    case 'mark_read':
                        $message->setStatus('read');
                        if (!$message->getReadAt()) {
                            $message->setReadAt(new \DateTimeImmutable());
                        }
                        break;
                    case 'mark_unread':
                        $message->setStatus('unread');
                        break;
                    case 'archive':
                        $message->setStatus('archived');
                        break;
                    case 'delete':
                        $em->remove($message);
                        break;
                }
            }
        }

        $em->flush();
        $this->addFlash('success', 'Bulk action completed successfully.');
        return $this->redirectToRoute('app_contact_message_index');
    }

    // ── Mobile API ────────────────────────────────────────────────

    #[Route('/api/contact-messages/{id}/seen', name: 'api_contact_message_seen', methods: ['POST'])]
    public function markSeenByUser(
        ContactMessage $message,
        EntityManagerInterface $em
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$user || $user->getEmail() !== $message->getEmail()) {
            return $this->json(['message' => 'Forbidden'], 403);
        }

        $message->markSeenByUser();
        $em->flush();

        return $this->json(['success' => true, 'userSeenAt' => $message->getUserSeenAt()?->format(\DateTimeInterface::ATOM)]);
    }
}