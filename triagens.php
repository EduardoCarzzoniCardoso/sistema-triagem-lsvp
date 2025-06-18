<?php
require_once 'logout_handler.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$mensagem_feedback = '';
$mensagem_feedback_class = '';
if (isset($_SESSION['mensagem_sucesso'])) {
    $mensagem_feedback = $_SESSION['mensagem_sucesso'];
    $mensagem_feedback_class = 'sucesso';
    unset($_SESSION['mensagem_sucesso']);
}
if (isset($_SESSION['mensagem_erro'])) {
    $mensagem_feedback = $_SESSION['mensagem_erro'];
    $mensagem_feedback_class = 'erro';
    unset($_SESSION['mensagem_erro']);
}

if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header("Location: login.php");
    exit();
}

$nome_usuario_logado = $_SESSION['nome_usuario'] ?? 'Usuário Desconhecido';

require_once 'conexaobanco.php';
$pdo = conexaodb();

$buscar_nome_cpf = trim($_GET['buscar_nome_cpf'] ?? '');
$data_inicial_filtro = $_GET['data_inicial'] ?? '';
$data_final_filtro = $_GET['data_final'] ?? '';
$tipo_data_filtro = $_GET['tipo_data'] ?? '';
$etapa_filtro = $_GET['etapa_filtro'] ?? '';

$idosos = [];
$query_base = "SELECT fi.id_idoso, t.id_triagem, fi.nome_idoso, fi.cpf_idoso, fi.data_nascimento, t.data_de_inicio_cadastro_idoso, t.etapa_atual AS etapa FROM ficha_idosos fi JOIN triagens t ON fi.id_idoso = t.id_idoso";
$where_clauses = [];
$params = [];

if (empty($etapa_filtro) || $etapa_filtro !== 'Finalizada') {
    $where_clauses[] = "t.status != 'Concluida' AND t.status != 'Cancelada'";
}
if (!empty($buscar_nome_cpf)) {
    $where_clauses[] = "(fi.nome_idoso LIKE :buscar_nome_cpf OR fi.cpf_idoso LIKE :buscar_nome_cpf)";
    $params[':buscar_nome_cpf'] = '%' . $buscar_nome_cpf . '%';
}
if (!empty($tipo_data_filtro) && (!empty($data_inicial_filtro) || !empty($data_final_filtro))) {
    $data_coluna = '';
    if ($tipo_data_filtro === 'data_nascimento') $data_coluna = 'fi.data_nascimento';
    elseif ($tipo_data_filtro === 'data_inicio_triagem') $data_coluna = 't.data_de_inicio_cadastro_idoso';
    if (!empty($data_coluna)) {
        if (!empty($data_inicial_filtro) && !empty($data_final_filtro)) {
            $where_clauses[] = "DATE({$data_coluna}) BETWEEN :data_inicial AND :data_final";
            $params[':data_inicial'] = $data_inicial_filtro;
            $params[':data_final'] = $data_final_filtro;
        } elseif (!empty($data_inicial_filtro)) {
            $where_clauses[] = "DATE({$data_coluna}) >= :data_inicial";
            $params[':data_inicial'] = $data_inicial_filtro;
        } elseif (!empty($data_final_filtro)) {
            $where_clauses[] = "DATE({$data_coluna}) <= :data_final";
            $params[':data_final'] = $data_final_filtro;
        }
    }
}
if (!empty($etapa_filtro)) {
    if ($etapa_filtro === 'Finalizada') {
        $where_clauses = array_filter($where_clauses, function($clause) { return $clause !== "t.status != 'Concluida' AND t.status != 'Cancelada'"; });
        $where_clauses[] = "t.status = 'Concluida'";
    } else {
       $where_clauses[] = "t.etapa_atual = :etapa_filtro";
       $params[':etapa_filtro'] = $etapa_filtro;
    }
}

$full_query = $query_base;
if (count($where_clauses) > 0) {
    $full_query .= " WHERE " . implode(" AND ", $where_clauses);
}
$full_query .= " ORDER BY fi.nome_idoso ASC";
$stmt = $pdo->prepare($full_query);
$stmt->execute($params);
$idosos = $stmt->fetchAll(PDO::FETCH_ASSOC);

