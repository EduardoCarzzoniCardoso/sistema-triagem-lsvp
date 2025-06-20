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
    header("Location: ficha_triagem_continuacao.php");
    exit();
}

if (!isset($_SESSION['current_idoso_id']) || !isset($_SESSION['current_triagem_id'])) {
    header("Location: ficha_triagem_inicio.php");
    exit();
}

$nome_usuario_logado = $_SESSION['nome_usuario'] ?? 'Usuário Desconhecido';
$id_idoso_sessao = $_SESSION['current_idoso_id'];
$id_triagem_sessao = $_SESSION['current_triagem_id'];

require_once 'conexaobanco.php';
$pdo = conexaodb();

$mensagem = '';
$mensagem_class = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->beginTransaction();
    try {
        $pai_nome = trim($_POST['nome_pai'] ?? '');
        $mae_nome = trim($_POST['nome_mae'] ?? '');
        $local_nascimento = trim($_POST['local_nascimento'] ?? '');
        $data_nascimento = !empty($_POST['data_nascimento']) ? trim($_POST['data_nascimento']) : null;
        $estado_civil = trim($_POST['estado_civil'] ?? '');
        $nacionalidade = trim($_POST['nacionalidade'] ?? '');
        $profissao_form = trim($_POST['profissao'] ?? '');
        $sexo = trim($_POST['sexo'] ?? '');
        $religiao = trim($_POST['religiao'] ?? '');
        $comentarios = trim($_POST['comentarios'] ?? '');

        $stmt = $pdo->prepare("UPDATE ficha_idosos SET pai_nome = :pai_nome, mae_nome = :mae_nome, local_nascimento = :local_nascimento, data_nascimento = :data_nascimento, estado_civil_idoso = :estado_civil, nacionalidade_idoso = :nacionalidade, profissão = :profissao, sexo = :sexo, religiao = :religiao, comentarios = :comentarios WHERE id_idoso = :id_idoso");
        $stmt->execute([
            ':pai_nome' => $pai_nome,
            ':mae_nome' => $mae_nome,
            ':local_nascimento' => $local_nascimento,
            ':data_nascimento' => $data_nascimento,
            ':estado_civil' => $estado_civil,
            ':nacionalidade' => $nacionalidade,
            ':profissao' => $profissao_form,
            ':sexo' => $sexo,
            ':religiao' => $religiao,
            ':comentarios' => $comentarios,
            ':id_idoso' => $id_idoso_sessao
        ]);

        $stmt_delete_parentes = $pdo->prepare("DELETE FROM parentes_ou_conhecidos WHERE id_idoso = :id_idoso");
        $stmt_delete_parentes->execute([':id_idoso' => $id_idoso_sessao]);

        if (isset($_POST['parente_nome']) && is_array($_POST['parente_nome'])) {
            $stmt_insert_parente = $pdo->prepare("INSERT INTO parentes_ou_conhecidos (id_idoso, nome_parente_conhecido, endereco_parente_conhecido, cidade_parente_conhecido, telefone_parente_conhecido) VALUES (:id_idoso, :nome, :endereco, :cidade, :telefone)");
            foreach ($_POST['parente_nome'] as $key => $nome) {
                if (!empty($nome)) {
                    $stmt_insert_parente->execute([
                        ':id_idoso' => $id_idoso_sessao,
                        ':nome' => $nome,
                        ':endereco' => $_POST['parente_endereco'][$key] ?? '',
                        ':cidade' => $_POST['parente_cidade'][$key] ?? '',
                        ':telefone' => $_POST['parente_telefone'][$key] ?? ''
                    ]);
                }
            }
        }

        $mensagem = "Dados salvos com sucesso!";
        $mensagem_class = "sucesso";

        if (isset($_POST['avancar_etapa'])) {
            $stmt_triagem = $pdo->prepare("UPDATE triagens SET etapa_atual = 'Ficha de Triagem - Contrato' WHERE id_triagem = :id_triagem");
            $stmt_triagem->execute([':id_triagem' => $id_triagem_sessao]);
            $pdo->commit();
            session_write_close();
            header("Location: ficha_triagem_contrato.php");
            exit();
        }
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $mensagem = "Erro ao salvar os dados: " . $e->getMessage();
        $mensagem_class = "erro";
    }
}

