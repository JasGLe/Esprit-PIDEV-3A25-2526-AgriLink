<?php

namespace App\Controller\UserManagement\Admin;

use App\Repository\UserManagement\SecurityEventRepository;
use App\Repository\UserManagement\UserRepository;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

#[Route('/admin/security', name: 'admin_security_')]
#[IsGranted('ROLE_ADMIN')]
class SecurityController extends AbstractController
{
    public function __construct(
        private SecurityEventRepository $securityEventRepository,
        private UserRepository $userRepository,
        private Environment $twig
    ) {}

    #[Route('/logs', name: 'logs', methods: ['GET'])]
    public function securityDashboard(Request $request): Response
    {
        // Security statistics for different periods
        $stats24h = $this->securityEventRepository->getStatistics(1);
        $stats7d = $this->securityEventRepository->getStatistics(7);
        $stats30d = $this->securityEventRepository->getStatistics(30);

        // Users with security concerns
        $lockedAccounts = $this->userRepository->findLockedAccounts();
        $failedLoginUsers = $this->userRepository->findUsersWithFailedLogins(5);
        $unverifiedAccounts = $this->userRepository->findUnverifiedAccounts(30);

        // Recent security events (last 20)
        $recentEvents = $this->securityEventRepository->findRecent(20);

        // Account status overview
        $accountStats = $this->userRepository->getAccountSecurityStats();

        return $this->render('user_management/admin/security/logs.html.twig', [
            'stats24h' => $stats24h,
            'stats7d' => $stats7d,
            'stats30d' => $stats30d,
            'lockedAccounts' => $lockedAccounts,
            'failedLoginUsers' => $failedLoginUsers,
            'unverifiedAccounts' => $unverifiedAccounts,
            'recentEvents' => $recentEvents,
            'accountStats' => $accountStats,
        ]);
    }

    #[Route('/export/pdf', name: 'export_pdf', methods: ['GET'])]
    public function exportPdf(): Response
    {
        $stats24h = $this->securityEventRepository->getStatistics(1);
        $stats7d = $this->securityEventRepository->getStatistics(7);
        $stats30d = $this->securityEventRepository->getStatistics(30);
        $accountStats = $this->userRepository->getAccountSecurityStats();

        $html = $this->twig->render('user_management/admin/security/export_pdf.html.twig', [
            'stats24h' => $stats24h,
            'stats7d' => $stats7d,
            'stats30d' => $stats30d,
            'accountStats' => $accountStats,
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
                'Content-Disposition' => 'attachment; filename="rapport-securite-' . date('Ymd') . '.pdf"',
            ]
        );
    }

    #[Route('/export/excel', name: 'export_excel', methods: ['GET'])]
    public function exportExcel(): StreamedResponse
    {
        $stats24h = $this->securityEventRepository->getStatistics(1);
        $stats7d = $this->securityEventRepository->getStatistics(7);
        $stats30d = $this->securityEventRepository->getStatistics(30);
        $accountStats = $this->userRepository->getAccountSecurityStats();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rapport Sécurité');

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
        $sheet->mergeCells('A1:C1');
        $sheet->setCellValue('A1', 'RAPPORT SÉCURITÉ AGRILINK - ' . date('d/m/Y'));
        $sheet->getStyle('A1')->applyFromArray($titleStyle);
        $sheet->getRowDimension(1)->setRowHeight(25);

        $currentRow = 3;

        // ── STATISTICS 30 DAYS ────────────────────────────────────
        $sheet->mergeCells('A' . $currentRow . ':B' . $currentRow);
        $sheet->setCellValue('A' . $currentRow, '📊 STATISTIQUES 30 DERNIERS JOURS');
        $sheet->getStyle('A' . $currentRow)->applyFromArray($headerStyle);
        $sheet->getRowDimension($currentRow)->setRowHeight(20);
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
            ['Connexions réussies', $stats30d['loginSuccess'] ?? 0],
            ['Connexions échouées', $stats30d['loginFailed'] ?? 0],
            ['2FA réussi', $stats30d['twoFaSuccess'] ?? 0],
            ['Comptes verrouillés', $stats30d['accountLocked'] ?? 0],
            ['Mots de passe changés', $stats30d['passwordChanged'] ?? 0],
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

        // ── STATISTICS 24H ────────────────────────────────────────
        $sheet->mergeCells('A' . $currentRow . ':B' . $currentRow);
        $sheet->setCellValue('A' . $currentRow, '⏱️ STATISTIQUES 24H');
        $sheet->getStyle('A' . $currentRow)->applyFromArray($headerStyle);
        $sheet->getRowDimension($currentRow)->setRowHeight(20);
        $currentRow++;

        $sheet->setCellValue('A' . $currentRow, 'Connexions réussies');
        $sheet->setCellValue('B' . $currentRow, $stats24h['loginSuccess'] ?? 0);
        $currentRow++;
        $sheet->setCellValue('A' . $currentRow, 'Connexions échouées');
        $sheet->setCellValue('B' . $currentRow, $stats24h['loginFailed'] ?? 0);

        $currentRow += 2;

        // ── ACCOUNT OVERVIEW ───────────────────────────────────────
        $sheet->mergeCells('A' . $currentRow . ':B' . $currentRow);
        $sheet->setCellValue('A' . $currentRow, '👥 APERÇU COMPTES');
        $sheet->getStyle('A' . $currentRow)->applyFromArray($headerStyle);
        $sheet->getRowDimension($currentRow)->setRowHeight(20);
        $currentRow++;

        $accountRows = [
            ['Total comptes', $accountStats['total'] ?? 0],
            ['Comptes actifs', $accountStats['active'] ?? 0],
            ['Emails vérifiés', $accountStats['verified'] ?? 0],
            ['2FA activé', $accountStats['twoFactorEnabled'] ?? 0],
            ['Comptes verrouillés actuellement', $accountStats['lockedNow'] ?? 0],
        ];

        foreach ($accountRows as $idx => $row) {
            $sheet->setCellValue('A' . $currentRow, $row[0]);
            $sheet->setCellValue('B' . $currentRow, $row[1]);
            if ($idx % 2 === 0) {
                $sheet->getStyle('A' . $currentRow . ':B' . $currentRow)->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF3F4F6');
            }
            $currentRow++;
        }

        // ── Column widths ──────────────────────────────────────────
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(15);

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $filename = 'rapport-securite-' . date('Ymd') . '.xlsx';
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}\"");
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }
}
