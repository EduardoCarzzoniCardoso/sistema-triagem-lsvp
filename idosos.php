<?php
require_once 'logout_handler.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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

// A query busca ambos os pareceres da diretoria, se existirem
$query_base = "SELECT
                    i.nome_idoso,
                    i.cpf_idoso,
                    i.data_nascimento,
                    t.data_de_inicio_cadastro_idoso,
                    ft.caminho_relatorio_gerado,
                    ft.caminho_contrato_gerado,
                    pcd1.status AS status_parecer_primeiro,
                    pcd1.data_finalizacao_parecer_coord_dir AS data_parecer_primeiro,
                    pcd2.status AS status_parecer_segundo,
                    pcd2.data_finalizacao_parecer_coord_dir AS data_acolhimento
                FROM
                    ficha_idosos AS i
                JOIN
                    triagens AS t ON i.id_idoso = t.id_idoso
                LEFT JOIN
                    parecer_coordenador_diretoria AS pcd1 ON t.id_triagem = pcd1.id_triagem AND pcd1.tipo = 'Diretoria' AND pcd1.ordem = 'Primeiro'
                LEFT JOIN
                    parecer_coordenador_diretoria AS pcd2 ON t.id_triagem = pcd2.id_triagem AND pcd2.tipo = 'Diretoria' AND pcd2.ordem = 'Segundo'
                LEFT JOIN
                    finalizacao_triagem AS ft ON t.id_triagem = ft.id_triagem";

$where_clauses = [];
$params = [];

if (!empty($buscar_nome_cpf)) {
    $where_clauses[] = "(i.nome_idoso LIKE :buscar_nome_cpf OR i.cpf_idoso LIKE :buscar_nome_cpf)";
    $params[':buscar_nome_cpf'] = '%' . $buscar_nome_cpf . '%';
}

