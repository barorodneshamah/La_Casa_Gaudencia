<?php

namespace App\Controller;

use App\Entity\ContactMessage;
use App\Form\ContactMessageType;
use App\Repository\ContactMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ContactMessageController extends AbstractController
{
    // ========================================
    // PUBLIC: Contact Form (Landing Page)
    // ========================================
    
    #[Route('/contact', name: 'app_contact')]
    public function contact(Request $request, EntityManagerInterface $em): Response
    {
        $contactMessage = new ContactMessage();
        $form = $this->createForm(ContactMessageType::class, $contactMessage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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

    // ========================================
    // DASHBOARD: List All Messages
    // ========================================
    
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
            'messages' => $messages,
            'stats' => $repo->getStats(),
            'currentStatus' => $status,
            'searchQuery' => $search,
            'unread_count' => $repo->countUnread(),
        ]);
    }

    // ========================================
    // DASHBOARD: View Single Message
    // ========================================
    
    #[Route('/dashboard/messages/{id}', name: 'app_contact_message_show')]
    public function show(ContactMessage $message, EntityManagerInterface $em, ContactMessageRepository $repo): Response
    {
        // Mark as read when viewing
        $message->markAsRead();
        $em->flush();

        return $this->render('contact_message/show.html.twig', [
            'message' => $message,
            'unread_count' => $repo->countUnread(),
        ]);
    }

    // ========================================
    // DASHBOARD: Update Message Status
    // ========================================
    
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

    // ========================================
    // DASHBOARD: Add/Update Notes
    // ========================================
    
    #[Route('/dashboard/messages/{id}/notes', name: 'app_contact_message_notes', methods: ['POST'])]
    public function updateNotes(ContactMessage $message, Request $request, EntityManagerInterface $em): Response
    {
        $notes = $request->request->get('notes');
        $message->setNotes($notes);
        $em->flush();

        $this->addFlash('success', 'Notes saved successfully.');
        return $this->redirectToRoute('app_contact_message_show', ['id' => $message->getId()]);
    }

    // ========================================
    // DASHBOARD: Delete Message
    // ========================================
    
    #[Route('/dashboard/messages/{id}/delete', name: 'app_contact_message_delete', methods: ['POST'])]
    public function delete(ContactMessage $message, EntityManagerInterface $em): Response
    {
        $em->remove($message);
        $em->flush();

        $this->addFlash('success', 'Message deleted successfully.');
        return $this->redirectToRoute('app_contact_message_index');
    }

    // ========================================
    // DASHBOARD: Bulk Actions
    // ========================================
    
    #[Route('/dashboard/messages/bulk-action', name: 'app_contact_message_bulk', methods: ['POST'])]
    public function bulkAction(Request $request, ContactMessageRepository $repo, EntityManagerInterface $em): Response
    {
        $action = $request->request->get('action');
        $ids = $request->request->all('messages');

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
}