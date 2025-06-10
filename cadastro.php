<?php
session_start();
require_once 'conexaobanco.php';

$erro = false;
$mensagem_erro = '';
$cargo_selecionado = '';
$nome_completo_pre_preenchido = '';
$codigo_acesso_pre_preenchido = '';

unset($_SESSION['id_usuario_para_redefinir']);
unset($_SESSION['nome_para_redefinir']);
unset($_SESSION['cargo_para_redefinir']);
unset($_SESSION['codigo_para_redefinir']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_completo = trim($_POST['nome'] ?? '');
    $codigo_acesso = trim($_POST['codigo'] ?? '');
    $senha_temporaria_digitada = $_POST['senha'] ?? '';
    $cargo_selecionado = trim($_POST['cargo'] ?? '');

    if (empty($nome_completo) || empty($codigo_acesso) || empty($senha_temporaria_digitada) || empty($cargo_selecionado)) {
        $erro = true;
        $mensagem_erro = "Todos os campos são obrigatórios.";
    } else {
        try {
            $pdo = conexaodb();
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Busca o usuário pelo código de acesso, status de inativo e senha temporária = 1
            $stmt = $pdo->prepare("SELECT id_usuario, senha, senha_temporaria, status_usuario, cargo FROM usuarios WHERE codigo_acesso = :codigo_acesso AND status_usuario = 'Inativo' AND senha_temporaria = 1 LIMIT 1");
            $stmt->bindParam(':codigo_acesso', $codigo_acesso);
            $stmt->execute();
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario) {
                // Compara a senha temporária digitada com a senha (texto puro) do banco
                if ($senha_temporaria_digitada === $usuario['senha']) {
                    // Verifica se o cargo selecionado no formulário coincide com o cargo no banco de dados para aquele código
                    if ($cargo_selecionado === $usuario['cargo']) {
                        // Dados corretos e cargo coincide, armazena na sessão e redireciona
                        $_SESSION['id_usuario_para_redefinir'] = $usuario['id_usuario'];
                        $_SESSION['nome_para_redefinir'] = $nome_completo; // Nome completo do formulário
                        $_SESSION['cargo_para_redefinir'] = $cargo_selecionado; // Cargo do formulário
                        $_SESSION['codigo_para_redefinir'] = $codigo_acesso; // Código de acesso do formulário (opcional)

                        header("Location: redefinirsenha.php"); // Redireciona para a página de redefinição
                        exit();

                    } else {
                        $erro = true;
                        $mensagem_erro = "O cargo selecionado não corresponde ao código de acesso e senha temporária.";
                    }
                } else {
                    $erro = true;
                    $mensagem_erro = "Senha temporária incorreta.";
                }
            } else {
                $erro = true;
                $mensagem_erro = "Código de acesso não encontrado, conta já ativada, ou código inválido.";
            }

        } catch (PDOException $e) {
            $erro = true;
            $mensagem_erro = "Erro ao processar a ativação da conta. Por favor, tente novamente mais tarde.";
        }
    }
    $nome_completo_pre_preenchido = $nome_completo;
    $codigo_acesso_pre_preenchido = $codigo_acesso;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Ativação de Conta - SVP Brasil</title>
    <link rel="stylesheet" href="cadastro.css" />
</head>
<body>
    <div class="container-pagina">
        <div class="caixa-ativacao">
            <div class="logo">
                <img src="images/logo_lvsp2.png" alt="Logo SVP Brasil" />
            </div>
            <h2>Ativação de Conta</h2>
            <form action="cadastro.php" method="POST" novalidate>
                <div class="campo-formulario">
                    <label for="nome">Nome Completo *</label>
                    <input type="text" id="nome" name="nome" placeholder="Nome Completo" required value="<?= htmlspecialchars($nome_completo_pre_preenchido) ?>" />
                </div>
                <div class="campo-formulario">
                    <label for="codigo">Código de Acesso *</label>
                    <input type="text" id="codigo" name="codigo" placeholder="Digite seu código de acesso" required value="<?= htmlspecialchars($codigo_acesso_pre_preenchido) ?>" />
                </div>
                <div class="campo-formulario">
                    <label for="senha">Senha Temporária *</label> <input type="password" id="senha" name="senha" placeholder="Insira sua senha temporária" required />
                    <span id="togglePassword" class="icone-mostrar-senha">
                        <svg id="eye-open" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        <svg id="eye-closed" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                    </span>
                </div>
                
                <div class="campo-formulario campo-com-seta">
                    <label for="cargo">Cargo *</label>
                    <select id="cargo" name="cargo" required>
                        <option value="" disabled <?php if($cargo_selecionado == '') echo 'selected'; ?>>Selecione o Cargo</option>
                        <option value="Diretoria" <?php if($cargo_selecionado == 'Diretoria') echo 'selected'; ?>>Diretoria</option>
                        <option value="Coordenador" <?php if($cargo_selecionado == 'Coordenador') echo 'selected'; ?>>Coordenador(a)</option>
                        <option value="Enfermeiro" <?php if($cargo_selecionado == 'Enfermeiro') echo 'selected'; ?>>Enfermeiro(a)</option>
                        <option value="Psicologo" <?php if($cargo_selecionado == 'Psicologo') echo 'selected'; ?>>Psicólogo(a)</option>
                        <option value="Assistente social" <?php if($cargo_selecionado == 'Assistente social') echo 'selected'; ?>>Assistente Social</option>
                    </select>
                </div>
                <?php if ($erro): ?>
                <div class="mensagem-erro-campo">
                    <svg viewBox="0 0 24 24"><path d="M11.001 10h2v5h-2zm0 7h2v2h-2z"/><path d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10 10-4.486 10-10S17.514 2 12 2zm0 20c-4.411 0-8-3.589-8-8s3.589-8 8-8 8 3.589 8 8-3.589 8-8 8z"/></svg>
                    <span><?= htmlspecialchars($mensagem_erro) ?></span>
                </div>
                <?php endif; ?>
                <button type="submit">Continuar</button>
            </form>
        </div>
        <div class="caixa-imagem">
            <img src="images/fundo_lvsp.png" alt="Imagem de suporte à saúde" />
        </div>
    </div>
<script>
    const togglePassword = document.getElementById('togglePassword');
    if (togglePassword) {
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
    }
</script>
</body>
</html>