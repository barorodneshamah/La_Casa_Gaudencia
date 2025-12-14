<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Form\ActivityLogFilterType;
use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/activity-logs')]
#[IsGranted('ROLE_ADMIN')]
class ActivityLogController extends AbstractController
{
    #[Route('', name: 'app_activity_log_index', methods: ['GET'])]
    public function index(Request $request, ActivityLogRepository $repository): Response
    {
        $form = $this->createForm(ActivityLogFilterType::class);
        $form->handleRequest($request);

        $filters = [];
        $page = $request->query->getInt('page', 1);
        $limit = 20;

        if ($form->isSubmitted() && $form->isValid()) {
            $filters = array_filter($form->getData());
        }

        $logs = $repository->findByFilters($filters, $page, $limit);
        $total = $repository->countByFilters($filters);
        $totalPages = ceil($total / $limit);

        // Get statistics
        $actionStats = $repository->getStatsByAction();
        $entityStats = $repository->getStatsByEntityType();

        return $this->render('activity_log/index.html.twig', [
            'logs' => $logs,
            'form' => $form->createView(),
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
            'actionStats' => $actionStats,
            'entityStats' => $entityStats,
        ]);
    }

    #[Route('/show/{id}', name: 'app_activity_log_show', methods: ['GET'])]
    public function show(ActivityLog $activityLog): Response
    {
        return $this->render('activity_log/show.html.twig', [
            'log' => $activityLog,
        ]);
    }

    #[Route('/export/csv', name: 'app_activity_log_export', methods: ['GET'])]
    public function export(Request $request, ActivityLogRepository $repository): Response
    {
        $form = $this->createForm(ActivityLogFilterType::class);
        $form->handleRequest($request);

        $filters = [];
        if ($form->isSubmitted() && $form->isValid()) {
            $filters = array_filter($form->getData());
        }

        $logs = $repository->findByFilters($filters, 1, 10000);

        $csv = "ID,User,Role,Action,Entity Type,Entity ID,Entity Name,Description,IP Address,Date/Time\n";
        
        foreach ($logs as $log) {
            $csv .= sprintf(
                "%d,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $log->getId(),
                $log->getUsername() ?? 'N/A',
                $log->getUserRole() ?? 'N/A',
                $log->getAction(),
                $log->getEntityType() ?? 'N/A',
                $log->getEntityId() ?? 'N/A',
                str_replace(',', ';', $log->getEntityName() ?? 'N/A'),
                str_replace(',', ';', $log->getDescription() ?? 'N/A'),
                $log->getIpAddress() ?? 'N/A',
                $log->getCreatedAt()->format('Y-m-d H:i:s')
            );
        }

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="activity_logs_' . date('Y-m-d') . '.csv"');

        return $response;
    }
}