<?php
/**
 * Script para geração de Livro Razão em PDF com cálculo de saldo dinâmico
 * Consulta dados do PostgreSQL nas tabelas tce_4111 e bal_ver
 * 
 * Requisitos:
 * - PHP >= 7.4
 * - Extensão pdo_pgsql
 * - Biblioteca FPDF (instalar via composer: composer require setasign/fpdf)
 * 
 * Uso:
 * php gerar_livro_razao.php
 */

// Verifica se o autoload do Composer existe
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    die("Erro: Execute 'composer install' antes de executar este script.\n");
}

require_once(__DIR__ . '/vendor/autoload.php');

/**
 * Formata a conta contábil no padrão #.#.#.#.#.##.##.##.##.##
 */
function formatarContaContabil($conta) {
    // Remove caracteres não numéricos
    $conta = preg_replace('/[^0-9]/', '', $conta);
    
    // Garante que tenha pelo menos 15 dígitos (preenche com zeros à esquerda se necessário)
    $conta = str_pad($conta, 15, '0', STR_PAD_LEFT);
    
    // Formata: #.#.#.#.#.##.##.##.##.##
    $formato = substr($conta, 0, 1) . '.' . 
               substr($conta, 1, 1) . '.' . 
               substr($conta, 2, 1) . '.' . 
               substr($conta, 3, 1) . '.' . 
               substr($conta, 4, 1) . '.' . 
               substr($conta, 5, 2) . '.' . 
               substr($conta, 7, 2) . '.' . 
               substr($conta, 9, 2) . '.' . 
               substr($conta, 11, 2) . '.' . 
               substr($conta, 13, 2);
    
    return $formato;
}

/**
 * Traduz o código da entidade para o nome simplificado para o cabeçalho
 */
function traduzirEntidade($entidade) {
    $traducoes = [
        'pm' => 'Prefeitura',
        'cm' => 'Câmara',
        'fpsm' => 'RPPS'
    ];
    
    return $traducoes[strtolower($entidade)] ?? $entidade;
}

/**
 * Converte o valor monetário do PostgreSQL (money) para float
 */
function moneyToFloat($moneyStr) {
    // Remove símbolos de moeda e converte para float
    // $moneyStr = preg_replace('/[^0-9,.-]/', '', $moneyStr);
    // $moneyStr = str_replace(',', '.', $moneyStr);
    return (float)$moneyStr;
}

/**
 * Classe PDF customizada para Livro Razão
 */
class LivroRazaoPDF extends FPDF {
    private $ano;
    private $entidade;
    private $dataHoraGeracao;
    
    public function setInfo($ano, $entidade) {
        $this->ano = $ano;
        $this->entidade = $entidade;
        $this->dataHoraGeracao = date('d/m/Y H:i:s');
    }
    
    function Header() {
        $this->SetFont('Arial', 'B', 14);
        $titulo = 'Livro razão do ano de ' . $this->ano . ' da entidade ' . $this->entidade;
        $this->Cell(0, 10, $this->convertToLatin1($titulo), 0, 1, 'C');
        $this->Ln(5);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $texto = 'Gerado em: ' . $this->dataHoraGeracao . ' - Página ' . $this->PageNo() . '/{nb}';
        $this->Cell(0, 10, $this->convertToLatin1($texto), 0, 0, 'C');
    }
    
    /**
     * Converte texto UTF-8 para Latin1 (ISO-8859-1)
     */
    function convertToLatin1($text) {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
    }
}

// ==================== CONFIGURAÇÃO ====================

// Configuração do banco de dados (ajuste conforme necessário)
$dbConfig = [
    'host' => 'localhost',
    'port' => '5432',
    'dbname' => '',
    'user' => '',
    'password' => ''
];

// ==================== INTERAÇÃO COM USUÁRIO ====================

echo "=======================================================\n";
echo "       GERAÇÃO DE LIVRO RAZÃO - FORMATO PDF           \n";
echo "=======================================================\n\n";

// Solicita a remessa
echo "Digite a remessa (formato YYYYMM): ";
$remessa = trim(fgets(STDIN));

if (!preg_match('/^\d{6}$/', $remessa)) {
    die("Erro: Remessa inválida. Use o formato YYYYMM (ex: 202312).\n");
}

$ano = substr($remessa, 0, 4);
$mes = substr($remessa, 4, 2);

echo "\n";
echo "Remessa selecionada: $remessa (Ano: $ano, Mês: $mes)\n\n";

// Solicita a entidade
echo "Entidades disponíveis:\n";
echo "  [pm]   - Prefeitura\n";
echo "  [cm]   - Câmara\n";
echo "  [fpsm] - RPPS\n";
echo "\nDigite a entidade: ";
$entidade = strtolower(trim(fgets(STDIN)));

if (!in_array($entidade, ['pm', 'cm', 'fpsm'])) {
    die("Erro: Entidade inválida. Use pm, cm ou fpsm.\n");
}