function formatCpf($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) === 11) {
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
    return $cpf;
}
function formatDate($date) {
    if ($date && $date !== '0000-00-00 00:00:00' && $date !== '0000-00-00') {
        try { return (new DateTime($date))->format('d/m/Y'); } catch (Exception $e) { return 'Data inválida'; }
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Triagens - Sistema de Triagem LSVP</title>
    <link rel="stylesheet" href="paginainicial.css">
    <link rel="stylesheet" href="triagens.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="chatbot.css">
    <style>
        .toast-notification {
            visibility: hidden;
            min-width: 250px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 4px;
            padding: 16px;
            position: fixed;
            z-index: 100;
            right: 30px;
            bottom: 30px;
            font-size: 17px;
            opacity: 0;
            transition: visibility 0.5s, opacity 0.5s linear;
        }
        .toast-notification.show {
            visibility: visible;
            opacity: 1;
        }
        .toast-notification.sucesso {
            background-color: #28a745;
        }
        .toast-notification.erro {
            background-color: #dc3545;
        }
    </style>
</head>
<body class="page-triagens">
    <div class="container">
        <header class="header-top">
            <div class="logo-area"><img src="images/logo_lvsp2.png" alt="Logo SVP Brasil"><span class="username-display"><?php echo htmlspecialchars($nome_usuario_logado); ?></span></div>
            <div class="logout-area">
                <button class="logout-button" onclick="window.location.href='<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?logout=true'">Logout</button>
            </div>
        </header>
        <nav class="main-nav">
            <ul>
                <li><a href="paginainicial.php">Início</a></li>
                <li><a href="triagens.php" class="active">Triagens</a></li>
                <li><a href="idosos.php">Idosos</a></li>
                <li><a href="usuarios.php">Usuários</a></li>
            </ul>
        </nav>
        <main class="main-content">
            <h1>Triagens</h1>
            <div class="content-area">
                <div class="filter-section">
                    <form action="triagens.php" method="GET">
                        <div class="filter-inputs-wrapper">
                            <div class="filter-group"><label for="buscar_nome_cpf">Buscar</label><input type="text" id="buscar_nome_cpf" name="buscar_nome_cpf" placeholder="Nome ou CPF" value="<?= htmlspecialchars($buscar_nome_cpf) ?>"></div>
                            <div class="filter-group"><label>Período</label><div class="date-range-inputs"><input type="date" name="data_inicial" value="<?= htmlspecialchars($data_inicial_filtro) ?>"><input type="date" name="data_final" value="<?= htmlspecialchars($data_final_filtro) ?>"></div></div>
                            <div class="filter-group"><label>Tipo de data</label><select name="tipo_data"><option value="">Selecione</option><option value="data_nascimento" <?= $tipo_data_filtro == 'data_nascimento' ? 'selected' : '' ?>>Data de nascimento</option><option value="data_inicio_triagem" <?= $tipo_data_filtro == 'data_inicio_triagem' ? 'selected' : '' ?>>Data início triagem</option></select></div>
                            <div class="filter-group"><label>Etapa</label><select name="etapa_filtro"><option value="">Todas em Andamento</option><option value="Ficha de Triagem - Início" <?= $etapa_filtro == 'Ficha de Triagem - Início' ? 'selected' : '' ?>>Ficha de Triagem - Início</option><option value="Ficha de Triagem - Continuação" <?= $etapa_filtro == 'Ficha de Triagem - Continuação' ? 'selected' : '' ?>>Ficha de Triagem - Continuação</option><option value="Ficha de Triagem - Contrato" <?= $etapa_filtro == 'Ficha de Triagem - Contrato' ? 'selected' : '' ?>>Ficha de Triagem - Contrato</option><option value="Parecer do(a) Coordenador(a)" <?= $etapa_filtro == 'Parecer do(a) Coordenador(a)' ? 'selected' : '' ?>>Parecer do(a) Coordenador(a)</option><option value="Parecer da Diretoria" <?= $etapa_filtro == 'Parecer da Diretoria' ? 'selected' : '' ?>>Parecer da Diretoria</option><option value="Parecer do Médico" <?= $etapa_filtro == 'Parecer do Médico' ? 'selected' : '' ?>>Parecer do Médico</option><option value="Parecer Psicológico" <?= $etapa_filtro == 'Parecer Psicológico' ? 'selected' : '' ?>>Parecer Psicológico</option><option value="Finalizada" <?= $etapa_filtro == 'Finalizada' ? 'selected' : '' ?>>Finalizada</option></select></div>
                            <button type="submit" class="filter-button">Filtrar</button>
                        </div>
                    </form>
                    <a href="ficha_triagem_inicio.php?nova=1" class="start-new-triage-button">Começar Nova Triagem</a>
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr><th>Nome</th><th>CPF</th><th>Nascimento</th><th>Etapa</th><th>Data início triagem</th><th>Ações</th></tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($idosos)): ?>
                                <?php foreach ($idosos as $idoso): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($idoso['nome_idoso']) ?></td>
                                        <td><?= htmlspecialchars(formatCpf($idoso['cpf_idoso'])) ?></td>
                                        <td><?= htmlspecialchars(formatDate($idoso['data_nascimento'])) ?></td>
                                        <td><?= htmlspecialchars($idoso['etapa']) ?></td>
                                        <td><?= htmlspecialchars(formatDate($idoso['data_de_inicio_cadastro_idoso'])) ?></td>
                                        <td class="actions-cell">
                                            <a href="ficha_triagem_inicio.php?id_idoso=<?= $idoso['id_idoso'] ?>&id_triagem=<?= $idoso['id_triagem'] ?>" class="btn-action">Editar</a>
                                            <a href="excluir_triagem.php?id_idoso=<?= $idoso['id_idoso'] ?>&id_triagem=<?= $idoso['id_triagem'] ?>" class="btn-action-delete" onclick="return confirm('Você tem CERTEZA ABSOLUTA que deseja excluir esta triagem? TODOS OS DADOS (fichas, pareceres, etc.) serão APAGADOS PERMANENTEMENTE!');">Excluir</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="no-results">Nenhum registro encontrado.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
        <footer class="footer-bottom">
            Sistema de Triagem LSVP - Lar São Vicente de Paulo | © 2025 - Versão 1.0
        </footer>
    </div>

    <div id="toast-notification" class="toast-notification"></div>

    <?php if ($mensagem_feedback): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toast = document.getElementById('toast-notification');
            toast.textContent = '<?= addslashes(htmlspecialchars($mensagem_feedback)) ?>';
            toast.className = 'toast-notification show <?= $mensagem_feedback_class ?>';
            setTimeout(function(){
                toast.className = toast.className.replace(' show', '');
            }, 4000);
        });
    </script>
    <?php endif; ?>
    <script>
        const userNameLoggedIn = "<?php echo htmlspecialchars($nome_usuario_logado); ?>";
    </script>
    <script src="chatbot.js"></script>
</body>
</html>