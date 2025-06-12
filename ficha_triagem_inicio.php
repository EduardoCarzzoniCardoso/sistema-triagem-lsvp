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
    header("Location: ficha_triagem_inicio.php");
    exit();
}

if (isset($_GET['nova'])) {
    unset($_SESSION['current_idoso_id']);
    unset($_SESSION['current_triagem_id']);
    session_write_close();
    header("Location: ficha_triagem_inicio.php");
    exit();
}

$nome_usuario_logado = $_SESSION['nome_usuario'] ?? 'Usuário Desconhecido';
$id_usuario_logado = $_SESSION['id_usuario'] ?? 0;
$id_idoso_sessao = $_SESSION['current_idoso_id'] ?? null;
$id_triagem_sessao = $_SESSION['current_triagem_id'] ?? null;

require_once 'conexaobanco.php';
$pdo = conexaodb();

$mensagem = '';
$mensagem_class = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_form = trim($_POST['nome'] ?? '');
    $cpf_form = trim($_POST['cpf'] ?? '');
    $erros = [];
    if (empty($nome_form)) $erros[] = "Nome é obrigatório.";
    if (empty($cpf_form)) $erros[] = "CPF é obrigatório.";
    if (empty($erros)) {
        $acao_avancar = isset($_POST['avancar_etapa']);
        try {
            $pdo->beginTransaction();
            if (!$id_idoso_sessao) {
                $stmt_idoso = $pdo->prepare("INSERT INTO ficha_idosos (id_usuario, nome_idoso, cpf_idoso, endereco_idoso, numero_idoso, bairro_idoso, cidade_idoso, cep_idoso, estado_idoso, rg_idoso, titulo_eleitor, carteira_profissional, reservista, certidao_nascimento, certidao_casamento, outros_documentos_idoso) VALUES (:id_usuario, :nome, :cpf, :endereco, :numero, :bairro, :cidade, :cep, :estado, :rg, :titulo_eleitor, :cart_prof, :reservista, :cert_nasc, :cert_casamento, :outros)");
                $stmt_idoso->execute([':id_usuario' => $id_usuario_logado, ':nome' => $nome_form, ':cpf' => $cpf_form, ':endereco' => trim($_POST['endereco'] ?? ''), ':numero' => trim($_POST['numero'] ?? ''), ':bairro' => trim($_POST['bairro'] ?? ''), ':cidade' => trim($_POST['cidade'] ?? ''), ':cep' => trim($_POST['cep'] ?? ''), ':estado' => trim($_POST['estado'] ?? ''), ':rg' => trim($_POST['rg'] ?? ''), ':titulo_eleitor' => trim($_POST['titulo_eleitor'] ?? ''), ':cart_prof' => trim($_POST['cart_prof'] ?? ''), ':reservista' => trim($_POST['reservista'] ?? ''), ':cert_nasc' => trim($_POST['cert_nasc'] ?? ''), ':cert_casamento' => trim($_POST['cert_casamento'] ?? ''), ':outros' => trim($_POST['outros'] ?? '')]);
                $id_idoso_sessao = $pdo->lastInsertId();
                $stmt_triagem = $pdo->prepare("INSERT INTO triagens (id_idoso, etapa_atual, status, data_de_inicio_cadastro_idoso, id_usuario_iniciou_triagem) VALUES (:id_idoso, 'Ficha de Triagem - Início', 'Em andamento', NOW(), :id_usuario_logado)");
                $stmt_triagem->execute([':id_idoso' => $id_idoso_sessao, ':id_usuario_logado' => $id_usuario_logado]);
                $id_triagem_sessao = $pdo->lastInsertId();
                $_SESSION['current_idoso_id'] = $id_idoso_sessao;
                $_SESSION['current_triagem_id'] = $id_triagem_sessao;
                $mensagem = "Rascunho salvo com sucesso!";
            } else {
                $stmt_idoso = $pdo->prepare("UPDATE ficha_idosos SET nome_idoso = :nome, cpf_idoso = :cpf, endereco_idoso = :endereco, numero_idoso = :numero, bairro_idoso = :bairro, cidade_idoso = :cidade, cep_idoso = :cep, estado_idoso = :estado, rg_idoso = :rg, titulo_eleitor = :titulo_eleitor, carteira_profissional = :cart_prof, reservista = :reservista, certidao_nascimento = :cert_nasc, certidao_casamento = :cert_casamento, outros_documentos_idoso = :outros WHERE id_idoso = :id_idoso");
                $stmt_idoso->execute([':nome' => $nome_form, ':cpf' => $cpf_form, ':endereco' => trim($_POST['endereco'] ?? ''), ':numero' => trim($_POST['numero'] ?? ''), ':bairro' => trim($_POST['bairro'] ?? ''), ':cidade' => trim($_POST['cidade'] ?? ''), ':cep' => trim($_POST['cep'] ?? ''), ':estado' => trim($_POST['estado'] ?? ''), ':rg' => trim($_POST['rg'] ?? ''), ':titulo_eleitor' => trim($_POST['titulo_eleitor'] ?? ''), ':cart_prof' => trim($_POST['cart_prof'] ?? ''), ':reservista' => trim($_POST['reservista'] ?? ''), ':cert_nasc' => trim($_POST['cert_nasc'] ?? ''), ':cert_casamento' => trim($_POST['cert_casamento'] ?? ''), ':outros' => trim($_POST['outros'] ?? ''), ':id_idoso' => $id_idoso_sessao]);
                $mensagem = "Rascunho atualizado com sucesso!";
            }
            if ($acao_avancar) {
                $stmt_triagem = $pdo->prepare("UPDATE triagens SET etapa_atual = 'Ficha de Triagem - Continuação' WHERE id_triagem = :id_triagem");
                $stmt_triagem->execute([':id_triagem' => $id_triagem_sessao]);
            }
            $pdo->commit();
            $mensagem_class = 'sucesso';
            if ($acao_avancar) {
                session_write_close();
                header("Location: ficha_triagem_continuacao.php");
                exit();
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $mensagem = 'Erro ao salvar: ' . $e->getMessage();
            $mensagem_class = 'erro';
        }
    } else {
        $mensagem = implode('<br>', $erros);
        $mensagem_class = 'erro';
    }
}

