<?php
require_once 'conexaobanco.php';

$nome = '';
$cargo = '';
$msg = '';
$msgClass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $cargo = trim($_POST['cargo'] ?? '');

    if ($nome !== '' && $cargo !== '') {
        try {
            $pdo = conexaodb();
            $stmt = $pdo->prepare("SELECT codigo_acesso FROM usuarios WHERE nome_usuario = :nome AND cargo = :cargo LIMIT 1");
            $stmt->execute([':nome' => $nome, ':cargo' => $cargo]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($resultado) {
                $codigoAcesso = $resultado['codigo_acesso'];
                $msg = "O código de acesso para <strong>" . htmlspecialchars($nome) . "</strong> é <strong>" . htmlspecialchars($codigoAcesso) . "</strong>.";
                $msgClass = 'sucesso';
            } else {
                $msg = 'Nome ou cargo não encontrado no sistema.';
                $msgClass = 'erro';
            }
        } catch (PDOException $e) {
            $msg = "Erro de conexão com o banco de dados.";
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
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Verificação do Código de Acesso - SVP Brasil</title>
        <link rel="stylesheet" href="recuperarcodigo.css" />
        <style>
            .mensagem-padrao {
                margin-top: 20px;
                padding: 12px;
                font-size: 14px;
                border-radius: 5px;
                display: flex;
                justify-content: center;
                align-items: center;
                text-align: center;
                word-break: break-word;
            }
            .mensagem-padrao.sucesso {
                color: #155724;
                background-color: #d4edda;
                border: 1px solid #c3e6cb;
            }
            .mensagem-padrao.erro {
                color: #721c24;
                background-color: #f8d7da;
                border: 1px solid #f5c6cb;
            }
            .botao-voltar {
                display: block;
                width: 100%;
                padding: 12px;
                font-size: 16px;
                color: white;
                background-color: #007bff;
                border: none;
                border-radius: 8px;
                cursor: pointer;
                text-align: center;
                text-decoration: none;
                margin-top: 15px;
            }
            .botao-voltar:hover {
                background-color: #0056b3; 
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="login-section">
                <div class="logo">
                    <img src="images/logo_lvsp2.png" alt="Logo SVP Brasil" />
                </div>
                <h2>Verificação do Código de Acesso</h2>
                <form action="" method="POST" novalidate>
                    <div class="input-group">
                        <label for="nome">Nome Completo</label>
                        <input type="text" id="nome" name="nome" placeholder="Nome Completo" required value="<?php echo htmlspecialchars($nome); ?>" />
                    </div>
                    <div class="input-group">
                        <label for="cargo">Cargo</label>
                        <select id="cargo" name="cargo" required>
                            <option value="" disabled <?php if($cargo == '') echo 'selected'; ?>>Selecione o Cargo</option>
                            <option value="Diretoria" <?php if($cargo == 'Diretoria') echo 'selected'; ?>>Diretoria</option>
                            <option value="Coordenador" <?php if($cargo == 'Coordenador') echo 'selected'; ?>>Coordenador(a)</option>
                            <option value="Enfermeiro" <?php if($cargo == 'Enfermeiro') echo 'selected'; ?>>Enfermeiro(a)</option>
                            <option value="Psicologo" <?php if($cargo == 'Psicologo') echo 'selected'; ?>>Psicólogo(a)</option>
                            <option value="Assistente social" <?php if($cargo == 'Assistente social') echo 'selected'; ?>>Assistente Social</option>
                        </select>
                    </div>

                    <?php if ($msgClass !== 'sucesso'): ?>
                        <button type="submit">Verificar</button>
                    <?php endif; ?>

                    <?php if (!empty($msg)): ?>
                        <div class="mensagem-padrao <?= $msgClass ?>">
                            <span><?php echo $msg; ?></span>
                        </div>
                        
                        <?php if ($msgClass === 'sucesso'): ?>
                            <a href="login.php" class="botao-voltar">Voltar ao Login</a>
                        <?php endif; ?>
                    <?php endif; ?>

                </form>
            </div>
            <div class="image-section">
                <img src="images/fundo_lvsp.png" alt="Ilustração idosos e cuidadora" />
            </div>
        </div>
    </body>
</html>