if (!empty($tipo_data_filtro) && (!empty($data_inicial_filtro) || !empty($data_final_filtro))) {
    $data_coluna = '';
    if ($tipo_data_filtro === 'data_nascimento') {
        $data_coluna = 'i.data_nascimento';
    } elseif ($tipo_data_filtro === 'data_inicio_triagem') {
        $data_coluna = 't.data_de_inicio_cadastro_idoso';
    } elseif ($tipo_data_filtro === 'data_acolhimento') {
        $data_coluna = 'pcd2.data_finalizacao_parecer_coord_dir';
    } elseif ($tipo_data_filtro === 'data_primeiro_parecer') {
        $data_coluna = 'pcd1.data_finalizacao_parecer_coord_dir';
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

// Lógica de filtro PRINCIPAL para as abas, baseada nas SUAS DEFINIÇÕES CLARAS.
if ($aba_ativa === 'espera') {
    // Para 'Lista de Espera': Primeiro parecer é 'Lista de Espera' E o segundo parecer é 'Aprovado'
    $where_clauses[] = "pcd1.status = 'Lista de Espera'";
    $where_clauses[] = "pcd2.status = 'Aprovado'";
} elseif ($aba_ativa === 'acolhidos') {
    // Para 'Acolhidos': Ambos os pareceres são 'Aprovado'
    $where_clauses[] = "pcd1.status = 'Aprovado'";
    $where_clauses[] = "pcd2.status = 'Aprovado'";
}

// O filtro de status geral foi ajustado para refletir a nova lógica das abas
if (!empty($status_filtro)) {
    if ($status_filtro === 'Ativo') {
        // Ativo = (Primeiro: Lista de Espera E Segundo: Aprovado) OU (Primeiro: Aprovado E Segundo: Aprovado)
        $where_clauses[] = "( (pcd1.status = 'Lista de Espera' AND pcd2.status = 'Aprovado') OR (pcd1.status = 'Aprovado' AND pcd2.status = 'Aprovado') )";
    } elseif ($status_filtro === 'Inativo') {
        // Inativo: idoso tem qualquer parecer da diretoria como 'Rejeitado'
        $where_clauses[] = "(pcd1.status = 'Rejeitado' OR pcd2.status = 'Rejeitado')";
    }
} else {
    // Se nenhum filtro de status for aplicado, precisamos garantir que apenas os idosos
    // que se enquadram nas condições das abas (Lista de Espera OU Aprovado) sejam exibidos,
    // e que os Rejeitados não apareçam.
    // Esta condição é vital para não mostrar idosos rejeitados ou sem pareceres válidos.
    $where_clauses[] = "
        ( (pcd1.status = 'Lista de Espera' AND pcd2.status = 'Aprovado') OR
          (pcd1.status = 'Aprovado' AND pcd2.status = 'Aprovado') )
    ";
}


$full_query = $query_base;

if (count($where_clauses) > 0) {
    $full_query .= " WHERE " . implode(" AND ", $where_clauses);
}

$full_query .= " ORDER BY i.nome_idoso ASC";

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
    <link rel="stylesheet" href="idosos.css">
    <link rel="stylesheet" href="chatbot.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="page-idosos">
    <div class="container">
        <header class="header-top">
            <div class="logo-area">
                <img src="images/logo_lvsp2.png" alt="Logo SVP Brasil">
                <span class="username-display"><?php echo htmlspecialchars($nome_usuario_logado); ?></span>
            </div>
            <div class="logout-area">
                <button class="logout-button" onclick="window.location.href='<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?logout=true'">Logout</button>
            </div>
        </header>

        <nav class="main-nav">
            <ul>
                <li><a href="paginainicial.php">Início</a></li>
                <li><a href="triagens.php">Triagens</a></li>
                <li><a href="idosos.php" class="active">Idosos</a></li>
                <li><a href="usuarios.php">Usuários</a></li>
            </ul>
        </nav>

        <main class="main-content">
            <h1>Idosos</h1>

            <div class="content-area">
                <div class="tabs">
                    <button class="tab-button <?= ($aba_ativa === 'espera') ? 'active-tab' : '' ?>" onclick="window.location.href='idosos.php?aba=espera'">Lista de espera</button>
                    <button class="tab-button <?= ($aba_ativa === 'acolhidos') ? 'active-tab' : '' ?>" onclick="window.location.href='idosos.php?aba=acolhidos'">Idosos acolhidos</button>
                </div>
                
                <div class="filter-section">
                    <form action="idosos.php" method="GET">
                        <input type="hidden" name="aba" value="<?= htmlspecialchars($aba_ativa) ?>">
                        <div class="filter-grid">
                            
                            <div class="filter-group">
                                <label for="buscar_nome_cpf">Buscar</label>
                                <input type="text" id="buscar_nome_cpf" name="buscar_nome_cpf" placeholder="Nome ou CPF" value="<?= htmlspecialchars($buscar_nome_cpf) ?>">
                            </div>
                            
                            <div class="filter-group">
                                <label>Período</label>
                                <div class="date-range-inputs">
                                    <input type="date" name="data_inicial" value="<?= htmlspecialchars($data_inicial_filtro) ?>">
                                    <input type="date" name="data_final" value="<?= htmlspecialchars($data_final_filtro) ?>">
                                </div>
                            </div>
                            
                            <div class="filter-group">
                                <label>Tipo de data</label>
                                <select name="tipo_data">
                                    <option value="">Selecione</option>
                                    <?php if ($aba_ativa === 'acolhidos'): ?>
                                        <option value="data_nascimento" <?= $tipo_data_filtro == 'data_nascimento' ? 'selected' : '' ?>>Data de nascimento</option>
                                        <option value="data_acolhimento" <?= $tipo_data_filtro == 'data_acolhimento' ? 'selected' : '' ?>>Data de acolhimento</option>
                                    <?php else: ?>
                                        <option value="data_nascimento" <?= $tipo_data_filtro == 'data_nascimento' ? 'selected' : '' ?>>Data de nascimento</option>
                                        <option value="data_inicio_triagem" <?= $tipo_data_filtro == 'data_inicio_triagem' ? 'selected' : '' ?>>Data início triagem</option>
                                        <option value="data_primeiro_parecer" <?= $tipo_data_filtro == 'data_primeiro_parecer' ? 'selected' : '' ?>>Data 1º Parecer</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label>Status</label>
                                <select name="status_filtro">
                                    <option value="">Todos</option>
                                    <option value="Ativo" <?= $status_filtro == 'Ativo' ? 'selected' : '' ?>>Ativo</option>
                                    <option value="Inativo" <?= $status_filtro == 'Inativo' ? 'selected' : '' ?>>Inativo (Rejeitado)</option>
                                </select>
                            </div>

                            <button type="submit" class="filter-button">Filtrar</button>

                        </div>
                    </form>
                </div>

                <div class="table-wrapper">
                    <?php if ($aba_ativa === 'acolhidos'): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>CPF</th>
                                    <th>Nascimento</th>
                                    <th>Status</th>
                                    <th>Data de acolhimento</th>
                                    <th>Relatórios e contratos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($idosos)): ?>
                                    <?php foreach ($idosos as $idoso): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($idoso['nome_idoso']) ?></td>
                                            <td><?= htmlspecialchars(formatCpf($idoso['cpf_idoso'])) ?></td>
                                            <td><?= htmlspecialchars(formatDate($idoso['data_nascimento'])) ?></td>
                                            <td>Acolhido</td>
                                            <td><?= htmlspecialchars(formatDate($idoso['data_acolhimento'])) ?></td>
                                            <td class="td-actions">
                                                <?php if (!empty($idoso['caminho_relatorio_gerado'])): ?>
                                                    <a href="<?= htmlspecialchars($idoso['caminho_relatorio_gerado']) ?>" class="btn-action" target="_blank">Relatório</a>
                                                <?php else: ?>
                                                    <button class="btn-action disabled" disabled>Relatório</button>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($idoso['caminho_contrato_gerado'])): ?>
                                                    <a href="<?= htmlspecialchars($idoso['caminho_contrato_gerado']) ?>" class="btn-action" target="_blank">Contrato</a>
                                                <?php else: ?>
                                                    <button class="btn-action disabled" disabled>Contrato</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="no-results">Nenhum idoso acolhido encontrado.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Nome</th>
                                    <th>CPF</th>
                                    <th>Nascimento</th>
                                    <th>Status</th>
                                    <th>Data início triagem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($idosos)): ?>
                                    <?php foreach ($idosos as $idoso): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($idoso['nome_idoso']) ?></td>
                                            <td><?= htmlspecialchars(formatCpf($idoso['cpf_idoso'])) ?></td>
                                            <td><?= htmlspecialchars(formatDate($idoso['data_nascimento'])) ?></td>
                                            <td>Lista de Espera</td>
                                            <td><?= htmlspecialchars(formatDate($idoso['data_de_inicio_cadastro_idoso'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="no-results">Nenhum idoso na lista de espera encontrado.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>

        <footer class="footer-bottom">
            Sistema de Triagem LSVP - Lar São Vicente de Paulo | © 2025 - Versão 1.0
        </footer>
    </div>
</body>
    <script>
        const userNameLoggedIn = "<?php echo htmlspecialchars($nome_usuario_logado); ?>";
    </script>
    <script src="chatbot.js"></script>
</html>