<?php
function escaparCadastro(string $valor): string { return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <title>Criar conta | PagueProf</title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<main class="auth-shell">
    <section class="auth-card">
        <a class="marca" href="index.php"><span class="marca-icone">P</span><span>Pague<strong>Prof</strong></span></a>
        <p class="eyebrow">Comece agora</p>
        <h1>Crie sua conta</h1>
        <p class="subtitulo">Escolha os acessos que deseja usar. Você pode selecionar os dois.</p>
        <?php if ($erro): ?><div class="alerta erro" role="alert"><?= escaparCadastro($erro) ?></div><?php endif; ?>
        <form method="post" action="index.php?rota=cadastro" autocomplete="on">
            <input type="hidden" name="csrf" value="<?= escaparCadastro($_SESSION['csrf']) ?>">
            <label for="nome">Nome completo</label>
            <input id="nome" name="nome" type="text" required minlength="2" maxlength="140" autocomplete="name">
            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" required maxlength="190" autocomplete="email">
            <label for="senha">Senha</label>
            <input id="senha" name="senha" type="password" required minlength="8" autocomplete="new-password">
            <fieldset class="perfis"><legend>Quero acessar como</legend>
                <label class="opcao"><input type="checkbox" name="perfis[]" value="profissional"> Profissional</label>
                <label class="opcao"><input type="checkbox" name="perfis[]" value="cliente"> Cliente</label>
            </fieldset>
            <button class="botao" type="submit">Criar conta</button>
        </form>
        <p class="rodape-auth">Já tem uma conta? <a href="index.php?rota=login">Entrar</a></p>
        <p class="nota">Você pode ter acesso como profissional, cliente ou nos dois perfis.</p>
    </section>
    <aside class="auth-aside"><span class="selo">PAGUEPROF</span><h2>Uma conta, os acessos que você precisa.</h2><p>A mesma pessoa pode contratar serviços e também prestar serviços profissionais.</p><div class="aside-linha"></div><small>Seus dados são protegidos com senha criptografada.</small></aside>
</main>
</body>
</html>