$dados_idoso = [];
if ($id_idoso_sessao) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM ficha_idosos WHERE id_idoso = :id_idoso");
        $stmt->execute([':id_idoso' => $id_idoso_sessao]);
        $dados_idoso = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$dados_idoso) {
            unset($_SESSION['current_idoso_id']);
            unset($_SESSION['current_triagem_id']);
            $id_idoso_sessao = null;
            $id_triagem_sessao = null;
        }
    } catch (PDOException $e) {
        $mensagem = 'Erro ao carregar rascunho: ' . $e->getMessage();
        $mensagem_class = 'erro';
    }
}

$etapa_atual_bd = 'Ficha de Triagem - Início';
if ($id_triagem_sessao) {
    $stmt_etapa = $pdo->prepare("SELECT etapa_atual FROM triagens WHERE id_triagem = :id_triagem");
    $stmt_etapa->execute([':id_triagem' => $id_triagem_sessao]);
    $etapa_atual_bd = $stmt_etapa->fetchColumn();
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
if ($indice_etapa_atual === false) {
    $indice_etapa_atual = 0;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Ficha de Triagem - Início</title>
    <link rel="stylesheet" href="paginainicial.css">
    <link rel="stylesheet" href="ficha_triagem_inicio.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .sidebar-button:disabled { background-color: #e9ecef; color: #6c757d; cursor: not-allowed; opacity: 0.7; }
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
                    $indice_pagina_atual = 0;
                    foreach ($ordem_etapas as $etapa_nome => $etapa_arquivo) {
                        $etapa_indice_loop = array_search($etapa_nome, $etapa_keys);
                        $link_url = ($id_idoso_sessao) ? "{$etapa_arquivo}?id_idoso={$id_idoso_sessao}&id_triagem={$id_triagem_sessao}" : "#";
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
                    <h2><?php echo $id_idoso_sessao ? 'Editando Ficha de Triagem' : 'Nova Ficha de Triagem'; ?></h2>
                    <?php if ($mensagem): ?>
                        <div class="admin-message <?= $mensagem_class ?>"><?= html_entity_decode(htmlspecialchars($mensagem)) ?></div>
                    <?php endif; ?>
                    <form class="form-grid" method="POST" action="ficha_triagem_inicio.php">
                        <fieldset>
                            <legend>Candidato - Idoso</legend>
                            <input type="text" placeholder="Nome Completo..." name="nome" value="<?= htmlspecialchars($dados_idoso['nome_idoso'] ?? '') ?>" required />
                            <input type="text" placeholder="Endereço..." name="endereco" value="<?= htmlspecialchars($dados_idoso['endereco_idoso'] ?? '') ?>" />
                            <input type="text" placeholder="Nº" name="numero" value="<?= htmlspecialchars($dados_idoso['numero_idoso'] ?? '') ?>" />
                            <input type="text" placeholder="Bairro" name="bairro" value="<?= htmlspecialchars($dados_idoso['bairro_idoso'] ?? '') ?>" />
                            <input type="text" placeholder="Cidade" name="cidade" value="<?= htmlspecialchars($dados_idoso['cidade_idoso'] ?? '') ?>" />
                            <input type="text" placeholder="CEP" name="cep" value="<?= htmlspecialchars($dados_idoso['cep_idoso'] ?? '') ?>" />
                            <input type="text" placeholder="Estado" name="estado" value="<?= htmlspecialchars($dados_idoso['estado_idoso'] ?? '') ?>" />
                        </fieldset>
                        <fieldset>
                            <legend>Documentação</legend>
                            <input type="text" placeholder="RG" name="rg" value="<?= htmlspecialchars($dados_idoso['rg_idoso'] ?? '') ?>" />
                            <input type="text" placeholder="CPF" name="cpf" value="<?= htmlspecialchars($dados_idoso['cpf_idoso'] ?? '') ?>" required />
                            <input type="text" placeholder="Título de Eleitor" name="titulo_eleitor" value="<?= htmlspecialchars($dados_idoso['titulo_eleitor'] ?? '') ?>" />
                            <input type="text" placeholder="Cart. Profissional" name="cart_prof" value="<?= htmlspecialchars($dados_idoso['carteira_profissional'] ?? '') ?>" />
                            <input type="text" placeholder="Reservista" name="reservista" value="<?= htmlspecialchars($dados_idoso['reservista'] ?? '') ?>" />
                            <input type="text" placeholder="Certidão de Nascimento" name="cert_nasc" value="<?= htmlspecialchars($dados_idoso['certidao_nascimento'] ?? '') ?>" />
                            <input type="text" placeholder="Certidão de Casamento" name="cert_casamento" value="<?= htmlspecialchars($dados_idoso['certidao_casamento'] ?? '') ?>" />
                            <textarea placeholder="Outros documentos" name="outros"><?= htmlspecialchars($dados_idoso['outros_documentos_idoso'] ?? '') ?></textarea>
                        </fieldset>
                        <div class="form-buttons">
                            <button type="submit" name="salvar_rascunho" class="btn-secondary"><?php echo $id_idoso_sessao ? 'Atualizar Rascunho' : 'Salvar Rascunho'; ?></button>
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