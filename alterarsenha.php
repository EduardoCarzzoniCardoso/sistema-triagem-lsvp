<?php
session_start();
require_once 'conexaobanco.php';

$msg = '';
$msgClass = '';

$id_usuario_alvo = null;
$is_recovery_flow = false;
$current_action_title = 'Alterar Senha';

if (isset($_SESSION['id_usuario_para_alterar_senha_recuperacao']) && !empty($_SESSION['id_usuario_para_alterar_senha_recuperacao'])) {
    $id_usuario_alvo = $_SESSION['id_usuario_para_alterar_senha_recuperacao'];
    $is_recovery_flow = true;
    $current_action_title = 'Definir Nova Senha';

} 

elseif (isset($_SESSION['usuario_logado']) && $_SESSION['usuario_logado'] === true) {
    $id_usuario_alvo = $_SESSION['id_usuario'];
} 

else {
    header("Location: login.php");
    exit();
}

if ($id_usuario_alvo === null) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senha_atual = $_POST['senha_atual'] ?? ''; 
    $nova_senha = $_POST['senha'] ?? '';
    $confirmar_nova_senha = $_POST['confirmar_senha'] ?? '';

    if (empty($nova_senha) || empty($confirmar_nova_senha)) {
        $msg = 'Por favor, preencha todos os campos.';
        $msgClass = 'erro';
    } elseif ($nova_senha !== $confirmar_nova_senha) {
        $msg = 'As senhas não coincidem.';
        $msgClass = 'erro';
    } elseif (strlen($nova_senha) < 8) { 
        $msg = 'A nova senha deve conter pelo menos 8 caracteres.';
        $msgClass = 'erro';
    } elseif (!preg_match('/[A-Z]/', $nova_senha)) { 
        $msg = 'A nova senha deve conter pelo menos uma letra maiúscula.';
        $msgClass = 'erro';
    } elseif (!preg_match('/\d/', $nova_senha)) { 
        $msg = 'A nova senha deve conter pelo menos um número.';
        $msgClass = 'erro';
    } else {
        try {
            $pdo = conexaodb();
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            if (!$is_recovery_flow) {
                $stmt_check_current_password = $pdo->prepare("SELECT senha FROM usuarios WHERE id_usuario = :id_usuario");
                $stmt_check_current_password->bindParam(':id_usuario', $id_usuario_alvo);
                $stmt_check_current_password->execute();
                $usuario_db = $stmt_check_current_password->fetch(PDO::FETCH_ASSOC);

                if (!$usuario_db || !password_verify($senha_atual, $usuario_db['senha'])) {
                    $msg = 'A senha atual está incorreta.';
                    $msgClass = 'erro';
                }
            }
            
            if ($msgClass !== 'erro') { 
                $nova_senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);

                $stmt_update_password = $pdo->prepare("UPDATE usuarios SET
                    senha = :nova_senha_hash,
                    senha_temporaria = 0, 
                    data_ultima_modificacao_perfil_usuario = NOW()
                    WHERE id_usuario = :id_usuario");

                $stmt_update_password->bindParam(':nova_senha_hash', $nova_senha_hash);
                $stmt_update_password->bindParam(':id_usuario', $id_usuario_alvo);
                $stmt_update_password->execute();

                if ($is_recovery_flow && isset($_SESSION['id_usuario_para_alterar_senha_recuperacao'])) {
                    unset($_SESSION['id_usuario_para_alterar_senha_recuperacao']);
                }
                unset($_SESSION['id_usuario_para_redefinir']); 
                unset($_SESSION['nome_para_redefinir']);
                unset($_SESSION['cargo_para_redefinir']);
                unset($_SESSION['codigo_para_redefinir']);
                unset($_SESSION['id_usuario_recuperar']); 

                $msg = 'Senha alterada com sucesso! Redirecionando para o login...';
                $msgClass = 'sucesso';

                header("Refresh: 3; URL=login.php");
                exit();
            }

        } catch (PDOException $e) {
            $msg = "Erro ao alterar a senha. Por favor, tente novamente mais tarde.";
            $msgClass = 'erro';
            error_log("Erro no alterarsenha.php: " . $e->getMessage()); 
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo $current_action_title; ?> - SVP Brasil</title>
  <link rel="stylesheet" href="alterarsenha.css" />
</head>
<body>
  <div class="container">
    <div class="login-section">
      <div class="logo">
        <img src="images/logo_lvsp2.png" alt="Logo SVP Brasil" /> <br>
      </div>
      <h2><?php echo $current_action_title; ?></h2>
      <form action="alterarsenha.php" method="POST">
        <?php if($msg): ?>
        <div class="mensagem-login <?= $msgClass ?>" style="display:block;">
            <?= htmlspecialchars($msg) ?>
        </div>
        <?php endif; ?>
        
        <?php if (!$is_recovery_flow): ?>
        <div class="input-group" id="input-group-senha-atual">
            <label for="senha_atual">Senha Atual <span style="color:red">*</span></label>
            <input
                type="password"
                id="senha_atual"
                name="senha_atual"
                placeholder="Insira sua senha atual"
                required
            />
        </div>
        <?php endif; ?>

        <div class="input-group">
          <label for="senha">Nova Senha <span style="color:red">*</span></label>
          <input
            type="password"
            id="senha"
            name="senha"
            placeholder="Insira sua nova senha"
            required
          />
          <div class="senha-requisitos" style="display:none;">
            <span class="requisito" id="req-length"
              ><span class="icon">✘</span> A senha deve conter pelo menos 8 caracteres</span>
            <span class="requisito" id="req-uppercase"
              ><span class="icon">✘</span> A senha deve conter pelo menos uma letra maiúscula</span>
            <span class="requisito" id="req-number"
              ><span class="icon">✘</span> A senha deve conter pelo menos um número</span>
          </div>
        </div>

        <div class="input-group">
          <label for="confirmar_senha">Confirmar Nova Senha <span style="color:red">*</span></label>
          <input
            type="password"
            id="confirmar_senha"
            name="confirmar_senha"
            placeholder="Confirme sua nova senha"
            required
          />
          <small class="msg-error" id="msg-confirmar-senha" style="display:none;">As senhas não coincidem</small>
        </div>

        <button type="submit">Alterar Senha</button>
      </form>
    </div>

    <div class="image-section">
      <img src="images/fundo_lvsp.png" alt="Imagem de suporte à saúde" />
    </div>
  </div>

  <script src="alterarsenha.js"></script>
</body>
</html>