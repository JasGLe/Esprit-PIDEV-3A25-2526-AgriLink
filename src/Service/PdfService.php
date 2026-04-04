<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Response;

class PdfService
{
    public function renderPdfContent(
        string $html,
        string $paperSize = 'A4',
        string $orientation = 'portrait'
    ): string {
        $dompdf = new \Dompdf\Dompdf();
        $options = $dompdf->getOptions();
        $options->setIsRemoteEnabled(true);

        $dompdf->setPaper($paperSize, $orientation);
        $dompdf->loadHtml($html);
        $dompdf->render();

        return $dompdf->output();
    }

    public function generatePdf(
        string $html,
        string $filename = 'document.pdf',
        string $paperSize = 'A4',
        string $orientation = 'portrait'
    ): Response {
        try {
            $pdfContent = $this->renderPdfContent($html, $paperSize, $orientation);

            return new Response(
                $pdfContent,
                Response::HTTP_OK,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
                    'Cache-Control' => 'no-cache, no-store, must-revalidate',
                    'Pragma' => 'no-cache',
                    'Expires' => '0',
                ]
            );
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Erreur lors de la génération du PDF: ' . $exception->getMessage());
        }
    }

    public function generatePdfPreview(string $html, string $filename = 'document.pdf'): Response
    {
        try {
            $pdfContent = $this->renderPdfContent($html, 'A4', 'portrait');

            return new Response(
                $pdfContent,
                Response::HTTP_OK,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => sprintf('inline; filename="%s"', $filename),
                    'Cache-Control' => 'no-cache, no-store, must-revalidate',
                    'Pragma' => 'no-cache',
                    'Expires' => '0',
                ]
            );
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Erreur lors de la génération du PDF: ' . $exception->getMessage());
        }
    }

    public function getImageDataUri(string $publicRelativePath): ?string
    {
        if (!extension_loaded('gd')) {
            return null;
        }

        $relative = ltrim(str_replace('\\', '/', $publicRelativePath), '/');
        $absolutePath = dirname(__DIR__, 2) . '/public/' . $relative;

        if (!is_file($absolutePath)) {
            return null;
        }

        $mimeType = mime_content_type($absolutePath) ?: 'image/png';
        $raw = file_get_contents($absolutePath);

        if ($raw === false) {
            return null;
        }

        return sprintf('data:%s;base64,%s', $mimeType, base64_encode($raw));
    }
}