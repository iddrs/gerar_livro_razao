<?php
/**
 * Arquivo de exemplo de configuração do banco de dados
 * 
 * Copie este arquivo e ajuste as configurações conforme seu ambiente
 */

// Exemplo de configuração para PostgreSQL local
$dbConfig = [
    'host' => 'localhost',
    'port' => '5432',
    'dbname' => 'contabilidade',
    'user' => 'postgres',
    'password' => 'senha123'
];

// Exemplo de configuração para PostgreSQL remoto
/*
$dbConfig = [
    'host' => '192.168.1.100',
    'port' => '5432',
    'dbname' => 'tce_municipio',
    'user' => 'usuario_contabil',
    'password' => 'senha_segura'
];
*/

// Exemplo de configuração para PostgreSQL em nuvem (AWS RDS, Azure, etc)
/*
$dbConfig = [
    'host' => 'meu-banco.abc123.us-east-1.rds.amazonaws.com',
    'port' => '5432',
    'dbname' => 'contabilidade_publica',
    'user' => 'admin_contabil',
    'password' => 'senha_muito_segura_123'
];
*/

// Teste de conexão (opcional - descomente para testar)
/*
try {
    $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']}";
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✓ Conexão bem-sucedida ao banco de dados!\n";
    
    // Testa se a tabela existe
    $stmt = $pdo->query("SELECT COUNT(*) FROM pad.tce_4111 LIMIT 1");
    echo "✓ Tabela pad.tce_4111 encontrada!\n";
    
} catch (PDOException $e) {
    echo "✗ Erro ao conectar: " . $e->getMessage() . "\n";
}
*/
?>
