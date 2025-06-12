<?php
require_once 'logout_handler.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_logado']) || $_SESSION['usuario_logado'] !== true) {
    header("Location: login.php");
    exit();
}

$nome_usuario_logado = $_SESSION['nome_usuario'] ?? 'Usuário Desconhecido';

require_once 'conexaobanco.php';
$pdo = conexaodb();

function gerarCodigoAleatorio($tamanho = 8) {
    $caracteres = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $codigo = '';
    $max = strlen($caracteres) - 1;
    for ($i = 0; $i < $tamanho; $i++) {
        $codigo .= $caracteres[random_int(0, $max)];
    }
    return $codigo;
}

function gerarSenhaSegura() {
    $numeros = '0123456789';
    $letras_minusculas = 'abcdefghijklmnopqrstuvwxyz';
    $letras_maiusculas = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    $senha = '';
    $senha .= $letras_maiusculas[random_int(0, strlen($letras_maiusculas) - 1)];
    $senha .= $numeros[random_int(0, strlen($numeros) - 1)];
    $senha .= $letras_minusculas[random_int(0, strlen($letras_minusculas) - 1)];

    $todos_os_caracteres = $numeros . $letras_minusculas . $letras_maiusculas;
    for ($i = strlen($senha); $i < 8; $i++) {
        $senha .= $todos_os_caracteres[random_int(0, strlen($todos_os_caracteres) - 1)];
    }

    return str_shuffle($senha);
}

$admin_message = '';
$admin_message_class = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gerar_credenciais'])) {
    $cargo_selecionado = $_POST['cargo_para_gerar'] ?? '';

    if (empty($cargo_selecionado)) {
        $admin_message = 'Por favor, selecione um cargo para gerar as credenciais.';
        $admin_message_class = 'erro';
    } else {
        try {
            $novo_codigo = gerarCodigoAleatorio(8);
            $nova_senha_temporaria = gerarSenhaSegura();

            $stmt = $pdo->prepare(
                "INSERT INTO usuarios (nome_usuario, codigo_acesso, senha, cargo, status_usuario, senha_temporaria, data_de_cadastro_usuario, ultimo_acesso) 
                 VALUES (:nome, :codigo, :senha, :cargo, 'Inativo', 1, NOW(), NULL)"
            );
            
            $nome_placeholder = ""; 

            $stmt->execute([
                ':nome' => $nome_placeholder,
                ':codigo' => $novo_codigo,
                ':senha' => $nova_senha_temporaria,
                ':cargo' => $cargo_selecionado
            ]);

            $admin_message = "Credenciais geradas com sucesso para o cargo de <strong>" . htmlspecialchars($cargo_selecionado) . "</strong>:<br>" .
                             "<strong>Código de Acesso:</strong> " . htmlspecialchars($novo_codigo) . "<br>" .
                             "<strong>Senha Temporária:</strong> " . htmlspecialchars($nova_senha_temporaria);
            $admin_message_class = 'sucesso';

        } catch (Exception $e) {
            $admin_message = 'Erro ao gerar credenciais: ' . $e->getMessage();
            $admin_message_class = 'erro';
        }
    }
}

$buscar_filtro = trim($_GET['buscar'] ?? '');
$status_filtro = $_GET['status'] ?? '';
$periodo_inicial_filtro = $_GET['periodo_inicial'] ?? '';
$periodo_final_filtro = $_GET['periodo_final'] ?? '';
$cargo_filtro = $_GET['cargo'] ?? '';

$where_clauses = [];
$params = [];

$query_base = "SELECT id_usuario, codigo_acesso, nome_usuario, cargo, status_usuario, ultimo_acesso FROM usuarios";

if (!empty($buscar_filtro)) {
    $where_clauses[] = "(nome_usuario LIKE :buscar OR codigo_acesso LIKE :buscar)";
    $params[':buscar'] = '%' . $buscar_filtro . '%';
}

if (!empty($status_filtro)) {
    $where_clauses[] = "status_usuario = :status";
    $params[':status'] = $status_filtro;
}

if (!empty($cargo_filtro)) {
    $where_clauses[] = "cargo = :cargo";
    $params[':cargo'] = $cargo_filtro;
}

if (!empty($periodo_inicial_filtro) && !empty($periodo_final_filtro)) {
    $where_clauses[] = "DATE(ultimo_acesso) BETWEEN :periodo_inicial AND :periodo_final";
    $params[':periodo_inicial'] = $periodo_inicial_filtro;
    $params[':periodo_final'] = $periodo_final_filtro;
} elseif (!empty($periodo_inicial_filtro)) {
    $where_clauses[] = "DATE(ultimo_acesso) >= :periodo_inicial";
    $params[':periodo_inicial'] = $periodo_inicial_filtro;
} elseif (!empty($periodo_final_filtro)) {
    $where_clauses[] = "DATE(ultimo_acesso) <= :periodo_final";
    $params[':periodo_final'] = $periodo_final_filtro;
}

$full_query = $query_base;
if (count($where_clauses) > 0) {
    $full_query .= " WHERE " . implode(" AND ", $where_clauses);
}
$full_query .= " ORDER BY nome_usuario ASC";

$stmt = $pdo->prepare($full_query);
$stmt->execute($params);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

