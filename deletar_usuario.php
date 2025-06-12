<?php
require_once 'logout_handler.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header("Location: login.php");
    exit();
}

require_once 'conexaobanco.php';
$pdo = conexaodb();

$mensagem = '';
$mensagem_class = '';

$id_usuario_para_deletar = $_GET['id'] ?? null;

if ($id_usuario_para_deletar) {
    try {
        $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = :id_usuario");
        $stmt->execute([':id_usuario' => $id_usuario_para_deletar]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['admin_message'] = 'Usuário deletado com sucesso!';
            $_SESSION['admin_message_class'] = 'sucesso';
        } else {
            $_SESSION['admin_message'] = 'Usuário não encontrado ou já foi deletado.';
            $_SESSION['admin_message_class'] = 'erro';
        }
    } catch (PDOException $e) {
        $_SESSION['admin_message'] = 'Erro ao deletar usuário: ' . $e->getMessage();
        $_SESSION['admin_message_class'] = 'erro';
    }
} else {
    $_SESSION['admin_message'] = 'ID de usuário não fornecido para deletar.';
    $_SESSION['admin_message_class'] = 'erro';
}

header("Location: usuarios.php");
exit();

?>