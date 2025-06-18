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
    header("Location: parecer_diretoria.php");
    exit();
}

if (!isset($_SESSION['current_idoso_id']) || !isset($_SESSION['current_triagem_id'])) {
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
        $primeiro_parecer_status = $_POST['primeiro_parecer_status'] ?? null;
        $primeiro_parecer_obs = trim($_POST['primeiro_parecer_obs'] ?? '');
        
        $segundo_parecer_status = $_POST['segundo_parecer_status'] ?? null;
        $segundo_parecer_obs = trim($_POST['segundo_parecer_obs'] ?? '');

        if ($primeiro_parecer_status) {
            $check_stmt = $pdo->prepare("SELECT id_parecer_coordenador_diretoria FROM parecer_coordenador_diretoria WHERE id_triagem = :id_triagem AND tipo = 'Diretoria' AND ordem = 'Primeiro'");
            $check_stmt->execute([':id_triagem' => $id_triagem_sessao]);
            if ($check_stmt->fetch()) {
                $stmt = $pdo->prepare("UPDATE parecer_coordenador_diretoria SET status = :status, observacao = :obs, id_usuario = :id_usuario, responsavel_nome = :responsavel_nome, data_finalizacao_parecer_coord_dir = NOW() WHERE id_triagem = :id_triagem AND tipo = 'Diretoria' AND ordem = 'Primeiro'");
            } else {
                $stmt = $pdo->prepare("INSERT INTO parecer_coordenador_diretoria (id_triagem, tipo, ordem, status, observacao, id_usuario, responsavel_nome, data_finalizacao_parecer_coord_dir) VALUES (:id_triagem, 'Diretoria', 'Primeiro', :status, :obs, :id_usuario, :responsavel_nome, NOW())");
            }
            $stmt->execute([':id_triagem' => $id_triagem_sessao, ':status' => $primeiro_parecer_status, ':obs' => $primeiro_parecer_obs, ':id_usuario' => $id_usuario_logado, ':responsavel_nome' => $nome_usuario_logado]);
        }
        
        if ($segundo_parecer_status) {
            $check_stmt = $pdo->prepare("SELECT id_parecer_coordenador_diretoria FROM parecer_coordenador_diretoria WHERE id_triagem = :id_triagem AND tipo = 'Diretoria' AND ordem = 'Segundo'");
            $check_stmt->execute([':id_triagem' => $id_triagem_sessao]);
            if ($check_stmt->fetch()) {
                $stmt = $pdo->prepare("UPDATE parecer_coordenador_diretoria SET status = :status, observacao = :obs, id_usuario = :id_usuario, responsavel_nome = :responsavel_nome, data_finalizacao_parecer_coord_dir = NOW() WHERE id_triagem = :id_triagem AND tipo = 'Diretoria' AND ordem = 'Segundo'");
            } else {
                $stmt = $pdo->prepare("INSERT INTO parecer_coordenador_diretoria (id_triagem, tipo, ordem, status, observacao, id_usuario, responsavel_nome, data_finalizacao_parecer_coord_dir) VALUES (:id_triagem, 'Diretoria', 'Segundo', :status, :obs, :id_usuario, :responsavel_nome, NOW())");
            }
            $stmt->execute([':id_triagem' => $id_triagem_sessao, ':status' => $segundo_parecer_status, ':obs' => $segundo_parecer_obs, ':id_usuario' => $id_usuario_logado, ':responsavel_nome' => $nome_usuario_logado]);
        }

        $mensagem = "Parecer salvo com sucesso!";
        $mensagem_class = "sucesso";

        if (isset($_POST['avancar_etapa'])) {
            $stmt_triagem = $pdo->prepare("UPDATE triagens SET etapa_atual = 'Parecer do Médico' WHERE id_triagem = :id_triagem");
            $stmt_triagem->execute([':id_triagem' => $id_triagem_sessao]);
            $pdo->commit();
            session_write_close();
            header("Location: parecer_medico.php");
            exit();
        }

        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $mensagem = "Erro ao salvar os dados: " . $e->getMessage();
        $mensagem_class = "erro";
    }
}

