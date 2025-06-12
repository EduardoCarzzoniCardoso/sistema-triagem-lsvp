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

// Variáveis para armazenar o ID do idoso e da triagem da sessão (se existirem)
$id_idoso_sessao = $_SESSION['current_idoso_id'] ?? null;
$id_triagem_sessao = $_SESSION['current_triagem_id'] ?? null;

// Dados do formulário para pré-preenchimento
$nome = '';
$endereco = '';
$numero = '';
$bairro = '';
$cidade = '';
$cep = '';
$estado = '';
$rg = '';
$cpf = '';
$titulo_eleitor = '';
$cart_prof = '';
$reservista = '';
$cert_nasc = '';
$cert_casamento = '';
$outros = '';

// --- Lógica para carregar dados de um rascunho existente se houver ID na sessão ou GET ---
if ($id_idoso_sessao && $id_triagem_sessao) {
    try {
        // Carrega dados da ficha_idosos
        $stmt = $pdo->prepare("SELECT * FROM ficha_idosos WHERE id_idoso = :id_idoso");
        $stmt->execute([':id_idoso' => $id_idoso_sessao]);
        $dados_idoso = $stmt->fetch(PDO::FETCH_ASSOC);

        // Carrega dados da triagem para verificar a etapa
        $stmt_triagem = $pdo->prepare("SELECT * FROM triagens WHERE id_triagem = :id_triagem AND id_idoso = :id_idoso");
        $stmt_triagem->execute([':id_triagem' => $id_triagem_sessao, ':id_idoso' => $id_idoso_sessao]);
        $dados_triagem = $stmt_triagem->fetch(PDO::FETCH_ASSOC);

        if ($dados_idoso && $dados_triagem) {
            // Pré-preenche os campos do formulário com os dados do rascunho
            $nome = $dados_idoso['nome_idoso'] ?? '';
            $endereco = $dados_idoso['endereco_idoso'] ?? '';
            $numero = $dados_idoso['numero_casa_idoso'] ?? '';
            $bairro = $dados_idoso['bairro_idoso'] ?? '';
            $cidade = $dados_idoso['cidade_idoso'] ?? '';
            $cep = $dados_idoso['cep_idoso'] ?? '';
            $estado = $dados_idoso['estado_idoso'] ?? '';
            $rg = $dados_idoso['rg_idoso'] ?? '';
            $cpf = $dados_idoso['cpf_idoso'] ?? '';
            $titulo_eleitor = $dados_idoso['titulo_eleitor_idoso'] ?? '';
            $cart_prof = $dados_idoso['cart_profissional_idoso'] ?? '';
            $reservista = $dados_idoso['reservista_idoso'] ?? '';
            $cert_nasc = $dados_idoso['certidao_nascimento_idoso'] ?? '';
            $cert_casamento = $dados_idoso['certidao_casamento_idoso'] ?? '';
            $outros = $dados_idoso['outros_documentos_idoso'] ?? '';

            // Se a triagem já estiver em uma etapa avançada, o usuário pode ter voltado
            if ($dados_triagem['etapa_atual'] !== 'Ficha de Triagem - Início') {
                $mensagem = "Retomando triagem. Você está na etapa: " . htmlspecialchars($dados_triagem['etapa_atual']);
                $mensagem_class = 'sucesso';
            }
        } else {
            // IDs na sessão não correspondem a um rascunho válido, então limpa a sessão
            unset($_SESSION['current_idoso_id']);
            unset($_SESSION['current_triagem_id']);
        }
    } catch (PDOException $e) {
        $mensagem = 'Erro ao carregar rascunho: ' . $e->getMessage();
        $mensagem_class = 'erro';
    }
}

