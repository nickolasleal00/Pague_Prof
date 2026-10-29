<?php
function escaparRecuperar(string $valor): string { return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
  <title>Recuperar senha | PagueProf</title>
  <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<main class="auth-shell">
  <section class="auth-card">
    <a class="marca" href="index.php"><span class="marca-icone">P</span><span>Pague<strong>Prof</strong></span></a>
    <p class="eyebrow">Recuperação de acesso</p>
    <h1>Esqueceu a senha?</h1>
    <p class="subtitulo">Informe o e-mail da sua conta. Se estiver cadastrado, enviaremos um link temporário para criar uma nova senha.</p>
    <?php if (!empty($mensagem)): ?><div class="alerta sucesso" role="status"><?= escaparRecuperar($mensagem) ?></div><?php endif; ?>
    <?php if (!empty($erro)): ?><div class="alerta erro" role="alert"><?= escaparRecuperar($erro) ?></div><?php endif; ?>
    <form method="post" action="index.php?rota=recuperar_senha" autocomplete="on">
      <input type="hidden" name="csrf" value="<?= escaparRecuperar($_SESSION['csrf'] ?? '') ?>">
      <label for="email">E-mail</label>
      <input id="email" name="email" type="email" maxlength="190" autocomplete="email" required>
      <button class="botao" type="submit">Enviar link de recuperação</button>
    </form>
    <p class="rodape-auth"><a href="index.php?rota=login">Voltar ao login</a></p>
  </section>
  <aside class="auth-aside"><span class="selo">PAGUEPROF</span><h2>Recupere seu acesso.</h2><p>O link é válido por uma hora e só pode ser usado uma vez.</p><div class="aside-linha"></div><small>Se não solicitou a troca, ignore o e-mail.</small></aside>
</main>
</body>
</html>
