<?php
require_once 'logout_handler.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id_idoso']) && isset($_GET['id_triagem'])) {
    $_SESSION['current_idoso_id'] = (int)$_GET['id_idoso'];
    $_SESSION['current_triagem_id'] = (int)$_GET['id_triagem'];
    session_write_close();
    header("Location: parecer_psicologico.php");
    exit();
}

if (!isset($_SESSION['current_idoso_id'])) {
    header("Location: ficha_triagem_inicio.php");
    exit();
}

$nome_usuario_logado = $_SESSION['nome_usuario'] ?? 'Usuário Desconhecido';
$id_usuario_logado = $_SESSION['id_usuario'] ?? 0;
$id_idoso_sessao = $_SESSION['current_idoso_id'];
$id_triagem_sessao = $_SESSION['current_triagem_id'];

require_once 'conexaobanco.php';
$pdo = conexaodb();

$mensagem = '';
$mensagem_class = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->beginTransaction();
    try {
        $conteudo_texto = trim($_POST['conteudo_texto'] ?? '');
        $parecer_final = $_POST['parecer_final'] ?? null;
        
        $check_stmt = $pdo->prepare("SELECT id_parecer_psicologico FROM parecer_psicologico WHERE id_triagem = :id_triagem");
        $check_stmt->execute([':id_triagem' => $id_triagem_sessao]);
        $parecer_id = $check_stmt->fetchColumn();

        if ($parecer_id) {
            $stmt = $pdo->prepare("UPDATE parecer_psicologico SET conteudo_texto = :conteudo, parecer_final_psicologico = :parecer_final, id_usuario = :id_usuario, data_finalizacao_parecer_psicologico = NOW() WHERE id_parecer_psicologico = :id_parecer_psicologico");
            $stmt->execute([':conteudo' => $conteudo_texto, ':parecer_final' => $parecer_final, ':id_usuario' => $id_usuario_logado, ':id_parecer_psicologico' => $parecer_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO parecer_psicologico (id_triagem, id_usuario, conteudo_texto, parecer_final_psicologico, data_finalizacao_parecer_psicologico) VALUES (:id_triagem, :id_usuario, :conteudo, :parecer_final, NOW())");
            $stmt->execute([':id_triagem' => $id_triagem_sessao, ':id_usuario' => $id_usuario_logado, ':conteudo' => $conteudo_texto, ':parecer_final' => $parecer_final]);
        }
        
        $mensagem = "Parecer psicológico salvo com sucesso!";
        $mensagem_class = "sucesso";

        if (isset($_POST['avancar_etapa'])) {
            $stmt_triagem = $pdo->prepare("UPDATE triagens SET etapa_atual = 'Finalização da Triagem' WHERE id_triagem = :id_triagem");
            $stmt_triagem->execute([':id_triagem' => $id_triagem_sessao]);
            
            $pdo->commit();
            session_write_close();
            header("Location: finalizacao_triagem.php");
            exit();
        }

        $pdo->commit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        $mensagem = "Erro ao salvar os dados: " . $e->getMessage();
        $mensagem_class = "erro";
    }
}

$parecer_psicologico = [];
$etapa_atual_bd = '';
try {
    $stmt_parecer = $pdo->prepare("SELECT * FROM parecer_psicologico WHERE id_triagem = :id_triagem");
    $stmt_parecer->execute([':id_triagem' => $id_triagem_sessao]);
    $parecer_psicologico = $stmt_parecer->fetch(PDO::FETCH_ASSOC);
    if(!$parecer_psicologico) $parecer_psicologico = [];

    $stmt_etapa = $pdo->prepare("SELECT etapa_atual FROM triagens WHERE id_triagem = :id_triagem");
    $stmt_etapa->execute([':id_triagem' => $id_triagem_sessao]);
    $etapa_atual_bd = $stmt_etapa->fetchColumn();
} catch (PDOException $e) {
    $mensagem = "Erro ao carregar os dados: " . $e->getMessage();
    $mensagem_class = "erro";
}

$ordem_etapas = [
    'Ficha de Triagem - Início' => 'ficha_triagem_inicio.php',
    'Ficha de Triagem - Continuação' => 'ficha_triagem_continuacao.php',
    'Ficha de Triagem - Contrato' => 'ficha_triagem_contrato.php',
    'Parecer do(a) Coordenador(a)' => 'parecer_coordenador.php',
    'Parecer da Diretoria' => 'parecer_diretoria.php',
    'Parecer do Médico' => 'parecer_medico.php',
    'Parecer Psicológico' => 'parecer_psicologico.php',
    'Finalização da Triagem' => 'finalizacao_triagem.php',
];
$etapa_keys = array_keys($ordem_etapas);
$indice_etapa_atual = array_search($etapa_atual_bd, $etapa_keys);
if ($indice_etapa_atual === false) $indice_etapa_atual = 0;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Parecer Psicológico</title>
    <link rel="stylesheet" href="paginainicial.css">
    <link rel="stylesheet" href="ficha_triagem_inicio.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .sidebar-button:disabled { background-color: #e9ecef; color: #6c757d; cursor: not-allowed; opacity: 0.7; }
        .radio-group { display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1rem; }
        .radio-group label { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; }
    </style>
</head>
<body class="page-triagens">
    <div class="container">
        <header class="header-top">
            <div class="logo-area"><img src="images/logo_lvsp2.png" alt="Logo SVP Brasil"><span class="username-display"><?php echo htmlspecialchars($nome_usuario_logado); ?></span></div>
            <div class="logout-area"><i class="fas fa-bell"></i><button class="logout-button" onclick="window.location.href='<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?logout=true'">Logout</button></div>
        </header>
        <nav class="main-nav">
            <ul><li><a href="paginainicial.php">Início</a></li><li><a href="triagens.php" class="active">Triagens</a></li><li><a href="#">Idosos</a></li><li><a href="#">Usuário</a></li></ul>
        </nav>
        <main class="main-content">
            <h1>Triagens</h1>
            <div class="triagem-layout">
                <aside class="triagem-sidebar">
                    <?php
                    $indice_pagina_atual = 6;
                    foreach ($ordem_etapas as $etapa_nome => $etapa_arquivo) {
                        $etapa_indice_loop = array_search($etapa_nome, $etapa_keys);
                        $link_url = "{$etapa_arquivo}?id_idoso={$id_idoso_sessao}&id_triagem={$id_triagem_sessao}";
                        if ($etapa_indice_loop < $indice_etapa_atual) {
                            echo "<a href='{$link_url}' class='sidebar-button'>{$etapa_nome}</a>";
                        } elseif ($etapa_indice_loop == $indice_pagina_atual) {
                            echo "<button class='sidebar-button active'>{$etapa_nome}</button>";
                        } else {
                            echo "<button class='sidebar-button' disabled>{$etapa_nome}</button>";
                        }
                    }
                    ?>
                </aside>
                <section class="triagem-form-content">
                    <h2>Parecer Psicológico</h2>
                    <?php if ($mensagem): ?>
                        <div class="admin-message <?= $mensagem_class ?>"><?= html_entity_decode(htmlspecialchars($mensagem)) ?></div>
                    <?php endif; ?>
                    <form method="POST" action="parecer_psicologico.php">
                        <fieldset>
                            <legend>Parecer do(a) Psicólogo(a)</legend>
                            <textarea name="conteudo_texto" rows="10" placeholder="Escreva aqui o parecer descritivo..."><?= htmlspecialchars($parecer_psicologico['conteudo_texto'] ?? '') ?></textarea>
                        </fieldset>

                        <fieldset>
                            <legend>Parecer final</legend>
                            <div class="radio-group">
                                <label><input type="radio" name="parecer_final" value="Aprovado" <?php echo (($parecer_psicologico['parecer_final_psicologico'] ?? '') === 'Aprovado') ? 'checked' : ''; ?>> O(A) psicólogo(a) confirma a aprovação do(a) candidato(a).</label>
                                <label><input type="radio" name="parecer_final" value="Rejeitado" <?php echo (($parecer_psicologico['parecer_final_psicologico'] ?? '') === 'Rejeitado') ? 'checked' : ''; ?>> O(A) psicólogo(a) confirma a rejeição do(a) candidato(a).</label>
                            </div>
                        </fieldset>
                        
                        <div class="form-buttons">
                            <a href="parecer_medico.php?id_idoso=<?= $id_idoso_sessao ?>&id_triagem=<?= $id_triagem_sessao ?>" class="btn-secondary">Voltar</a>
                            <button type="submit" name="atualizar_rascunho" class="btn-secondary">Salvar Rascunho</button>
                            <button type="submit" name="avancar_etapa" class="btn-primary">Avançar</button>
                        </div>
                    </form>
                </section>
            </div>
        </main>
        <footer class="footer-bottom">
            Sistema de Triagem LSVP - Lar São Vicente de Paulo | © 2025 - Versão 1.0
        </footer>
    </div>
</body>
</html>