<?php
session_start();
require_once 'conexaobanco.php';

$msg = '';
$msgClass = '';

if (!isset($_SESSION['id_usuario_para_redefinir'])) {
    header("Location: login.php");
    exit();
}

$id_usuario = $_SESSION['id_usuario_para_redefinir'];
$nome_completo = $_SESSION['nome_para_redefinir'];
$cargo_selecionado = $_SESSION['cargo_para_redefinir'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nova_senha = $_POST['senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';

    if (empty($nova_senha) || empty($confirmar_senha)) {
        $msg = 'Por favor, preencha todos os campos.';
        $msgClass = 'erro';
    } elseif ($nova_senha !== $confirmar_senha) {
        $msg = 'As senhas não coincidem.';
        $msgClass = 'erro';
    } elseif (strlen($nova_senha) < 8) {
        $msg = 'A senha deve conter pelo menos 8 caracteres.';
        $msgClass = 'erro';
    } elseif (!preg_match('/[A-Z]/', $nova_senha)) {
        $msg = 'A senha deve conter pelo menos uma letra maiúscula.';
        $msgClass = 'erro';
    } elseif (!preg_match('/\d/', $nova_senha)) {
        $msg = 'A senha deve conter pelo menos um número.';
        $msgClass = 'erro';
    } else {
        try {
            $pdo = conexaodb();
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $nova_senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("UPDATE usuarios SET
                senha = :nova_senha_hash,
                nome_usuario = :nome_usuario,
                cargo = :cargo,
                status_usuario = 'Ativo',
                senha_temporaria = 0,
                data_de_cadastro_usuario = COALESCE(data_de_cadastro_usuario, NOW()),
                ultimo_acesso = NOW(),
                data_ultima_modificacao_perfil_usuario = NOW()
                WHERE id_usuario = :id_usuario");

            $stmt->bindParam(':nova_senha_hash', $nova_senha_hash);
            $stmt->bindParam(':nome_usuario', $nome_completo);
            $stmt->bindParam(':cargo', $cargo_selecionado);
            $stmt->bindParam(':id_usuario', $id_usuario);

            $stmt->execute();

            unset($_SESSION['id_usuario_para_redefinir']);
            unset($_SESSION['nome_para_redefinir']);
            unset($_SESSION['cargo_para_redefinir']);
            unset($_SESSION['codigo_para_redefinir']);

            $msg = 'Senha redefinida e conta ativada com sucesso! Redirecionando...';
            $msgClass = 'sucesso';

            header("Refresh: 3; URL=login.php");
            exit();

        } catch (PDOException $e) {
            $msg = "Erro ao redefinir a senha. Por favor, tente novamente mais tarde.";
            $msgClass = 'erro';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Redefinição de Senha - SVP Brasil</title>
  <link rel="stylesheet" href="redefinirsenha.css" />
</head>
<body>
  <div class="container">
    <div class="login-section">
      <div class="logo">
        <img src="images/logo_lvsp2.png" alt="Logo SVP Brasil" /> <br>
      </div>
      <h2>Redefinição de Senha</h2>
      <form action="redefinirsenha.php" method="POST">
        <?php if($msg): ?>
        <div class="mensagem-login <?= $msgClass ?>" style="display:block;">
            <?= htmlspecialchars($msg) ?>
        </div>
        <?php endif; ?>
        <div class="input-group">
          <label for="senha">Senha <span style="color:red">*</span></label>
          <input
            type="password"
            id="senha"
            name="senha"
            placeholder="Insira sua nova senha"
            required
          />
          <div class="senha-requisitos">
            <span class="requisito" id="req-length"
              ><span class="icon">✘</span> A senha deve conter pelo menos 8 caracteres</span>
            <span class="requisito" id="req-uppercase"
              ><span class="icon">✘</span> A senha deve conter pelo menos uma letra maiúscula</span>
            <span class="requisito" id="req-number"
              ><span class="icon">✘</span> A senha deve conter pelo menos um número</span>
          </div>
        </div>

        <div class="input-group">
          <label for="confirmar_senha">Confirmar Senha <span style="color:red">*</span></label>
          <input
            type="password"
            id="confirmar_senha"
            name="confirmar_senha"
            placeholder="Confirme sua nova senha"
            required
          />
          <small class="msg-error">As senhas não coincidem</small>
        </div>

        <button type="submit">Alterar Senha</button>
      </form>
    </div>

    <div class="image-section">
      <img src="images/fundo_lvsp.png" alt="Imagem de suporte à saúde" />
    </div>
  </div>

  <script src="redefinirsenha.js"></script>
</body>
</html>