<?php
require_once 'logout_handler.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id_idoso']) || !isset($_GET['id_triagem'])) {
    header("Location: triagens.php");
    exit();
}

$id_idoso = (int)$_GET['id_idoso'];
$id_triagem = (int)$_GET['id_triagem'];

require_once 'conexaobanco.php';
$pdo = conexaodb();

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id_parecer_medico FROM parecer_medico WHERE id_triagem = :id_triagem");
    $stmt->execute([':id_triagem' => $id_triagem]);
    $parecer_medico = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($parecer_medico) {
        $stmt = $pdo->prepare("DELETE FROM checkbox_medico WHERE id_parecer_medico = :id_parecer_medico");
        $stmt->execute([':id_parecer_medico' => $parecer_medico['id_parecer_medico']]);
    }
    
    $tables_to_delete_by_triagem = [
        'finalizacao_triagem',
        'parecer_psicologico',
        'parecer_medico',
        'parecer_coordenador_diretoria'
    ];
    foreach ($tables_to_delete_by_triagem as $table) {
        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id_triagem = :id_triagem");
        $stmt->execute([':id_triagem' => $id_triagem]);
    }
    
    $tables_to_delete_by_idoso = [
        'dados_complementares_contrato',
        'parentes_ou_conhecidos',
        'triagens'
    ];
    foreach ($tables_to_delete_by_idoso as $table) {
        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id_idoso = :id_idoso");
        $stmt->execute([':id_idoso' => $id_idoso]);
    }

    $stmt = $pdo->prepare("DELETE FROM ficha_idosos WHERE id_idoso = :id_idoso");
    $stmt->execute([':id_idoso' => $id_idoso]);
    
    $pdo->commit();
    
    $_SESSION['mensagem_sucesso'] = "Triagem e todos os dados associados foram excluídos permanentemente.";

} catch (PDOException $e) {
    $pdo->rollBack();
    $_SESSION['mensagem_erro'] = "Erro ao excluir a triagem: " . $e->getMessage();
}

session_write_close();
header("Location: triagens.php");
exit();