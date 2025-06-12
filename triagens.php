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

$buscar_nome_cpf = trim($_GET['buscar_nome_cpf'] ?? '');
$data_inicial_filtro = $_GET['data_inicial'] ?? '';
$data_final_filtro = $_GET['data_final'] ?? '';
$tipo_data_filtro = $_GET['tipo_data'] ?? '';
$etapa_filtro = $_GET['etapa_filtro'] ?? '';

$idosos = [];
$query_base = "SELECT
                    fi.nome_idoso,
                    fi.cpf_idoso,
                    fi.data_nascimento,
                    t.data_de_inicio_cadastro_idoso,
                    t.etapa_atual AS etapa
                FROM
                    ficha_idosos fi
                JOIN
                    triagens t ON fi.id_idoso = t.id_idoso";

$where_clauses = [];
$params = [];

if (empty($etapa_filtro) || $etapa_filtro !== 'Finalizada') {
    $where_clauses[] = "t.etapa_atual != 'Finalizada'";
}

if (!empty($buscar_nome_cpf)) {
    $where_clauses[] = "(fi.nome_idoso LIKE :buscar_nome_cpf OR fi.cpf_idoso LIKE :buscar_nome_cpf)";
    $params[':buscar_nome_cpf'] = '%' . $buscar_nome_cpf . '%';
}

if (!empty($tipo_data_filtro) && (!empty($data_inicial_filtro) || !empty($data_final_filtro))) {
    $data_coluna = '';
    if ($tipo_data_filtro === 'data_nascimento') {
        $data_coluna = 'fi.data_nascimento';
    } elseif ($tipo_data_filtro === 'data_inicio_triagem') {
        $data_coluna = 't.data_de_inicio_cadastro_idoso';
    }

    if (!empty($data_coluna)) {
        if (!empty($data_inicial_filtro) && !empty($data_final_filtro)) {
            $where_clauses[] = "DATE({$data_coluna}) BETWEEN :data_inicial AND :data_final";
            $params[':data_inicial'] = $data_inicial_filtro;
            $params[':data_final'] = $data_final_filtro;
        } elseif (!empty($data_inicial_filtro)) {
            $where_clauses[] = "DATE({$data_coluna}) >= :data_inicial";
            $params[':data_inicial'] = $data_inicial_filtro;
        } elseif (!empty($data_final_filtro)) {
            $where_clauses[] = "DATE({$data_coluna}) <= :data_final";
            $params[':data_final'] = $data_final_filtro;
        }
    }
}

if (!empty($etapa_filtro)) {
    if ($etapa_filtro === 'Finalizada') {
        foreach ($where_clauses as $key => $clause) {
            if ($clause === "t.etapa_atual != 'Finalizada'") {
                unset($where_clauses[$key]);
            }
        }
    }
    $where_clauses[] = "t.etapa_atual = :etapa_filtro";
    $params[':etapa_filtro'] = $etapa_filtro;
}


$full_query = $query_base;
if (count($where_clauses) > 0) {
    $full_query .= " WHERE " . implode(" AND ", $where_clauses);
}
$full_query .= " ORDER BY fi.nome_idoso ASC";

$stmt = $pdo->prepare($full_query);
$stmt->execute($params);
$idosos = $stmt->fetchAll(PDO::FETCH_ASSOC);

