<?php
function escapar(string $valor): string { return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8'); }
$mensagem = $_SESSION['mensagem'] ?? '';
unset($_SESSION['mensagem']);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Acesse sua conta PagueProf para acompanhar serviços, cobranças e pagamentos.">
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <title>Entrar | PagueProf</title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<main class="auth-shell">
    <section class="auth-card" aria-labelledby="titulo">
        <a class="marca" href="index.php" aria-label="PagueProf, página inicial"><span class="marca-icone">P</span><span>Pague<strong>Prof</strong></span></a>
        <p class="eyebrow">Acesso à plataforma</p>
        <h1 id="titulo">Entre na sua conta</h1>
        <p class="subtitulo">Acompanhe serviços, cobranças e pagamentos em um só lugar.</p>
        <?php if ($mensagem): ?><div class="alerta sucesso" role="status"><?= escapar($mensagem) ?></div><?php endif; ?>
        <?php if ($erro): ?><div class="alerta erro" role="alert"><?= escapar($erro) ?></div><?php endif; ?>
        <form method="post" action="index.php?rota=login" autocomplete="on">
            <input type="hidden" name="csrf" value="<?= escapar($_SESSION['csrf']) ?>">
            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" required maxlength="190" autocomplete="email" placeholder="voce@exemplo.com">
            <label for="senha">Senha</label>
            <input id="senha" name="senha" type="password" required autocomplete="current-password" placeholder="Sua senha">
            <button class="botao" type="submit">Entrar</button>
        </form>
        <p class="rodape-auth"><a href="index.php?rota=recuperar_senha">Esqueci minha senha</a></p>
        <p class="rodape-auth">Ainda não tem conta? <a href="index.php?rota=cadastro">Criar conta</a></p>
        <p class="nota">O mesmo acesso pode ter perfil de profissional, cliente ou ambos.</p>
    </section>
    <aside class="auth-aside"><span class="selo">PAGUEPROF</span><h2>Mais tempo para o seu trabalho.</h2><p>Organize serviços, cobranças e recebimentos em um só lugar.</p><div class="aside-linha"></div><small>Gestão simples para profissionais e clientes.</small></aside>
</main>
</body>
</html>