function formatarData($data) {
    if ($data && $data !== '0000-00-00 00:00:00') {
        return (new DateTime($data))->format('d/m/Y H:i:s');
    }
    return 'N/A';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuários - Sistema de Triagem LSVP</title>
    <link rel="stylesheet" href="paginainicial.css">
    <link rel="stylesheet" href="usuarios.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="page-usuarios">
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
                <li><a href="paginainicial.php">Início</a></li>
                <li><a href="triagens.php">Triagens</a></li>
                <li><a href="idosos.php">Idosos</a></li>
                <li><a href="usuarios.php" class="active">Usuários</a></li>
            </ul>
        </nav>

        <main class="main-content">
            <h1>Usuários</h1>

            <div class="admin-actions-section">
                <form action="usuarios.php" method="POST">
                    <div class="actions-grid">
                        <div class="action-group">
                            <label for="cargo_para_gerar">Cargo</label>
                            <select id="cargo_para_gerar" name="cargo_para_gerar">
                                <option value="" disabled selected>Selecione um Cargo</option>
                                <option value="Diretoria">Diretoria</option>
                                <option value="Coordenador">Coordenador(a)</option>
                                <option value="Enfermeiro">Enfermeiro(a)</option>
                                <option value="Psicologo">Psicólogo(a)</option>
                                <option value="Assistente social">Assistente Social</option>
                            </select>
                        </div>
                        <div class="action-item">
                            <span>Link para cadastro de usuário</span>
                            <a href="cadastro.php" target="_blank" class="action-button-link">Gerar Link</a>
                        </div>
                        <div class="action-item">
                            <span>Gerar código de acesso e senha</span>
                            <button type="submit" name="gerar_credenciais" class="action-button">Gerar</button>
                        </div>
                    </div>
                </form>
                <?php if ($admin_message): ?>
                    <div class="admin-message <?= $admin_message_class ?>">
                        <?= $admin_message ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="filter-section">
                <div class="section-title">Tabela de Usuários</div>
                <form action="usuarios.php" method="GET">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <label for="buscar">Buscar</label>
                            <input type="text" id="buscar" name="buscar" placeholder="Nome ou código" value="<?= htmlspecialchars($buscar_filtro) ?>">
                        </div>
                        <div class="filter-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="">Todos</option>
                                <option value="Ativo" <?= $status_filtro == 'Ativo' ? 'selected' : '' ?>>Ativo</option>
                                <option value="Inativo" <?= $status_filtro == 'Inativo' ? 'selected' : '' ?>>Inativo</option>
                            </select>
                        </div>
                         <div class="filter-group">
                            <label>Período (Último Acesso)</label>
                            <div class="date-range-inputs">
                                <input type="date" name="periodo_inicial" value="<?= htmlspecialchars($periodo_inicial_filtro) ?>">
                                <input type="date" name="periodo_final" value="<?= htmlspecialchars($periodo_final_filtro) ?>">
                            </div>
                        </div>
                        <div class="filter-group">
                            <label for="cargo">Cargo</label>
                            <select id="cargo" name="cargo">
                                <option value="">Todos</option>
                                <option value="Diretoria" <?= $cargo_filtro == 'Diretoria' ? 'selected' : '' ?>>Diretoria</option>
                                <option value="Coordenador" <?= $cargo_filtro == 'Coordenador' ? 'selected' : '' ?>>Coordenador(a)</option>
                                <option value="Enfermeiro" <?= $cargo_filtro == 'Enfermeiro' ? 'selected' : '' ?>>Enfermeiro(a)</option>
                                <option value="Psicologo" <?= $cargo_filtro == 'Psicologo' ? 'selected' : '' ?>>Psicólogo(a)</option>
                                <option value="Assistente social" <?= $cargo_filtro == 'Assistente social' ? 'selected' : '' ?>>Assistente Social</option>
                            </select>
                        </div>
                        <button type="submit" class="filter-button">Filtrar</button>
                    </div>
                </form>
            </div>
            
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="col-codigo">Código</th>
                            <th class="col-nome">Nome Completo</th>
                            <th class="col-cargo">Cargo</th>
                            <th class="col-status">Status</th>
                            <th class="col-acesso">Último Acesso</th>
                            <th class="col-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($usuarios)): ?>
                            <?php foreach ($usuarios as $usuario): ?>
                                <tr>
                                    <td class="col-codigo"><?= htmlspecialchars($usuario['codigo_acesso']) ?></td>
                                    <td class="col-nome"><?= htmlspecialchars($usuario['nome_usuario']) ?></td>
                                    <td class="col-cargo"><?= htmlspecialchars($usuario['cargo']) ?></td>
                                    <td class="col-status"><?= htmlspecialchars($usuario['status_usuario']) ?></td>
                                    <td class="col-acesso"><?= htmlspecialchars(formatarData($usuario['ultimo_acesso'])) ?></td>
                                    <td class="col-actions">
                                        <a href="editar_usuario.php?id=<?= htmlspecialchars($usuario['id_usuario']) ?>" class="btn-action btn-edit">Editar</a>
                                        <a href="deletar_usuario.php?id=<?= htmlspecialchars($usuario['id_usuario']) ?>" class="btn-action btn-delete" onclick="return confirm('Tem certeza que deseja deletar este usuário?');">Deletar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="no-results">Nenhum usuário encontrado.</td>
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