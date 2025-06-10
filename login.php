<?php
session_start();

require_once 'conexaobanco.php';
$msg = '';
$msgClass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo_acesso = $_POST['codigo'] ?? '';
    $senha_usuario = $_POST['senha'] ?? '';

    $pdo = conexaodb();

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE codigo_acesso = :codigo_acesso AND senha_temporaria = 0");
    $stmt->bindParam(':codigo_acesso', $codigo_acesso);
    $stmt->execute();

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && password_verify($senha_usuario, $usuario['senha'])) {
        $_SESSION['usuario_logado'] = true;
        $_SESSION['id_usuario'] = $usuario['id_usuario'];
        $_SESSION['nome_usuario'] = $usuario['nome_usuario'];

        $update_stmt = $pdo->prepare("UPDATE usuarios SET ultimo_acesso = NOW() WHERE codigo_acesso = :codigo_acesso");
        $update_stmt->bindParam(':codigo_acesso', $codigo_acesso);
        $update_stmt->execute();

        header("Location: paginainicial.php");
        exit();
    } else {
        $msg = 'Código de acesso ou senha incorretos.';
        $msgClass = 'erro';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login - SVP Brasil</title>
    <link rel="stylesheet" href="login.css" />
</head>
<body>
    <div class="container">
        <div class="login-section">
            <div class="logo">
                <img src="images/logo_lvsp2.png" alt="Logo SVP Brasil" />
            </div>
            <h2>Login</h2>
            <form action="login.php" method="POST" novalidate>
                <div class="input-group">
                    <label for="codigo">Código de Acesso</label>
                    <input type="text" id="codigo" name="codigo" placeholder="Digite seu código de acesso" required />
                </div>
                <div class="input-group">
                    <label for="senha">Senha</label>
                    <input type="password" id="senha" name="senha" placeholder="Digite sua senha" required />
                    <span id="togglePassword" class="toggle-password">
                        <svg id="eye-open" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        <svg id="eye-closed" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                    </span>
                </div>
                <button type="submit">Login</button>
                <?php if($msg): ?>
                <div class="mensagem-login <?= $msgClass ?>">
                    <?= htmlspecialchars($msg) ?>
                </div>
                <?php endif; ?>
                <div class="links">
                    <br> <a href="recuperarsenha.php">Esqueceu sua senha?</a>
                    <a href="recuperarcodigo.php">Esqueceu seu código de acesso?</a>
                </div>
            </form>
        </div>
        <div class="image-section">
            <img src="images/fundo_lvsp.png" alt="Imagem de suporte à saúde" />
        </div>
    </div>

<script>
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('senha');
    const eyeOpen = document.getElementById('eye-open');
    const eyeClosed = document.getElementById('eye-closed');

    togglePassword.addEventListener('click', function () {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);

        if (type === 'password') {
            eyeOpen.style.display = 'block';
            eyeClosed.style.display = 'none';
        } else {
            eyeOpen.style.display = 'none';
            eyeClosed.style.display = 'block';
        }
    });
</script>
</body>
</html>