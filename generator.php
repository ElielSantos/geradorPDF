<?php
// Habilita a exibição de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclua o arquivo de autoload do Composer
require __DIR__ . '/vendor/autoload.php';

use TCPDF;

// Caminho do arquivo XML
$xmlFile = __DIR__ . '/output.xml';

// Verifica se o arquivo XML existe
if (!file_exists($xmlFile)) {
    die('Erro: Arquivo XML não encontrado.');
}

// IDs dos campos e suas descrições amigáveis
$descricoes = [
    'G01Q01' => 'Nome',
    'G01Q10' => 'RG',
    'G01Q010' => 'Lotação',
    'G01Q11' => 'Cargo',
    'G01Q03_SQ001' => 'Período de Férias',
    'G01Q03_SQ001comment' => 'Comentário do Período de Férias',
    'G01Q04' => 'Ano de Exercício',
    'G01Q05' => 'Início das Férias',
    'G01Q06' => 'Término das Férias',
    'G01Q07_SQ001' => 'Assinatura do Servidor',
    'G01Q08' => 'Data',
    'G01Q09_SQ001' => 'Parecer da Chefia',
    'G01Q09_SQ001comment' => 'Comentário do Parecer da Chefia'
];

// Carrega o arquivo XML
$xml = simplexml_load_file($xmlFile);

// Verifica se o arquivo foi carregado corretamente
if ($xml === false) {
    die('Erro ao carregar o arquivo XML.');
}

// Função para buscar valores baseados no varname
function buscarValorPorVarname($xml, $varname) {
    foreach ($xml->sections->item0->questions->children() as $question) {
        foreach ($question->responses->children() as $response) {
            if ((string) $response->varname == $varname) {
                return (string) $response->defaultvalue;
            }
            // Verifica subquestions
            if (isset($response->subquestions)) {
                foreach ($response->subquestions->children() as $subquestion) {
                    if ((string) $subquestion->varname == $varname) {
                        return (string) $subquestion->defaultvalue;
                    }
                }
            }
        }
    }
    return null;
}

// Cria um novo objeto TCPDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Define informações do documento
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Seu Nome');
$pdf->SetTitle('Requerimento de Férias');
$pdf->SetSubject('Requerimento de Férias');
$pdf->SetKeywords('TCPDF, PDF, Requerimento, Férias');

// Define margens
$pdf->SetMargins(20, 20, 20);

// Adiciona uma página
$pdf->AddPage();

// Incluir o logo
$pdf->Image('imagens/logo.jpg', (210 - 30) / 2, 5, 30);

// Título
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 70, 'REQUERIMENTO DE FÉRIAS', 0, 1, 'C');

// Dados do requerente
$pdf->SetFont('helvetica', '', 12);
$nome = buscarValorPorVarname($xml, 'G01Q01');
$cargo = buscarValorPorVarname($xml, 'G01Q11');
$rg = buscarValorPorVarname($xml, 'G01Q10');
$lotação = buscarValorPorVarname($xml, 'G01Q010');
$municipio = buscarValorPorVarname($xml, 'G01Q010');
$pdf->MultiCell(0, 10, "Eu, $nome, cargo $cargo, RG $rg, lotado(a) no $lotação, da Polícia Científica do município de $municipio, venho requerer autorização para a concessão de:", 0, 'J');

// Tipo de férias
$pdf->SetFont('helvetica', 'B', 12);
$pdf->MultiCell(0, 10, '( ) Férias regulamentares de 30 dias OU ( ) Saldo de férias de ______ dias', 0, 'J');

// Período de férias
$pdf->SetFont('helvetica', '', 12);
$anoExercicio = buscarValorPorVarname($xml, 'G01Q04');
$inicioFerias = buscarValorPorVarname($xml, 'G01Q05');
$terminoFerias = buscarValorPorVarname($xml, 'G01Q06');

// Formatar as datas para 'd-m-Y'
if ($inicioFerias !== null) {
    $inicioFerias = (new DateTime($inicioFerias))->format('d-m-Y');
}
if ($terminoFerias !== null) {
    $terminoFerias = (new DateTime($terminoFerias))->format('d-m-Y');
}

$pdf->MultiCell(0, 10, "Referentes ao exercício do ano de $anoExercicio a serem usufruídas conforme abaixo:", 0, 'J');
$pdf->MultiCell(0, 10, "INÍCIO $inicioFerias TÉRMINO $terminoFerias", 0, 'J');

// Data de solicitação e assinatura do servidor
$data = buscarValorPorVarname($xml, 'G01Q08');
if ($data !== null) {
    $date = new DateTime($data);
    $dateOnly = $date->format('d-m-Y');
} else {
    $dateOnly = ''; // Handle case where the date is not found
}

$assinaturaServidor = buscarValorPorVarname($xml, 'G01Q07_SQ001');
$pdf->MultiCell(0, 10, "Data $dateOnly", 0, 'J');
$pdf->MultiCell(0, 10, "Assinatura Servidor $assinaturaServidor", 0, 'J');

// Quebra de linha
$pdf->Ln();

// Parecer da chefia imediata
$pdf->SetFont('helvetica', 'B', 12);
$pdf->MultiCell(0, 10, '--------------------------------- PARECER DA CHEFIA IMEDIATA -----------------------------------', 0, 'J');

// Opção de parecer favorável
$pdf->SetFont('helvetica', '', 12);
$pdf->MultiCell(0, 10, '( ) Esta chefia é favorável a concessão de férias conforme acima especificado e, caso o requerente ocupe cargo de chefia, indico para substituí-lo:', 0, 'J');
$parecerChefia = buscarValorPorVarname($xml, 'G01Q09_SQ001comment');
$pdf->MultiCell(0, 10, $parecerChefia, 0, 'J');

// Opção de parecer não favorável
$pdf->MultiCell(0, 10, '( ) Esta chefia NÃO é favorável a concessão de férias em razão de:', 0, 'J');
$pdf->MultiCell(0, 10, $parecerChefia, 0, 'J');

// Data e assinatura da chefia imediata
$dataChefia = buscarValorPorVarname($xml, 'G01Q08');
if ($dataChefia !== null) {
    $dateChefia = new DateTime($dataChefia);
    $dateOnlyChefia = $dateChefia->format('d-m-Y');
} else {
    $dateOnlyChefia = ''; // Handle case where the date is not found
}

$assinaturaChefia = buscarValorPorVarname($xml, 'G01Q09_SQ001');
$pdf->MultiCell(0, 10, "Data $dateOnlyChefia", 0, 'J');
$pdf->MultiCell(0, 10, "Assinatura Chefia Imediata $assinaturaChefia", 0, 'J');

// Saída do PDF
$pdf->Output('Requerimento_de_Férias.pdf', 'D');
// Termina o script
exit;
