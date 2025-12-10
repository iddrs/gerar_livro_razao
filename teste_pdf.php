<?php
/**
 * Script de teste para verificar a geração de PDF com lógica de saldo dinâmico
 * Usa dados fictícios para testar a formatação sem necessidade de banco de dados
 */

if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    die("Erro: Execute 'composer install' antes de executar este script.\n");
}

require_once(__DIR__ . '/vendor/autoload.php');

/**
 * Formata a conta contábil no padrão #.#.#.#.#.##.##.##.##.##
 */
function formatarContaContabil($conta) {
    $conta = preg_replace('/[^0-9]/', '', $conta);
    $conta = str_pad($conta, 15, '0', STR_PAD_LEFT);
    
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
    
    function convertToLatin1($text) {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
    }
}

// Dados fictícios para teste
$ano = '2023';
$entidade = 'Prefeitura';

// Dados simulando o resultado da junção de bal_ver e tce_4111
$contasAgrupadas = [
    // Conta de Natureza Devedora (Ativo - 1)
    '111110101010101' => [
        'especificacao' => 'CAIXA GERAL',
        'saldo_inicial' => 1000.00, // Saldo inicial devedor
        'lancamentos' => [
            [
                'data_lancamento' => '2023-12-01',
                'tipo_lancamento' => 'D', // Débito (Aumenta saldo)
                'historico_lancamento' => 'Recebimento de transferência',
                'nr_lancamento' => 1001,
                'nr_lote' => 100,
                'valor_lancamento' => 500.00
            ],
            [
                'data_lancamento' => '2023-12-05',
                'tipo_lancamento' => 'C', // Crédito (Diminui saldo)
                'historico_lancamento' => 'Pagamento de despesa',
                'nr_lancamento' => 1002,
                'nr_lote' => 100,
                'valor_lancamento' => 200.00
            ],
            [
                'data_lancamento' => '2023-12-10',
                'tipo_lancamento' => 'D', // Débito (Aumenta saldo)
                'historico_lancamento' => 'Recebimento de ICMS',
                'nr_lancamento' => 1003,
                'nr_lote' => 101,
                'valor_lancamento' => 300.00
            ],
        ]
    ],
    // Conta de Natureza Credora (Passivo - 2)
    '211110101010101' => [
        'especificacao' => 'FORNECEDORES NACIONAIS',
        'saldo_inicial' => 5000.00, // Saldo inicial credor
        'lancamentos' => [
            [
                'data_lancamento' => '2023-12-02',
                'tipo_lancamento' => 'C', // Crédito (Aumenta saldo)
                'historico_lancamento' => 'Registro de dívida com fornecedor',
                'nr_lancamento' => 2001,
                'nr_lote' => 200,
                'valor_lancamento' => 1000.00
            ],
            [
                'data_lancamento' => '2023-12-15',
                'tipo_lancamento' => 'D', // Débito (Diminui saldo)
                'historico_lancamento' => 'Pagamento a fornecedor',
                'nr_lancamento' => 2002,
                'nr_lote' => 200,
                'valor_lancamento' => 800.00
            ],
        ]
    ],
];

echo "Gerando PDF de teste com lógica de saldo...\n\n";

try {
    $pdf = new LivroRazaoPDF('P', 'mm', 'A4');
    $pdf->setInfo($ano, $entidade);
    $pdf->AliasNbPages();
    $pdf->SetAutoPageBreak(true, 20);
    
    $contaNum = 0;
    foreach ($contasAgrupadas as $conta => $dadosConta) {
        $contaNum++;
        
        $pdf->AddPage();
        
        // Título da conta
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
        
        // Dados
        $pdf->SetFont('Arial', '', 7);
        
        // 1. Linha do Saldo Inicial
        $saldoAtual = $dadosConta['saldo_inicial'];
        $saldoFormatado = 'R$ ' . number_format($saldoAtual, 2, ',', '.');
        
        $pdf->SetFillColor(230, 230, 230);
        $pdf->Cell(160, 5, $pdf->convertToLatin1('SALDO INICIAL'), 1, 0, 'L', true);
        $pdf->Cell(30, 5, $saldoFormatado, 1, 1, 'R', true);
        
        $primeiroDigito = (int)substr($conta, 0, 1);
        $naturezaDevedora = ($primeiroDigito % 2 != 0); // Ímpar = Devedora (Ativo, Despesa)
        
        foreach ($dadosConta['lancamentos'] as $lancamento) {
            // Processa o valor
            $valor = $lancamento['valor_lancamento'];
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
            
            // Imprime linha da tabela
            $pdf->Cell(20, 5, date('d/m/Y', strtotime($lancamento['data_lancamento'])), 1, 0, 'C');
            $pdf->Cell(10, 5, $lancamento['tipo_lancamento'], 1, 0, 'C');
            $pdf->Cell(65, 5, $pdf->convertToLatin1($lancamento['historico_lancamento']), 1, 0, 'L');
            $pdf->Cell(20, 5, $lancamento['nr_lancamento'], 1, 0, 'C');
            $pdf->Cell(20, 5, $lancamento['nr_lote'], 1, 0, 'C');
            $pdf->Cell(25, 5, $valorFormatado, 1, 0, 'R');
            $pdf->Cell(30, 5, $saldoLinhaFormatado, 1, 1, 'R'); // Saldo
        }
        
        echo "✓ Processada conta $contaNum/" . count($contasAgrupadas) . ": $contaFormatada\n";
    }
    
    $nomeArquivo = "teste_livro_razao_saldo_" . date('YmdHis') . ".pdf";
    $pdf->Output('F', $nomeArquivo);
    
    echo "\n✓ PDF de teste gerado com sucesso: $nomeArquivo\n";
    echo "  Tamanho: " . number_format(filesize($nomeArquivo) / 1024, 2) . " KB\n";
    
} catch (Exception $e) {
    die("✗ Erro ao gerar PDF: " . $e->getMessage() . "\n");
}
?>
