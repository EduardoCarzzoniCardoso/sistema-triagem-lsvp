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

function handle_file_upload($file_input_name, $id_triagem, $document_type) {
    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
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
            'anexo_certidao' => handle_file_upload('anexo_certidao', $id_triagem_sessao, 'certidao') ?? ($existing_record['anexo_certidao_nasc_ou_casamento'] ?? null),
            'anexo_rg' => handle_file_upload('anexo_rg', $id_triagem_sessao, 'rg') ?? ($existing_record['anexo_rg'] ?? null),
            'anexo_cpf' => handle_file_upload('anexo_cpf', $id_triagem_sessao, 'cpf') ?? ($existing_record['anexo_cpf'] ?? null),
            'anexo_receituarios' => handle_file_upload('anexo_receituarios', $id_triagem_sessao, 'receituarios') ?? ($existing_record['anexo_receituarios'] ?? null),
            'anexo_medicamentos' => handle_file_upload('anexo_medicamentos', $id_triagem_sessao, 'medicamentos') ?? ($existing_record['anexo_medicamentos'] ?? null),
            'anexo_fotos' => handle_file_upload('anexo_fotos', $id_triagem_sessao, 'fotos3x4') ?? ($existing_record['anexo_duas_fotos_3x4'] ?? null),
            'caminho_relatorio_gerado' => handle_file_upload('anexo_relatorio', $id_triagem_sessao, 'relatorio') ?? ($existing_record['caminho_relatorio_gerado'] ?? null),
            'caminho_contrato_gerado' => handle_file_upload('anexo_contrato', $id_triagem_sessao, 'contrato') ?? ($existing_record['caminho_contrato_gerado'] ?? null),
        ];

        $params = [
            ':id_triagem' => $id_triagem_sessao, ':id_usuario' => $id_usuario_logado,
            ':certidao' => isset($_POST['certidao']) ? 1 : 0, ':anexo_certidao' => $anexos['anexo_certidao'],
            ':rg' => isset($_POST['rg']) ? 1 : 0, ':anexo_rg' => $anexos['anexo_rg'],
            ':cpf' => isset($_POST['cpf']) ? 1 : 0, ':anexo_cpf' => $anexos['anexo_cpf'],
            ':receituarios' => isset($_POST['receituarios']) ? 1 : 0, ':anexo_receituarios' => $anexos['anexo_receituarios'],
            ':medicamentos' => isset($_POST['medicamentos']) ? 1 : 0, ':anexo_medicamentos' => $anexos['anexo_medicamentos'],
            ':roupas' => isset($_POST['roupas']) ? 1 : 0,
            ':fotos' => isset($_POST['fotos']) ? 1 : 0, ':anexo_fotos' => $anexos['anexo_fotos'],
            ':caminho_relatorio_gerado' => $anexos['caminho_relatorio_gerado'],
            ':caminho_contrato_gerado' => $anexos['caminho_contrato_gerado'],
        ];

        if ($existing_record) {
            $sql = "UPDATE finalizacao_triagem SET id_usuario = :id_usuario, certidao_nasc_ou_casamento = :certidao, anexo_certidao_nasc_ou_casamento = :anexo_certidao, rg = :rg, anexo_rg = :anexo_rg, cpf = :cpf, anexo_cpf = :anexo_cpf, receituarios = :receituarios, anexo_receituarios = :anexo_receituarios, medicamentos = :medicamentos, anexo_medicamentos = :anexo_medicamentos, roupas_uso_pessoal = :roupas, duas_fotos_3x4 = :fotos, anexo_duas_fotos_3x4 = :anexo_fotos, caminho_relatorio_gerado = :caminho_relatorio_gerado, caminho_contrato_gerado = :caminho_contrato_gerado WHERE id_triagem = :id_triagem";
        } else {
            $sql = "INSERT INTO finalizacao_triagem (id_triagem, id_usuario, certidao_nasc_ou_casamento, anexo_certidao_nasc_ou_casamento, rg, anexo_rg, cpf, anexo_cpf, receituarios, anexo_receituarios, medicamentos, anexo_medicamentos, roupas_uso_pessoal, duas_fotos_3x4, anexo_duas_fotos_3x4, caminho_relatorio_gerado, caminho_contrato_gerado) VALUES (:id_triagem, :id_usuario, :certidao, :anexo_certidao, :rg, :anexo_rg, :cpf, :anexo_cpf, :receituarios, :anexo_receituarios, :medicamentos, :anexo_medicamentos, :roupas, :fotos, :anexo_fotos, :caminho_relatorio_gerado, :caminho_contrato_gerado)";
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
            $_SESSION['mensagem_sucesso'] = "Triagem finalizada com sucesso!";
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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Finalização da Triagem</title>
    <link rel="stylesheet" href="paginainicial.css">
    <link rel="stylesheet" href="ficha_triagem_inicio.css">
    <link rel="stylesheet" href="chatbot.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .sidebar-button:disabled { background-color: #e9ecef; color: #6c757d; cursor: not-allowed; opacity: 0.7; }
        .checklist-fieldset { display: block !important; border: 1px solid #dee2e6 !important; border-radius: 8px !important; padding: 1.5rem !important; margin-bottom: 1.5rem !important; }
        .checklist-fieldset legend { width: auto; border-bottom: none; }
        .checklist-item { display: flex; align-items: center; gap: 15px; padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
        .checklist-item:last-child { border-bottom: none; padding-bottom: 0; }
        .checklist-item:first-child { padding-top: 0; }
        .checklist-item .item-label { flex-grow: 1; margin: 0; display: flex; align-items: center; gap: 10px; font-weight: 500; }
        .checklist-item input[type="file"] { display: none; }
        .checklist-item .file-upload-label { background-color: #007bff; color: white; padding: 8px 12px; border-radius: 6px; cursor: pointer; transition: background-color 0.2s; white-space: nowrap; font-size: 0.9em; }
        .checklist-item .file-upload-label:hover { background-color: #0056b3; }
        .checklist-item .view-anexo-btn { background-color: #6c757d; margin-left: auto; color: white; text-decoration: none; padding: 8px 12px; border-radius: 6px; white-space: nowrap; font-size: 0.9em; }
        .btn-action[disabled] { background-color: #adb5bd; cursor: not-allowed; }
        .form-buttons { flex-wrap: wrap; }
        .final-action-button { width: 100%; padding: 15px; font-size: 1.2em; margin-top: 20px; }
        .custom-checkbox { position: relative; display: inline-block; width: 20px; height: 20px; flex-shrink: 0; }
        .custom-checkbox input { opacity: 0; width: 0; height: 0; }
        .custom-checkbox .checkmark { position: absolute; top: 0; left: 0; height: 20px; width: 20px; background-color: #fff; border: 2px solid #adb5bd; border-radius: 4px; transition: all 0.2s; }
        .custom-checkbox input:checked ~ .checkmark { background-color: #007bff; border-color: #007bff; }
        .custom-checkbox .checkmark:after { content: ""; position: absolute; display: none; }
        .custom-checkbox input:checked ~ .checkmark:after { display: block; }
        .custom-checkbox .checkmark:after { left: 6px; top: 2px; width: 5px; height: 10px; border: solid white; border-width: 0 3px 3px 0; transform: rotate(45deg); }
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
                    <a href="parecer_diretoria.php<?php echo ($id_idoso_sessao && $id_triagem_sessao) ? "?id_idoso={$id_idoso_sessao}&id_triagem={$id_triagem_sessao}" : "#"; ?>" class="sidebar-button<?php echo (empty($id_idoso_sessao) ? ' disabled' : ''); ?>">Parecer da Diretoria</a>
                    <a href="parecer_medico.php<?php echo ($id_idoso_sessao && $id_triagem_sessao) ? "?id_idoso={$id_idoso_sessao}&id_triagem={$id_triagem_sessao}" : "#"; ?>" class="sidebar-button<?php echo (empty($id_idoso_sessao) ? ' disabled' : ''); ?>">Parecer do Médico</a>
                    <a href="parecer_psicologico.php<?php echo ($id_idoso_sessao && $id_triagem_sessao) ? "?id_idoso={$id_idoso_sessao}&id_triagem={$id_triagem_sessao}" : "#"; ?>" class="sidebar-button<?php echo (empty($id_idoso_sessao) ? ' disabled' : ''); ?>">Parecer Psicológico</a>
                    <a href="finalizacao_triagem.php<?php echo ($id_idoso_sessao && $id_triagem_sessao) ? "?id_idoso={$id_idoso_sessao}&id_triagem={$id_triagem_sessao}" : "#"; ?>" class="sidebar-button active<?php echo (empty($id_idoso_sessao) ? ' disabled' : ''); ?>">Finalização da Triagem</a>
                </aside>
                <section class="triagem-form-content">
                    <h2>Finalização da Triagem</h2>
                    <?php if ($mensagem): ?>
                        <div class="admin-message <?= $mensagem_class ?>"><?= html_entity_decode(htmlspecialchars($mensagem)) ?></div>
                    <?php endif; ?>
                    <form method="POST" action="finalizacao_triagem.php" enctype="multipart/form-data">
                        <fieldset class="checklist-fieldset">
                            <legend>Documentos e itens necessários</legend>
                            <div class="checklist-item"><label class="item-label"><label class="custom-checkbox"><input type="checkbox" name="certidao" value="1" <?php echo !empty($dados_finalizacao['certidao_nasc_ou_casamento']) ? 'checked' : ''; ?>><span class="checkmark"></span></label> Cópia de certidão de nascimento ou de casamento</label><?php if(!empty($dados_finalizacao['anexo_certidao_nasc_ou_casamento'])) echo "<a href='{$dados_finalizacao['anexo_certidao_nasc_ou_casamento']}' class='view-anexo-btn' target='_blank'>Ver Anexo</a>"; ?><label for="anexo_certidao" class="file-upload-label">Anexar Documento</label><input type="file" id="anexo_certidao" name="anexo_certidao"></div>
                            <div class="checklist-item"><label class="item-label"><label class="custom-checkbox"><input type="checkbox" name="rg" value="1" <?php echo !empty($dados_finalizacao['rg']) ? 'checked' : ''; ?>><span class="checkmark"></span></label> RG</label><?php if(!empty($dados_finalizacao['anexo_rg'])) echo "<a href='{$dados_finalizacao['anexo_rg']}' class='view-anexo-btn' target='_blank'>Ver Anexo</a>"; ?><label for="anexo_rg" class="file-upload-label">Anexar Documento</label><input type="file" id="anexo_rg" name="anexo_rg"></div>
                            <div class="checklist-item"><label class="item-label"><label class="custom-checkbox"><input type="checkbox" name="cpf" value="1" <?php echo !empty($dados_finalizacao['cpf']) ? 'checked' : ''; ?>><span class="checkmark"></span></label> CPF</label><?php if(!empty($dados_finalizacao['anexo_cpf'])) echo "<a href='{$dados_finalizacao['anexo_cpf']}' class='view-anexo-btn' target='_blank'>Ver Anexo</a>"; ?><label for="anexo_cpf" class="file-upload-label">Anexar Documento</label><input type="file" id="anexo_cpf" name="anexo_cpf"></div>
                            <div class="checklist-item"><label class="item-label"><label class="custom-checkbox"><input type="checkbox" name="receituarios" value="1" <?php echo !empty($dados_finalizacao['receituarios']) ? 'checked' : ''; ?>><span class="checkmark"></span></label> Receituários</label><?php if(!empty($dados_finalizacao['anexo_receituarios'])) echo "<a href='{$dados_finalizacao['anexo_receituarios']}' class='view-anexo-btn' target='_blank'>Ver Anexo</a>"; ?><label for="anexo_receituarios" class="file-upload-label">Anexar Documento</label><input type="file" id="anexo_receituarios" name="anexo_receituarios"></div>
                            <div class="checklist-item"><label class="item-label"><label class="custom-checkbox"><input type="checkbox" name="medicamentos" value="1" <?php echo !empty($dados_finalizacao['medicamentos']) ? 'checked' : ''; ?>><span class="checkmark"></span></label> Medicamentos</label><?php if(!empty($dados_finalizacao['anexo_medicamentos'])) echo "<a href='{$dados_finalizacao['anexo_medicamentos']}' class='view-anexo-btn' target='_blank'>Ver Anexo</a>"; ?><label for="anexo_medicamentos" class="file-upload-label">Anexar Documento</label><input type="file" id="anexo_medicamentos" name="anexo_medicamentos"></div>
                            <div class="checklist-item"><label class="item-label"><label class="custom-checkbox"><input type="checkbox" name="roupas" value="1" <?php echo !empty($dados_finalizacao['roupas_uso_pessoal']) ? 'checked' : ''; ?>><span class="checkmark"></span></label> Roupas de uso pessoal</label></div>
                            <div class="checklist-item"><label class="item-label"><label class="custom-checkbox"><input type="checkbox" name="fotos" value="1" <?php echo !empty($dados_finalizacao['duas_fotos_3x4']) ? 'checked' : ''; ?>><span class="checkmark"></span></label> Duas fotos 3x4 recentes</label><?php if(!empty($dados_finalizacao['anexo_duas_fotos_3x4'])) echo "<a href='{$dados_finalizacao['anexo_duas_fotos_3x4']}' class='view-anexo-btn' target='_blank'>Ver Anexo</a>"; ?><label for="anexo_fotos" class="file-upload-label">Anexar Documento</label><input type="file" id="anexo_fotos" name="anexo_fotos"></div>
                        </fieldset>
                        <fieldset class="checklist-fieldset">
                            <legend>Relatórios e contrato</legend>
                            <div class="checklist-item">
                                <label class="item-label">
                                    <label class="custom-checkbox"><input type="checkbox" name="gerar_relatorio" value="1" disabled><span class="checkmark"></span></label> Relatório de triagem completa
                                </label>
                                <?php if(!empty($dados_finalizacao['caminho_relatorio_gerado'])) echo "<a href='{$dados_finalizacao['caminho_relatorio_gerado']}' class='view-anexo-btn' target='_blank'>Ver Anexo</a>"; ?>
                                <label for="anexo_relatorio" class="file-upload-label">Anexar Relatório</label>
                                <input type="file" id="anexo_relatorio" name="anexo_relatorio">
                            </div>
                            <div class="checklist-item">
                                <label class="item-label">
                                    <label class="custom-checkbox"><input type="checkbox" name="gerar_contrato" value="1" disabled><span class="checkmark"></span></label> Contrato
                                </label>
                                <?php if(!empty($dados_finalizacao['caminho_contrato_gerado'])) echo "<a href='{$dados_finalizacao['caminho_contrato_gerado']}' class='view-anexo-btn' target='_blank'>Ver Anexo</a>"; ?>
                                <label for="anexo_contrato" class="file-upload-label">Anexar Contrato</label>
                                <input type="file" id="anexo_contrato" name="anexo_contrato">
                            </div>
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
    <script>
        const userNameLoggedIn = "<?php echo htmlspecialchars($nome_usuario_logado); ?>";
    </script>
    <script src="chatbot.js"></script>
</body>
</html>