$dados_idoso = [];
$parentes = [];
$etapa_atual_bd = '';
try {
    $stmt_idoso = $pdo->prepare("SELECT * FROM ficha_idosos WHERE id_idoso = :id_idoso");
    $stmt_idoso->execute([':id_idoso' => $id_idoso_sessao]);
    $dados_idoso = $stmt_idoso->fetch(PDO::FETCH_ASSOC);

    $stmt_parentes = $pdo->prepare("SELECT * FROM parentes_ou_conhecidos WHERE id_idoso = :id_idoso");
    $stmt_parentes->execute([':id_idoso' => $id_idoso_sessao]);
    $parentes = $stmt_parentes->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Ficha de Triagem - Continuação</title>
    <link rel="stylesheet" href="paginainicial.css">
    <link rel="stylesheet" href="ficha_triagem_inicio.css">
    <link rel="stylesheet" href="chatbot.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .sidebar-button:disabled { background-color: #e9ecef; color: #6c757d; cursor: not-allowed; opacity: 0.7; }
        .remove-parente-btn { cursor: pointer; color: #dc3545; background: none; border: none; font-size: 1.5rem; line-height: 1; padding: 0 5px; }
        .data-table input[type="text"] { width: 100%; box-sizing: border-box; }
        .data-table td { vertical-align: middle; }

        .input-group {
            display: flex;
            flex-direction: column;
            flex-basis: calc(50% - 10px); 
            max-width: calc(50% - 10px); 
        }
        .input-group.w-33 { 
            flex-basis: calc(33.333% - 13.333px);
            max-width: calc(33.333% - 13.333px);
        }
        .input-group.w-100 { 
            flex-basis: 100%;
            max-width: 100%;
        }

        .input-group label {
            font-weight: 500;
            margin-bottom: 5px;
            display: block;
            color: #333;
        }
        
        fieldset {
            display: flex;
            flex-wrap: wrap; 
            gap: 20px; 
            margin-bottom: 20px;
            border: none;
            padding: 0;
        }
        fieldset legend {
            width: 100%; 
            margin-bottom: 10px;
            padding: 0;
            font-size: 1.1em;
            font-weight: bold;
            border-bottom: 1px solid #eee;
        }

        .input-group input[type="text"],
        .input-group input[type="date"],
        .input-group select,
        .input-group textarea {
            width: 100%; 
            padding: 0.6rem;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 0.95rem;
            box-sizing: border-box;
            background-color: white;
        }
        textarea {
            min-height: 60px;
            resize: vertical;
        }

        .form-grid .table-wrapper {
            width: 100%;
            margin-top: 1rem;
            margin-bottom: 1rem;
        }
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
                    <a href="ficha_triagem_continuacao.php<?php echo ($id_idoso_sessao && $id_triagem_sessao) ? "?id_idoso={$id_idoso_sessao}&id_triagem={$id_triagem_sessao}" : "#"; ?>" class="sidebar-button active<?php echo (empty($id_idoso_sessao) ? ' disabled' : ''); ?>">Ficha de Triagem - Continuação</a>
                    <a href="ficha_triagem_contrato.php<?php echo ($id_idoso_sessao && $id_triagem_sessao) ? "?id_idoso={$id_idoso_sessao}&id_triagem={$id_triagem_sessao}" : "#"; ?>" class="sidebar-button<?php echo (empty($id_idoso_sessao) ? ' disabled' : ''); ?>">Ficha de Triagem - Contrato</a>
                    <a href="parecer_coordenador.php<?php echo ($id_idoso_sessao && $id_triagem_sessao) ? "?id_idoso={$id_idoso_sessao}&id_triagem={$id_triagem_sessao}" : "#"; ?>" class="sidebar-button<?php echo (empty($id_idoso_sessao) ? ' disabled' : ''); ?>">Parecer do(a) Coordenador(a)</a>
                    <a href="parecer_diretoria.php<?php echo ($id_idoso_sessao && $id_triagem_sessao) ? "?id_idoso={$id_idoso_sessao}&id_triagem={$id_triagem_sessao}" : "#"; ?>" class="sidebar-button<?php echo (empty($id_idoso_sessao) ? ' disabled' : ''); ?>">Parecer da Diretoria</a>
                    <a href="parecer_medico.php<?php echo ($id_idoso_sessao && $id_triagem_sessao) ? "?id_idoso={$id_idoso_sessao}&id_triagem={$id_triagem_sessao}" : "#"; ?>" class="sidebar-button<?php echo (empty($id_idoso_sessao) ? ' disabled' : ''); ?>">Parecer do Médico</a>
                    <a href="parecer_psicologico.php<?php echo ($id_idoso_sessao && $id_triagem_sessao) ? "?id_idoso={$id_idoso_sessao}&id_triagem={$id_triagem_sessao}" : "#"; ?>" class="sidebar-button<?php echo (empty($id_idoso_sessao) ? ' disabled' : ''); ?>">Parecer Psicológico</a>
                    <a href="finalizacao_triagem.php<?php echo ($id_idoso_sessao && $id_triagem_sessao) ? "?id_idoso={$id_idoso_sessao}&id_triagem={$id_triagem_sessao}" : "#"; ?>" class="sidebar-button<?php echo (empty($id_idoso_sessao) ? ' disabled' : ''); ?>">Finalização da Triagem</a>
                </aside>
                <section class="triagem-form-content">
                    <h2>Ficha de Triagem - Continuação</h2>
                    <?php if ($mensagem): ?>
                        <div class="admin-message <?= $mensagem_class ?>"><?= html_entity_decode(htmlspecialchars($mensagem)) ?></div>
                    <?php endif; ?>
                    <form class="form-grid" method="POST" action="ficha_triagem_continuacao.php">
                        <fieldset>
                            <legend>Filiação</legend>
                            <div class="input-group">
                                <label for="nome_pai">Nome do Pai</label>
                                <input type="text" id="nome_pai" name="nome_pai" value="<?= htmlspecialchars($dados_idoso['pai_nome'] ?? '') ?>" />
                            </div>
                            <div class="input-group">
                                <label for="nome_mae">Nome da Mãe</label>
                                <input type="text" id="nome_mae" name="nome_mae" value="<?= htmlspecialchars($dados_idoso['mae_nome'] ?? '') ?>" />
                            </div>
                        </fieldset>
                        <fieldset>
                            <legend>Dados de Nascimento</legend>
                            <div class="input-group">
                                <label for="local_nascimento">Local de Nascimento</label>
                                <input type="text" id="local_nascimento" name="local_nascimento" value="<?= htmlspecialchars($dados_idoso['local_nascimento'] ?? '') ?>" />
                            </div>
                            <div class="input-group">
                                <label for="data_nascimento">Data de Nascimento</label>
                                <input type="date" id="data_nascimento" name="data_nascimento" value="<?= htmlspecialchars($dados_idoso['data_nascimento'] ?? '') ?>" />
                            </div>
                        </fieldset>
                        <fieldset>
                            <legend>Informações Pessoais</legend>
                            <div class="input-group">
                                <label for="estado_civil">Estado Civil</label>
                                <input type="text" id="estado_civil" name="estado_civil" value="<?= htmlspecialchars($dados_idoso['estado_civil_idoso'] ?? '') ?>" />
                            </div>
                            <div class="input-group">
                                <label for="nacionalidade">Nacionalidade</label>
                                <input type="text" id="nacionalidade" name="nacionalidade" value="<?= htmlspecialchars($dados_idoso['nacionalidade_idoso'] ?? '') ?>" />
                            </div>
                            <div class="input-group">
                                <label for="profissao">Profissão</label>
                                <input type="text" id="profissao" name="profissao" value="<?= htmlspecialchars($dados_idoso['profissão'] ?? '') ?>" />
                            </div>
                            <div class="input-group">
                                <label for="sexo">Sexo</label>
                                <select id="sexo" name="sexo">
                                    <option value="" disabled <?= empty($dados_idoso['sexo']) ? 'selected' : '' ?>>Selecione o Sexo...</option>
                                    <option value="Masculino" <?= ($dados_idoso['sexo'] ?? '') == 'Masculino' ? 'selected' : '' ?>>Masculino</option>
                                    <option value="Feminino" <?= ($dados_idoso['sexo'] ?? '') == 'Feminino' ? 'selected' : '' ?>>Feminino</option>
                                </select>
                            </div>
                            <div class="input-group">
                                <label for="religiao">Religião</label>
                                <input type="text" id="religiao" name="religiao" value="<?= htmlspecialchars($dados_idoso['religiao'] ?? '') ?>" />
                            </div>
                        </fieldset>
                        <h2>Filhos, parentes ou pessoas conhecidas</h2>
                        <div class="table-wrapper">
                            <table class="data-table">
                                <thead><tr><th>Nome</th><th>Endereço</th><th>Cidade</th><th>Telefone</th><th>Ação</th></tr></thead>
                                <tbody id="tabela-parentes-body">
                                    <?php if (!empty($parentes)): ?>
                                        <?php foreach ($parentes as $parente): ?>
                                            <tr>
                                                <td><input type="text" name="parente_nome[]" value="<?= htmlspecialchars($parente['nome_parente_conhecido']) ?>" placeholder="Nome do parente..."></td>
                                                <td><input type="text" name="parente_endereco[]" value="<?= htmlspecialchars($parente['endereco_parente_conhecido']) ?>" placeholder="Endereço..."></td>
                                                <td><input type="text" name="parente_cidade[]" value="<?= htmlspecialchars($parente['cidade_parente_conhecido']) ?>" placeholder="Cidade..."></td>
                                                <td><input type="text" name="parente_telefone[]" value="<?= htmlspecialchars($parente['telefone_parente_conhecido']) ?>" placeholder="Telefone..."></td>
                                                <td><button type="button" class="remove-parente-btn">&times;</button></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <button type="button" id="adicionar-parente" class="btn-secondary" style="margin-top: 10px; align-self: flex-start;">Adicionar Parente</button>
                        <fieldset>
                            <legend>Comentários</legend>
                            <div class="input-group full-width">
                                <label for="comentarios">Comentários</label>
                                <textarea id="comentarios" name="comentarios"><?= htmlspecialchars($dados_idoso['comentarios'] ?? '') ?></textarea>
                            </div>
                        </fieldset>
                        <div class="form-buttons">
                            <a href="ficha_triagem_inicio.php?id_idoso=<?= $id_idoso_sessao ?>&id_triagem=<?= $id_triagem_sessao ?>" class="btn-secondary">Voltar</a>
                            <button type="submit" name="atualizar_rascunho" class="btn-secondary">Atualizar Rascunho</button>
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
        document.addEventListener('DOMContentLoaded', function() {
            const addParenteBtn = document.getElementById('adicionar-parente');
            const parentesTbody = document.getElementById('tabela-parentes-body');
            function addRemoveListener(button) {
                button.addEventListener('click', function() {
                    this.closest('tr').remove();
                });
            }
            addParenteBtn.addEventListener('click', function() {
                const newRow = parentesTbody.insertRow();
                newRow.innerHTML = `<td><input type="text" name="parente_nome[]" placeholder="Nome do parente..."></td><td><input type="text" name="parente_endereco[]" placeholder="Endereço..."></td><td><input type="text" name="parente_cidade[]" placeholder="Cidade..."></td><td><input type="text" name="parente_telefone[]" placeholder="Telefone..."></td><td><button type="button" class="remove-parente-btn">&times;</button></td>`;
                addRemoveListener(newRow.querySelector('.remove-parente-btn'));
            });
            document.querySelectorAll('.remove-parente-btn').forEach(addRemoveListener);
        });
        const userNameLoggedIn = "<?php echo htmlspecialchars($nome_usuario_logado); ?>";
    </script>
    <script src="chatbot.js"></script>
</body>
</html>