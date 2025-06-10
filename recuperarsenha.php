<?php
session_start();
require_once 'conexaobanco.php';

$msg = '';
$msgClass = '';
$codigo_acesso = '';
$nome_completo = '';

unset($_SESSION['id_usuario_para_redefinir']);
unset($_SESSION['nome_para_redefinir']);
unset($_SESSION['cargo_para_redefinir']);
unset($_SESSION['codigo_para_redefinir']);
unset($_SESSION['id_usuario_recuperar']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo_acesso = trim($_POST['codigo_acesso'] ?? '');
    $nome_completo = trim($_POST['nome_completo'] ?? '');

    if ($codigo_acesso !== '' && $nome_completo !== '') {
        try {
            $pdo = conexaodb();
            $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE codigo_acesso = :codigo AND nome_usuario = :nome AND status_usuario = 'Ativo' AND senha_temporaria = 0 LIMIT 1");
            $stmt->execute([':codigo' => $codigo_acesso, ':nome' => $nome_completo]);
            
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario) {
                $_SESSION['id_usuario_recuperar'] = $usuario['id_usuario'];
                $_SESSION['codigo_para_redefinir'] = $codigo_acesso;

                header("Location: redefinirsenha.php");
                exit();
            } else {
                $msg = 'Código de acesso ou nome inválidos ou conta não ativada.';
                $msgClass = 'erro';
            }
        } catch (PDOException $e) {
            $msg = "Erro de conexão com o banco de dados. Por favor, tente novamente.";
            $msgClass = 'erro';
        }
    } else {
        $msg = 'Por favor, preencha todos os campos.';
        $msgClass = 'erro';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Recuperação de Senha - SVP Brasil</title>
    <link rel="stylesheet" href="recuperarsenha.css" />
</head>
<body>
    <div class="container">
        <div class="login-section">
            <div class="logo">
                <img src="images/logo_lvsp2.png" alt="Logo SVP Brasil" />
            </div>
            <h2>Recuperação de Senha</h2>
            <form action="recuperarsenha.php" method="POST" novalidate>
                <div class="input-group">
                    <label for="codigo_acesso">Código de Acesso *</label>
                    <input type="text" id="codigo_acesso" name="codigo_acesso" placeholder="Digite seu código de acesso" required value="<?php echo htmlspecialchars($codigo_acesso); ?>" />
                </div>
                <div class="input-group">
                    <label for="nome_completo">Nome Completo *</label>
                    <input type="text" id="nome_completo" name="nome_completo" placeholder="Nome Completo" required value="<?php echo htmlspecialchars($nome_completo); ?>" />
                </div>
                
                <button type="submit">Recuperar Senha</button>

                <?php if ($msgClass === 'erro'): ?>
                <div class="mensagem-login erro">
                    <?= $msg ?>
                </div>
                <?php endif; ?>
            </form>
        </div>
        <div class="image-section">
            <img src="images/fundo_lvsp.png" alt="Ilustração idosos e cuidadora" />
        </div>
    </div>
</body>
</html>