function formatCpf($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    if (strlen($cpf) === 11) {
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
    return $cpf;
}

function formatDate($date) {
    if ($date && $date !== '0000-00-00 00:00:00' && $date !== '0000-00-00') {
        return (new DateTime($date))->format('d/m/Y');
    }
    return '';
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Triagens - Sistema de Triagem LSVP</title>
  <link rel="stylesheet" href="paginainicial.css">
  <link rel="stylesheet" href="triagens.css">
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
        <li><a href="paginainicial.php">Início</a></li>
        <li><a href="triagens.php" class="active">Triagens</a></li>
        <li><a href="idosos.php">Idosos</a></li>
        <li><a href="usuarios.php">Usuários</a></li>
      </ul>
    </nav>

  <main class="main-content">
    <h1>Triagens</h1>
    <div class="content-area">
      <div class="filter-section">
        <form action="triagens.php" method="GET">
          <div class="filter-inputs-wrapper">
            <div class="filter-group">
              <label for="buscar_nome_cpf">Buscar</label>
              <input type="text" id="buscar_nome_cpf" name="buscar_nome_cpf" placeholder="Nome ou CPF" value="<?= htmlspecialchars($buscar_nome_cpf) ?>">
            </div>
            <div class="filter-group">
              <label>Período</label>
              <div class="date-range-inputs">
                <input type="date" name="data_inicial" value="<?= htmlspecialchars($data_inicial_filtro) ?>">
                <input type="date" name="data_final" value="<?= htmlspecialchars($data_final_filtro) ?>">
              </div>
            </div>
            <div class="filter-group">
              <label>Tipo de data</label>
              <select name="tipo_data">
                <option value="">Selecione</option>
                <option value="data_nascimento" <?= $tipo_data_filtro == 'data_nascimento' ? 'selected' : '' ?>>Data de nascimento</option>
                <option value="data_inicio_triagem" <?= $tipo_data_filtro == 'data_inicio_triagem' ? 'selected' : '' ?>>Data início triagem</option>
              </select>
            </div>
            <div class="filter-group">
              <label>Etapa</label>
              <select name="etapa_filtro">
                <option value="">Em Andamento</option>
                <option value="Em andamento Ficha de Triagem - Início" <?= $etapa_filtro == 'Em andamento Ficha de Triagem - Início' ? 'selected' : '' ?>>Ficha de Triagem - Início</option>
                <option value="Em andamento Ficha de Triagem - Continuação" <?= $etapa_filtro == 'Em andamento Ficha de Triagem - Continuação' ? 'selected' : '' ?>>Ficha de Triagem - Continuação</option>
                <option value="Em andamento Ficha de Triagem - Contrato" <?= $etapa_filtro == 'Em andamento Ficha de Triagem - Contrato' ? 'selected' : '' ?>>Ficha de Triagem - Contrato</option>
                <option value="Em andamento Parecer do Coordenador" <?= $etapa_filtro == 'Em andamento Parecer do Coordenador' ? 'selected' : '' ?>>Parecer da Coordenação</option>
                <option value="Em andamento Parecer da Diretoria" <?= $etapa_filtro == 'Em andamento Parecer da Diretoria' ? 'selected' : '' ?>>Parecer da Diretoria</option>
                <option value="Em andamento Parecer do Médico" <?= $etapa_filtro == 'Em andamento Parecer do Médico' ? 'selected' : '' ?>>Parecer da Equipe Médica</option>
                <option value="Em andamento Parecer Psicológico" <?= $etapa_filtro == 'Em andamento Parecer Psicológico' ? 'selected' : '' ?>>Parecer da Psicologia</option>
                <option value="Em andamento Segundo Parecer do Coordenador" <?= $etapa_filtro == 'Em andamento Segundo Parecer do Coordenador' ? 'selected' : '' ?>>Segundo Parecer da Coordenação</option>
                <option value="Em andamento Segundo Parecer da Diretoria" <?= $etapa_filtro == 'Em andamento Segundo Parecer da Diretoria' ? 'selected' : '' ?>>Segundo Parecer da Diretoria</option>
                <option value="Finalizada" <?= $etapa_filtro == 'Finalizada' ? 'selected' : '' ?>>Finalizada</option>
              </select>
            </div>
            <button type="submit" class="filter-button">Filtrar</button>
          </div>
        </form>
        <a href="nova_triagem.php" class="start-new-triage-button">Começar Nova Triagem</a>
      </div>
      <div class="table-wrapper">
        <table class="data-table">
          <thead>
            <tr>
              <th>Nome</th>
              <th>CPF</th>
              <th>Nascimento</th>
              <th>Etapa</th>
              <th>Data início triagem</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($idosos)): ?>
              <?php foreach ($idosos as $idoso): ?>
                <tr>
                  <td><?= htmlspecialchars($idoso['nome_idoso']) ?></td>
                  <td><?= htmlspecialchars(formatCpf($idoso['cpf_idoso'])) ?></td>
                  <td><?= htmlspecialchars(formatDate($idoso['data_nascimento'])) ?></td>
                  <td><?= htmlspecialchars($idoso['etapa']) ?></td>
                  <td><?= htmlspecialchars(formatDate($idoso['data_de_inicio_cadastro_idoso'])) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="5" class="no-results">Nenhum registro encontrado.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>

  <footer class="footer-bottom">
    Sistema de Triagem LSVP - Lar São Vicente de Paulo | © 2025 - Versão 1.0
  </footer>
</body>
</html>