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

$mensagem = '';
$mensagem_class = '';
$usuario_para_editar = null;
$id_usuario = $_GET['id'] ?? null;

if ($id_usuario) {
    try {
        $stmt = $pdo->prepare("SELECT id_usuario, nome_usuario, codigo_acesso, cargo, status_usuario FROM usuarios WHERE id_usuario = :id_usuario");
        $stmt->execute([':id_usuario' => $id_usuario]);
        $usuario_para_editar = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario_para_editar) {
            $mensagem = 'Usuário não encontrado.';
            $mensagem_class = 'erro';
            $id_usuario = null; 
        }
    } catch (PDOException $e) {
        $mensagem = 'Erro ao carregar dados do usuário: ' . $e->getMessage();
        $mensagem_class = 'erro';
    }
} else {
    $mensagem = 'ID de usuário não fornecido.';
    $mensagem_class = 'erro';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $id_usuario) {
    $novo_nome = trim($_POST['nome_usuario'] ?? '');
    $novo_cargo = trim($_POST['cargo'] ?? '');
    $novo_status = trim($_POST['status_usuario'] ?? '');

    if (empty($novo_cargo) || empty($novo_status) || ($novo_status == 'Ativo' && empty($novo_nome))) {
        $mensagem = 'Por favor, preencha todos os campos obrigatórios (Nome Completo é obrigatório se o status for Ativo).';
        $mensagem_class = 'erro';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE usuarios SET nome_usuario = :nome, cargo = :cargo, status_usuario = :status, data_ultima_modificacao_perfil_usuario = NOW() WHERE id_usuario = :id_usuario");
            $stmt->execute([
                ':nome' => $novo_nome,
                ':cargo' => $novo_cargo,
                ':status' => $novo_status,
                ':id_usuario' => $id_usuario
            ]);

            $mensagem = 'Usuário atualizado com sucesso!';
            $mensagem_class = 'sucesso';
            $usuario_para_editar['nome_usuario'] = $novo_nome;
            $usuario_para_editar['cargo'] = $novo_cargo;
            $usuario_para_editar['status_usuario'] = $novo_status;

        } catch (PDOException $e) {
            $mensagem = 'Erro ao atualizar usuário: ' . $e->getMessage();
            $mensagem_class = 'erro';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuário - Sistema de Triagem LSVP</title>
    <link rel="stylesheet" href="paginainicial.css">
    <link rel="stylesheet" href="usuarios.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .edit-form-section {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 30px;
            max-width: 600px;
            margin: 30px auto;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .edit-form-section h2 {
            text-align: center;
            color: var(--text-color);
            margin-bottom: 25px;
            font-size: 1.6em;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 500;
            color: var(--text-color);
            margin-bottom: 8px;
        }
        .form-group input[type="text"],
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1em;
            box-sizing: border-box;
            height: 40px; /* Altura padrão para campos */
        }
        .form-actions {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin-top: 30px;
        }
        .form-actions button, .form-actions a {
            flex-grow: 1;
            padding: 12px 20px;
            border-radius: 6px;
            text-align: center;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .form-actions button.save-button {
            background-color: #28a745; /* Verde para salvar */
            color: white;
            border: none;
        }
        .form-actions button.save-button:hover {
            background-color: #218838;
        }
        .form-actions a.cancel-button {
            background-color: #6c757d; /* Cinza para cancelar */
            color: white;
            border: none;
        }
        .form-actions a.cancel-button:hover {
            background-color: #5a6268;
        }
        .admin-message {
            margin-top: 15px;
            padding: 12px;
            border-radius: 6px;
            font-size: 0.9em;
            text-align: center;
        }
        .admin-message.sucesso {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .admin-message.erro {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
    </style>
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
            <h1>Editar Usuário</h1>

            <div class="edit-form-section">
                <?php if ($mensagem): ?>
                    <div class="admin-message <?= $mensagem_class ?>">
                        <?= htmlspecialchars($mensagem) ?>
                    </div>
                <?php endif; ?>

                <?php if ($usuario_para_editar && $mensagem_class !== 'erro'): ?>
                    <form action="editar_usuario.php?id=<?= htmlspecialchars($usuario_para_editar['id_usuario']) ?>" method="POST">
                        <div class="form-group">
                            <label for="id_usuario">ID do Usuário:</label>
                            <input type="text" id="id_usuario" name="id_usuario" value="<?= htmlspecialchars($usuario_para_editar['id_usuario']) ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="codigo_acesso">Código de Acesso:</label>
                            <input type="text" id="codigo_acesso" name="codigo_acesso" value="<?= htmlspecialchars($usuario_para_editar['codigo_acesso']) ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label for="nome_usuario">Nome Completo:
                                <?php if ($usuario_para_editar['status_usuario'] == 'Ativo'): ?>
                                    <span style="color:red;">*</span>
                                <?php endif; ?>
                            </label>
                            <input type="text" id="nome_usuario" name="nome_usuario" value="<?= htmlspecialchars($usuario_para_editar['nome_usuario']) ?>"
                                <?php if ($usuario_para_editar['status_usuario'] == 'Ativo'): ?>required<?php endif; ?>
                            >
                        </div>
                        <div class="form-group">
                            <label for="cargo">Cargo: <span style="color:red;">*</span></label>
                            <select id="cargo" name="cargo" required>
                                <option value="Diretoria" <?= ($usuario_para_editar['cargo'] == 'Diretoria') ? 'selected' : '' ?>>Diretoria</option>
                                <option value="Coordenador" <?= ($usuario_para_editar['cargo'] == 'Coordenador') ? 'selected' : '' ?>>Coordenador(a)</option>
                                <option value="Enfermeiro" <?= ($usuario_para_editar['cargo'] == 'Enfermeiro') ? 'selected' : '' ?>>Enfermeiro(a)</option>
                                <option value="Psicologo" <?= ($usuario_para_editar['cargo'] == 'Psicologo') ? 'selected' : '' ?>>Psicólogo(a)</option>
                                <option value="Assistente social" <?= ($usuario_para_editar['cargo'] == 'Assistente social') ? 'selected' : '' ?>>Assistente Social</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="status_usuario">Status: <span style="color:red;">*</span></label>
                            <select id="status_usuario" name="status_usuario" required>
                                <option value="Ativo" <?= ($usuario_para_editar['status_usuario'] == 'Ativo') ? 'selected' : '' ?>>Ativo</option>
                                <option value="Inativo" <?= ($usuario_para_editar['status_usuario'] == 'Inativo') ? 'selected' : '' ?>>Inativo</option>
                            </select>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="save-button">Salvar Alterações</button>
                            <a href="usuarios.php" class="cancel-button">Cancelar</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

        </main>

        <footer class="footer-bottom">
            Sistema de Triagem LSVP - Lar São Vicente de Paulo | © 2025 - Versão 1.0
        </footer>
    </div>
</body>
</html>