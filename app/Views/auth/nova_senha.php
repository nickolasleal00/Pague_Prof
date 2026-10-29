<?php
function escaparNovaSenha(string $valor): string { return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
  <title>Definir nova senha | PagueProf</title>
  <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<main class="auth-shell">
  <section class="auth-card">
    <a class="marca" href="index.php"><span class="marca-icone">P</span><span>Pague<strong>Prof</strong></span></a>
    <p class="eyebrow">Segurança da conta</p>
    <h1>Defina uma nova senha</h1>
    <p class="subtitulo">Use uma senha com pelo menos 8 caracteres.</p>
    <?php if (!empty($erro)): ?><div class="alerta erro" role="alert"><?= escaparNovaSenha($erro) ?></div><?php endif; ?>
    <?php if (empty($erro)): ?>
    <form method="post" action="index.php?rota=nova_senha" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= escaparNovaSenha($_SESSION['csrf'] ?? '') ?>">
      <input type="hidden" name="token" value="<?= escaparNovaSenha($token) ?>">
      <label for="senha">Nova senha</label>
      <input id="senha" name="senha" type="password" required minlength="8" autocomplete="new-password">
      <label for="confirmacao">Confirme a nova senha</label>
      <input id="confirmacao" name="confirmacao" type="password" required minlength="8" autocomplete="new-password">
      <button class="botao" type="submit">Salvar nova senha</button>
    </form>
    <?php endif; ?>
    <p class="rodape-auth"><a href="index.php?rota=login">Voltar ao login</a></p>
  </section>
  <aside class="auth-aside"><span class="selo">PAGUEPROF</span><h2>Uma senha nova protege sua conta.</h2><p>Este link expira após uma hora e deixa de funcionar depois de ser usado.</p><div class="aside-linha"></div></aside>
</main>
</body>
</html>