$entidadeTraduzida = traduzirEntidade($entidade);
echo "\nEntidade selecionada: " . $entidadeTraduzida . "\n\n";
echo "=======================================================\n\n";

// ==================== CONEXÃO E CONSULTA ====================

try {
    // Conecta ao banco de dados
    $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']}";
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✓ Conectado ao banco de dados PostgreSQL\n";
    
    // 1. Consulta os saldos iniciais e especificações (bal_ver)
    $sqlBalVer = "SELECT 
                      conta_contabil,
                      especificacao_conta_contabil,
                      saldo_inicial::decimal
                  FROM pad.bal_ver
                  WHERE remessa = :remessa 
                    AND LOWER(entidade) = :entidade
                  ORDER BY conta_contabil";
    
    $stmtBalVer = $pdo->prepare($sqlBalVer);
    $stmtBalVer->execute([
        ':remessa' => (int)$remessa,
        ':entidade' => $entidade
    ]);
    
    $saldosIniciais = $stmtBalVer->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($saldosIniciais)) {
        die("✗ Nenhum saldo inicial encontrado na tabela bal_ver para a remessa $remessa e entidade $entidade.\n");
    }
    
    // 2. Consulta os lançamentos (tce_4111)
    $sqlTce = "SELECT 
                conta_contabil,
                data_lancamento,
                tipo_lancamento,
                historico_lancamento,
                nr_lancamento,
                nr_lote,
                valor_lancamento::decimal
            FROM pad.tce_4111
            WHERE remessa = :remessa 
              AND LOWER(entidade) = :entidade
            ORDER BY conta_contabil, data_lancamento";
    
    $stmtTce = $pdo->prepare($sqlTce);
    $stmtTce->execute([
        ':remessa' => (int)$remessa,
        ':entidade' => $entidade
    ]);
    
    $lancamentos = $stmtTce->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($lancamentos)) {
        echo "⚠ Nenhum lançamento encontrado na tabela tce_4111. O PDF será gerado apenas com saldos iniciais.\n";
    }
    
    // ==================== PROCESSAMENTO DOS DADOS ====================
    
    // Agrupa dados por conta contábil, incluindo saldo inicial e lançamentos
    $contasAgrupadas = [];
    
    // Preenche com saldos iniciais e especificações
    foreach ($saldosIniciais as $saldo) {
        $conta = $saldo['conta_contabil'];
        $contasAgrupadas[$conta] = [
            'especificacao' => $saldo['especificacao_conta_contabil'],
            'saldo_inicial' => moneyToFloat($saldo['saldo_inicial']),
            'lancamentos' => []
        ];
    }
    
    // Adiciona lançamentos
    foreach ($lancamentos as $lancamento) {
        $conta = $lancamento['conta_contabil'];
        if (isset($contasAgrupadas[$conta])) {
            $contasAgrupadas[$conta]['lancamentos'][] = $lancamento;
        }
    }
    
    echo "✓ Dados agrupados em " . count($contasAgrupadas) . " contas contábeis\n\n";
    echo "Gerando PDF...\n";
    
    // ==================== GERAÇÃO DO PDF ====================
    
    $pdf = new LivroRazaoPDF('P', 'mm', 'A4');
    $pdf->setInfo($ano, $entidadeTraduzida);
    $pdf->AliasNbPages();
    $pdf->SetAutoPageBreak(true, 20);
    
    // Gera uma página para cada conta contábil
    $contaAtual = 0;
    foreach ($contasAgrupadas as $conta => $dadosConta) {
        $contaAtual++;
        
        // Adiciona nova página
        $pdf->AddPage();
        
        // Título da conta contábil com especificação
        $pdf->SetFont('Arial', 'B', 11);
        $contaFormatada = formatarContaContabil($conta);
        $titulo = $contaFormatada . ' - ' . $dadosConta['especificacao'];
        $pdf->Cell(0, 8, $pdf->convertToLatin1($titulo), 0, 1, 'L');
        $pdf->Ln(2);
        
        // Cabeçalho da tabela
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(200, 200, 200);
        $pdf->Cell(20, 6, 'Data', 1, 0, 'C', true);
        $pdf->Cell(10, 6, 'Tipo', 1, 0, 'C', true);
        $pdf->Cell(65, 6, $pdf->convertToLatin1('Histórico'), 1, 0, 'C', true);
        $pdf->Cell(20, 6, $pdf->convertToLatin1('Nº Lanç.'), 1, 0, 'C', true);
        $pdf->Cell(20, 6, $pdf->convertToLatin1('Nº Lote'), 1, 0, 'C', true);
        $pdf->Cell(25, 6, 'Valor', 1, 0, 'C', true);
        $pdf->Cell(30, 6, 'Saldo', 1, 1, 'C', true); // Nova coluna Saldo
        
        // Dados da tabela
        $pdf->SetFont('Arial', '', 7);
        
        // 1. Linha do Saldo Inicial
        $saldoAtual = $dadosConta['saldo_inicial'];
        $saldoFormatado = 'R$ ' . number_format($saldoAtual, 2, ',', '.');
        
        $pdf->SetFillColor(230, 230, 230);
        $pdf->Cell(160, 5, $pdf->convertToLatin1('SALDO INICIAL'), 1, 0, 'L', true);
        $pdf->Cell(30, 5, $saldoFormatado, 1, 1, 'R', true);
        
        // 2. Lançamentos
        $primeiroDigito = (int)substr($conta, 0, 1);
        $naturezaDevedora = ($primeiroDigito % 2 != 0); // Ímpar = Devedora (Ativo, Despesa)
        
        foreach ($dadosConta['lancamentos'] as $lancamento) {
            // Verifica se precisa adicionar nova página
            if ($pdf->GetY() > 260) {
                $pdf->AddPage();
                
                // Reimprime título da conta
                $pdf->SetFont('Arial', 'B', 11);
                $pdf->Cell(0, 8, $pdf->convertToLatin1($titulo . ' (continuação)'), 0, 1, 'L');
                $pdf->Ln(2);
                
                // Reimprime cabeçalho da tabela
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->SetFillColor(200, 200, 200);
                $pdf->Cell(20, 6, 'Data', 1, 0, 'C', true);
                $pdf->Cell(10, 6, 'Tipo', 1, 0, 'C', true);
                $pdf->Cell(65, 6, $pdf->convertToLatin1('Histórico'), 1, 0, 'C', true);
                $pdf->Cell(20, 6, $pdf->convertToLatin1('Nº Lanç.'), 1, 0, 'C', true);
                $pdf->Cell(20, 6, $pdf->convertToLatin1('Nº Lote'), 1, 0, 'C', true);
                $pdf->Cell(25, 6, 'Valor', 1, 0, 'C', true);
                $pdf->Cell(30, 6, 'Saldo', 1, 1, 'C', true);
                
                $pdf->SetFont('Arial', '', 7);
            }
            
            // Formata a data
            $data = date('d/m/Y', strtotime($lancamento['data_lancamento']));
            
            // Processa o valor
            $valor = moneyToFloat($lancamento['valor_lancamento']);
            $valorFormatado = 'R$ ' . number_format($valor, 2, ',', '.');
            
            // Lógica de cálculo do Saldo
            if ($naturezaDevedora) { // Contas de natureza Devedora (1, 3, 5, 7, 9)
                if ($lancamento['tipo_lancamento'] == 'D') {
                    $saldoAtual += $valor; // Débito aumenta saldo
                } else {
                    $saldoAtual -= $valor; // Crédito diminui saldo
                }
            } else { // Contas de natureza Credora (2, 4, 6, 8)
                if ($lancamento['tipo_lancamento'] == 'D') {
                    $saldoAtual -= $valor; // Débito diminui saldo
                } else {
                    $saldoAtual += $valor; // Crédito aumenta saldo
                }
            }
            
            $saldoLinhaFormatado = 'R$ ' . number_format($saldoAtual, 2, ',', '.');
            
            // Trunca o histórico se for muito longo
            $historico = mb_substr($lancamento['historico_lancamento'], 0, 40);
            
            // Imprime linha da tabela
            $pdf->Cell(20, 5, $data, 1, 0, 'C');
            $pdf->Cell(10, 5, $lancamento['tipo_lancamento'], 1, 0, 'C');
            $pdf->Cell(65, 5, $pdf->convertToLatin1($historico), 1, 0, 'L');
            $pdf->Cell(20, 5, $lancamento['nr_lancamento'], 1, 0, 'C');
            $pdf->Cell(20, 5, $lancamento['nr_lote'], 1, 0, 'C');
            $pdf->Cell(25, 5, $valorFormatado, 1, 0, 'R');
            $pdf->Cell(30, 5, $saldoLinhaFormatado, 1, 1, 'R'); // Saldo
        }
        
        echo "  ✓ Processada conta $contaAtual/" . count($contasAgrupadas) . ": $contaFormatada\n";
    }
    
    // ==================== SALVAR PDF ====================
    
    $nomeArquivo = "livro_razao_{$remessa}_{$entidade}_" . date('YmdHis') . ".pdf";
    $pdf->Output('F', $nomeArquivo);
    
    echo "\n=======================================================\n";
    echo "✓ PDF gerado com sucesso!\n";
    echo "  Arquivo: $nomeArquivo\n";
    echo "  Tamanho: " . number_format(filesize($nomeArquivo) / 1024, 2) . " KB\n";
    echo "=======================================================\n";
    
} catch (PDOException $e) {
    die("\n✗ Erro ao conectar ao banco de dados:\n  " . $e->getMessage() . "\n");
} catch (Exception $e) {
    die("\n✗ Erro ao gerar PDF:\n  " . $e->getMessage() . "\n");
}
?>
