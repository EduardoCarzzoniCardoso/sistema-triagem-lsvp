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

$data_inicial_filtro = $_GET['data_inicial'] ?? '';
$data_final_filtro = $_GET['data_final'] ?? '';

$contadores = [
    'idosos_acolhidos' => 0,
    'idosos_espera' => 0,
    'triagens_realizadas' => 0,
    'triagens_andamento' => 0,
    'usuarios_ativos' => 0,
];

$query_acolhidos = "SELECT COUNT(DISTINCT t.id_idoso) FROM parecer_coordenador_diretoria pcd JOIN triagens t ON pcd.id_triagem = t.id_triagem";
$current_where_acolhidos = " pcd.status = 'Aprovado'";
$where_clause_acolhidos_data = '';
$params_acolhidos = [];

if (!empty($data_inicial_filtro) && !empty($data_final_filtro)) {
    $where_clause_acolhidos_data = " AND DATE(pcd.data_finalizacao_parecer_coord_dir) BETWEEN :data_inicial AND :data_final";
    $params_acolhidos[':data_inicial'] = $data_inicial_filtro;
    $params_acolhidos[':data_final'] = $data_final_filtro;
} elseif (!empty($data_inicial_filtro)) {
    $where_clause_acolhidos_data = " AND DATE(pcd.data_finalizacao_parecer_coord_dir) >= :data_inicial";
    $params_acolhidos[':data_inicial'] = $data_inicial_filtro;
} elseif (!empty($data_final_filtro)) {
    $where_clause_acolhidos_data = " AND DATE(pcd.data_finalizacao_parecer_coord_dir) <= :data_final";
    $params_acolhidos[':data_final'] = $data_final_filtro;
}

$stmt_acolhidos = $pdo->prepare($query_acolhidos . " WHERE " . $current_where_acolhidos . $where_clause_acolhidos_data);
$stmt_acolhidos->execute($params_acolhidos);
$contadores['idosos_acolhidos'] = $stmt_acolhidos->fetchColumn();


$query_espera = "SELECT COUNT(DISTINCT t.id_idoso) FROM parecer_coordenador_diretoria pcd JOIN triagens t ON pcd.id_triagem = t.id_triagem";
$current_where_espera = " pcd.status = 'Lista de Espera'";
$where_clause_espera_data = '';
$params_espera = [];

if (!empty($data_inicial_filtro) && !empty($data_final_filtro)) {
    $where_clause_espera_data = " AND DATE(pcd.data_finalizacao_parecer_coord_dir) BETWEEN :data_inicial AND :data_final";
    $params_espera[':data_inicial'] = $data_inicial_filtro;
    $params_espera[':data_final'] = $data_final_filtro;
} elseif (!empty($data_inicial_filtro)) {
    $where_clause_espera_data = " AND DATE(pcd.data_finalizacao_parecer_coord_dir) >= :data_inicial";
    $params_espera[':data_inicial'] = $data_inicial_filtro;
} elseif (!empty($data_final_filtro)) {
    $where_clause_espera_data = " AND DATE(pcd.data_finalizacao_parecer_coord_dir) <= :data_final";
    $params_espera[':data_final'] = $data_final_filtro;
}

$stmt_espera = $pdo->prepare($query_espera . " WHERE " . $current_where_espera . $where_clause_espera_data);
$stmt_espera->execute($params_espera);
$contadores['idosos_espera'] = $stmt_espera->fetchColumn();


$where_data_clause_triagens = '';
$params_triagens = [];

if (!empty($data_inicial_filtro) && !empty($data_final_filtro)) {
    $where_data_clause_triagens = " WHERE DATE(data_coluna) BETWEEN :data_inicial AND :data_final";
    $params_triagens[':data_inicial'] = $data_inicial_filtro;
    $params_triagens[':data_final'] = $data_final_filtro;
} elseif (!empty($data_inicial_filtro)) {
    $where_data_clause_triagens = " WHERE DATE(data_coluna) >= :data_inicial";
    $params_triagens[':data_inicial'] = $data_inicial_filtro;
} elseif (!empty($data_final_filtro)) {
    $where_data_clause_triagens = " WHERE DATE(data_coluna) <= :data_final";
    $params_triagens[':data_final'] = $data_final_filtro;
}


$query_realizadas = "SELECT COUNT(*) FROM triagens";
$current_where_realizadas = " status = 'Concluida'";
$final_query_realizadas = $query_realizadas;
$final_params_realizadas = [];

if (!empty($where_data_clause_triagens)) {
    $final_query_realizadas .= str_replace('data_coluna', 'data_finalizacao_geral_triagem', $where_data_clause_triagens);
    $final_params_realizadas = $params_triagens;
    if (!empty($current_where_realizadas)) {
        $final_query_realizadas = str_replace('WHERE', 'WHERE ' . $current_where_realizadas . ' AND', $final_query_realizadas);
    }
} else {
    if (!empty($current_where_realizadas)) {
        $final_query_realizadas .= " WHERE " . $current_where_realizadas;
    }
}
$stmt_realizadas = $pdo->prepare($final_query_realizadas);
$stmt_realizadas->execute($final_params_realizadas);
$contadores['triagens_realizadas'] = $stmt_realizadas->fetchColumn();

