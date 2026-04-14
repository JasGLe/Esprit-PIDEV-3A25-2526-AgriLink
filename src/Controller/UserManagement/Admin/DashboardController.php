<?php

namespace App\Controller\UserManagement\Admin;

use App\Repository\UserManagement\SecurityEventRepository;
use App\Repository\UserManagement\UserRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

#[Route('/admin', name: 'admin_')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private SecurityEventRepository $securityEventRepository,
        private Environment $twig
    ) {}

    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function index(): Response
    {
        // Get user statistics
        $stats = $this->userRepository->getStatistics();
        
        // Get recent registrations (last 7 days)
        $recentUsers = $this->userRepository->findRecentUsers(7, 10);
        
        // Get security events summary
        $securityStats = $this->securityEventRepository->getStatistics(30); // last 30 days
        
        // Get recent security events
        $recentEvents = $this->securityEventRepository->findRecent(15);
        
        // Calculate growth rates
        $previousPeriodStats = $this->userRepository->getStatisticsForPeriod(
            new \DateTime('-60 days'),
            new \DateTime('-30 days')
        );
        
        $currentPeriodStats = $this->userRepository->getStatisticsForPeriod(
            new \DateTime('-30 days'),
            new \DateTime('now')
        );
        
        $growthRate = $this->calculateGrowthRate(
            $previousPeriodStats['newUsers'] ?? 0,
            $currentPeriodStats['newUsers'] ?? 0
        );

        return $this->render('user_management/dashboard/admin.html.twig', [
            'stats' => $stats,
            'recentUsers' => $recentUsers,
            'securityStats' => $securityStats,
            'recentEvents' => $recentEvents,
            'growthRate' => $growthRate,
            'currentPeriodStats' => $currentPeriodStats,
        ]);
    }

    #[Route('/export/pdf', name: 'export_pdf', methods: ['GET'])]
    public function exportPdf(): Response
    {
        $stats = $this->userRepository->getStatistics();
        $recentUsers = $this->userRepository->findRecentUsers(7, 10);
        $securityStats = $this->securityEventRepository->getStatistics(30);
        $recentEvents = $this->securityEventRepository->findRecent(15);
        
        $previousPeriodStats = $this->userRepository->getStatisticsForPeriod(
            new \DateTime('-60 days'),
            new \DateTime('-30 days')
        );
        $currentPeriodStats = $this->userRepository->getStatisticsForPeriod(
            new \DateTime('-30 days'),
            new \DateTime('now')
        );
        $growthRate = $this->calculateGrowthRate(
            $previousPeriodStats['newUsers'] ?? 0,
            $currentPeriodStats['newUsers'] ?? 0
        );

        $html = $this->twig->render('user_management/dashboard/admin_export_pdf.html.twig', [
            'stats' => $stats,
            'recentUsers' => $recentUsers,
            'securityStats' => $securityStats,
            'recentEvents' => $recentEvents,
            'growthRate' => $growthRate,
            'currentPeriodStats' => $currentPeriodStats,
            'date' => new \DateTime(),
        ]);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('dpi', 96);
        $options->set('enable_html5_parser', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="rapport-agrilink-' . date('Ymd') . '.pdf"',
            ]
        );
    }

    #[Route('/export/excel', name: 'export_excel', methods: ['GET'])]
    public function exportExcel(): StreamedResponse
    {
        $stats = $this->userRepository->getStatistics();
        $securityStats = $this->securityEventRepository->getStatistics(30);
        $recentUsers = $this->userRepository->findRecentUsers(7, 10);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rapport Admin');

        // ── Style headers ──────────────────────────────────────────
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 12],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1f2937']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
        ];

        $titleStyle = [
            'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FF1f2937']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ];

        // Title
        $sheet->mergeCells('A1:D1');
        $sheet->setCellValue('A1', 'RAPPORT AGRILINK - ' . date('d/m/Y'));
        $sheet->getStyle('A1')->applyFromArray($titleStyle);
        $sheet->setRowDimension(1, 25);

        $currentRow = 3;

        // ── USER STATISTICS ────────────────────────────────────────
        $sheet->mergeCells('A' . $currentRow . ':D' . $currentRow);
        $sheet->setCellValue('A' . $currentRow, '📊 STATISTIQUES UTILISATEURS');
        $sheet->getStyle('A' . $currentRow)->applyFromArray($headerStyle);
        $sheet->setRowDimension($currentRow, 20);
        $currentRow++;

        $userHeaders = ['Catégorie', 'Nombre', 'Pourcentage'];
        $sheet->setCellValue('A' . $currentRow, $userHeaders[0]);
        $sheet->setCellValue('B' . $currentRow, $userHeaders[1]);
        $sheet->setCellValue('C' . $currentRow, $userHeaders[2]);
        $sheet->getStyle('A' . $currentRow . ':C' . $currentRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF6b7280']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $currentRow++;

        $userRows = [
            ['Total Utilisateurs', $stats['total'] ?? 0, '100%'],
            ['Agriculteurs', $stats['by_role']['ROLE_AGRICULTEUR'] ?? 0, $stats['total'] > 0 ? round(($stats['by_role']['ROLE_AGRICULTEUR'] ?? 0) / $stats['total'] * 100, 1) . '%' : '0%'],
            ['Fournisseurs', $stats['by_role']['ROLE_FOURNISSEUR'] ?? 0, $stats['total'] > 0 ? round(($stats['by_role']['ROLE_FOURNISSEUR'] ?? 0) / $stats['total'] * 100, 1) . '%' : '0%'],
            ['AgriPlus', $stats['by_role']['ROLE_AGRIPLUS'] ?? 0, $stats['total'] > 0 ? round(($stats['by_role']['ROLE_AGRIPLUS'] ?? 0) / $stats['total'] * 100, 1) . '%' : '0%'],
            ['Utilisateurs Standard', $stats['by_role']['ROLE_USER'] ?? 0, $stats['total'] > 0 ? round(($stats['by_role']['ROLE_USER'] ?? 0) / $stats['total'] * 100, 1) . '%' : '0%'],
        ];

        foreach ($userRows as $idx => $row) {
            $sheet->setCellValue('A' . $currentRow, $row[0]);
            $sheet->setCellValue('B' . $currentRow, $row[1]);
            $sheet->setCellValue('C' . $currentRow, $row[2]);
            if ($idx % 2 === 0) {
                $sheet->getStyle('A' . $currentRow . ':C' . $currentRow)->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF3F4F6');
            }
            $currentRow++;
        }

        $currentRow += 2;

        // ── SECURITY STATISTICS ────────────────────────────────────
        $sheet->mergeCells('A' . $currentRow . ':C' . $currentRow);
        $sheet->setCellValue('A' . $currentRow, '🔒 SÉCURITÉ (30 DERNIERS JOURS)');
        $sheet->getStyle('A' . $currentRow)->applyFromArray($headerStyle);
        $sheet->setRowDimension($currentRow, 20);
        $currentRow++;

        $secHeaders = ['Événement', 'Nombre'];
        $sheet->setCellValue('A' . $currentRow, $secHeaders[0]);
        $sheet->setCellValue('B' . $currentRow, $secHeaders[1]);
        $sheet->getStyle('A' . $currentRow . ':B' . $currentRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF6b7280']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $currentRow++;

        $secRows = [
            ['Connexions réussies', $securityStats['loginSuccess'] ?? 0],
            ['Connexions échouées', $securityStats['loginFailed'] ?? 0],
            ['Comptes verrouillés', $securityStats['accountLocked'] ?? 0],
            ['Changements mdp', $securityStats['passwordChanged'] ?? 0],
            ['2FA réussi', $securityStats['twoFaSuccess'] ?? 0],
        ];

        foreach ($secRows as $idx => $row) {
            $sheet->setCellValue('A' . $currentRow, $row[0]);
            $sheet->setCellValue('B' . $currentRow, $row[1]);
            if ($idx % 2 === 0) {
                $sheet->getStyle('A' . $currentRow . ':B' . $currentRow)->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF3F4F6');
            }
            $currentRow++;
        }

        $currentRow += 2;

        // ── GROWTH ─────────────────────────────────────────────────
        $sheet->mergeCells('A' . $currentRow . ':B' . $currentRow);
        $sheet->setCellValue('A' . $currentRow, '📈 CROISSANCE');
        $sheet->getStyle('A' . $currentRow)->applyFromArray($headerStyle);
        $sheet->setRowDimension($currentRow, 20);
        $currentRow++;

        $growthData = ['Taux croissance (30j)', round($growthRate, 1) . '%'];
        $sheet->setCellValue('A' . $currentRow, $growthData[0]);
        $sheet->setCellValue('B' . $currentRow, $growthData[1]);
        $currentRow++;

        $newRegData = ['Nouvelles inscriptions (7j)', count($recentUsers)];
        $sheet->setCellValue('A' . $currentRow, $newRegData[0]);
        $sheet->setCellValue('B' . $currentRow, $newRegData[1]);

        // ── Column widths ──────────────────────────────────────────
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(15);

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $filename = 'rapport-agrilink-' . date('Ymd') . '.xlsx';
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}\"");
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    private function calculateGrowthRate(int $previous, int $current): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : 0.0;
        }
        
        return (($current - $previous) / $previous) * 100;
    }
}
