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
    header("Location: ficha_triagem_contrato.php");
    exit();
}

if (!isset($_SESSION['current_idoso_id'])) {
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
    try {
        $pdo->beginTransaction();

        $check_stmt = $pdo->prepare("SELECT id_dados_contrato FROM dados_complementares_contrato WHERE id_idoso = :id_idoso");
        $check_stmt->execute([':id_idoso' => $id_idoso_sessao]);
        $exists = $check_stmt->fetchColumn();

        $params = [
            ':id_idoso' => $id_idoso_sessao,
            ':situacao_ocupacional' => trim($_POST['situacao_ocupacional'] ?? ''),
            ':telefone_idoso' => trim($_POST['telefone_idoso'] ?? ''),
            ':nome_responsavel_solidario' => trim($_POST['nome_responsavel_solidario'] ?? ''),
            ':grau_de_parentesco' => trim($_POST['grau_de_parentesco'] ?? ''),
            ':logradouro_responsavel_solidario' => trim($_POST['logradouro_responsavel_solidario'] ?? ''),
            ':numero_responsavel_solidario' => trim($_POST['numero_responsavel_solidario'] ?? ''),
            ':bairro_responsavel_solidario' => trim($_POST['bairro_responsavel_solidario'] ?? ''),
            ':cidade_responsavel_solidario' => trim($_POST['cidade_responsavel_solidario'] ?? ''),
            ':cep_responsavel_solidario' => trim($_POST['cep_responsavel_solidario'] ?? ''),
            ':estado_responsavel_solidario' => trim($_POST['estado_responsavel_solidario'] ?? ''),
            ':telefone_responsavel_solidario' => trim($_POST['telefone_responsavel_solidario'] ?? ''),
            ':cpf_responsavel_solidario' => trim($_POST['cpf_responsavel_solidario'] ?? ''),
            ':estado_civil_responsavel_solidario' => trim($_POST['estado_civil_responsavel_solidario'] ?? ''),
            ':rg_responsavel_solidario' => trim($_POST['rg_responsavel_solidario'] ?? ''),
            ':nacionalidade_responsavel_solidario' => trim($_POST['nacionalidade_responsavel_solidario'] ?? ''),
            ':nome_assistente_social_contrato' => trim($_POST['nome_assistente_social_contrato'] ?? ''),
            ':nome_coordenador_administrativo_contrato' => trim($_POST['nome_coordenador_administrativo_contrato'] ?? '')
        ];

        if ($exists) {
            $sql = "UPDATE dados_complementares_contrato SET situacao_ocupacional = :situacao_ocupacional, telefone_idoso = :telefone_idoso, nome_responsavel_solidario = :nome_responsavel_solidario, grau_de_parentesco = :grau_de_parentesco, logradouro_responsavel_solidario = :logradouro_responsavel_solidario, numero_responsavel_solidario = :numero_responsavel_solidario, bairro_responsavel_solidario = :bairro_responsavel_solidario, cidade_responsavel_solidario = :cidade_responsavel_solidario, cep_responsavel_solidario = :cep_responsavel_solidario, estado_responsavel_solidario = :estado_responsavel_solidario, telefone_responsavel_solidario = :telefone_responsavel_solidario, cpf_responsavel_solidario = :cpf_responsavel_solidario, estado_civil_responsavel_solidario = :estado_civil_responsavel_solidario, rg_responsavel_solidario = :rg_responsavel_solidario, nacionalidade_responsavel_solidario = :nacionalidade_responsavel_solidario, nome_assistente_social_contrato = :nome_assistente_social_contrato, nome_coordenador_administrativo_contrato = :nome_coordenador_administrativo_contrato, data_finalizacao_dados_contrato = NOW() WHERE id_idoso = :id_idoso";
        } else {
            $sql = "INSERT INTO dados_complementares_contrato (id_idoso, situacao_ocupacional, telefone_idoso, nome_responsavel_solidario, grau_de_parentesco, logradouro_responsavel_solidario, numero_responsavel_solidario, bairro_responsavel_solidario, cidade_responsavel_solidario, cep_responsavel_solidario, estado_responsavel_solidario, telefone_responsavel_solidario, cpf_responsavel_solidario, estado_civil_responsavel_solidario, rg_responsavel_solidario, nacionalidade_responsavel_solidario, nome_assistente_social_contrato, nome_coordenador_administrativo_contrato, data_finalizacao_dados_contrato) VALUES (:id_idoso, :situacao_ocupacional, :telefone_idoso, :nome_responsavel_solidario, :grau_de_parentesco, :logradouro_responsavel_solidario, :numero_responsavel_solidario, :bairro_responsavel_solidario, :cidade_responsavel_solidario, :cep_responsavel_solidario, :estado_responsavel_solidario, :telefone_responsavel_solidario, :cpf_responsavel_solidario, :estado_civil_responsavel_solidario, :rg_responsavel_solidario, :nacionalidade_responsavel_solidario, :nome_assistente_social_contrato, :nome_coordenador_administrativo_contrato, NOW())";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $mensagem = "Dados do contrato salvos com sucesso!";
        $mensagem_class = "sucesso";

        if (isset($_POST['avancar_etapa'])) {
            $stmt_triagem = $pdo->prepare("UPDATE triagens SET etapa_atual = 'Parecer do(a) Coordenador(a)' WHERE id_triagem = :id_triagem");
            $stmt_triagem->execute([':id_triagem' => $id_triagem_sessao]);
            
            $pdo->commit();
            session_write_close();
            header("Location: parecer_coordenador.php");
            exit();
        }

        $pdo->commit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        $mensagem = "Erro ao salvar os dados: " . $e->getMessage();
        $mensagem_class = "erro";
    }
}

$dados_contrato = [];
$etapa_atual_bd = '';

try {
    $stmt_contrato = $pdo->prepare("SELECT * FROM dados_complementares_contrato WHERE id_idoso = :id_idoso");
    $stmt_contrato->execute([':id_idoso' => $id_idoso_sessao]);
    $dados_contrato = $stmt_contrato->fetch(PDO::FETCH_ASSOC);
    if(!$dados_contrato) $dados_contrato = [];

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
    <title>Ficha de Triagem - Contrato</title>
    <link rel="stylesheet" href="paginainicial.css">
    <link rel="stylesheet" href="ficha_triagem_inicio.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .sidebar-button:disabled { background-color: #e9ecef; color: #6c757d; cursor: not-allowed; opacity: 0.7; }
        .form-grid label { font-weight: 500; margin-bottom: 5px; display: block; }
        .form-grid .input-group { display: flex; flex-direction: column; }
        .fieldset-row { display: flex; gap: 20px; width: 100%; margin-bottom: 15px; }
        .fieldset-row > .input-group { flex: 1; }
    </style>
</head>
<body class="page-triagens">
    <div class="container">
        <header class="header-top">
             <div class="logo-area"><img src="images/logo_lvsp2.png" alt="Logo SVP Brasil"><span class="username-display"><?php echo htmlspecialchars($nome_usuario_logado); ?></span></div>
             <div class="logout-area"><i class="fas fa-bell"></i><button class="logout-button" onclick="window.location.href='<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?logout=true'">Logout</button></div>
        </header>
        <nav class="main-nav">
             <ul><li><a href="paginainicial.php">Início</a></li><li><a href="triagens.php" class="active">Triagens</a></li><li><a href="idosos.php">Idosos</a></li><li><a href="usuarios.php">Usuário</a></li></ul>
        </nav>
        <main class="main-content">
            <h1>Triagens</h1>
            <div class="triagem-layout">
                <aside class="triagem-sidebar">
                    <?php
                    $indice_pagina_atual = 2;
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
                    <h2>Ficha de Triagem - Contrato</h2>
                    <?php if ($mensagem): ?>
                        <div class="admin-message <?= $mensagem_class ?>"><?= html_entity_decode(htmlspecialchars($mensagem)) ?></div>
                    <?php endif; ?>
                    <form class="form-grid" method="POST" action="ficha_triagem_contrato.php">
                        <fieldset>
                            <legend>Dados Pessoais do Idoso</legend>
                            <div class="fieldset-row">
                                <div class="input-group"><label>Situação ocupacional</label><input type="text" placeholder="Aposentado, Pensionista, etc..." name="situacao_ocupacional" value="<?= htmlspecialchars($dados_contrato['situacao_ocupacional'] ?? '') ?>" /></div>
                                <div class="input-group"><label>Telefone</label><input type="text" placeholder="(XX) XXXXX-XXXX" name="telefone_idoso" value="<?= htmlspecialchars($dados_contrato['telefone_idoso'] ?? '') ?>" /></div>
                            </div>
                        </fieldset>
                        <fieldset>
                            <legend>Responsável Solidário</legend>
                            <div class="fieldset-row">
                                <div class="input-group"><label>Nome Completo</label><input type="text" placeholder="Nome do responsável..." name="nome_responsavel_solidario" value="<?= htmlspecialchars($dados_contrato['nome_responsavel_solidario'] ?? '') ?>" /></div>
                                <div class="input-group"><label>Grau de parentesco</label><input type="text" placeholder="Filho(a), Parente, etc..." name="grau_de_parentesco" value="<?= htmlspecialchars($dados_contrato['grau_de_parentesco'] ?? '') ?>" /></div>
                            </div>
                            <div class="fieldset-row">
                                <div class="input-group"><label>CPF</label><input type="text" placeholder="CPF do responsável..." name="cpf_responsavel_solidario" value="<?= htmlspecialchars($dados_contrato['cpf_responsavel_solidario'] ?? '') ?>" /></div>
                                <div class="input-group"><label>RG</label><input type="text" placeholder="RG do responsável..." name="rg_responsavel_solidario" value="<?= htmlspecialchars($dados_contrato['rg_responsavel_solidario'] ?? '') ?>" /></div>
                            </div>
                            <div class="fieldset-row">
                                <div class="input-group"><label>Estado Civil</label><input type="text" placeholder="Estado cívil do responsável..." name="estado_civil_responsavel_solidario" value="<?= htmlspecialchars($dados_contrato['estado_civil_responsavel_solidario'] ?? '') ?>" /></div>
                                <div class="input-group"><label>Nacionalidade</label><input type="text" placeholder="Nacionalidade do responsável..." name="nacionalidade_responsavel_solidario" value="<?= htmlspecialchars($dados_contrato['nacionalidade_responsavel_solidario'] ?? '') ?>" /></div>
                            </div>
                             <div class="fieldset-row">
                                 <div class="input-group"><label>Telefone</label><input type="text" placeholder="(XX) XXXXX-XXXX" name="telefone_responsavel_solidario" value="<?= htmlspecialchars($dados_contrato['telefone_responsavel_solidario'] ?? '') ?>" /></div>
                            </div>
                        </fieldset>
                        <fieldset>
                            <legend>Endereço do Responsável</legend>
                            <div class="fieldset-row">
                                <div class="input-group"><label>Logradouro</label><input type="text" placeholder="Rua, Avenida..." name="logradouro_responsavel_solidario" value="<?= htmlspecialchars($dados_contrato['logradouro_responsavel_solidario'] ?? '') ?>" /></div>
                                <div class="input-group"><label>Número</label><input type="text" placeholder="Nº..." name="numero_responsavel_solidario" value="<?= htmlspecialchars($dados_contrato['numero_responsavel_solidario'] ?? '') ?>" /></div>
                            </div>
                             <div class="fieldset-row">
                                <div class="input-group"><label>Bairro</label><input type="text" placeholder="Bairro..." name="bairro_responsavel_solidario" value="<?= htmlspecialchars($dados_contrato['bairro_responsavel_solidario'] ?? '') ?>" /></div>
                                <div class="input-group"><label>Cidade</label><input type="text" placeholder="Cidade..." name="cidade_responsavel_solidario" value="<?= htmlspecialchars($dados_contrato['cidade_responsavel_solidario'] ?? '') ?>" /></div>
                            </div>
                            <div class="fieldset-row">
                                <div class="input-group"><label>CEP</label><input type="text" placeholder="XXXXX-XXX" name="cep_responsavel_solidario" value="<?= htmlspecialchars($dados_contrato['cep_responsavel_solidario'] ?? '') ?>" /></div>
                                <div class="input-group"><label>Estado</label><input type="text" placeholder="UF" name="estado_responsavel_solidario" value="<?= htmlspecialchars($dados_contrato['estado_responsavel_solidario'] ?? '') ?>" /></div>
                            </div>
                        </fieldset>
                        <fieldset>
                            <legend>Assinaturas do Contrato</legend>
                            <div class="fieldset-row">
                                <div class="input-group"><label>Nome do(a) Assistente Social</label><input type="text" placeholder="Nome do(a) profissional..." name="nome_assistente_social_contrato" value="<?= htmlspecialchars($dados_contrato['nome_assistente_social_contrato'] ?? '') ?>" /></div>
                                <div class="input-group"><label>Nome do(a) Coordenador(a) Administrativo(a)</label><input type="text" placeholder="Nome do(a) coordenador(a)..." name="nome_coordenador_administrativo_contrato" value="<?= htmlspecialchars($dados_contrato['nome_coordenador_administrativo_contrato'] ?? '') ?>" /></div>
                            </div>
                        </fieldset>
                        <div class="form-buttons">
                            <a href="ficha_triagem_continuacao.php?id_idoso=<?= $id_idoso_sessao ?>&id_triagem=<?= $id_triagem_sessao ?>" class="btn-secondary">Voltar</a>
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
</body>
</html>