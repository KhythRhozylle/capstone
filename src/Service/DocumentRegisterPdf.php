<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class DocumentRegisterPdf
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public function render(array $records, array $columns = []): string
    {
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $pdf = new Dompdf($options);
        $pdf->loadHtml($this->twig->render('staff/document_records/print.html.twig', ['records' => $records, 'pdf' => true, 'columns' => $columns]), 'UTF-8');
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        return $pdf->output();
    }
}
