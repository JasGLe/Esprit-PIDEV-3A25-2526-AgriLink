<?php
namespace App\Service;

use App\Repository\Exploitation\ExploitationRepository;
use App\Entity\Exploitation\Exploitation;
use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Twig\Environment;

class ExportService
{
    public function __construct(
        private ExploitationRepository $repo,
        private Environment $twig
    ) {}

    /**
     * Génère le PDF des exploitations d'un utilisateur
     */
    public function exportPdf(
        \Symfony\Component\Security\Core\User\UserInterface|null $user,
        bool $isAdmin
    ): Response {
            /** @var Exploitation[] $exploitations */
            $exploitations = $isAdmin
                ? $this->repo->findAll()
                : $this->repo->findBy(['user' => $user]);

        
            $rows = $this->buildRows($exploitations);

        
            $docNumber = 'AGR-' . date('Y') . '-' . strtoupper(uniqid());

        
            $logoPath   = __DIR__ . '/../../public/logo_with_text.png';
            $logoBase64 = null;

            if (file_exists($logoPath)) {
                $logoBase64 = 'data:image/png;base64,' . base64_encode((string) file_get_contents($logoPath));
            }

            $html = $this->twig->render('exploitation/export/pdf.html.twig', [
                'exploitations' => $exploitations,
                'rows'          => $rows,
                'date'          => new \DateTimeImmutable(),
                'docNumber'     => $docNumber,
                'logoBase64'    => $logoBase64,
            ]);

        
            $options = new Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            return new Response(
                $dompdf->output(),
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="rapport_exploitations_' . date('Ymd') . '.pdf"',
                ]
            );
        }

        /**
         * @param Exploitation[] $exploitations
         * @return array<int, array<string, mixed>>
         */
        private function buildRows(array $exploitations): array
        {
            $rows = [];

            foreach ($exploitations as $e) {
                $nbCultures = 0;
                $superficie = 0;

                foreach ($e->getParcelles() as $p) {
                    $superficie += $p->getSuperficie();

                    foreach ($p->getCultures() as $c) {
                        $nbCultures++;
                    }
                }

                $rows[] = [
                    'nom'         => $e->getNom(),
                    'ville'       => $e->getVille()?->label() ?? '—',
                    'nbParcelles' => $e->getParcelles()->count(),
                    'nbCultures'  => $nbCultures,
                    'superficie'  => $superficie,
                ];
            }

            return $rows;
        }

    /**
     * Génère le fichier Excel des exploitations
     */
    public function exportExcel(
        \Symfony\Component\Security\Core\User\UserInterface|null $user,
        bool $isAdmin
    ): StreamedResponse {
        /** @var Exploitation[] $exploitations */
        $exploitations = $isAdmin
            ? $this->repo->findAll()
            : $this->repo->findBy(['user' => $user]);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Exploitations');

        // ── En-têtes ──────────────────────────────────────────────
        $headers = [
            'A' => 'Exploitation',
            'B' => 'Ville',
            'C' => 'Superficie (ha)',
            'D' => 'Nb Parcelles',
            'E' => 'Nb Cultures',
            'F' => 'Superficie totale parcelles (ha)',
            'G' => 'Culture récente',
            'H' => 'Date semis',
        ];

        foreach ($headers as $col => $label) {
            $sheet->setCellValue($col . '1', $label);
        }

        // Style en-têtes
        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF16A34A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN,
                                              'color' => ['argb' => 'FF0F6E56']]],
        ];
        $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);

        // ── Données ──────────────────────────────────────────────
        $row = 2;
        foreach ($exploitations as $exploitation) {
            $nbParcelles = $exploitation->getParcelles()->count();
            $nbCultures = 0;
            $superficieParcelles = 0;
            $cultureRecente = null;

            foreach ($exploitation->getParcelles() as $parcelle) {
                $superficieParcelles += $parcelle->getSuperficie();
                foreach ($parcelle->getCultures() as $culture) {
                    $nbCultures++;
                    if (!$cultureRecente
                        || ($culture->getDateSemis()
                            && $culture->getDateSemis() > $cultureRecente->getDateSemis())) {
                        $cultureRecente = $culture;
                    }
                }
            }

            $sheet->setCellValue('A' . $row, $exploitation->getNom());
            $sheet->setCellValue('B' . $row, $exploitation->getVille()?->label() ?? '—');
            $sheet->setCellValue('C' . $row, $exploitation->getSuperficieTotale() ?? 0);
            $sheet->setCellValue('D' . $row, $nbParcelles);
            $sheet->setCellValue('E' . $row, $nbCultures);
            $sheet->setCellValue('F' . $row, $superficieParcelles);
            $sheet->setCellValue('G' . $row, $cultureRecente?->getNom() ?? '—');
            $sheet->setCellValue('H' . $row, $cultureRecente?->getDateSemis()?->format('d/m/Y') ?? '—');

            // Alternance couleur lignes
            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:H{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFF0FDF4');
            }

            $row++;
        }

        // ── Largeurs colonnes auto ────────────────────────────────
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // ── Feuille 2 : Détail parcelles ─────────────────────────
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Parcelles');

        $headers2 = ['A'=>'Exploitation','B'=>'Parcelle','C'=>'Superficie (ha)',
                     'D'=>'Type sol','E'=>'État','F'=>'Nb Cultures'];
        foreach ($headers2 as $col => $label) {
            $sheet2->setCellValue($col . '1', $label);
        }
        $sheet2->getStyle('A1:F1')->applyFromArray($headerStyle);

        $row2 = 2;
        foreach ($exploitations as $exploitation) {
            foreach ($exploitation->getParcelles() as $parcelle) {
                $sheet2->setCellValue('A' . $row2, $exploitation->getNom());
                $sheet2->setCellValue('B' . $row2, $parcelle->getNom());
                $sheet2->setCellValue('C' . $row2, $parcelle->getSuperficie());
                $sheet2->setCellValue('D' . $row2, $parcelle->getTypeSol() ?? '—');
                $sheet2->setCellValue('E' . $row2, $parcelle->getEtat() ?? '—');
                $sheet2->setCellValue('F' . $row2, $parcelle->getCultures()->count());
                $row2++;
            }
        }
        foreach (range('A', 'F') as $col) {
            $sheet2->getColumnDimension($col)->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $filename = 'exploitations_' . date('Ymd') . '.xlsx';
        $response->headers->set('Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition',
            "attachment; filename=\"{$filename}\"");
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

}