// --- Lógica para processar a submissão do formulário ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Coleta dados do formulário
    $nome_form = trim($_POST['nome'] ?? '');
    $endereco_form = trim($_POST['endereco'] ?? '');
    $numero_form = trim($_POST['numero'] ?? '');
    $bairro_form = trim($_POST['bairro'] ?? '');
    $cidade_form = trim($_POST['cidade'] ?? '');
    $cep_form = trim($_POST['cep'] ?? '');
    $estado_form = trim($_POST['estado'] ?? '');
    $rg_form = trim($_POST['rg'] ?? '');
    $cpf_form = trim($_POST['cpf'] ?? '');
    $titulo_eleitor_form = trim($_POST['titulo_eleitor'] ?? '');
    $cart_prof_form = trim($_POST['cart_prof'] ?? '');
    $reservista_form = trim($_POST['reservista'] ?? '');
    $cert_nasc_form = trim($_POST['cert_nasc'] ?? '');
    $cert_casamento_form = trim($_POST['cert_casamento'] ?? '');
    $outros_form = trim($_POST['outros'] ?? '');

    // Validação básica para 'Avançar' e 'Salvar/Alterar'
    $erros = [];
    if (empty($nome_form)) $erros[] = "Nome é obrigatório.";
    if (empty($cpf_form)) $erros[] = "CPF é obrigatório.";
    // Adicione mais validações aqui para outros campos obrigatórios antes de avançar/salvar

    if (empty($erros)) {
        // --- AÇÃO: SALVAR RASCUNHO (botão "Salvar" ou "Alterar") ---
        if (isset($_POST['salvar_rascunho']) || isset($_POST['alterar_rascunho'])) {
            try {
                $pdo->beginTransaction();

                if (!$id_idoso_sessao) { // Se não tem ID na sessão, é um NOVO rascunho
                    // 1. INSERIR em ficha_idosos
                    $stmt_idoso = $pdo->prepare(
                        "INSERT INTO ficha_idosos (nome_idoso, cpf_idoso, endereco_idoso, numero_casa_idoso, bairro_idoso, cidade_idoso, cep_idoso, estado_idoso, rg_idoso, titulo_eleitor_idoso, cart_profissional_idoso, reservista_idoso, certidao_nascimento_idoso, certidao_casamento_idoso, outros_documentos_idoso, data_de_cadastro_idoso) 
                         VALUES (:nome, :cpf, :endereco, :numero, :bairro, :cidade, :cep, :estado, :rg, :titulo_eleitor, :cart_prof, :reservista, :cert_nasc, :cert_casamento, :outros, NOW())"
                    );
                    $stmt_idoso->execute([
                        ':nome' => $nome_form, ':cpf' => $cpf_form, ':endereco' => $endereco_form, ':numero' => $numero_form,
                        ':bairro' => $bairro_form, ':cidade' => $cidade_form, ':cep' => $cep_form, ':estado' => $estado_form,
                        ':rg' => $rg_form, ':titulo_eleitor' => $titulo_eleitor_form, ':cart_prof' => $cart_prof_form,
                        ':reservista' => $reservista_form, ':cert_nasc' => $cert_nasc_form, ':cert_casamento' => $cert_casamento_form,
                        ':outros' => $outros_form
                    ]);
                    $id_idoso_sessao = $pdo->lastInsertId();

                    // 2. INSERIR em triagens (associando ao idoso e marcando como rascunho)
                    $stmt_triagem = $pdo->prepare(
                        "INSERT INTO triagens (id_idoso, etapa_atual, status, data_de_inicio_cadastro_idoso, id_usuario_iniciou_triagem) 
                         VALUES (:id_idoso, 'Ficha de Triagem - Início', 'Em andamento', NOW(), :id_usuario_logado)"
                    );
                    $stmt_triagem->execute([
                        ':id_idoso' => $id_idoso_sessao,
                        ':id_usuario_logado' => $_SESSION['id_usuario'] // ID do usuário logado que iniciou
                    ]);
                    $id_triagem_sessao = $pdo->lastInsertId();

                    // Salva IDs na sessão para próximas etapas ou retomada
                    $_SESSION['current_idoso_id'] = $id_idoso_sessao;
                    $_SESSION['current_triagem_id'] = $id_triagem_sessao;

                    $mensagem = "Rascunho salvo com sucesso! Você pode continuar preenchendo mais tarde.";
                    $mensagem_class = 'sucesso';

                } else { // Se já tem ID na sessão, é uma ATUALIZAÇÃO de rascunho existente
                    // 1. ATUALIZAR ficha_idosos
                    $stmt_idoso = $pdo->prepare(
                        "UPDATE ficha_idosos SET nome_idoso = :nome, cpf_idoso = :cpf, endereco_idoso = :endereco, numero_casa_idoso = :numero, bairro_idoso = :bairro, cidade_idoso = :cidade, cep_idoso = :cep, estado_idoso = :estado, rg_idoso = :rg, titulo_eleitor_idoso = :titulo_eleitor, cart_profissional_idoso = :cart_prof, reservista_idoso = :reservista, certidao_nascimento_idoso = :cert_nasc, certidao_casamento_idoso = :cert_casamento, outros_documentos_idoso = :outros WHERE id_idoso = :id_idoso"
                    );
                    $stmt_idoso->execute([
                        ':nome' => $nome_form, ':cpf' => $cpf_form, ':endereco' => $endereco_form, ':numero' => $numero_form,
                        ':bairro' => $bairro_form, ':cidade' => $cidade_form, ':cep' => $cep_form, ':estado' => $estado_form,
                        ':rg' => $rg_form, ':titulo_eleitor' => $titulo_eleitor_form, ':cart_prof' => $cart_prof_form,
                        ':reservista' => $reservista_form, ':cert_nasc' => $cert_nasc_form, ':cert_casamento' => $cert_casamento_form,
                        ':outros' => $outros_form, ':id_idoso' => $id_idoso_sessao
                    ]);

                    // 2. ATUALIZAR etapa_atual na tabela triagens (se necessário)
                    $stmt_triagem = $pdo->prepare(
                        "UPDATE triagens SET etapa_atual = 'Em andamento Ficha de Triagem - Início' WHERE id_triagem = :id_triagem"
                    );
                    $stmt_triagem->execute([':id_triagem' => $id_triagem_sessao]);

                    $mensagem = "Rascunho atualizado com sucesso!";
                    $mensagem_class = 'sucesso';
                }

                $pdo->commit();

            } catch (PDOException $e) {
                $pdo->rollBack();
                $mensagem = 'Erro ao salvar/atualizar rascunho: ' . $e->getMessage();
                $mensagem_class = 'erro';
            }
        }
        
        // --- AÇÃO: AVANÇAR ETAPA (botão "Avançar") ---
        if (isset($_POST['avancar_etapa'])) {
            try {
                $pdo->beginTransaction();

                if (!$id_idoso_sessao) { // Se não tem ID na sessão, cria o rascunho inicial antes de avançar
                    // Mesmo lógica de INSERT do Salvar Rascunho
                    $stmt_idoso = $pdo->prepare(
                        "INSERT INTO ficha_idosos (nome_idoso, cpf_idoso, endereco_idoso, numero_casa_idoso, bairro_idoso, cidade_idoso, cep_idoso, estado_idoso, rg_idoso, titulo_eleitor_idoso, cart_profissional_idoso, reservista_idoso, certidao_nascimento_idoso, certidao_casamento_idoso, outros_documentos_idoso, data_de_cadastro_idoso) 
                         VALUES (:nome, :cpf, :endereco, :numero, :bairro, :cidade, :cep, :estado, :rg, :titulo_eleitor, :cart_prof, :reservista, :cert_nasc, :cert_casamento, :outros, NOW())"
                    );
                    $stmt_idoso->execute([
                        ':nome' => $nome_form, ':cpf' => $cpf_form, ':endereco' => $endereco_form, ':numero' => $numero_form,
                        ':bairro' => $bairro_form, ':cidade' => $cidade_form, ':cep' => $cep_form, ':estado' => $estado_form,
                        ':rg' => $rg_form, ':titulo_eleitor' => $titulo_eleitor_form, ':cart_prof' => $cart_prof_form,
                        ':reservista' => $reservista_form, ':cert_nasc' => $cert_nasc_form, ':cert_casamento' => $cert_casamento_form,
                        ':outros' => $outros_form
                    ]);
                    $id_idoso_sessao = $pdo->lastInsertId();

                    $stmt_triagem = $pdo->prepare(
                        "INSERT INTO triagens (id_idoso, etapa_atual, status, data_de_inicio_cadastro_idoso, id_usuario_iniciou_triagem) 
                         VALUES (:id_idoso, 'Em andamento Ficha de Triagem - Início', 'Em andamento', NOW(), :id_usuario_logado)"
                    );
                    $stmt_triagem->execute([
                        ':id_idoso' => $id_idoso_sessao,
                        ':id_usuario_logado' => $_SESSION['id_usuario']
                    ]);
                    $id_triagem_sessao = $pdo->lastInsertId();

                    $_SESSION['current_idoso_id'] = $id_idoso_sessao;
                    $_SESSION['current_triagem_id'] = $id_triagem_sessao;

                } else { // Se já tem IDs, atualiza o rascunho existente antes de avançar
                    // Mesmo lógica de UPDATE do Salvar Rascunho
                    $stmt_idoso = $pdo->prepare(
                        "UPDATE ficha_idosos SET nome_idoso = :nome, cpf_idoso = :cpf, endereco_idoso = :endereco, numero_casa_idoso = :numero, bairro_idoso = :bairro, cidade_idoso = :cidade, cep_idoso = :cep, estado_idoso = :estado, rg_idoso = :rg, titulo_eleitor_idoso = :titulo_eleitor, cart_profissional_idoso = :cart_prof, reservista_idoso = :reservista, certidao_nascimento_idoso = :cert_nasc, certidao_casamento_idoso = :cert_casamento, outros_documentos_idoso = :outros WHERE id_idoso = :id_idoso"
                    );
                    $stmt_idoso->execute([
                        ':nome' => $nome_form, ':cpf' => $cpf_form, ':endereco' => $endereco_form, ':numero' => $numero_form,
                        ':bairro' => $bairro_form, ':cidade' => $cidade_form, ':cep' => $cep_form, ':estado' => $estado_form,
                        ':rg' => $rg_form, ':titulo_eleitor' => $titulo_eleitor_form, ':cart_prof' => $cart_prof_form,
                        ':reservista' => $reservista_form, ':cert_nasc' => $cert_nasc_form, ':cert_casamento' => $cert_casamento_form,
                        ':outros' => $outros_form, ':id_idoso' => $id_idoso_sessao
                    ]);

                    $stmt_triagem = $pdo->prepare(
                        "UPDATE triagens SET etapa_atual = 'Em andamento Ficha de Triagem - Continuação' WHERE id_triagem = :id_triagem"
                    );
                    $stmt_triagem->execute([':id_triagem' => $id_triagem_sessao]);
                }
                
                $pdo->commit();
                
                // Redireciona para a próxima página da triagem
                header("Location: ficha_triagem_continuacao.php");
                exit();

            } catch (PDOException $e) {
                $pdo->rollBack();
                $mensagem = 'Erro ao avançar etapa: ' . $e->getMessage();
                $mensagem_class = 'erro';
            }
        }

    } else {
        $mensagem = implode('<br>', $erros);
        $mensagem_class = 'erro';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Ficha de Triagem - Início - Sistema de Triagem LSVP</title>
  <link rel="stylesheet" href="paginainicial.css">
  <link rel="stylesheet" href="nova_triagem.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body class="page-triagens">
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
        <li><a href="#">Início</a></li>
        <li><a href="#" class="active">Triagens</a></li>
        <li><a href="#">Idosos</a></li>
        <li><a href="#">Usuário</a></li>
      </ul>
    </nav>

  <main class="main-content">
    <h1>Triagens</h1>
    <div class="triagem-layout">
        <aside class="triagem-sidebar">
          <button class="sidebar-button active">Ficha de Triagem - Início</button>
          <button class="sidebar-button">Ficha de Triagem - Continuação</button>
          <button class="sidebar-button">Ficha de Triagem - Contrato</button>
          <button class="sidebar-button">Parecer do(a) Coordenador(a)</button>
          <button class="sidebar-button">Parecer da Diretoria</button>
          <button class="sidebar-button">Parecer do Médico</button>
          <button class="sidebar-button">Parecer Psicológico</button>
          <button class="sidebar-button">Finalização da Triagem</button>
        </aside>

        <section class="triagem-form-content">
          <h2>Ficha de Triagem - Início</h2>

          <?php if ($mensagem): ?>
            <div class="admin-message <?= $mensagem_class ?>">
                <?= htmlspecialchars($mensagem) ?>
            </div>
          <?php endif; ?>

          <form class="form-grid" method="POST" action="nova_triagem.php">
            <fieldset>
              <legend>Candidato - Idoso</legend>

              <input type="text" placeholder="Nome Completo..." name="nome" value="<?= htmlspecialchars($nome) ?>" required />
              <input type="text" placeholder="Endereço..." name="endereco" value="<?= htmlspecialchars($endereco) ?>" />
              <input type="text" placeholder="Nº" name="numero" value="<?= htmlspecialchars($numero) ?>" />
              <input type="text" placeholder="Bairro" name="bairro" value="<?= htmlspecialchars($bairro) ?>" />
              <input type="text" placeholder="Cidade" name="cidade" value="<?= htmlspecialchars($cidade) ?>" />
              <input type="text" placeholder="CEP" name="cep" value="<?= htmlspecialchars($cep) ?>" />
              <input type="text" placeholder="Estado" name="estado" value="<?= htmlspecialchars($estado) ?>" />
            </fieldset>

            <fieldset>
              <legend>Documentação</legend>
              <input type="text" placeholder="RG" name="rg" value="<?= htmlspecialchars($rg) ?>" />
              <input type="text" placeholder="CPF" name="cpf" value="<?= htmlspecialchars($cpf) ?>" required />
              <input type="text" placeholder="Título de Eleitor" name="titulo_eleitor" value="<?= htmlspecialchars($titulo_eleitor) ?>" />
              <input type="text" placeholder="Cart. Profissional" name="cart_prof" value="<?= htmlspecialchars($cart_prof) ?>" />
              <input type="text" placeholder="Reservista" name="reservista" value="<?= htmlspecialchars($reservista) ?>" />
              <input type="text" placeholder="Certidão de Nascimento" name="cert_nasc" value="<?= htmlspecialchars($cert_nasc) ?>" />
              <input type="text" placeholder="Certidão de Casamento" name="cert_casamento" value="<?= htmlspecialchars($cert_casamento) ?>" />
              <textarea placeholder="Outros documentos" name="outros"><?= htmlspecialchars($outros) ?></textarea>
            </fieldset>

            <div class="form-buttons">
              <button type="submit" name="salvar_rascunho" class="btn-primary">Salvar</button>
              <button type="submit" name="alterar_rascunho" class="btn-secondary">Alterar</button>
              <button type="submit" name="avancar_etapa" class="btn-primary">Avançar</button>
            </div>
          </form>
        </section>
    </div>
  </main>

  <footer class="footer-bottom">
            Sistema de Triagem LSVP - Lar São Vicente de Paulo | © 2025 - Versão 1.0
    </footer>
</body>
</html>