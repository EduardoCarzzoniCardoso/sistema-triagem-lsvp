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
    header("Location: finalizacao_triagem.php");
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

function handle_file_upload($file_input_name, $id_triagem, $document_type) {
    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/uploads/';
        $extensao = strtolower(pathinfo($_FILES[$file_input_name]['name'], PATHINFO_EXTENSION));
        $nome_arquivo = $document_type . "_" . $id_triagem . "_" . time() . "." . $extensao;
        $caminho_anexo = $upload_dir . $nome_arquivo;
        if (move_uploaded_file($_FILES[$file_input_name]['tmp_name'], $caminho_anexo)) {
            return 'uploads/' . $nome_arquivo;
        }
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->beginTransaction();
    try {
        $check_stmt = $pdo->prepare("SELECT * FROM finalizacao_triagem WHERE id_triagem = :id_triagem");
        $check_stmt->execute([':id_triagem' => $id_triagem_sessao]);
        $existing_record = $check_stmt->fetch(PDO::FETCH_ASSOC);

        $anexos = [
            'anexo_certidao' => handle_file_upload('anexo_certidao', $id_triagem_sessao, 'certidao') ?? $existing_record['anexo_certidao_nasc_ou_casamento'] ?? null,
            'anexo_rg' => handle_file_upload('anexo_rg', $id_triagem_sessao, 'rg') ?? $existing_record['anexo_rg'] ?? null,
            'anexo_cpf' => handle_file_upload('anexo_cpf', $id_triagem_sessao, 'cpf') ?? $existing_record['anexo_cpf'] ?? null,
            'anexo_receituarios' => handle_file_upload('anexo_receituarios', $id_triagem_sessao, 'receituarios') ?? $existing_record['anexo_receituarios'] ?? null,
            'anexo_medicamentos' => handle_file_upload('anexo_medicamentos', $id_triagem_sessao, 'medicamentos') ?? $existing_record['anexo_medicamentos'] ?? null,
            'anexo_fotos' => handle_file_upload('anexo_fotos', $id_triagem_sessao, 'fotos3x4') ?? $existing_record['anexo_duas_fotos_3x4'] ?? null,
        ];

        $params = [
            ':id_triagem' => $id_triagem_sessao,
            ':id_usuario' => $id_usuario_logado,
            ':certidao' => isset($_POST['certidao']) ? 1 : 0,
            ':anexo_certidao' => $anexos['anexo_certidao'],
            ':rg' => isset($_POST['rg']) ? 1 : 0,
            ':anexo_rg' => $anexos['anexo_rg'],
            ':cpf' => isset($_POST['cpf']) ? 1 : 0,
            ':anexo_cpf' => $anexos['anexo_cpf'],
            ':receituarios' => isset($_POST['receituarios']) ? 1 : 0,
            ':anexo_receituarios' => $anexos['anexo_receituarios'],
            ':medicamentos' => isset($_POST['medicamentos']) ? 1 : 0,
            ':anexo_medicamentos' => $anexos['anexo_medicamentos'],
            ':roupas' => isset($_POST['roupas']) ? 1 : 0,
            ':fotos' => isset($_POST['fotos']) ? 1 : 0,
            ':anexo_fotos' => $anexos['anexo_fotos'],
        ];

        if ($existing_record) {
            $sql = "UPDATE finalizacao_triagem SET id_usuario = :id_usuario, certidao_nasc_ou_casamento = :certidao, anexo_certidao_nasc_ou_casamento = :anexo_certidao, rg = :rg, anexo_rg = :anexo_rg, cpf = :cpf, anexo_cpf = :anexo_cpf, receituarios = :receituarios, anexo_receituarios = :anexo_receituarios, medicamentos = :medicamentos, anexo_medicamentos = :anexo_medicamentos, roupas_uso_pessoal = :roupas, duas_fotos_3x4 = :fotos, anexo_duas_fotos_3x4 = :anexo_fotos WHERE id_triagem = :id_triagem";
        } else {
            $sql = "INSERT INTO finalizacao_triagem (id_triagem, id_usuario, certidao_nasc_ou_casamento, anexo_certidao_nasc_ou_casamento, rg, anexo_rg, cpf, anexo_cpf, receituarios, anexo_receituarios, medicamentos, anexo_medicamentos, roupas_uso_pessoal, duas_fotos_3x4, anexo_duas_fotos_3x4) VALUES (:id_triagem, :id_usuario, :certidao, :anexo_certidao, :rg, :anexo_rg, :cpf, :anexo_cpf, :receituarios, :anexo_receituarios, :medicamentos, :anexo_medicamentos, :roupas, :fotos, :anexo_fotos)";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $mensagem = "Dados da finalização salvos com sucesso!";
        $mensagem_class = "sucesso";

        if (isset($_POST['finalizar_triagem'])) {
            $stmt_triagem = $pdo->prepare("UPDATE triagens SET etapa_atual = 'Finalizada', status = 'Concluida', id_usuario_finalizou_triagem = :id_usuario, data_finalizacao_geral_triagem = NOW() WHERE id_triagem = :id_triagem");
            $stmt_triagem->execute([':id_usuario' => $id_usuario_logado, ':id_triagem' => $id_triagem_sessao]);
            
            $stmt_idoso = $pdo->prepare("UPDATE ficha_idosos SET data_finalizacao_ficha = NOW() WHERE id_idoso = :id_idoso");
            $stmt_idoso->execute([':id_idoso' => $id_idoso_sessao]);
            
            unset($_SESSION['current_idoso_id']);
            unset($_SESSION['current_triagem_id']);
            
            $pdo->commit();
            session_write_close();
            header("Location: triagens.php");
            exit();
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensagem = "Erro ao salvar os dados: " . $e->getMessage();
        $mensagem_class = "erro";
    }
}

$dados_finalizacao = [];
$etapa_atual_bd = '';
try {
    $stmt_final = $pdo->prepare("SELECT * FROM finalizacao_triagem WHERE id_triagem = :id_triagem");
    $stmt_final->execute([':id_triagem' => $id_triagem_sessao]);
    $dados_finalizacao = $stmt_final->fetch(PDO::FETCH_ASSOC);
    if(!$dados_finalizacao) $dados_finalizacao = [];
    
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
    <title>Finalização da Triagem</title>
    <link rel="stylesheet" href="paginainicial.css">
    <link rel="stylesheet" href="ficha_triagem_inicio.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .sidebar-button:disabled { background-color: #e9ecef; color: #6c757d; cursor: not-allowed; opacity: 0.7; }
        .checklist-item { display: flex; align-items: center; gap: 15px; padding: 10px; border-bottom: 1px solid #eee; }
        .checklist-item label { flex-grow: 1; margin: 0; }
        .checklist-item .btn-action { flex-shrink: 0; }
        .final-action-button { width: 100%; padding: 15px; font-size: 1.2em; margin-top: 20px; }
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
                    $indice_pagina_atual = 7;
                    foreach ($ordem_etapas as $etapa_nome => $etapa_arquivo) {
                        $etapa_indice_loop = array_search($etapa_nome, $etapa_keys);
                        $link_url = "{$etapa_arquivo}?id_idoso={$id_idoso_sessao}&id_triagem={$id_triagem_sessao}";
                        if ($etapa_indice_loop < $indice_pagina_atual) {
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
                    <h2>Finalização da Triagem</h2>
                    <?php if ($mensagem): ?>
                        <div class="admin-message <?= $mensagem_class ?>"><?= html_entity_decode(htmlspecialchars($mensagem)) ?></div>
                    <?php endif; ?>
                    <form method="POST" action="finalizacao_triagem.php" enctype="multipart/form-data">
                        <fieldset>
                            <legend>Documentos e itens necessários</legend>
                            <div class="checklist-item"><label><input type="checkbox" name="certidao" value="1" <?php echo !empty($dados_finalizacao['certidao_nasc_ou_casamento']) ? 'checked' : ''; ?>> Cópia de certidão de nascimento ou de casamento</label><?php if(!empty($dados_finalizacao['anexo_certidao_nasc_ou_casamento'])) echo "<a href='{$dados_finalizacao['anexo_certidao_nasc_ou_casamento']}' target='_blank'>Ver</a>"; ?><input type="file" name="anexo_certidao"></div>
                            <div class="checklist-item"><label><input type="checkbox" name="rg" value="1" <?php echo !empty($dados_finalizacao['rg']) ? 'checked' : ''; ?>> RG</label><?php if(!empty($dados_finalizacao['anexo_rg'])) echo "<a href='{$dados_finalizacao['anexo_rg']}' target='_blank'>Ver</a>"; ?><input type="file" name="anexo_rg"></div>
                            <div class="checklist-item"><label><input type="checkbox" name="cpf" value="1" <?php echo !empty($dados_finalizacao['cpf']) ? 'checked' : ''; ?>> CPF</label><?php if(!empty($dados_finalizacao['anexo_cpf'])) echo "<a href='{$dados_finalizacao['anexo_cpf']}' target='_blank'>Ver</a>"; ?><input type="file" name="anexo_cpf"></div>
                            <div class="checklist-item"><label><input type="checkbox" name="receituarios" value="1" <?php echo !empty($dados_finalizacao['receituarios']) ? 'checked' : ''; ?>> Receituários</label><?php if(!empty($dados_finalizacao['anexo_receituarios'])) echo "<a href='{$dados_finalizacao['anexo_receituarios']}' target='_blank'>Ver</a>"; ?><input type="file" name="anexo_receituarios"></div>
                            <div class="checklist-item"><label><input type="checkbox" name="medicamentos" value="1" <?php echo !empty($dados_finalizacao['medicamentos']) ? 'checked' : ''; ?>> Medicamentos</label><?php if(!empty($dados_finalizacao['anexo_medicamentos'])) echo "<a href='{$dados_finalizacao['anexo_medicamentos']}' target='_blank'>Ver</a>"; ?><input type="file" name="anexo_medicamentos"></div>
                            <div class="checklist-item"><label><input type="checkbox" name="roupas" value="1" <?php echo !empty($dados_finalizacao['roupas_uso_pessoal']) ? 'checked' : ''; ?>> Roupas de uso pessoal</label></div>
                            <div class="checklist-item"><label><input type="checkbox" name="fotos" value="1" <?php echo !empty($dados_finalizacao['duas_fotos_3x4']) ? 'checked' : ''; ?>> Duas fotos 3x4 recentes</label><?php if(!empty($dados_finalizacao['anexo_duas_fotos_3x4'])) echo "<a href='{$dados_finalizacao['anexo_duas_fotos_3x4']}' target='_blank'>Ver</a>"; ?><input type="file" name="anexo_fotos"></div>
                        </fieldset>
                        <fieldset>
                            <legend>Relatórios e contrato</legend>
                            <div class="checklist-item"><label><input type="checkbox" name="gerar_relatorio" value="1" disabled> Gerar relatório de triagem completa</label><button type="button" class="btn-action" disabled>Gerar</button></div>
                            <div class="checklist-item"><label><input type="checkbox" name="gerar_contrato" value="1" disabled> Gerar contrato</label><button type="button" class="btn-action" disabled>Gerar</button></div>
                        </fieldset>
                        <div class="form-buttons">
                             <a href="parecer_psicologico.php?id_idoso=<?= $id_idoso_sessao ?>&id_triagem=<?= $id_triagem_sessao ?>" class="btn-secondary">Voltar</a>
                             <button type="submit" name="salvar_rascunho" class="btn-secondary">Salvar</button>
                             <button type="submit" name="finalizar_triagem" class="btn-primary final-action-button">Finalizar Triagem</button>
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