$query_andamento = "SELECT COUNT(*) FROM triagens";
$current_where_andamento = " status = 'Em andamento'";
$final_query_andamento = $query_andamento;
$final_params_andamento = [];

if (!empty($where_data_clause_triagens)) {
    $final_query_andamento .= str_replace('data_coluna', 'data_de_inicio_cadastro_idoso', $where_data_clause_triagens);
    $final_params_andamento = $params_triagens;
    if (!empty($current_where_andamento)) {
        $final_query_andamento = str_replace('WHERE', 'WHERE ' . $current_where_andamento . ' AND', $final_query_andamento);
    }
} else {
    if (!empty($current_where_andamento)) {
        $final_query_andamento .= " WHERE " . $current_where_andamento;
    }
}
$stmt_andamento = $pdo->prepare($final_query_andamento);
$stmt_andamento->execute($final_params_andamento);
$contadores['triagens_andamento'] = $stmt_andamento->fetchColumn();


$query_usuarios_ativos = "SELECT COUNT(*) FROM usuarios";
$current_where_ativos = " status_usuario = 'Ativo'";
$final_query_usuarios_ativos = $query_usuarios_ativos;
$final_params_usuarios_ativos = [];

$where_data_clause_usuarios = '';
if (!empty($data_inicial_filtro) && !empty($data_final_filtro)) {
    $where_data_clause_usuarios = " WHERE DATE(ultimo_acesso) BETWEEN :data_inicial AND :data_final";
    $final_params_usuarios_ativos[':data_inicial'] = $data_inicial_filtro;
    $final_params_usuarios_ativos[':data_final'] = $data_final_filtro;
} elseif (!empty($data_inicial_filtro)) {
    $where_data_clause_usuarios = " WHERE DATE(ultimo_acesso) >= :data_inicial";
    $final_params_usuarios_ativos[':data_inicial'] = $data_inicial_filtro;
} elseif (!empty($data_final_filtro)) {
    $where_data_clause_usuarios = " WHERE DATE(ultimo_acesso) <= :data_final";
    $final_params_usuarios_ativos[':data_final'] = $data_final_filtro;
}


if (!empty($where_data_clause_usuarios)) {
    $final_query_usuarios_ativos = $query_usuarios_ativos;
    if (!empty($current_where_ativos)) {
        $final_query_usuarios_ativos .= " WHERE " . $current_where_ativos;
    }
    $final_query_usuarios_ativos .= str_replace('WHERE', ' AND', $where_data_clause_usuarios);
} else {
    if (!empty($current_where_ativos)) {
        $final_query_usuarios_ativos .= " WHERE " . $current_where_ativos;
    }
}

$stmt_usuarios_ativos = $pdo->prepare($final_query_usuarios_ativos);
$stmt_usuarios_ativos->execute($final_params_usuarios_ativos);
$contadores['usuarios_ativos'] = $stmt_usuarios_ativos->fetchColumn();

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Início - Sistema de Triagem LSVP</title>
    <link rel="stylesheet" href="paginainicial.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="container">
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
                <li><a href="paginainicial.php" class="active">Início</a></li>
                <li><a href="triagens.php">Triagens</a></li>
                <li><a href="idosos.php">Idosos</a></li>
                <li><a href="usuarios.php">Usuários</a></li>
            </ul>
        </nav>

        <main class="main-content">
            <h1>Início</h1>

            <form action="" method="GET" class="period-filter">
                <div class="filter-group">
                    <label for="data-inicial">Data Inicial</label>
                    <input type="date" id="data-inicial" name="data_inicial" value="<?php echo htmlspecialchars($data_inicial_filtro); ?>">
                </div>
                <div class="filter-group">
                    <label for="data-final">Data Final</label>
                    <input type="date" id="data-final" name="data_final" value="<?php echo htmlspecialchars($data_final_filtro); ?>">
                </div>
                <button type="submit" class="filter-button">Filtrar</button>
            </form>

            <div class="dashboard-cards">
                <div class="card">
                    <span class="card-label">Total de idosos acolhidos</span>
                    <span class="card-value"><?php echo $contadores['idosos_acolhidos']; ?></span>
                </div>
                <div class="card">
                    <span class="card-label">Idosos na lista de espera</span>
                    <span class="card-value"><?php echo $contadores['idosos_espera']; ?></span>
                </div>
                <div class="card">
                    <span class="card-label">Total de triagens realizadas</span>
                    <span class="card-value"><?php echo $contadores['triagens_realizadas']; ?></span>
                </div>
                <div class="card">
                    <span class="card-label">Total de triagens em andamento</span>
                    <span class="card-value"><?php echo $contadores['triagens_andamento']; ?></span>
                </div>
                <div class="card">
                    <span class="card-label">Usuários ativos no sistema</span>
                    <span class="card-value"><?php echo $contadores['usuarios_ativos']; ?></span>
                </div>
            </div>
        </main>

        <footer class="footer-bottom">
            Sistema de Triagem LSVP - Lar São Vicente de Paulo | © 2025 - Versão 1.0
        </footer>
    </div>
</body>
</html>