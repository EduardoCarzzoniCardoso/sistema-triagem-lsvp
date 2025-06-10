<?php
require_once 'logout_handler.php';

if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header("Location: login.php");
    exit();
}

$nome_usuario_logado = "Usuário Desconhecido";
if (isset($_SESSION['nome_usuario'])) {
    $nome_usuario_logado = $_SESSION['nome_usuario'];
}

require_once 'conexaobanco.php';
$pdo = conexaodb();

$buscar_nome_cpf = trim($_GET['buscar_nome_cpf'] ?? '');
$data_inicial_filtro = $_GET['data_inicial'] ?? '';
$data_final_filtro = $_GET['data_final'] ?? '';
$tipo_data_filtro = $_GET['tipo_data'] ?? '';
$status_filtro = $_GET['status_filtro'] ?? '';
$aba_ativa = $_GET['aba'] ?? 'espera';

$idosos = [];
$query_base = "SELECT
                    i.nome_idoso,
                    i.cpf_idoso,
                    i.data_nascimento,
                    pcd.status AS status_parecer,
                    t.data_de_inicio_cadastro_idoso,
                    pcd.data_finalizacao_parecer_coord_dir AS data_acolhimento,
                    ft.caminho_relatorio_gerado,
                    ft.caminho_contrato_gerado
                FROM
                    ficha_idosos i
                JOIN
                    triagens t ON i.id_idoso = t.id_idoso
                LEFT JOIN
                    parecer_coordenador_diretoria pcd ON t.id_triagem = pcd.id_triagem
                LEFT JOIN
                    finalizacao_triagem ft ON t.id_triagem = ft.id_triagem";

$where_clauses = [];
$params = [];

if (!empty($buscar_nome_cpf)) {
    $where_clauses[] = "(i.nome_idoso LIKE :buscar_nome_cpf OR i.cpf_idoso LIKE :buscar_nome_cpf)";
    $params[':buscar_nome_cpf'] = '%' . $buscar_nome_cpf . '%';
}

