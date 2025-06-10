<?php
require_once 'conexaobanco.php';

echo "<h1>Iniciando atualização segura de senhas...</h1>";

$pdo = conexaodb();
if (!$pdo) {
    die("FALHA: Não foi possível conectar ao banco de dados.");
}

try {
    $stmt = $pdo->query("SELECT id_usuario, senha FROM usuarios");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($usuarios)) {
        echo "<p>Nenhum usuário encontrado para verificar.</p>";
    }

    $updateStmt = $pdo->prepare("UPDATE usuarios SET senha = :senha_hash WHERE id_usuario = :id");

    foreach ($usuarios as $usuario) {
        $id = $usuario['id_usuario'];
        $senha_no_banco = $usuario['senha'];

        if (strlen($senha_no_banco) < 60) {
            
            $senha_hash = password_hash($senha_no_banco, PASSWORD_DEFAULT);

            $updateStmt->execute(['senha_hash' => $senha_hash, 'id' => $id]);
            
            echo "<p style='color:green;'>Senha do usuário ID $id foi atualizada com sucesso!</p>";
        } else {
            echo "<p style='color:blue;'>Senha do usuário ID $id já está segura. Nenhuma ação necessária.</p>";
        }
    }

    echo "<h2>Processo finalizado.</h2>";
    echo "<strong style='color:red;'>AVISO: APAGUE ESTE ARQUIVO (atualizar_senhas.php) DO SEU SERVIDOR IMEDIATAMENTE.</strong>";

} catch (PDOException $e) {
    die("Erro ao processar o banco de dados: " . $e->getMessage());
}
?>