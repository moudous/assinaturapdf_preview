<?php

require 'vendor/autoload.php';

use setasign\Fpdi\Tcpdf\Fpdi;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;

function responderErro(string $mensagem, int $status = 422): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $mensagem;
    exit;
}

/**
 * Regrava xref/object streams em uma estrutura compatível com o parser
 * gratuito do FPDI. O arquivo original nunca é alterado.
 */
function normalizarPdfParaFpdi(string $arquivo): string
{
    $qpdf = null;

    foreach (['/usr/bin/qpdf', '/usr/local/bin/qpdf'] as $candidato) {
        if (is_executable($candidato)) {
            $qpdf = $candidato;
            break;
        }
    }

    if ($qpdf === null) {
        throw new RuntimeException(
            'Este PDF usa uma compactação não suportada pelo FPDI gratuito. ' .
            'Instale o qpdf no servidor (sudo apt-get install qpdf) e tente novamente.'
        );
    }

    $temporario = tempnam(sys_get_temp_dir(), 'fpdi_');
    if ($temporario === false) {
        throw new RuntimeException('Não foi possível criar o PDF temporário.');
    }

    // O qpdf deve criar o arquivo; removemos apenas o placeholder do tempnam().
    unlink($temporario);
    $temporario .= '.pdf';

    $comando = escapeshellarg($qpdf)
        . ' --object-streams=disable --force-version=1.4 -- '
        . escapeshellarg($arquivo) . ' '
        . escapeshellarg($temporario) . ' 2>&1';

    $saidaComando = [];
    $codigoSaida = 0;
    exec($comando, $saidaComando, $codigoSaida);

    // qpdf usa 3 para avisos recuperáveis; nesses casos o PDF foi gerado.
    if (!in_array($codigoSaida, [0, 3], true) || !is_file($temporario)) {
        @unlink($temporario);
        throw new RuntimeException(
            'Não foi possível converter este PDF para um formato compatível.'
        );
    }

    return $temporario;
}

/*
|--------------------------------------------------------------------------
| ARQUIVOS CERTIFICADO
|--------------------------------------------------------------------------
*/

//reapath pois OpenSSL internamente e espera stream wrapper
$certificado = 'file://'.realpath(
    'certificados/certificate.crt'
);

$privateKey = 'file://'.realpath(
    'certificados/private.key'
);

/*
|--------------------------------------------------------------------------
| PDF ORIGINAL
|--------------------------------------------------------------------------
*/

//pasta output verificar se existe e permissão de escrita
if(!is_dir('output')){

    mkdir('output', 0777, true);

}

if(!is_writable('output')){

    die('Pasta output sem permissão de escrita');
}


//pasta uploads verificar se existe e permissão de escrita
if(!is_dir('uploads')){

    mkdir('uploads', 0777, true);
}

if(!is_writable('uploads')){
    die('Pasta uploads sem permissão de escrita');
}   


$nomeArquivo = basename((string) ($_POST['arquivo'] ?? ''));
$arquivo = realpath('uploads/'.$nomeArquivo);

if ($nomeArquivo === '' || $arquivo === false || !is_file($arquivo)) {
    responderErro('Arquivo PDF não encontrado.');
}

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
$arquivoTemporario = null;

try {
    $pageCount = $pdf->setSourceFile($arquivo);
} catch (CrossReferenceException $erro) {
    try {
        $arquivoTemporario = normalizarPdfParaFpdi($arquivo);
        register_shutdown_function(static function () use ($arquivoTemporario): void {
            if (is_file($arquivoTemporario)) {
                unlink($arquivoTemporario);
            }
        });

        // A instância anterior já inicializou o parser com erro.
        $pdf = new FPDI();
        $pageCount = $pdf->setSourceFile($arquivoTemporario);
    } catch (Throwable $erroNormalizacao) {
        responderErro($erroNormalizacao->getMessage());
    }
}

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
        'Name' => 'Murilo',
        'Location' => 'BR',
        'Reason' => 'Documento assinado digitalmente',
        'ContactInfo' => 'mm@nossafco.com.br'
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

/*
|--------------------------------------------------------------------------
| GERA PDF EM MEMÓRIA
|--------------------------------------------------------------------------
*/

$pdfString = $pdf->Output('', 'S');

/*
|--------------------------------------------------------------------------
| SALVA MANUALMENTE
|--------------------------------------------------------------------------
*/

file_put_contents(
    $saida,
    $pdfString
);

/*
|--------------------------------------------------------------------------
| DOWNLOAD
|--------------------------------------------------------------------------
*/

header('Content-Type: application/pdf');

header(
    'Content-Disposition: attachment; filename="documento_assinado.pdf"'
);

header(
    'Content-Length: '.filesize($saida)
);

readfile($saida);

exit;