if (!empty($data_inicial_filtro) || !empty($data_final_filtro)) {
    $data_coluna = '';
    if ($tipo_data_filtro === 'data_inicio_triagem') {
        $data_coluna = 't.data_de_inicio_cadastro_idoso';
    } elseif ($tipo_data_filtro === 'data_finalizacao_parecer') {
        $data_coluna = 'pcd.data_finalizacao_parecer_coord_dir';
    }

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

if ($aba_ativa === 'espera') {
    $where_clauses[] = "pcd.status = 'Lista de Espera'";
} elseif ($aba_ativa === 'acolhidos') {
    $where_clauses[] = "pcd.status = 'Aprovado'";
}

if (!empty($status_filtro)) {
    $where_clauses[] = "pcd.status = :status_filtro";
    $params[':status_filtro'] = $status_filtro;
}

$full_query = $query_base;
if (count($where_clauses) > 0) {
    $full_query .= " WHERE " . implode(" AND ", $where_clauses);
}
$full_query .= " ORDER BY t.data_de_inicio_cadastro_idoso DESC";

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
    if ($date && $date !== '0000-00-00 00:00:00') {
        return (new DateTime($date))->format('d/m/Y');
    }
    return '';
}

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Idosos - Sistema de Triagem LSVP</title>
    <link rel="stylesheet" href="paginainicial.css">
    <link rel="stylesheet" href="idosos.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container" style = "max-height:90%, height:800px">
        <header class="header-top">
            <div class="logo-area">
                <img src="images/logo_lvsp2.png" alt="Logo SVP Brasil">
                <span class="username-display"><?php echo htmlspecialchars($nome_usuario_logado); ?></span>
            </div>
            <div class="logout-area">
                <i class="fas fa-bell"></i>
                <button class="logout-button" onclick="window.location.href='<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?logout=true'">Logout</button>
            </div>
        </header>

        <nav class="main-nav">
            <ul>
                <li><a href="paginainicial.php">Início</a></li>
                <li><a href="triagens.php">Triagens</a></li>
                <li><a href="idosos.php" class="active">Idosos</a></li>
                <li><a href="usuario.php">Usuário</a></li>
            </ul>
        </nav>

        <main class="main-content">
            <h1>Idosos</h1>

            <div class="tabs">
                <button class="tab-button <?= ($aba_ativa === 'espera') ? 'active-tab' : '' ?>" onclick="window.location.href='idosos.php?aba=espera<?= (!empty($buscar_nome_cpf) ? '&buscar_nome_cpf=' . urlencode($buscar_nome_cpf) : '') ?><?= (!empty($data_inicial_filtro) ? '&data_inicial=' . urlencode($data_inicial_filtro) : '') ?><?= (!empty($data_final_filtro) . '&data_final=' . urlencode($data_final_filtro)) ?><?= (!empty($tipo_data_filtro) ? '&tipo_data=' . urlencode($tipo_data_filtro) : '') ?><?= (!empty($status_filtro) ? '&status_filtro=' . urlencode($status_filtro) : '') ?>'">Lista de espera</button>
                <button class="tab-button <?= ($aba_ativa === 'acolhidos') ? 'active-tab' : '' ?>" onclick="window.location.href='idosos.php?aba=acolhidos<?= (!empty($buscar_nome_cpf) ? '&buscar_nome_cpf=' . urlencode($buscar_nome_cpf) : '') ?><?= (!empty($data_inicial_filtro) ? '&data_inicial=' . urlencode($data_inicial_filtro) : '') ?><?= (!empty($data_final_filtro) . '&data_final=' . urlencode($data_final_filtro)) ?><?= (!empty($tipo_data_filtro) ? '&tipo_data=' . urlencode($tipo_data_filtro) : '') ?><?= (!empty($status_filtro) ? '&status_filtro=' . urlencode($status_filtro) : '') ?>'">Idosos acolhidos</button>
            </div>

            <div class="filter-section">
                <h3>Buscar</h3>
                <form action="idosos.php" method="GET">
                    <input type="hidden" name="aba" value="<?= htmlspecialchars($aba_ativa) ?>">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label for="buscar_nome_cpf">Nome ou CPF</label>
                            <input type="text" id="buscar_nome_cpf" name="buscar_nome_cpf" placeholder="Nome ou CPF" value="<?= htmlspecialchars($buscar_nome_cpf) ?>">
                        </div>
                        <div class="filter-group">
                            <label for="tipo_data">Tipo de data</label>
                            <select id="tipo_data" name="tipo_data">
                                <option value="">Selecione</option>
                                <option value="data_inicio_triagem" <?= ($tipo_data_filtro === 'data_inicio_triagem') ? 'selected' : '' ?>>Data início triagem</option>
                                <option value="data_finalizacao_parecer" <?= ($tipo_data_filtro === 'data_finalizacao_parecer') ? 'selected' : '' ?>>Data finalização parecer</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="data_inicial">Data Inicial</label>
                            <input type="date" id="data_inicial" name="data_inicial" value="<?= htmlspecialchars($data_inicial_filtro) ?>">
                        </div>
                        <div class="filter-group">
                            <label for="data_final">Data Final</label>
                            <input type="date" id="data_final" name="data_final" value="<?= htmlspecialchars($data_final_filtro) ?>">
                        </div>
                        <div class="filter-group">
                            <label for="status_filtro">Status</label>
                            <select id="status_filtro" name="status_filtro">
                                <option value="">Todos</option>
                                <option value="Aprovado" <?= ($status_filtro === 'Aprovado') ? 'selected' : '' ?>>Aprovado</option>
                                <option value="Lista de Espera" <?= ($status_filtro === 'Lista de Espera') ? 'selected' : '' ?>>Lista de Espera</option>
                                <option value="Reprovado" <?= ($status_filtro === 'Reprovado') ? 'selected' : '' ?>>Reprovado</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <button type="submit" class="filter-button">Filtrar</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="data-table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>CPF</th>
                            <th>Nascimento</th>
                            <th>Status</th>
                            <th><?php echo ($aba_ativa === 'acolhidos') ? 'Data de Acolhimento' : 'Data Início Triagem'; ?></th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($idosos) > 0): ?>
                            <?php foreach ($idosos as $idoso): ?>
                                <tr>
                                    <td><?= htmlspecialchars($idoso['nome_idoso']) ?></td>
                                    <td><?= htmlspecialchars(formatCpf($idoso['cpf_idoso'])) ?></td>
                                    <td><?= htmlspecialchars(formatDate($idoso['data_nascimento'])) ?></td>
                                    <td><?= htmlspecialchars($idoso['status_parecer']) ?></td>
                                    <td>
                                        <?php if ($aba_ativa === 'acolhidos'): ?>
                                            <?= htmlspecialchars(formatDate($idoso['data_acolhimento'])) ?>
                                        <?php else: ?>
                                            <?= htmlspecialchars(formatDate($idoso['data_de_inicio_cadastro_idoso'])) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="table-actions">
                                        <?php if (!empty($idoso['caminho_relatorio_gerado'])): ?>
                                            <a href="<?= htmlspecialchars($idoso['caminho_relatorio_gerado']) ?>" target="_blank" class="btn-relatorio">Relatório</a>
                                        <?php endif; ?>
                                        <?php if (!empty($idoso['caminho_contrato_gerado'])): ?>
                                            <a href="<?= htmlspecialchars($idoso['caminho_contrato_gerado']) ?>" target="_blank" class="btn-contrato">Contrato</a>
                                        <?php endif; ?>
                                        <?php if (empty($idoso['caminho_relatorio_gerado']) && empty($idoso['caminho_contrato_gerado'])): ?>
                                            <span>N/A</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="no-results">Nenhum idoso encontrado para os filtros aplicados.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>

        <footer class="footer-bottom">
            Sistema de Triagem LSVP - Lar São Vicente de Paulo | © 2025 - Versão 1.0
        </footer>
    </div>
</body>
</html>