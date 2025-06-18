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

$data_inicial_filtro = $_GET['data_inicial'] ?? '';
$data_final_filtro = $_GET['data_final'] ?? '';

$contadores = [
    'idosos_acolhidos' => 0,
    'idosos_espera' => 0,
    'triagens_realizadas' => 0,
    'triagens_andamento' => 0,
    'usuarios_ativos' => 0,
];

$query_acolhidos = "SELECT COUNT(DISTINCT i.id_idoso)
                    FROM ficha_idosos AS i
                    JOIN triagens AS t ON i.id_idoso = t.id_idoso
                    JOIN parecer_coordenador_diretoria AS pcd2 ON t.id_triagem = pcd2.id_triagem AND pcd2.tipo = 'Diretoria' AND pcd2.ordem = 'Segundo'
                    WHERE pcd2.status = 'Aprovado'";
$params_acolhidos = [];

if (!empty($data_inicial_filtro) && !empty($data_final_filtro)) {
    $query_acolhidos .= " AND DATE(pcd2.data_finalizacao_parecer_coord_dir) BETWEEN :data_inicial AND :data_final";
    $params_acolhidos[':data_inicial'] = $data_inicial_filtro;
    $params_acolhidos[':data_final'] = $data_final_filtro;
} elseif (!empty($data_inicial_filtro)) {
    $query_acolhidos .= " AND DATE(pcd2.data_finalizacao_parecer_coord_dir) >= :data_inicial";
    $params_acolhidos[':data_inicial'] = $data_inicial_filtro;
} elseif (!empty($data_final_filtro)) {
    $query_acolhidos .= " AND DATE(pcd2.data_finalizacao_parecer_coord_dir) <= :data_final";
    $params_acolhidos[':data_final'] = $data_final_filtro;
}
$stmt_acolhidos = $pdo->prepare($query_acolhidos);
$stmt_acolhidos->execute($params_acolhidos);
$contadores['idosos_acolhidos'] = $stmt_acolhidos->fetchColumn();

$query_espera = "SELECT COUNT(DISTINCT i.id_idoso)
                 FROM ficha_idosos AS i
                 JOIN triagens AS t ON i.id_idoso = t.id_idoso
                 JOIN parecer_coordenador_diretoria AS pcd1 ON t.id_triagem = pcd1.id_triagem AND pcd1.tipo = 'Diretoria' AND pcd1.ordem = 'Primeiro'
                 JOIN parecer_coordenador_diretoria AS pcd2 ON t.id_triagem = pcd2.id_triagem AND pcd2.tipo = 'Diretoria' AND pcd2.ordem = 'Segundo'
                 WHERE pcd1.status = 'Lista de Espera' AND pcd2.status = 'Aprovado'";
$params_espera = [];

if (!empty($data_inicial_filtro) && !empty($data_final_filtro)) {
    $query_espera .= " AND DATE(pcd1.data_finalizacao_parecer_coord_dir) BETWEEN :data_inicial AND :data_final";
    $params_espera[':data_inicial'] = $data_inicial_filtro;
    $params_espera[':data_final'] = $data_final_filtro;
} elseif (!empty($data_inicial_filtro)) {
    $query_espera .= " AND DATE(pcd1.data_finalizacao_parecer_coord_dir) >= :data_inicial";
    $params_espera[':data_inicial'] = $data_inicial_filtro;
} elseif (!empty($data_final_filtro)) {
    $query_espera .= " AND DATE(pcd1.data_finalizacao_parecer_coord_dir) <= :data_final";
    $params_espera[':data_final'] = $data_final_filtro;
}
$stmt_espera = $pdo->prepare($query_espera);
$stmt_espera->execute($params_espera);
$contadores['idosos_espera'] = $stmt_espera->fetchColumn();

$query_realizadas = "SELECT COUNT(*) FROM triagens WHERE status = 'Concluida'";
$params_realizadas = [];

if (!empty($data_inicial_filtro) && !empty($data_final_filtro)) {
    $query_realizadas .= " AND DATE(data_finalizacao_geral_triagem) BETWEEN :data_inicial AND :data_final";
    $params_realizadas[':data_inicial'] = $data_inicial_filtro;
    $params_realizadas[':data_final'] = $data_final_filtro;
} elseif (!empty($data_inicial_filtro)) {
    $query_realizadas .= " AND DATE(data_finalizacao_geral_triagem) >= :data_inicial";
    $params_realizadas[':data_inicial'] = $data_inicial_filtro;
} elseif (!empty($data_final_filtro)) {
    $query_realizadas .= " AND DATE(data_finalizacao_geral_triagem) <= :data_final";
    $params_realizadas[':data_final'] = $data_final_filtro;
}
$stmt_realizadas = $pdo->prepare($query_realizadas);
$stmt_realizadas->execute($params_realizadas);
$contadores['triagens_realizadas'] = $stmt_realizadas->fetchColumn();

$query_andamento = "SELECT COUNT(*) FROM triagens WHERE status = 'Em andamento'";
$params_andamento = [];

if (!empty($data_inicial_filtro) && !empty($data_final_filtro)) {
    $query_andamento .= " AND DATE(data_de_inicio_cadastro_idoso) BETWEEN :data_inicial AND :data_final";
    $params_andamento[':data_inicial'] = $data_inicial_filtro;
    $params_andamento[':data_final'] = $data_final_filtro;
} elseif (!empty($data_inicial_filtro)) {
    $query_andamento .= " AND DATE(data_de_inicio_cadastro_idoso) >= :data_inicial";
    $params_andamento[':data_inicial'] = $data_inicial_filtro;
} elseif (!empty($data_final_filtro)) {
    $query_andamento .= " AND DATE(data_de_inicio_cadastro_idoso) <= :data_final";
    $params_andamento[':data_final'] = $data_final_filtro;
}
$stmt_andamento = $pdo->prepare($query_andamento);
$stmt_andamento->execute($params_andamento);
$contadores['triagens_andamento'] = $stmt_andamento->fetchColumn();

$query_usuarios_ativos = "SELECT COUNT(*) FROM usuarios WHERE status_usuario = 'Ativo'";
$params_usuarios_ativos = [];

if (!empty($data_inicial_filtro) && !empty($data_final_filtro)) {
    $query_usuarios_ativos .= " AND DATE(ultimo_acesso) BETWEEN :data_inicial AND :data_final";
    $params_usuarios_ativos[':data_inicial'] = $data_inicial_filtro;
    $params_usuarios_ativos[':data_final'] = $data_final_filtro;
} elseif (!empty($data_inicial_filtro)) {
    $query_usuarios_ativos .= " AND DATE(ultimo_acesso) >= :data_inicial";
    $params_usuarios_ativos[':data_inicial'] = $data_inicial_filtro;
} elseif (!empty($data_final_filtro)) {
    $query_usuarios_ativos .= " AND DATE(ultimo_acesso) <= :data_final";
    $params_usuarios_ativos[':data_final'] = $data_final_filtro;
}
$stmt_usuarios_ativos = $pdo->prepare($query_usuarios_ativos);
$stmt_usuarios_ativos->execute($params_usuarios_ativos);
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
    <link rel="stylesheet" href="chatbot.css">
</head>
<body>
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
    <script>
        const userNameLoggedIn = "<?php echo htmlspecialchars($nome_usuario_logado); ?>";
    </script>
    <script src="chatbot.js"></script>
</body>
</html>