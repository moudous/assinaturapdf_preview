<?php

require 'vendor/autoload.php';

use setasign\Fpdi\Tcpdf\Fpdi;

/*
|--------------------------------------------------------------------------
| ARQUIVOS CERTIFICADO
|--------------------------------------------------------------------------
*/

$certificado = 'certificados/certificate.crt';

$privateKey = 'file://'.realpath(
    'certificados/private.key'
);

/*
|--------------------------------------------------------------------------
| PDF ORIGINAL
|--------------------------------------------------------------------------
*/

$arquivo = 'uploads/'.$_POST['arquivo'];

$xTela = floatval($_POST['x']);
$yTela = floatval($_POST['y']);

$wTela = floatval($_POST['w']);
$hTela = floatval($_POST['h']);

$canvasW = floatval($_POST['canvas_w']);
$canvasH = floatval($_POST['canvas_h']);

$paginaSelecionada = intval($_POST['pagina']);

/*
|--------------------------------------------------------------------------
| TCPDF + FPDI
|--------------------------------------------------------------------------
*/

$pdf = new FPDI();

$pageCount = $pdf->setSourceFile($arquivo);

/*
|--------------------------------------------------------------------------
| ASSINATURA DIGITAL
|--------------------------------------------------------------------------
*/

$pdf->setSignature(

    $certificado,
    $privateKey,
    '123456', // senha da chave privada

    '',

    2,

    [
        'Name' => 'Marcelo',
        'Location' => 'BR',
        'Reason' => 'Documento assinado digitalmente',
        'ContactInfo' => 'marcelo@nossafco.com.br'
    ]

);

/*
|--------------------------------------------------------------------------
| IMPORTA PÁGINAS
|--------------------------------------------------------------------------
*/

for($pageNo = 1; $pageNo <= $pageCount; $pageNo++){

    $template = $pdf->importPage($pageNo);

    $size = $pdf->getTemplateSize($template);

    $pdf->AddPage(
        $size['orientation'],
        [$size['width'], $size['height']]
    );

    $pdf->useTemplate($template);

    /*
    |--------------------------------------------------------------------------
    | WATERMARK VISUAL
    |--------------------------------------------------------------------------
    */

    if($pageNo == $paginaSelecionada){

        $pdfX =
            ($xTela / $canvasW)
            * $size['width'];

        $pdfY =
            ($yTela / $canvasH)
            * $size['height'];

        $pdfW =
            ($wTela / $canvasW)
            * $size['width'];

        $pdfH =
            ($hTela / $canvasH)
            * $size['height'];

        $pdf->Image(
            'watermark.png',
            $pdfX,
            $pdfY,
            $pdfW,
            $pdfH
        );

    }

}

/*
|--------------------------------------------------------------------------
| ÁREA VISUAL DA ASSINATURA
|--------------------------------------------------------------------------
*/

$pdf->setSignatureAppearance(
    20,
    20,
    60,
    20
);

/*
|--------------------------------------------------------------------------
| SALVA PDF
|--------------------------------------------------------------------------
*/

$saida = 'output/'.uniqid().'.pdf';

$pdf->Output($saida, 'F');

/*
|--------------------------------------------------------------------------
| DOWNLOAD
|--------------------------------------------------------------------------
*/

header('Content-Type: application/pdf');

header(
    'Content-Disposition: attachment; filename="documento_assinado.pdf"'
);

readfile($saida);

exit;