$pareceres = [];
$etapa_atual_bd = '';
try {
    $stmt_pareceres = $pdo->prepare("SELECT * FROM parecer_coordenador_diretoria WHERE id_triagem = :id_triagem AND tipo = 'Diretoria'");
    $stmt_pareceres->execute([':id_triagem' => $id_triagem_sessao]);
    foreach($stmt_pareceres->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $pareceres[$row['ordem']] = $row;
    }

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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Parecer da Diretoria</title>
    <link rel="stylesheet" href="paginainicial.css">
    <link rel="stylesheet" href="ficha_triagem_inicio.css">
    <link rel="stylesheet" href="chatbot.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .sidebar-button:disabled { background-color: #e9ecef; color: #6c757d; cursor: not-allowed; opacity: 0.7; }
        .parecer-section { margin-bottom: 2rem; border: 1px solid #ddd; padding: 1.5rem; border-radius: 8px; }
        .parecer-section h3 { margin-top: 0; border-bottom: 2px solid #007bff; padding-bottom: 10px; margin-bottom: 1.5rem; }
        .radio-group { display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1rem; }
        .radio-group label { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; }
    </style>
</head>
<body class="page-triagens">
    <div class="container">
        <header class="header-top">
            <div class="logo-area"><img src="images/logo_lvsp2.png" alt="Logo SVP Brasil"><span class="username-display"><?php echo htmlspecialchars($nome_usuario_logado); ?></span></div>
            <div class="logout-area"><button class="logout-button" onclick="window.location.href='<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?logout=true'">Logout</button></div>
        </header>
        <nav class="main-nav">
            <ul><li><a href="paginainicial.php">Início</a></li><li><a href="triagens.php" class="active">Triagens</a></li><li><a href="idosos.php">Idosos</a></li><li><a href="usuarios.php">Usuário</a></li></ul>
        </nav>
        <main class="main-content">
            <h1>Triagens</h1>
            <div class="triagem-layout">
                <aside class="triagem-sidebar">
                    <a href="ficha_triagem_inicio.php<?php echo ($id_idoso_sessao && $id_triagem_sessao) ? "?id_idoso={$id_idoso_sessao}&id_triagem={$id_triagem_sessao}" : "?nova=true"; ?>" class="sidebar-button">Ficha de Triagem - Início</a>
                    <a href="ficha_triagem_continuacao.php<?php echo ($id_idoso_sessao && $id_triagem_sessao) ? "?id_idoso={$id_idoso_sessao}&id_triagem={$id_triagem_sessao}" : "#"; ?>" class="sidebar-button<?php echo (empty($id_idoso_sessao) ? ' disabled' : ''); ?>">Ficha de Triagem - Continuação</a>
                    <a href="ficha_triagem_contrato.php<?php echo ($id_idoso_sessao && $id_triagem_sessao) ? "?id_idoso={$id_idoso_sessao}&id_triagem={$id_triagem_sessao}" : "#"; ?>" class="sidebar-button<?php echo (empty($id_idoso_sessao) ? ' disabled' : ''); ?>">Ficha de Triagem - Contrato</a>
                    <a href="parecer_coordenador.php<?php echo ($id_idoso_sessao && $id_triagem_sessao) ? "?id_idoso={$id_idoso_sessao}&id_triagem={$id_triagem_sessao}" : "#"; ?>" class="sidebar-button<?php echo (empty($id_idoso_sessao) ? ' disabled' : ''); ?>">Parecer do(a) Coordenador(a)</a>
                    <a href="parecer_diretoria.php<?php echo ($id_idoso_sessao && $id_triagem_sessao) ? "?id_idoso={$id_idoso_sessao}&id_triagem={$id_triagem_sessao}" : "#"; ?>" class="sidebar-button active<?php echo (empty($id_idoso_sessao) ? ' disabled' : ''); ?>">Parecer da Diretoria</a>
                    <a href="parecer_medico.php<?php echo ($id_idoso_sessao && $id_triagem_sessao) ? "?id_idoso={$id_idoso_sessao}&id_triagem={$id_triagem_sessao}" : "#"; ?>" class="sidebar-button<?php echo (empty($id_idoso_sessao) ? ' disabled' : ''); ?>">Parecer do Médico</a>
                    <a href="parecer_psicologico.php<?php echo ($id_idoso_sessao && $id_triagem_sessao) ? "?id_idoso={$id_idoso_sessao}&id_triagem={$id_triagem_sessao}" : "#"; ?>" class="sidebar-button<?php echo (empty($id_idoso_sessao) ? ' disabled' : ''); ?>">Parecer Psicológico</a>
                    <a href="finalizacao_triagem.php<?php echo ($id_idoso_sessao && $id_triagem_sessao) ? "?id_idoso={$id_idoso_sessao}&id_triagem={$id_triagem_sessao}" : "#"; ?>" class="sidebar-button<?php echo (empty($id_idoso_sessao) ? ' disabled' : ''); ?>">Finalização da Triagem</a>
                </aside>
                <section class="triagem-form-content">
                    <h2>Parecer da Diretoria</h2>
                    <?php if ($mensagem): ?>
                        <div class="admin-message <?= $mensagem_class ?>"><?= html_entity_decode(htmlspecialchars($mensagem)) ?></div>
                    <?php endif; ?>
                    <form method="POST" action="parecer_diretoria.php">
                        <div class="parecer-section">
                            <h3>Primeiro parecer:</h3>
                            <div class="radio-group">
                                <label><input type="radio" name="primeiro_parecer_status" value="Aprovado" <?php echo (($pareceres['Primeiro']['status'] ?? '') === 'Aprovado') ? 'checked' : ''; ?>> A diretoria aprova o acolhimento do(a) candidato(a).</label>
                                <label><input type="radio" name="primeiro_parecer_status" value="Rejeitado" <?php echo (($pareceres['Primeiro']['status'] ?? '') === 'Rejeitado') ? 'checked' : ''; ?>> A diretoria rejeita o acolhimento do(a) candidato(a).</label>
                                <label><input type="radio" name="primeiro_parecer_status" value="Lista de Espera" <?php echo (($pareceres['Primeiro']['status'] ?? '') === 'Lista de Espera') ? 'checked' : ''; ?>> A diretoria coloca o(a) candidato(a) na lista de espera.</label>
                            </div>
                            <label for="primeiro_parecer_obs">Comentários adicionais:</label>
                            <textarea id="primeiro_parecer_obs" name="primeiro_parecer_obs" rows="5" placeholder="Escreva aqui..."><?= htmlspecialchars($pareceres['Primeiro']['observacao'] ?? '') ?></textarea>
                        </div>

                        <div class="parecer-section">
                            <h3>Segundo parecer</h3>
                            <div class="radio-group">
                                <label><input type="radio" name="segundo_parecer_status" value="Aprovado" <?php echo (($pareceres['Segundo']['status'] ?? '') === 'Aprovado') ? 'checked' : ''; ?>> A diretoria confirma a aprovação do(a) candidato(a).</label>
                                <label><input type="radio" name="segundo_parecer_status" value="Rejeitado" <?php echo (($pareceres['Segundo']['status'] ?? '') === 'Rejeitado') ? 'checked' : ''; ?>> A diretoria confirma a rejeição do(a) candidato(a).</label>
                            </div>
                            <label for="segundo_parecer_obs">Comentários adicionais:</label>
                            <textarea id="segundo_parecer_obs" name="segundo_parecer_obs" rows="5" placeholder="Escreva aqui..."><?= htmlspecialchars($pareceres['Segundo']['observacao'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-buttons">
                            <a href="parecer_coordenador.php?id_idoso=<?= $id_idoso_sessao ?>&id_triagem=<?= $id_triagem_sessao ?>" class="btn-secondary">Voltar</a>
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
    <script>
        const userNameLoggedIn = "<?php echo htmlspecialchars($nome_usuario_logado); ?>";
    </script>
    <script src="chatbot.js"></script>
</body>
</html>