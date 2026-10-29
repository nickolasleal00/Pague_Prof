<?php
function escaparPainel(string $valor): string { return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8'); }
$perfis = array_map(static fn($perfil) => ucfirst($perfil), $usuario['perfis']);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <title>Painel | PagueProf</title>
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<header class="barra"><a class="marca" href="index.php?rota=painel"><span class="marca-icone">P</span><span>Pague<strong>Prof</strong></span></a><a class="sair" href="index.php?rota=sair">Sair</a></header>
<main class="painel">
    <p class="eyebrow">Conta autenticada</p>
    <h1>Olá, <?= escaparPainel($usuario['nome']) ?></h1>
    <p>Você entrou com sucesso no PagueProf.</p>
    <section class="cartao-painel"><h2>Seus acessos</h2><div class="etiquetas"><?php foreach ($perfis as $perfil): ?><span class="etiqueta"><?= escaparPainel($perfil) ?></span><?php endforeach; ?></div><p>Cadastre clientes, organize serviços, gere cobranças e acompanhe seu financeiro.</p></section>
</main>
</body>
</html>
