<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Ficha de Triagem - Contrato - Sistema de Triagem LSVP</title>
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
<!-- Menu lateral -->
        <aside class="triagem-sidebar">
          <button class="sidebar-button">Ficha de Triagem - Início</button>
          <button class="sidebar-button">Ficha de Triagem - Continuação</button>
          <button class="sidebar-button active">Ficha de Triagem - Contrato</button>
          <button class="sidebar-button">Parecer do(a) Coordenador(a)</button>
          <button class="sidebar-button">Parecer da Diretoria</button>
          <button class="sidebar-button">Parecer do Médico</button>
          <button class="sidebar-button">Parecer Psicológico</button>
          <button class="sidebar-button">Finalização da Triagem</button>
        </aside>

        <!-- Formulário principal -->
        <section class="triagem-form-content">
          <h2>Ficha de Triagem - Contrato</h2>
          <form class="form-grid">
            
            <fieldset>
              <legend>Dados Pessoais</legend>
              <nav>
                <ul>
                  <li><a>Situação ocupacional</a><input type="text" placeholder="Situação ocupacional..." name="situacao_ocupacional" /></li>
                  <li><a>Telefone</a><input type="text" placeholder="Telefone..." name="telefone" /></li>
                </ul>
              </nav>
            </fieldset>
            <fieldset>
              <legend>Responsável Solidário</legend>
              <nav>
                <ul>
                  <li><a>Nome</a><input type="text" placeholder="Nome..." name="nome" /></li>
                  <li><a>Grau de parentesco</a><input type="text" placeholder="Grau de parentesco..." name="grau_parentesco" /></li>
                </ul>
              </nav>
            </fieldset>
            <fieldset>
              <nav>
                <ul>
                  <li><a>Logradouro</a><input type="text" placeholder="Logradouro..." name="logradouro" /></li>
                  <li><a>Numero</a><input type="text" placeholder="Numero..." name="numero" /></li>
                  <li><a>Bairro</a><input type="text" placeholder="Bairro..." name="bairro" /></li>
                </ul>
              </nav>
            </fieldset>
            <fieldset>
              <nav>
                <ul>
                  <li><a>Cidade</a><input type="text" placeholder="Cidade..." name="cidade" /></li>
                  <li><a>CEP</a><input type="text" placeholder="CEP..." name="cep" /></li>
                  <li><a>Estado</a><input type="text" placeholder="Estado..." name="estado" /></li>
                </ul>
              </nav>
            </fieldset>
            <fieldset>
              <nav>
                <ul>
                  <li><a>Telefone</a><input type="text" placeholder="Telefone..." name="telefone" /></li>
                  <li><a>CPF</a><input type="text" placeholder="CPF..." name="cpf" /></li>
                </ul>
              </nav>
            </fieldset>
            <fieldset>
              <nav>
                <ul>
                  <li><a>Estado cívil</a><input type="text" placeholder="Estado cívil..." name="estado_civil" /></li>
                  <li><a>RG</a><input type="text" placeholder="RG..." name="rg" /></li>
                  <li><a>Nacionalidade</a><input type="text" placeholder="Nacionalidade..." name="nacionalidade" /></li>
                </ul>
              </nav>
            </fieldset>
            <fieldset>
              <legend>Outros</legend>
              <nav>
                <ul>
                  <li><a>Nome do(a) assistente social</a><input type="text" placeholder="Nome assistente social..." name="nome_assistente_social" /></li>
                  <li><a>Nome do(a) coordenador(a)</a><input type="text" placeholder="Nome coordenador administrativo..." name="nome_coordenador_administrativo" /></li>
                </ul>
              </nav> 
            </fieldset>
            <div class="form-buttons">
              <button href="nova_triagem.html" type="submit" class="btn-primary">Voltar</button>
              <button type="submit" class="btn-primary">Salvar</button>
              <button type="button" class="btn-secondary">Alterar</button>
              <button type="submit" class="btn-primary">Finalizar</button>
              <button type="submit" class="btn-primary">Avançar</button>
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