# Gerador de Livro Razão em PDF com Saldo Dinâmico

Script PHP para geração de relatórios de Livro Razão a partir de dados armazenados em banco de dados PostgreSQL, incluindo o cálculo de saldo dinâmico por conta contábil.

## Requisitos

- **PHP** >= 7.4
- **Extensão PDO PostgreSQL** (pdo_pgsql)
- **Composer** (gerenciador de dependências PHP)
- **PostgreSQL** com acesso ao schema `pad` e tabelas `tce_4111` e `bal_ver`

## Instalação

Consulte o arquivo **INSTALACAO.md** para um guia passo a passo.

## Configuração

Edite o arquivo `gerar_livro_razao.php` e ajuste as configurações do banco de dados:

```php
$dbConfig = [
    'host' => 'localhost',      // Host do PostgreSQL
    'port' => '5432',           // Porta do PostgreSQL
    'dbname' => 'seu_banco',    // Nome do banco de dados
    'user' => 'seu_usuario',    // Usuário do banco
    'password' => 'sua_senha'   // Senha do banco
];
```

## Uso

Execute o script via linha de comando:

```bash
php gerar_livro_razao.php
```

O script solicitará:

1. **Remessa** (formato YYYYMM)
   - Exemplo: `202312` para dezembro de 2023

2. **Entidade**
   - `pm` - Prefeitura
   - `cm` - Câmara
   - `fpsm` - RPPS

## Estrutura do Banco de Dados

### Schema: `pad`

### Tabela: `tce_4111` (Lançamentos)

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `remessa` | integer | Formato YYYYMM (ano e mês) |
| `conta_contabil` | varchar | Código da conta contábil |
| `nr_lancamento` | bigint | Número do lançamento |
| `nr_lote` | bigint | Número do lote |
| `data_lancamento` | date | Data do lançamento (YYYY-MM-DD) |
| `valor_lancamento` | money | Valor do lançamento |
| `tipo_lancamento` | char | Tipo: D (Débito) ou C (Crédito) |
| `historico_lancamento` | varchar | Histórico do lançamento |
| `entidade` | varchar | Código da entidade (pm, cm, fpsm) |

### Tabela: `bal_ver` (Saldos e Especificações)

| Campo | Tipo | Descrição |
|-------|------|-----------|
| `remessa` | integer | Formato YYYYMM (ano e mês) |
| `conta_contabil` | varchar | Código da conta contábil |
| `especificacao_conta_contabil` | text | Descrição da conta |
| `entidade` | varchar | Código da entidade (pm, cm, fpsm) |
| `saldo_inicial` | money | Saldo inicial da conta |
| `saldo_atual` | money | Saldo final da conta (não usado no cálculo dinâmico) |

## Formato do PDF

O PDF gerado contém:

### Cabeçalho
- Título: "Livro razão do ano de YYYY da entidade [Nome Simplificado]"

### Conteúdo
- Uma página para cada conta contábil
- Conta contábil formatada: `#.#.#.#.#.##.##.##.##.##`
- Especificação da conta (`bal_ver.especificacao_conta_contabil`)
- Tabela com lançamentos contendo:
  - Data do lançamento
  - Tipo (D/C)
  - Histórico
  - Número do lançamento
  - Número do lote
  - Valor
  - **Saldo** (Calculado dinamicamente)

### Lógica de Cálculo do Saldo

O saldo é calculado linha a linha, começando pelo `saldo_inicial` da tabela `bal_ver`.

| Primeiro Dígito da Conta | Natureza | Tipo Lançamento | Efeito no Saldo |
|--------------------------|----------|-----------------|-----------------|
| Ímpar (1, 3, 5, 7, 9) | Devedora | D (Débito) | Saldo + Valor |
| Ímpar (1, 3, 5, 7, 9) | Devedora | C (Crédito) | Saldo - Valor |
| Par (2, 4, 6, 8) | Credora | D (Débito) | Saldo - Valor |
| Par (2, 4, 6, 8) | Credora | C (Crédito) | Saldo + Valor |

### Rodapé
- Data e hora de geração
- Paginação (página/total)

## Exemplo de Execução

```
=======================================================
       GERAÇÃO DE LIVRO RAZÃO - FORMATO PDF           
=======================================================

Digite a remessa (formato YYYYMM): 202312

Remessa selecionada: 202312 (Ano: 2023, Mês: 12)

Entidades disponíveis:
  [pm]   - Prefeitura
  [cm]   - Câmara
  [fpsm] - RPPS

Digite a entidade: pm

Entidade selecionada: Prefeitura

=======================================================

✓ Conectado ao banco de dados PostgreSQL
✓ Dados agrupados em 45 contas contábeis

Gerando PDF...
  ✓ Processada conta 1/45: 1.1.1.1.1.01.01.01.01.01
  ...

=======================================================
✓ PDF gerado com sucesso!
  Arquivo: livro_razao_202312_pm_20231215143022.pdf
=======================================================
```

## Solução de Problemas

Consulte o arquivo **INSTALACAO.md** para solução de problemas de conexão e dependências.

## Arquivos do Projeto

- `gerar_livro_razao.php` - Script principal
- `composer.json` - Configuração de dependências
- `README.md` - Este arquivo
- `INSTALACAO.md` - Guia de instalação
- `config_exemplo.php` - Exemplos de configuração do banco

## Licença

Este script foi desenvolvido para uso interno da administração pública municipal.
