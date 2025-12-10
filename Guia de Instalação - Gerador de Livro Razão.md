# Guia de Instalação - Gerador de Livro Razão

## Passo 1: Verificar Requisitos do Sistema

### 1.1 Verificar se o PHP está instalado

```bash
php --version
```

Se o PHP não estiver instalado, prossiga para o Passo 2.

### 1.2 Verificar extensões necessárias

```bash
php -m | grep -E "pdo|pgsql|mbstring"
```

Você deve ver:
- pdo
- pdo_pgsql
- mbstring

## Passo 2: Instalar PHP e Extensões

### Ubuntu/Debian

```bash
sudo apt-get update
sudo apt-get install -y php php-cli php-pgsql php-mbstring php-xml
```

### CentOS/RHEL

```bash
sudo yum install -y php php-cli php-pgsql php-mbstring php-xml
```

### Fedora

```bash
sudo dnf install -y php php-cli php-pgsql php-mbstring php-xml
```

## Passo 3: Instalar Composer

### Método 1: Download direto

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer
```

### Método 2: Via gerenciador de pacotes (Ubuntu/Debian)

```bash
sudo apt-get install composer
```

### Verificar instalação

```bash
composer --version
```

## Passo 4: Preparar os Arquivos do Projeto

### 4.1 Criar diretório do projeto

```bash
mkdir -p /opt/livro-razao
cd /opt/livro-razao
```

### 4.2 Copiar os arquivos

Copie os seguintes arquivos para o diretório:
- `gerar_livro_razao.php`
- `composer.json`
- `README.md`
- `config_exemplo.php`

```bash
# Exemplo:
cp /caminho/origem/* /opt/livro-razao/
```

## Passo 5: Instalar Dependências

```bash
cd /opt/livro-razao
composer install
```

Você verá algo como:

```
Loading composer repositories with package information
Updating dependencies
Lock file operations: 1 install, 0 updates, 0 removals
  - Locking setasign/fpdf (1.8.2)
Writing lock file
Installing dependencies from lock file
  - Installing setasign/fpdf (1.8.2): Extracting archive
Generating autoload files
```

## Passo 6: Configurar Conexão com o Banco de Dados

### 6.1 Editar o arquivo principal

```bash
nano gerar_livro_razao.php
```

ou

```bash
vim gerar_livro_razao.php
```

### 6.2 Localizar a seção de configuração

Procure por:

```php
$dbConfig = [
    'host' => 'localhost',
    'port' => '5432',
    'dbname' => 'seu_banco',
    'user' => 'seu_usuario',
    'password' => 'sua_senha'
];
```

### 6.3 Ajustar os valores

Substitua pelos dados corretos do seu banco:

```php
$dbConfig = [
    'host' => 'localhost',           // Endereço do servidor PostgreSQL
    'port' => '5432',                // Porta (padrão: 5432)
    'dbname' => 'contabilidade',     // Nome do banco de dados
    'user' => 'usuario_contabil',    // Usuário do banco
    'password' => 'senha123'         // Senha do usuário
];
```

## Passo 7: Testar a Conexão

### 7.1 Criar script de teste

```bash
cat > teste_conexao.php << 'EOF'
<?php
$dbConfig = [
    'host' => 'localhost',
    'port' => '5432',
    'dbname' => 'seu_banco',
    'user' => 'seu_usuario',
    'password' => 'sua_senha'
];

try {
    $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']}";
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✓ Conexão bem-sucedida!\n";
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM pad.tce_4111");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✓ Tabela encontrada com {$result['total']} registros\n";
    
} catch (PDOException $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
}
?>
EOF
```

### 7.2 Executar teste

```bash
php teste_conexao.php
```

## Passo 8: Executar o Script Principal

```bash
php gerar_livro_razao.php
```

### Exemplo de uso:

```
=======================================================
       GERAÇÃO DE LIVRO RAZÃO - FORMATO PDF           
=======================================================

Digite a remessa (formato YYYYMM): 202312

Remessa selecionada: 202312 (Ano: 2023, Mês: 12)

Entidades disponíveis:
  [pm]   - Prefeitura Municipal de Independência / RS
  [cm]   - Câmara de Vereadores de Independência / RS
  [fpsm] - Fundo de Previdência dos Servidores Municipais de Independência / RS

Digite a entidade: pm
```

## Passo 9: Verificar o PDF Gerado

O arquivo PDF será criado no mesmo diretório com o nome:

```
livro_razao_YYYYMM_ENTIDADE_TIMESTAMP.pdf
```

Exemplo:
```
livro_razao_202312_pm_20231215143022.pdf
```

Para visualizar:

```bash
# Linux com interface gráfica
xdg-open livro_razao_*.pdf

# Via navegador
firefox livro_razao_*.pdf

# Copiar para outro local
cp livro_razao_*.pdf /home/usuario/Documentos/
```

## Solução de Problemas Comuns

### Erro: "bash: php: command not found"

**Solução:** PHP não está instalado. Volte ao Passo 2.

### Erro: "could not find driver"

**Solução:** Extensão pdo_pgsql não está instalada.

```bash
# Ubuntu/Debian
sudo apt-get install php-pgsql
sudo systemctl restart apache2  # se usar Apache
```

### Erro: "Execute 'composer install' antes de executar"

**Solução:** Dependências não foram instaladas.

```bash
composer install
```

### Erro: "SQLSTATE[08006] Connection refused"

**Solução:** PostgreSQL não está rodando ou configuração incorreta.

```bash
# Verificar se PostgreSQL está rodando
sudo systemctl status postgresql

# Iniciar PostgreSQL
sudo systemctl start postgresql

# Verificar porta
sudo netstat -tulpn | grep 5432
```

### Erro: "SQLSTATE[42P01] relation does not exist"

**Solução:** Tabela não existe ou schema incorreto.

```bash
# Conectar ao PostgreSQL e verificar
psql -U seu_usuario -d seu_banco

# No psql:
\dt pad.*
SELECT * FROM pad.tce_4111 LIMIT 1;
```

### Erro: "Permission denied"

**Solução:** Ajustar permissões do diretório.

```bash
sudo chown -R seu_usuario:seu_usuario /opt/livro-razao
chmod 755 /opt/livro-razao
```

## Configuração Avançada

### Executar via linha de comando com parâmetros

Você pode modificar o script para aceitar parâmetros:

```bash
php gerar_livro_razao.php 202312 pm
```

### Agendar execução automática (cron)

```bash
# Editar crontab
crontab -e

# Adicionar linha para executar todo dia 1º às 8h
0 8 1 * * cd /opt/livro-razao && php gerar_livro_razao.php < /opt/livro-razao/parametros.txt
```

Criar arquivo `parametros.txt`:
```
202312
pm
```

### Configurar log de execução

Adicione ao final do script:

```php
// Adicionar ao final do script, antes do fechamento ?>
file_put_contents('log_execucao.txt', 
    date('Y-m-d H:i:s') . " - PDF gerado: $nomeArquivo\n", 
    FILE_APPEND
);
```

## Suporte

Para mais informações, consulte:
- README.md - Documentação completa
- config_exemplo.php - Exemplos de configuração
- teste_pdf.php - Script de teste sem banco de dados

## Checklist de Instalação

- [ ] PHP instalado (versão >= 7.4)
- [ ] Extensão pdo_pgsql instalada
- [ ] Extensão mbstring instalada
- [ ] Composer instalado
- [ ] Arquivos copiados para o diretório
- [ ] Dependências instaladas (composer install)
- [ ] Configuração do banco ajustada
- [ ] Teste de conexão bem-sucedido
- [ ] Script executado com sucesso
- [ ] PDF gerado e visualizado

## Próximos Passos

Após a instalação bem-sucedida:

1. Teste com diferentes remessas e entidades
2. Verifique a formatação dos PDFs gerados
3. Configure backup automático dos PDFs
4. Documente o processo para outros usuários
5. Configure controle de acesso se necessário
