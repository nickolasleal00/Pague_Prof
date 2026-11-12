<?php
function pagueprofEsc($v): string { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function pagueprofDinheiro($v): string { return 'R$ '.number_format((float)$v,2,',','.'); }
function pagueprofData($v): string { return $v ? date('d/m/Y',strtotime((string)$v)) : '—'; }
function pagueprofPixTipo(string $tipo): string { return ['cpf'=>'CPF','telefone'=>'telefone celular','email'=>'e-mail'][$tipo]??'chave Pix'; }
function pagueprofStatus(array $c): array {
    $s=(string)($c['status']??'pendente');
    if($s==='recusada')return ['Comprovante recusado','roxo'];
    if(in_array($s,['pendente','em_analise'],true) && !empty($c['data_vencimento']) && $c['data_vencimento']<date('Y-m-d'))return ['Vencida','laranja'];
    $rotulos=['pendente'=>'A pagar','paga'=>'Paga','em_analise'=>'Em análise','recusada'=>'Comprovante recusado','cancelada'=>'Cancelada'];
    $classes=['pendente'=>'amarelo','paga'=>'verde','em_analise'=>'azul','recusada'=>'roxo','cancelada'=>'neutro'];
    return [$rotulos[$s]??ucfirst($s),$classes[$s]??'neutro'];
}
$csrfValor=htmlspecialchars((string)($_SESSION['csrf']??''),ENT_QUOTES,'UTF-8');
$perfisAtivos=(array)($usuario['perfis']??[]);
$temProfissional=in_array('profissional',$perfisAtivos,true);$temCliente=in_array('cliente',$perfisAtivos,true);
$rotuloPerfis=$temProfissional&&$temCliente?'Cliente e Profissional':($temProfissional?'Profissional':($temCliente?'Cliente':''));
$nomeSessao=trim((string)($usuario['nome']??''));
$nomeSessao=preg_replace('/(?:\s*\((?:Cleiente|Cliente)\s+e\s+Profissional\))+\s*$/iu','',$nomeSessao)??$nomeSessao;
$nomeSessao=trim($nomeSessao);
$chavePixExibicao=(string)($configuracao['chave_pix']??'');
if(!empty($configuracao['chave_pix_tipo'])&&$chavePixExibicao!==''){try{$chavePixExibicao=Pix::formatarChave((string)$configuracao['chave_pix_tipo'],$chavePixExibicao);}catch(Throwable $e){}}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#f5f7fb">
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
  <title>PagueProf | <?= pagueprofEsc($pagina==='planos'?'Nova cobrança':ucfirst(str_replace('_',' ',$pagina))) ?></title>
  <link rel="stylesheet" href="assets/app.css?v=20261112-calendar-layout-v11">
</head>
<body class="app-body">
<header class="barra-app">
  <a class="marca" href="index.php?rota=<?= $temProfissional?'relatorios':'minhas_cobrancas' ?>"><span class="marca-icone">P</span><span>Pague<strong>Prof</strong></span></a>
  <div class="topo-direita"><span class="nome-sessao" title="<?= pagueprofEsc($nomeSessao) ?><?= $rotuloPerfis?' ('.pagueprofEsc($rotuloPerfis).')':'' ?>"><?= pagueprofEsc($nomeSessao) ?><?= $rotuloPerfis?' ('.pagueprofEsc($rotuloPerfis).')':'' ?></span><a class="sair" href="index.php?rota=sair">Sair</a></div>
  <button class="menu-hamburguer" id="menu-hamburguer" type="button" aria-controls="menu-app" aria-expanded="false" aria-label="Abrir menu de navegação"><span class="menu-hamburguer-icone" aria-hidden="true"><i></i><i></i><i></i></span><span>Menu</span></button>
</header>
<div class="app-shell">
  <nav class="menu-app" id="menu-app" aria-label="Menu principal">
    <?php if($temProfissional): ?>
      <a class="<?= $pagina==='clientes'?'ativo':'' ?>" href="index.php?rota=clientes"><svg class="icone-menu" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><circle cx="7" cy="6" r="3"></circle><path d="M2 17c.5-3 2.2-4.5 5-4.5s4.5 1.5 5 4.5M14 8.5a3 3 0 0 0 0-5M14 12.5c2 0 3.5 1.2 4 3.5"></path></svg><span>Clientes</span></a>
      <a class="<?= $pagina==='planos'?'ativo':'' ?>" href="index.php?rota=nova_cobranca"><svg class="icone-menu" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M4 2.5h8.5L16 6v11l-2-1.3L12 17l-2-1.3L8 17l-2-1.3L4 17V2.5Z"></path><path d="M7 7h5M7 10h3M13.5 12v5M11 14.5h5"></path></svg><span>Nova cobrança</span></a>
      <a class="<?= in_array($pagina,['cobrancas','editar_cobranca'],true)?'ativo':'' ?>" href="index.php?rota=cobrancas"><svg class="icone-menu" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><rect x="2" y="4" width="16" height="12" rx="2"></rect><path d="M2 8h16M5 12h4"></path></svg><span>Financeiro</span></a>
      <a class="<?= $pagina==='comprovantes'?'ativo':'' ?>" href="index.php?rota=comprovantes"><svg class="icone-menu" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M5 2.5h7l3 3V17H5V2.5Z"></path><path d="M12 2.5V6h3M8 9h4M8 12h4M8 15h2"></path></svg><span>Comprovantes</span></a>
      <a class="<?= $pagina==='relatorios'?'ativo':'' ?>" href="index.php?rota=relatorios"><svg class="icone-menu" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M3 17V10h3v7M8.5 17V6h3v11M14 17V3h3v14M2 17.5h16"></path></svg><span>Relatórios</span></a>
      <a class="<?= $pagina==='calendario'?'ativo':'' ?>" href="index.php?rota=calendario"><svg class="icone-menu" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><rect x="2.5" y="4" width="15" height="13" rx="2"></rect><path d="M6 2.5V6M14 2.5V6M2.5 8h15M6 11h2M11 11h2M6 14h2"></path></svg><span>Calendário mensal</span></a>
      <a class="<?= $pagina==='configuracoes'?'ativo':'' ?>" href="index.php?rota=configuracoes"><svg class="icone-menu" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><circle cx="10" cy="10" r="3"></circle><path d="M10 2v2M10 16v2M18 10h-2M4 10H2M15.7 4.3l-1.4 1.4M5.7 14.3l-1.4 1.4M15.7 15.7l-1.4-1.4M5.7 5.7 4.3 4.3"></path></svg><span>Config recebimentos</span></a>
    <?php endif; ?>
    <?php if($temCliente): ?><a class="<?= $pagina==='minhas_cobrancas'?'ativo':'' ?>" href="index.php?rota=minhas_cobrancas"><svg class="icone-menu" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><rect x="2" y="4" width="16" height="12" rx="2"></rect><path d="M2 8h16M5 12h3"></path></svg><span>Meus pagamentos</span></a><?php if(!$temProfissional): ?><form class="menu-form-profissional" method="post" action="index.php?rota=ativar_profissional"><input type="hidden" name="csrf" value="<?= $csrfValor ?>"><button class="menu-promover-profissional" type="submit"><svg class="icone-menu" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><circle cx="8" cy="6" r="3"></circle><path d="M2.5 17c.5-3 2.3-4.5 5.5-4.5M15 11v7M11.5 14.5h7"></path></svg><span>Quero ser um profissional</span></button></form><?php endif; ?><?php endif; ?>
    <a class="menu-sair" href="index.php?rota=sair"><svg class="icone-menu" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M8 3.5h8v13H8"></path><path d="M2.5 10h9M8 6.5l3.5 3.5L8 13.5"></path></svg><span>Sair</span></a>
  </nav>
  <main class="conteudo-app">
    <?php if($mensagem): ?><div class="alerta sucesso"><?= pagueprofEsc($mensagem) ?></div><?php endif; ?>
    <?php if($erroFlash || !empty($erro)): ?><div class="alerta erro"><?= pagueprofEsc($erroFlash ?: $erro) ?></div><?php endif; ?>

    <?php if($pagina==='clientes'): ?>
      <div class="cabecalho-pagina"><div><p class="eyebrow">PROFISSIONAL</p><h1>Clientes</h1><p>Cadastre clientes e acompanhe a situação de cada um.</p></div></div>
      <section class="bloco">
        <?php if(!empty($clienteEditar)): ?>
          <h2>Editar cliente</h2>
          <form method="post" action="index.php?rota=clientes" class="form-grid">
            <input type="hidden" name="csrf" value="<?= $csrfValor ?>"><input type="hidden" name="cliente_id" value="<?= (int)$clienteEditar['id'] ?>">
            <label>Nome completo<input name="nome" maxlength="140" autocomplete="name" value="<?= pagueprofEsc($clienteEditar['nome']??'') ?>" required></label>
            <label>E-mail<input name="email" type="email" maxlength="190" autocomplete="email" value="<?= pagueprofEsc($clienteEditar['email']??'') ?>" required></label>
            <label>Telefone<input name="telefone" type="tel" maxlength="40" value="<?= pagueprofEsc($clienteEditar['telefone']??'') ?>"></label>
            <label>Observação<input name="area_atuacao" maxlength="100" value="<?= pagueprofEsc($clienteEditar['area_atuacao']??'') ?>" placeholder="Ex.: Aluno de Piano"></label>
            <div class="form-acoes"><button class="botao" type="submit">Salvar alterações</button><a class="botao secundario" href="index.php?rota=clientes">Cancelar</a></div>
          </form>
        <?php else: ?>
          <h2>Novo cliente</h2>
          <form method="post" action="index.php?rota=clientes" class="form-grid">
            <input type="hidden" name="csrf" value="<?= $csrfValor ?>">
            <label>Nome completo<input name="nome" maxlength="140" autocomplete="name" value="<?= pagueprofEsc($clienteForm['nome']??'') ?>" required></label>
            <label>E-mail<input name="email" type="email" maxlength="190" autocomplete="email" value="<?= pagueprofEsc($clienteForm['email']??'') ?>" required></label>
            <label>Telefone<input name="telefone" type="tel" maxlength="40" value="<?= pagueprofEsc($clienteForm['telefone']??'') ?>"></label>
            <label>Observação<input name="area_atuacao" maxlength="100" value="<?= pagueprofEsc($clienteForm['area_atuacao']??'') ?>" placeholder="Ex.: Aluno de Piano"></label>
            <div class="form-acoes"><button class="botao" type="submit">Cadastrar cliente</button></div>
          </form>
        <?php endif; ?>
      </section>
      <section class="bloco"><div class="bloco-cabecalho"><div><h2>Lista de clientes</h2><p>Vermelho indica dívida vencida; verde indica que não há vencimentos em atraso.</p><p>Excluir desativa o cadastro; cobranças e comprovantes anteriores são preservados.</p></div></div><?php if(empty($clientes)): ?><p class="vazio">Nenhum cliente cadastrado.</p><?php else: foreach($clientes as $a): $temDivida=(int)$a['dividas_vencidas']>0; ?><article class="cliente-item <?= $temDivida?'cliente-divida':'cliente-em-dia' ?>"><div class="avatar-inicial"><?= pagueprofEsc(mb_strtoupper(mb_substr($a['nome'],0,1))) ?></div><div class="cliente-dados"><strong><?= pagueprofEsc($a['nome']) ?></strong><span><?= pagueprofEsc($a['email']) ?><?= $a['area_atuacao']?' · Observação: '.pagueprofEsc($a['area_atuacao']):'' ?></span></div><span class="estado <?= $temDivida?'vermelho':'verde' ?>"><?= $temDivida?'Em atraso':'Em dia' ?></span><div class="acoes-cliente"><a class="botao pequeno secundario" href="index.php?rota=clientes&amp;editar_id=<?= (int)$a['id'] ?>">Editar</a><form method="post" action="index.php?rota=excluir_cliente" onsubmit="return confirm('Excluir este cliente da lista? O histórico de cobranças e comprovantes será mantido.');"><input type="hidden" name="csrf" value="<?= $csrfValor ?>"><input type="hidden" name="cliente_id" value="<?= (int)$a['id'] ?>"><button class="botao pequeno perigo" type="submit">Excluir</button></form></div></article><?php endforeach; endif; ?></section>
    <?php elseif($pagina==='planos'): ?>
      <div class="cabecalho-pagina"><div><p class="eyebrow">PROFISSIONAL</p><h1>Nova cobrança</h1><p>Gere cobranças diretamente para um ou mais clientes, sem cadastrar um plano.</p></div><a class="botao secundario" href="index.php?rota=cobrancas">Abrir financeiro</a></div>
      <?php if(empty($temInstrucoesPagamento)): ?><div class="alerta erro">Antes de gerar cobranças, cadastre as instruções de pagamento. <a class="link-acao" href="index.php?rota=configuracoes">Cadastrar instruções</a></div><?php endif; ?>
      <?php if(empty($clientes)): ?>
        <section class="bloco"><p class="vazio">Cadastre ao menos um cliente antes de gerar cobranças. <a class="link-acao" href="index.php?rota=clientes">Cadastrar cliente</a></p></section>
      <?php else: ?>
        <section class="bloco"><h2>Nova geração</h2><form method="post" action="index.php?rota=gerar_cobrancas" class="form-grid" id="form-cobrancas">
          <input type="hidden" name="csrf" value="<?= $csrfValor ?>"><input type="hidden" name="token_cobranca_direta" value="<?= pagueprofEsc($tokenCobrancaDireta) ?>">
          <div class="selecao-clientes-cobranca campo-largo" role="group" aria-labelledby="titulo-clientes-cobranca">
            <div class="selecao-clientes-cabecalho"><div><h3 id="titulo-clientes-cobranca">Quem receberá a cobrança?</h3><p>Selecione ao menos um cliente. O mesmo serviço, valor, quantidade e vencimento serão usados para cada pessoa.</p></div><strong id="resumo-clientes-selecionados" aria-live="polite"><?= (int)($clienteInicialId??0)>0?'1 cliente selecionado':'Nenhum cliente selecionado' ?></strong></div>
            <label class="busca-clientes-cobranca" for="buscar-clientes-cobranca">Buscar cliente por nome ou e-mail<input type="search" id="buscar-clientes-cobranca" placeholder="Digite para filtrar a lista" autocomplete="off" aria-describedby="ajuda-selecao-clientes"></label>
            <p class="ajuda-selecao-clientes" id="ajuda-selecao-clientes">A primeira cobrança de cada cliente terá o envio inicial tentado; as próximas ficam no Financeiro.</p>
            <div class="acoes-selecao-clientes"><button class="botao pequeno secundario" id="marcar-clientes-visiveis" type="button">Marcar exibidos</button><button class="botao pequeno secundario" id="limpar-selecao-clientes" type="button">Limpar seleção</button></div>
            <div class="lista-clientes-cobranca" id="lista-clientes-cobranca" role="group" aria-label="Lista de clientes disponíveis">
              <?php foreach($clientes as $cliente): $clienteSelecionado=(int)($clienteInicialId??0)===(int)$cliente['id']; ?><label class="opcao-cliente-cobranca <?= $clienteSelecionado?'is-selecionado':'' ?>" data-busca="<?= pagueprofEsc(mb_strtolower((string)$cliente['nome'].' '.(string)($cliente['email']??''),'UTF-8')) ?>"><input type="checkbox" name="cliente_ids[]" value="<?= (int)$cliente['id'] ?>" <?= $clienteSelecionado?'checked':'' ?>><span class="dados-opcao-cliente"><strong><?= pagueprofEsc($cliente['nome']) ?></strong><small><?= pagueprofEsc($cliente['email']??'') ?></small></span></label><?php endforeach; ?>
            </div>
            <p class="lista-clientes-vazia" id="lista-clientes-vazia" hidden role="status">Nenhum cliente encontrado para essa busca.</p>
          </div>
          <div class="campo-servico-wrap"><div class="campo-servico-controle"><label for="tipo-servico-cobranca">Tipo de serviço<select id="tipo-servico-cobranca" name="tipo_servico_id" required><option value="">Selecione ou adicione um serviço</option><?php foreach($tipos as $tipo): ?><option value="<?= (int)$tipo['id'] ?>" <?= (int)($tipoServicoInicialId??0)===(int)$tipo['id']?'selected':'' ?>><?= pagueprofEsc($tipo['nome']) ?></option><?php endforeach; ?></select></label><button class="botao pequeno secundario" id="abrir-novo-servico" type="button">+ Novo serviço</button></div>
            <div class="novo-servico-inline" id="novo-servico-inline" hidden><label for="nome-novo-servico">Nome do serviço<input id="nome-novo-servico" maxlength="100" placeholder="Ex.: Aula de piano, manutenção"></label><div class="form-acoes"><button class="botao pequeno" id="salvar-novo-servico" type="button">Adicionar serviço</button><button class="botao pequeno secundario" id="cancelar-novo-servico" type="button">Cancelar</button></div><p id="estado-novo-servico" class="texto-secundario" aria-live="polite"></p></div>
          </div>
          <label>Valor do serviço por cobrança (R$)<input name="valor_servico" type="number" min="0.01" max="99999999.99" step="0.01" inputmode="decimal" placeholder="0,00" value="<?= pagueprofEsc($valorInicial??'') ?>" required></label>
          <div class="form-inline geracao-cobrancas">
            <label>Quantidade de cobranças<input id="quantidade-cobrancas" name="quantidade" type="number" min="1" max="36" value="1" required><small>De 1 a 36 cobranças, geradas mensalmente.</small></label>
            <label>Primeiro vencimento<input name="data_vencimento" type="date" value="<?= pagueprofEsc(date('Y-m-d',strtotime('+1 month'))) ?>" required></label>
            <button class="botao" type="submit" <?= empty($temInstrucoesPagamento)?'disabled':'' ?>>Gerar cobranças</button>
          </div>
          <p class="texto-secundario previsao-cobrancas" id="previsao-cobrancas" aria-live="polite">Selecione um ou mais clientes para ver o total de cobranças. Os vencimentos seguintes serão mensais.</p>
        </form></section>
        <section class="bloco"><p class="texto-secundario">Cada cliente selecionado receberá sua própria série de cobranças com os dados informados. A criação é feita em conjunto: se algum dado estiver inválido, nenhuma série será gravada.</p></section>
      <?php endif; ?>
    <?php elseif($pagina==='cobrancas'): ?>
      <div class="cabecalho-pagina"><div><p class="eyebrow">PROFISSIONAL</p><h1>Financeiro</h1><p>Consulte débitos, vencimentos e pagamentos recebidos.</p></div></div>
      <nav class="abas-financeiro" aria-label="Seções do financeiro">
        <a class="<?= ($aba??'cobrancas')==='cobrancas'?'ativa':'' ?>" <?= ($aba??'cobrancas')==='cobrancas'?'aria-current="page"':'' ?> href="index.php?rota=cobrancas&amp;aba=cobrancas&amp;cliente_id=<?= (int)$clienteId ?>">Cobranças</a>
        <a class="<?= ($aba??'cobrancas')==='sugestoes'?'ativa':'' ?>" <?= ($aba??'cobrancas')==='sugestoes'?'aria-current="page"':'' ?> href="index.php?rota=cobrancas&amp;aba=sugestoes&amp;cliente_id=<?= (int)$clienteId ?>">Sugestão de novas cobranças</a>
      </nav>
      <?php if(($aba??'cobrancas')==='cobrancas'): ?>
        <section class="bloco"><form method="get" action="index.php" class="filtros"><input type="hidden" name="rota" value="cobrancas"><input type="hidden" name="aba" value="cobrancas">
          <label>Cliente<select name="cliente_id"><option value="0">Todos</option><?php foreach($clientes as $cliente): ?><option value="<?= (int)$cliente['id'] ?>" <?= (int)$clienteId===(int)$cliente['id']?'selected':'' ?>><?= pagueprofEsc($cliente['nome'].((int)($cliente['ativo']??0)===1?'':' (inativo)')) ?></option><?php endforeach; ?></select></label>
          <label>De<input type="date" name="de" value="<?= pagueprofEsc($de) ?>"></label>
          <label>Até<input type="date" name="ate" value="<?= pagueprofEsc($ate) ?>"></label>
          <label>Situação<select name="status"><option value="">Todas</option><?php foreach(['pendente'=>'A pagar','paga'=>'Paga','em_analise'=>'Em análise','recusada'=>'Recusada','cancelada'=>'Cancelada'] as $v=>$t): ?><option value="<?= $v ?>" <?= $status===$v?'selected':'' ?>><?= $t ?></option><?php endforeach; ?></select></label>
          <label>Organizar por<select name="ordenar"><?php foreach(['vencimento_desc'=>'Vencimento: mais recente','vencimento_asc'=>'Vencimento: mais antigo','cliente_asc'=>'Cliente: A a Z','cliente_desc'=>'Cliente: Z a A','valor_desc'=>'Valor: maior primeiro','valor_asc'=>'Valor: menor primeiro','status'=>'Situação'] as $v=>$t): ?><option value="<?= $v ?>" <?= ($ordenar??'vencimento_desc')===$v?'selected':'' ?>><?= $t ?></option><?php endforeach; ?></select></label>
          <button class="botao secundario" type="submit">Aplicar filtros</button>
        </form><div class="filtros-exportar"><a class="botao pequeno secundario" href="index.php?<?= pagueprofEsc(http_build_query(['rota'=>'exportar_cobrancas','de'=>$de,'ate'=>$ate,'status'=>$status,'cliente_id'=>$clienteId,'ordenar'=>$ordenar])) ?>" title="Baixa um arquivo CSV compatível com Excel com os filtros selecionados">Exportar para Excel (.CSV)</a></div></section>
        <p class="resumo-contexto">Total geral = Recebido + Em aberto, sem contar uma cobrança duas vezes. Recebido usa a data do pagamento; Em aberto usa o vencimento e inclui recusados. Vencido inclui pendentes/em análise após o vencimento, sem somar recusados. O filtro de situação afeta apenas a lista.</p>
        <section class="grade-resumo resumo-padroes" aria-label="Totalizadores financeiros"><article class="cartao-resumo destaque-total-geral"><span>Total geral</span><strong><?= pagueprofDinheiro($resumo['total_geral']??0) ?></strong></article><article class="cartao-resumo"><span>Recebido</span><strong><?= pagueprofDinheiro($resumo['recebido']??0) ?></strong></article><article class="cartao-resumo"><span>Em aberto</span><strong><?= pagueprofDinheiro($resumo['aberto']??0) ?></strong></article><article class="cartao-resumo alerta-resumo"><span>Vencido</span><strong><?= pagueprofDinheiro($resumo['vencido']??0) ?></strong></article><article class="cartao-resumo resumo-rejeitado"><span>Rejeitado</span><strong><?= pagueprofDinheiro($resumo['rejeitado']??0) ?></strong></article></section><p class="indicador-clientes-periodo" title="Clientes com cobrança / total de clientes ativos" aria-label="Clientes no período: <?= (int)($resumo['clientes_periodo']??0) ?> de <?= (int)($resumo['clientes_ativos']??0) ?> clientes ativos têm cobrança"><span>Clientes no período:</span><strong><?= (int)($resumo['clientes_periodo']??0) ?>/<?= (int)($resumo['clientes_ativos']??0) ?></strong></p>
        <section class="bloco"><div class="bloco-cabecalho"><div><h2>Cobranças</h2><p><?= count($cobrancas) ?> registro(s) neste resultado.</p></div><a class="botao pequeno" href="index.php?rota=nova_cobranca">Nova cobrança</a></div>
          <?php if(empty($cobrancas)): ?><p class="vazio">Nenhuma cobrança neste filtro. Use “Nova cobrança” para gerar uma.</p><?php else: ?>
            <div class="tabela-scroll"><table class="tabela-financeiro"><thead><tr><th scope="col">Cliente</th><th scope="col">Serviço</th><th scope="col">Vencimento</th><th scope="col">Valor</th><th scope="col">Situação</th><th scope="col">E-mail</th><th scope="col">Ações</th></tr></thead><tbody>
            <?php foreach($cobrancas as $c): [$label,$classe]=pagueprofStatus($c); $statusEmail=(string)($c['notificacao_status']??''); $podeEditar=$c['status']==='pendente'&&(int)($c['emails_enviados']??0)===0&&(int)($c['emails_tentativas']??0)===0&&(int)($c['comprovantes_enviados']??0)===0; ?>
              <tr>
                <td data-label="Cliente"><strong><?= pagueprofEsc($c['cliente_nome']) ?></strong></td>
                <td data-label="Serviço"><?= pagueprofEsc($c['tipo_servico_nome']??'Serviço') ?></td>
                <td data-label="Vencimento"><?= pagueprofData($c['data_vencimento']) ?></td>
                <td data-label="Valor" class="coluna-valor"><?= pagueprofDinheiro($c['valor']) ?></td>
                <td data-label="Situação"><div class="status-comprovante"><span class="estado <?= $classe ?>"><?= pagueprofEsc($label) ?></span><?php if(in_array($c['status'],['em_analise','paga','recusada'],true)&&!empty($c['comprovante_id'])): ?><a class="botao pequeno secundario" href="index.php?rota=abrir_comprovante&amp;id=<?= (int)$c['comprovante_id'] ?>" title="Abrir o comprovante em uma janela PagueProf">Comprovante</a><?php endif; ?></div></td>
                <td data-label="E-mail"><?php if(in_array($statusEmail,['aguardando_envio_profissional','falhou'],true)&&!empty($c['notificacao_id'])&&(int)($c['notificacao_tentativas']??0)<5): ?><form method="post" action="index.php?rota=enviar_notificacao" class="form-acao-tabela"><input type="hidden" name="csrf" value="<?= $csrfValor ?>"><input type="hidden" name="notificacao_id" value="<?= (int)$c['notificacao_id'] ?>"><button class="botao pequeno secundario" type="submit"><?= $statusEmail==='falhou'?'Tentar novamente':'Enviar e-mail' ?></button></form><?php elseif($statusEmail==='enviada'): ?><span class="estado verde">Enviado</span><?php elseif($statusEmail==='falhou'): ?><span class="estado vermelho" title="Limite de tentativas atingido">Falha</span><?php elseif($statusEmail==='aguardando_envio_profissional'): ?><span class="estado neutro">Na fila</span><?php elseif($statusEmail==='pendente'): ?><span class="estado neutro">Pendente</span><?php elseif($statusEmail==='ignorada'): ?><span class="estado neutro">Ignorado</span><?php else: ?><span class="texto-secundario">—</span><?php endif; ?></td>
                <td data-label="Ações"><div class="acoes-tabela"><?php if($podeEditar): ?><a class="botao pequeno secundario" href="index.php?rota=editar_cobranca&amp;id=<?= (int)$c['id'] ?>">Editar</a><?php endif; ?><a class="botao pequeno secundario" href="index.php?<?= pagueprofEsc(http_build_query(['rota'=>'nova_cobranca','cliente_id'=>(int)$c['cliente_id'],'tipo_servico_id'=>(int)($c['tipo_servico_id']??0),'valor_servico'=>number_format((float)$c['valor'],2,'.','')])) ?>" title="Criar cobrança com este serviço e valor">Nova</a><?php if(!in_array($c['status'],['paga','cancelada'],true)): ?><form method="post" action="index.php?rota=excluir_cobranca" onsubmit="return confirm('Cancelar esta cobrança? O registro e os comprovantes serão preservados.');"><input type="hidden" name="csrf" value="<?= $csrfValor ?>"><input type="hidden" name="cobranca_id" value="<?= (int)$c['id'] ?>"><button class="botao pequeno perigo" type="submit">Cancelar</button></form><?php endif; ?><?php if(!$podeEditar&&in_array($c['status'],['paga','cancelada'],true)): ?><span class="texto-secundario">—</span><?php endif; ?></div></td>
              </tr>
            <?php endforeach; ?></tbody></table></div>
          <?php endif; ?>
        </section>
      <?php else: ?>
        <section class="bloco"><div class="bloco-cabecalho"><div><h2>Sugestão de novas cobranças</h2><p>A sugestão aparece quando todas as cobranças de uma série mensal forem confirmadas como pagas.</p></div><a class="botao pequeno" href="index.php?rota=nova_cobranca">Nova cobrança</a></div>
          <form method="get" class="filtros"><input type="hidden" name="rota" value="cobrancas"><input type="hidden" name="aba" value="sugestoes"><label>Cliente<select name="cliente_id"><option value="0">TODOS</option><?php foreach($clientes as $cliente): ?><option value="<?= (int)$cliente['id'] ?>" <?= (int)$clienteId===(int)$cliente['id']?'selected':'' ?>><?= pagueprofEsc($cliente['nome'].((int)($cliente['ativo']??0)===1?'':' (inativo)')) ?></option><?php endforeach; ?></select></label><button class="botao secundario" type="submit">Filtrar</button></form>
        </section>
        <?php if(empty($temInstrucoesPagamento)): ?><div class="alerta erro">Cadastre as instruções em <a class="link-acao" href="index.php?rota=configuracoes">Recebimentos</a> antes de gerar a próxima série.</div><?php endif; ?>
        <?php if(empty($sugestoes)): ?><section class="bloco"><p class="vazio">Não há séries quitadas com sugestão de renovação para este filtro.</p></section><?php else: ?>
          <div class="lista-sugestoes"><?php foreach($sugestoes as $s): ?>
            <article class="bloco sugestao-card"><div class="sugestao-topo"><div><h3><?= pagueprofEsc($s['cliente_nome']) ?></h3><p><?= pagueprofEsc($s['tipo_servico_nome']??'Serviço') ?> · último valor: <?= pagueprofDinheiro($s['valor_sugerido']) ?> por cobrança</p><p class="texto-secundario">Última cobrança quitada em <?= pagueprofData($s['ultima_paga']) ?></p></div><span class="estado verde">Quitada</span></div>
              <form method="post" action="index.php?rota=gerar_cobrancas" class="form-grid sugestao-gerar"><input type="hidden" name="csrf" value="<?= $csrfValor ?>"><input type="hidden" name="plano_id" value="<?= (int)$s['id'] ?>"><label>Valor sugerido por cobrança (R$)<input type="number" name="valor_sugerido" min="0.01" max="99999999.99" step="0.01" inputmode="decimal" value="<?= number_format((float)$s['valor_sugerido'],2,'.','') ?>" required><small>Preenchido com a última cobrança paga; pode ser alterado para esta nova série.</small></label><label>Primeiro vencimento<input type="date" name="data_vencimento" value="<?= pagueprofEsc($s['proximo_vencimento']) ?>" required></label><label>Quantidade de cobranças<input type="number" name="quantidade" min="1" max="36" value="3" required></label><div class="form-acoes"><button class="botao" type="submit" <?= empty($temInstrucoesPagamento)?'disabled':'' ?>>Gerar nova série</button></div></form>
              <div class="sugestao-rodape"><small>A remoção oculta a sugestão, mas preserva a série quitada no histórico.</small><form method="post" action="index.php?rota=remover_sugestao" onsubmit="return confirm('Remover esta sugestão? A série paga será preservada no histórico.');"><input type="hidden" name="csrf" value="<?= $csrfValor ?>"><input type="hidden" name="plano_id" value="<?= (int)$s['id'] ?>"><input type="hidden" name="cliente_id" value="<?= (int)$s['cliente_id'] ?>"><button class="botao pequeno perigo" type="submit">Remover sugestão</button></form></div>
            </article>
          <?php endforeach; ?></div>
        <?php endif; ?>
      <?php endif; ?>
    <?php elseif($pagina==='editar_cobranca'): ?>
      <div class="cabecalho-pagina"><div><p class="eyebrow">FINANCEIRO</p><h1>Editar cobrança</h1><p>Edite antes de enviar o e-mail ao cliente.</p></div></div>
      <section class="bloco"><div class="alerta informacao">A edição só fica disponível para cobrança pendente sem e-mail tentado/enviado e sem comprovante. As alterações valem somente para esta cobrança.</div><p><strong>Cliente:</strong> <?= pagueprofEsc($cobranca['cliente_nome']) ?></p>
        <form method="post" action="index.php?rota=editar_cobranca" class="form-grid"><input type="hidden" name="csrf" value="<?= $csrfValor ?>"><input type="hidden" name="cobranca_id" value="<?= (int)$cobranca['id'] ?>"><input type="hidden" name="cliente_id" value="<?= (int)$cobranca['cliente_id'] ?>">
          <label>Serviço<select name="tipo_servico_id"><option value="0" <?= empty($cobranca['tipo_servico_id'])?'selected':'' ?>>Sem serviço</option><?php $tipoAtualAtivo=false;foreach($tipos as $tipo):if((int)$tipo['id']===(int)($cobranca['tipo_servico_id']??0))$tipoAtualAtivo=true; ?><option value="<?= (int)$tipo['id'] ?>" <?= (int)$tipo['id']===(int)($cobranca['tipo_servico_id']??0)?'selected':'' ?>><?= pagueprofEsc($tipo['nome']) ?></option><?php endforeach; ?><?php if(!empty($cobranca['tipo_servico_id'])&&!$tipoAtualAtivo): ?><option value="<?= (int)$cobranca['tipo_servico_id'] ?>" selected><?= pagueprofEsc(($cobranca['tipo_servico_nome']??'Serviço').' (inativo; manter atual)') ?></option><?php endif; ?></select></label>
          <label>Valor (R$)<input name="valor" type="number" min="0.01" max="99999999.99" step="0.01" inputmode="decimal" value="<?= number_format((float)$cobranca['valor'],2,'.','') ?>" required></label>
          <label>Vencimento<input name="data_vencimento" type="date" value="<?= pagueprofEsc($cobranca['data_vencimento']) ?>" required></label>
          <div class="form-acoes"><button class="botao" type="submit">Salvar alterações</button><a class="botao secundario" href="index.php?rota=cobrancas&amp;cliente_id=<?= (int)$cobranca['cliente_id'] ?>">Voltar ao Financeiro</a></div>
        </form>
      </section>
    <?php elseif($pagina==='minhas_cobrancas'): ?>
      <div class="cabecalho-pagina"><div><p class="eyebrow">CLIENTE</p><h1>Meus pagamentos</h1><p>Consulte cobranças e envie comprovantes para conferência.</p></div></div>
      <section class="bloco"><form method="get" action="index.php" class="filtros filtro-pagamentos-cliente"><input type="hidden" name="rota" value="minhas_cobrancas">
        <label>Status<select name="status"><option value="a_pagar" <?= $statusPagamento==='a_pagar'?'selected':'' ?>>A pagar</option><option value="em_analise" <?= $statusPagamento==='em_analise'?'selected':'' ?>>Em análise</option><option value="vencida" <?= $statusPagamento==='vencida'?'selected':'' ?>>Vencida</option><option value="paga" <?= $statusPagamento==='paga'?'selected':'' ?>>Paga</option><option value="recusada" <?= $statusPagamento==='recusada'?'selected':'' ?>>Comprovante recusado</option><option value="cancelada" <?= $statusPagamento==='cancelada'?'selected':'' ?>>Cancelada</option><option value="todos" <?= $statusPagamento==='todos'?'selected':'' ?>>Todos</option></select></label>
        <label>Profissional<select name="profissional_id"><option value="0" <?= (int)$profissionalId===0?'selected':'' ?>>Todos</option><?php foreach($profissionais as $profissional): ?><option value="<?= (int)$profissional['id'] ?>" <?= (int)$profissionalId===(int)$profissional['id']?'selected':'' ?>><?= pagueprofEsc($profissional['nome']) ?></option><?php endforeach; ?></select></label>
        <input type="hidden" name="filtrar_vencimento" value="0"><label class="filtro-periodo-toggle" for="filtrar-vencimento-cliente"><input type="checkbox" id="filtrar-vencimento-cliente" name="filtrar_vencimento" value="1" <?= !empty($filtrarVencimento)?'checked':'' ?>><span>Filtrar por vencimento</span></label>
        <label>Vencimento inicial<input type="date" name="vencimento_inicial" value="<?= pagueprofEsc($vencimentoInicial) ?>" data-vencimento-cliente <?= empty($filtrarVencimento)?'disabled':'' ?>></label>
        <label>Vencimento final<input type="date" name="vencimento_final" value="<?= pagueprofEsc($vencimentoFinal) ?>" data-vencimento-cliente <?= empty($filtrarVencimento)?'disabled':'' ?>></label>
        <button class="botao secundario" type="submit">Aplicar filtros</button>
      </form></section>
      <?php if(empty($profissionais)): ?><section class="bloco"><p class="vazio">Nenhuma cobrança vinculada a esta conta. Confira se o profissional cadastrou este mesmo e-mail.</p></section><?php elseif(empty($cobrancas)): ?><section class="bloco"><p class="vazio">Nenhuma cobrança corresponde aos filtros. Tente alterar o status, selecionar “Todos” ou mudar o período.</p></section><?php else: foreach($cobrancas as $c): [$label,$classe]=pagueprofStatus($c); ?><section class="bloco pagamento-cliente"><div class="pagamento-topo"><div><p class="eyebrow">PROFISSIONAL</p><h2><?= pagueprofEsc($c['profissional_nome']) ?></h2><p>Vencimento: <?= pagueprofData($c['data_vencimento']) ?></p></div><div class="pagamento-valor"><strong><?= pagueprofDinheiro($c['valor']) ?></strong><span class="estado <?= $classe ?>"><?= pagueprofEsc($label) ?></span></div></div>
        <?php if(!empty($c['tipo_servico_id'])): ?><p><?= pagueprofEsc($c['tipo_servico_nome']??'Serviço') ?><?php if(!empty($c['quantidade_atividades'])): ?> · <?= (int)$c['quantidade_atividades'] ?> atividade(s)<?php endif; ?></p><?php endif; ?><?php if($c['status']==='paga'): ?><p class="alerta sucesso">Pagamento confirmado em <?= pagueprofData($c['data_pagamento']) ?>.</p><?php elseif($c['status']==='em_analise'): ?><p class="alerta informacao">Seu comprovante foi enviado e aguarda conferência do profissional.</p><?php else: ?>
          <div class="manual-area"><h3>Enviar comprovante</h3><?php if(!empty($c['instrucoes_pagamento'])): ?><p><?= nl2br(pagueprofEsc($c['instrucoes_pagamento'])) ?></p><?php else: ?><p>Faça o pagamento conforme combinado com o profissional e anexe o comprovante para conferência.</p><?php endif; ?>
            <?php if(!empty($c['pix_payload'])): ?><div class="pix-area"><div class="pix-qr" data-pix-payload="<?= pagueprofEsc($c['pix_payload']) ?>" role="img" aria-label="QR Code Pix estático sem valor"></div><div class="campo-codigo"><p><strong>Pix para <?= pagueprofEsc($c['nome_recebedor']) ?></strong></p><p>Chave Pix (<?= pagueprofEsc(pagueprofPixTipo((string)$c['chave_pix_tipo'])) ?>): <?= pagueprofEsc(Pix::formatarChave((string)$c['chave_pix_tipo'],(string)$c['chave_pix'])) ?></p><label>Pix copia e cola<textarea id="pix-copia-cola-<?= (int)$c['id'] ?>" readonly><?= pagueprofEsc($c['pix_payload']) ?></textarea></label><button class="botao pequeno secundario copiar-pix" type="button" data-copiar-qr="pix-copia-cola-<?= (int)$c['id'] ?>">Copiar Pix copia e cola</button><small>QR estático sem valor. Confira e informe o valor da cobrança no aplicativo do banco. O pagamento ainda será confirmado pelo profissional após conferir o comprovante.</small></div></div><?php endif; ?>
            <form method="post" enctype="multipart/form-data" action="index.php?rota=enviar_comprovante" class="form-inline"><input type="hidden" name="csrf" value="<?= $csrfValor ?>"><input type="hidden" name="cobranca_id" value="<?= (int)$c['id'] ?>"><label>Arquivo (PDF, JPG ou PNG; até 5 MB)<input type="file" name="comprovante" accept="application/pdf,image/jpeg,image/png" required></label><button class="botao secundario" type="submit">Enviar para análise</button></form></div>
        <?php endif; ?></section><?php endforeach; endif; ?>
    <?php elseif($pagina==='comprovantes'): ?>
      <div class="cabecalho-pagina"><div><p class="eyebrow">PROFISSIONAL</p><h1>Comprovantes</h1><p>Confira os anexos e confirme ou recuse o pagamento.</p></div></div>
      <section class="bloco">
        <form method="get" action="index.php" class="filtros" id="filtro-comprovantes">
          <input type="hidden" name="rota" value="comprovantes">
          <label>Status
            <select name="status">
              <option value="em_analise" <?= ($filtroStatus??'em_analise')==='em_analise'?'selected':'' ?>>Em análise</option>
              <option value="aprovado" <?= ($filtroStatus??'')==='aprovado'?'selected':'' ?>>Aprovado</option>
              <option value="rejeitado" <?= ($filtroStatus??'')==='rejeitado'?'selected':'' ?>>Recusado</option>
              <option value="" <?= ($filtroStatus??'em_analise')===''?'selected':'' ?>>Todos</option>
            </select>
          </label>
          <input type="hidden" name="filtrar_data" value="0">
          <label class="filtro-periodo-toggle" for="filtrar-periodo-comprovantes">
            <input type="checkbox" id="filtrar-periodo-comprovantes" name="filtrar_data" value="1" <?= !empty($filtrarData)?'checked':'' ?>>
            <span>Filtrar por data de envio</span>
          </label>
          <label>Enviado de<input type="date" name="de" value="<?= pagueprofEsc($de??date('Y-m-01')) ?>" data-periodo-comprovantes <?= empty($filtrarData)?'disabled':'' ?>></label>
          <label>Enviado até<input type="date" name="ate" value="<?= pagueprofEsc($ate??date('Y-m-t')) ?>" data-periodo-comprovantes <?= empty($filtrarData)?'disabled':'' ?>></label>
          <button class="botao secundario" type="submit">Aplicar filtros</button>
        </form>
      </section>
      <section class="bloco">
        <div class="bloco-cabecalho"><div><h2>Comprovantes encontrados</h2><p><?= count($comprovantes) ?> arquivo(s) neste filtro.</p></div></div>
        <?php if(empty($comprovantes)): ?>
          <p class="vazio">Nenhum comprovante corresponde a este filtro. Escolha outro status ou período.</p>
        <?php else: ?>
          <div class="tabela-scroll">
            <table class="tabela-financeiro tabela-comprovantes">
              <thead><tr><th scope="col">Cliente</th><th scope="col">Vencimento</th><th scope="col">Enviado</th><th scope="col">Valor</th><th scope="col">Situação</th><th scope="col">Arquivo</th><th scope="col">Ações</th></tr></thead>
              <tbody>
                <?php foreach($comprovantes as $cp): $cpStatus=(string)$cp['status'];$cpClasse=$cpStatus==='em_analise'?'azul':($cpStatus==='aprovado'?'verde':($cpStatus==='rejeitado'?'roxo':'neutro'));$cpRotulos=['em_analise'=>'Em análise','aprovado'=>'Aprovado','rejeitado'=>'Recusado'];$cpRotulo=$cpRotulos[$cpStatus]??ucfirst(str_replace('_',' ',$cpStatus));$cpUrl='index.php?rota=baixar_comprovante&id='.(int)$cp['id']; ?>
                  <tr id="comprovante-<?= (int)$cp['id'] ?>">
                    <td data-label="Cliente"><strong><?= pagueprofEsc($cp['cliente_nome']) ?></strong></td>
                    <td data-label="Vencimento"><?= pagueprofData($cp['data_vencimento']) ?></td>
                    <td data-label="Enviado"><?= pagueprofData($cp['enviado_em']) ?></td>
                    <td data-label="Valor" class="coluna-valor"><?= pagueprofDinheiro($cp['valor']) ?></td>
                    <td data-label="Situação"><span class="estado <?= $cpClasse ?>"><?= pagueprofEsc($cpRotulo) ?></span><?php if(!empty($cp['observacao_profissional'])): ?><small class="observacao-comprovante"><?= pagueprofEsc($cp['observacao_profissional']) ?></small><?php endif; ?></td>
                    <td data-label="Arquivo"><a class="botao pequeno secundario abrir-comprovante-popup" href="index.php?rota=abrir_comprovante&amp;id=<?= (int)$cp['id'] ?>" data-comprovante-id="<?= (int)$cp['id'] ?>" data-comprovante-url="<?= pagueprofEsc($cpUrl) ?>" data-tipo-arquivo="<?= pagueprofEsc($cp['tipo_arquivo']) ?>" data-titulo="<?= pagueprofEsc('Comprovante de '.$cp['cliente_nome']) ?>">Ver comprovante</a></td>
                    <td data-label="Ações">
                      <?php if($cpStatus==='em_analise'): ?>
                        <form method="post" action="index.php?rota=avaliar_comprovante" class="acoes-comprovante-form"><input type="hidden" name="csrf" value="<?= $csrfValor ?>"><input type="hidden" name="comprovante_id" value="<?= (int)$cp['id'] ?>"><input class="campo-observacao-comprovante" name="observacao" maxlength="500" placeholder="Observação opcional" aria-label="Observação opcional para este comprovante"><button class="botao pequeno" name="decisao" value="aprovado" type="submit">Confirmar</button><button class="botao pequeno perigo" name="decisao" value="rejeitado" type="submit">Recusar</button></form>
                      <?php else: ?><span class="texto-secundario">Analisado</span><?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
      <dialog class="comprovante-dialog" id="comprovante-dialog" aria-labelledby="comprovante-dialog-titulo" data-abrir-id="<?= (int)($abrirId??0) ?>">
        <div class="comprovante-dialog-conteudo">
          <header class="comprovante-dialog-cabecalho">
            <a class="marca marca-popup" href="index.php?rota=comprovantes"><span class="marca-icone">P</span><span>Pague<strong>Prof</strong></span></a>
            <div class="comprovante-dialog-identificacao"><p class="eyebrow">ÁREA PROFISSIONAL</p><h2 id="comprovante-dialog-titulo">Visualização do comprovante</h2><p id="comprovante-dialog-cliente"></p></div>
            <button type="button" class="botao secundario comprovante-dialog-fechar" aria-label="Fechar comprovante">Fechar</button>
          </header>
          <div class="comprovante-visualizador">
            <img id="comprovante-dialog-imagem" alt="Comprovante enviado pelo cliente" hidden>
            <iframe id="comprovante-dialog-pdf" title="Arquivo PDF do comprovante" hidden></iframe>
            <p id="comprovante-dialog-fallback" hidden>Pré-visualização indisponível. Use “Abrir em nova guia” para visualizar o arquivo.</p>
          </div>
          <footer class="comprovante-dialog-rodape"><a id="comprovante-dialog-nova-guia" class="botao pequeno secundario" href="#" target="_blank" rel="noopener">Abrir em nova guia</a><span>Arquivo privado da sua área PagueProf</span></footer>
        </div>
      </dialog>
    <?php elseif($pagina==='calendario'): ?>
      <div class="cabecalho-pagina"><div><p class="eyebrow">PROFISSIONAL</p><h1>Calendário mensal</h1><p>Consulte os valores das cobranças por cliente e data de vencimento.</p></div></div>
      <section class="bloco"><form method="get" class="filtros"><input type="hidden" name="rota" value="calendario"><label>Cliente<select name="cliente_id"><option value="0" <?= (int)$clienteId===0?'selected':'' ?>>TODOS</option><?php foreach($clientes as $cliente): ?><option value="<?= (int)$cliente['id'] ?>" <?= (int)$clienteId===(int)$cliente['id']?'selected':'' ?>><?= pagueprofEsc($cliente['nome'].((int)($cliente['ativo']??0)===1?'':' (inativo)')) ?></option><?php endforeach; ?></select></label><label>Mês<input type="month" name="mes" value="<?= pagueprofEsc($mesCalendario) ?>" required></label><button class="botao" type="submit">Aplicar filtros</button></form></section>
      <section class="bloco calendario-mensal">
        <div class="calendario-cabecalho"><div><h2><?= pagueprofEsc(date('m/Y',strtotime($mesCalendario.'-01'))) ?></h2><p>Valores agrupados pela data de vencimento. Vencidos e recusados têm cores próprias.</p></div><p class="indicador-clientes-periodo" title="Clientes com cobrança / total de clientes ativos" aria-label="Clientes no período: <?= (int)($resumo['clientes_periodo']??0) ?> de <?= (int)($resumo['clientes_ativos']??0) ?> clientes ativos têm cobrança"><span>Clientes no período:</span><strong><?= (int)($resumo['clientes_periodo']??0) ?>/<?= (int)($resumo['clientes_ativos']??0) ?></strong></p></div>
        <div class="legenda-calendario"><span><i class="marcador-legenda legenda-verde"></i>Pago e confirmado</span><span><i class="marcador-legenda legenda-azul"></i>Em análise, ainda no prazo</span><span><i class="marcador-legenda legenda-preta"></i>Em aberto, sem comprovante</span><span><i class="marcador-legenda legenda-laranja"></i>Vencido, sem confirmação</span><span><i class="marcador-legenda legenda-roxa"></i>Recusado (riscado)</span></div>
        <p class="resumo-contexto">Total geral considera cada cobrança uma vez, pela data de vencimento; os demais valores detalham categorias sem sobreposição.</p>
        <section class="grade-resumo resumo-padroes totalizadores-calendario-padrao" aria-label="Totalizadores do mês"><article class="cartao-resumo destaque-total-geral"><span>Total geral do mês</span><strong><?= pagueprofDinheiro($totalGeralCalendario??0) ?></strong></article><article class="cartao-resumo calendario-total-verde"><span>Pago e confirmado</span><strong><?= pagueprofDinheiro($totaisCalendario['confirmado']??0) ?></strong></article><article class="cartao-resumo calendario-total-azul"><span>Em análise</span><strong><?= pagueprofDinheiro($totaisCalendario['comprovante_enviado']??0) ?></strong></article><article class="cartao-resumo calendario-total-preto"><span>Sem comprovante</span><strong><?= pagueprofDinheiro($totaisCalendario['sem_comprovante']??0) ?></strong></article><article class="cartao-resumo calendario-total-vencido"><span>Vencido</span><strong><?= pagueprofDinheiro($totaisCalendario['vencido']??0) ?></strong></article><article class="cartao-resumo calendario-total-rejeitado"><span>Rejeitado (riscado)</span><strong><?= pagueprofDinheiro($totaisCalendario['rejeitado']??0) ?></strong></article></section>
        <div class="grade-calendario" aria-label="Calendário financeiro mensal">
          <?php foreach(['Seg','Ter','Qua','Qui','Sex','Sáb','Dom'] as $diaSemana): ?><span class="calendario-dia-semana"><?= $diaSemana ?></span><?php endforeach; ?>
          <?php for($vazio=0;$vazio<$diaInicialSemana;$vazio++): ?><span class="calendario-celula-vazia" aria-hidden="true"></span><?php endfor; ?>
          <?php for($dia=1;$dia<=$diasNoMes;$dia++): $valoresDia=(array)($calendarioMensal[$dia]??[]); ?>
            <article class="calendario-dia"><span class="calendario-numero"><?= $dia ?></span><div class="calendario-valores">
              <?php if((float)($valoresDia['confirmado']??0)>0): ?><span class="valor-dia valor-dia-verde" title="Pagamento confirmado"><?= number_format((float)$valoresDia['confirmado'],2,',','.') ?></span><?php endif; ?>
              <?php if((float)($valoresDia['comprovante_enviado']??0)>0): ?><span class="valor-dia valor-dia-azul" title="Comprovante em análise, ainda no prazo"><?= number_format((float)$valoresDia['comprovante_enviado'],2,',','.') ?></span><?php endif; ?>
              <?php if((float)($valoresDia['sem_comprovante']??0)>0): ?><span class="valor-dia valor-dia-preto" title="Cobrança em aberto, sem comprovante e ainda no prazo"><?= number_format((float)$valoresDia['sem_comprovante'],2,',','.') ?></span><?php endif; ?>
              <?php if((float)($valoresDia['vencido']??0)>0): ?><span class="valor-dia valor-dia-vencido" title="Cobrança vencida, ainda sem confirmação"><?= number_format((float)$valoresDia['vencido'],2,',','.') ?></span><?php endif; ?>
              <?php if((float)($valoresDia['rejeitado']??0)>0): ?><span class="valor-dia valor-dia-rejeitado" title="Comprovante recusado"><?= number_format((float)$valoresDia['rejeitado'],2,',','.') ?></span><?php endif; ?>
            </div></article>
          <?php endfor; ?>
          <?php for($vazio=$diaInicialSemana+$diasNoMes;$vazio<$celulasCalendario;$vazio++): ?><span class="calendario-celula-vazia" aria-hidden="true"></span><?php endfor; ?>
        </div>
      </section>
    <?php elseif($pagina==='relatorios'): ?>
      <div class="cabecalho-pagina"><div><p class="eyebrow">PROFISSIONAL</p><h1>Relatórios</h1><p>Resumo financeiro, indicadores, gráficos e movimentações do período selecionado.</p></div></div>
      <section class="bloco"><form method="get" class="filtros"><input type="hidden" name="rota" value="relatorios"><label>Cliente<select name="cliente_id"><option value="0" <?= (int)$clienteId===0?'selected':'' ?>>TODOS</option><?php foreach($clientes as $cliente): ?><option value="<?= (int)$cliente['id'] ?>" <?= (int)$clienteId===(int)$cliente['id']?'selected':'' ?>><?= pagueprofEsc($cliente['nome'].((int)($cliente['ativo']??0)===1?'':' (inativo)')) ?></option><?php endforeach; ?></select></label><label>De<input type="date" name="de" value="<?= pagueprofEsc($de) ?>" required></label><label>Até<input type="date" name="ate" value="<?= pagueprofEsc($ate) ?>" required></label><button class="botao" type="submit">Aplicar filtros</button></form></section>
      <p class="resumo-contexto">Total geral = Recebido + Em aberto, sem contar uma cobrança duas vezes. Recebido usa a data do pagamento; Em aberto usa o vencimento e inclui recusados. Vencido inclui pendentes/em análise após o vencimento; Rejeitado é separado.</p>
      <section class="grade-resumo resumo-padroes" aria-label="Totalizadores financeiros"><article class="cartao-resumo destaque-total-geral"><span>Total geral</span><strong><?= pagueprofDinheiro($resumo['total_geral']??0) ?></strong></article><article class="cartao-resumo"><span>Recebido</span><strong><?= pagueprofDinheiro($resumo['recebido']??0) ?></strong></article><article class="cartao-resumo"><span>Em aberto</span><strong><?= pagueprofDinheiro($resumo['aberto']??0) ?></strong></article><article class="cartao-resumo alerta-resumo"><span>Vencido</span><strong><?= pagueprofDinheiro($resumo['vencido']??0) ?></strong></article><article class="cartao-resumo resumo-rejeitado"><span>Rejeitado</span><strong><?= pagueprofDinheiro($resumo['rejeitado']??0) ?></strong></article></section><p class="indicador-clientes-periodo" title="Clientes com cobrança / total de clientes ativos" aria-label="Clientes no período: <?= (int)($resumo['clientes_periodo']??0) ?> de <?= (int)($resumo['clientes_ativos']??0) ?> clientes ativos têm cobrança"><span>Clientes no período:</span><strong><?= (int)($resumo['clientes_periodo']??0) ?>/<?= (int)($resumo['clientes_ativos']??0) ?></strong></p>
      <?php $dadosGrafico=(array)($graficoMensal??[]); $maxGraf=1.0; foreach($dadosGrafico as $gm){$maxGraf=max($maxGraf,(float)($gm['recebido']??0),(float)($gm['pendente']??0));} $totalProporcao=(float)($resumo['recebido']??0)+(float)($resumo['aberto']??0); $pctRecebido=$totalProporcao>0?100*(float)($resumo['recebido']??0)/$totalProporcao:0; $passoGraf=count($dadosGrafico)>0?min(70,550/count($dadosGrafico)):70; $larguraBarra=min(22,max(8,($passoGraf-4)/2)); ?><section class="grade-graficos"><article class="bloco grafico"><h2>Recebidos vs pendentes por mês</h2><?php if(empty($dadosGrafico)): ?><p class="vazio">Ainda não há movimentações no período para desenhar o gráfico.</p><?php else: ?><div class="grafico-scroll"><svg viewBox="0 0 640 240" role="img" aria-label="Gráfico mensal de valores recebidos e pendentes"><line x1="40" y1="205" x2="625" y2="205" stroke="#aebdce"/><?php foreach($dadosGrafico as $i=>$gm): $x=50+$i*$passoGraf; $rh=150*(float)($gm['recebido']??0)/$maxGraf; $ph=150*(float)($gm['pendente']??0)/$maxGraf; ?><rect x="<?= $x ?>" y="<?= 200-$rh ?>" width="<?= $larguraBarra ?>" height="<?= max(1,$rh) ?>" fill="#2673c9"><title>Recebido <?= pagueprofEsc($gm['recebido']??0) ?></title></rect><rect x="<?= $x+$larguraBarra+3 ?>" y="<?= 200-$ph ?>" width="<?= $larguraBarra ?>" height="<?= max(1,$ph) ?>" fill="#e6a629"><title>Pendente <?= pagueprofEsc($gm['pendente']??0) ?></title></rect><text x="<?= $x ?>" y="220"><?= pagueprofEsc($gm['mes']??$gm['label']??'') ?></text><?php endforeach; ?></svg></div><p>Azul: recebido · Amarelo: pendente</p><?php endif; ?></article><article class="bloco grafico"><h2>Proporção recebida e em aberto</h2><div class="barra-proporcao" role="img" aria-label="<?= number_format($pctRecebido,1,',','.') ?>% recebido"><span style="width:<?= max(0,min(100,$pctRecebido)) ?>%"></span></div><p>Recebido <?= number_format($pctRecebido,1,',','.') ?>% · Em aberto <?= number_format(100-$pctRecebido,1,',','.') ?>%</p></article></section>
      <section class="bloco bloco-movimentacoes"><h2>Movimentações do período</h2><?php if(empty($cobrancas)): ?><p class="vazio">Nenhuma cobrança para o período selecionado.</p><?php else: ?><div class="tabela-scroll movimentacoes-scroll"><table class="tabela-financeiro tabela-movimentacoes"><thead><tr><th scope="col">Cliente</th><th scope="col">Vencimento</th><th scope="col">Pago em</th><th scope="col">Valor</th><th scope="col">Situação</th></tr></thead><tbody><?php foreach($cobrancas as $c): [$label,$classe]=pagueprofStatus($c); ?><tr><td data-label="Cliente"><strong><?= pagueprofEsc($c['cliente_nome']) ?></strong></td><td data-label="Vencimento"><?= pagueprofData($c['data_vencimento']) ?></td><td data-label="Pago em"><?= pagueprofData($c['data_pagamento']??null) ?></td><td data-label="Valor" class="coluna-valor"><?= pagueprofDinheiro($c['valor']) ?></td><td data-label="Situação"><span class="estado <?= $classe ?>"><?= pagueprofEsc($label) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
    <?php elseif($pagina==='configuracoes'): ?>
      <div class="cabecalho-pagina"><div><p class="eyebrow">PROFISSIONAL</p><h1>Recebimentos</h1><p>Escolha como seus clientes poderão pagar.</p></div></div>
      <section class="bloco"><h2>Instruções e chave Pix</h2><form method="post" action="index.php?rota=configuracoes" class="form-grid" id="form-recebimentos"><input type="hidden" name="csrf" value="<?= $csrfValor ?>">
        <label class="campo-largo">Orientações para o cliente<textarea name="instrucoes_pagamento" rows="4" maxlength="1000" placeholder="Informe como o cliente deve pagar e enviar o comprovante."><?= pagueprofEsc($configuracao['instrucoes_pagamento']??'') ?></textarea></label>
        <label>Tipo da chave Pix<select name="chave_pix_tipo" id="tipo-chave-pix"><option value="" <?= empty($configuracao['chave_pix_tipo'])?'selected':'' ?>>Não cadastrar chave Pix</option><option value="cpf" <?= ($configuracao['chave_pix_tipo']??'')==='cpf'?'selected':'' ?>>CPF</option><option value="telefone" <?= ($configuracao['chave_pix_tipo']??'')==='telefone'?'selected':'' ?>>Telefone celular</option><option value="email" <?= ($configuracao['chave_pix_tipo']??'')==='email'?'selected':'' ?>>E-mail</option></select><small>O telefone é salvo no padrão +55; o e-mail é mantido sem máscara.</small></label>
        <label>Chave Pix<input name="chave_pix" id="chave-pix" type="text" autocomplete="off" maxlength="77" value="<?= pagueprofEsc($chavePixExibicao) ?>" placeholder="CPF, celular ou e-mail"><small id="ajuda-chave-pix">Selecione CPF, telefone ou e-mail. CPF e telefone recebem máscara; o e-mail é validado sem alteração.</small></label>
        <label>Nome do profissional/titular<input name="nome_recebedor" id="nome-recebedor" maxlength="25" value="<?= pagueprofEsc($configuracao['nome_recebedor']??$nomeProfissional??'') ?>" placeholder="Nome como aparece no banco"><small>Será usado no QR Pix; máximo de 25 caracteres após ajuste para o padrão BR Code.</small></label>
        <label>Cidade do recebedor<input name="cidade_recebedor" id="cidade-recebedor" maxlength="15" value="<?= pagueprofEsc($configuracao['cidade_recebedor']??'') ?>" placeholder="Ex.: São Paulo"><small>Máximo de 15 caracteres no QR.</small></label>
        <div class="form-acoes campo-largo"><button class="botao" type="submit">Salvar recebimentos</button></div></form>
        <?php if(!empty($pixPayload)): ?><div class="pix-area"><div class="pix-qr" data-pix-payload="<?= pagueprofEsc($pixPayload) ?>" role="img" aria-label="QR Code Pix estático sem valor"></div><div class="campo-codigo"><h3>Seu QR Pix</h3><p>Recebedor: <?= pagueprofEsc($configuracao['nome_recebedor']) ?> · Chave <?= pagueprofEsc(pagueprofPixTipo((string)$configuracao['chave_pix_tipo'])) ?>: <?= pagueprofEsc($chavePixExibicao) ?></p><label>Pix copia e cola<textarea id="pix-copia-cola-config" readonly><?= pagueprofEsc($pixPayload) ?></textarea></label><button class="botao pequeno secundario copiar-pix" type="button" data-copiar-qr="pix-copia-cola-config">Copiar Pix copia e cola</button></div></div><?php endif; ?>
        <p>A chave Pix é opcional. O QR é estático e não contém valor; o cliente informa o valor no banco e envia o comprovante. A confirmação permanece manual, sem consulta automática ao banco.</p></section>
    <?php endif; ?>
  </main>
</div>
<script src="assets/vendor/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const menuButton = document.getElementById('menu-hamburguer');
  const menu = document.getElementById('menu-app');
  if (menuButton && menu) {
    document.body.classList.add('menu-js');
    const setMenuOpen = function (aberto) {
      menu.classList.toggle('aberto', aberto);
      menuButton.setAttribute('aria-expanded', aberto ? 'true' : 'false');
      menuButton.setAttribute('aria-label', aberto ? 'Fechar menu de navegação' : 'Abrir menu de navegação');
    };
    menuButton.addEventListener('click', function () { setMenuOpen(!menu.classList.contains('aberto')); });
    menu.addEventListener('click', function (event) { if (event.target.closest('a')) setMenuOpen(false); });
    document.addEventListener('click', function (event) {
      if (menu.classList.contains('aberto') && !menu.contains(event.target) && !menuButton.contains(event.target)) setMenuOpen(false);
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && menu.classList.contains('aberto')) { setMenuOpen(false); menuButton.focus(); }
    });
  }
  const quantidade = document.getElementById('quantidade-cobrancas');
  const previsao = document.getElementById('previsao-cobrancas');
  const formulario = document.getElementById('form-cobrancas');
  const buscaClientes = document.getElementById('buscar-clientes-cobranca');
  const listaClientes = document.getElementById('lista-clientes-cobranca');
  const clienteOptions = Array.from(document.querySelectorAll('.opcao-cliente-cobranca'));
  const clienteCheckboxes = Array.from(document.querySelectorAll('input[name="cliente_ids[]"]'));
  const resumoClientes = document.getElementById('resumo-clientes-selecionados');
  const mensagemListaVazia = document.getElementById('lista-clientes-vazia');
  const marcarVisiveis = document.getElementById('marcar-clientes-visiveis');
  const limparSelecao = document.getElementById('limpar-selecao-clientes');
  const normalizarBusca = function (texto) { return (texto || '').toLocaleLowerCase('pt-BR').normalize('NFD').replace(/[\u0300-\u036f]/g, ''); };
  const atualizarPrevisao = function () {
    if (!quantidade || !previsao) return;
    const n = Math.max(1, Math.min(36, parseInt(quantidade.value || '1', 10)));
    const clientesSelecionados = clienteCheckboxes.filter(function (campo) { return campo.checked; }).length;
    if (!clientesSelecionados) { previsao.textContent = 'Selecione um ou mais clientes para calcular o total de cobranças.'; return; }
    const total = clientesSelecionados * n;
    previsao.textContent = clientesSelecionados + (clientesSelecionados === 1 ? ' cliente selecionado × ' : ' clientes selecionados × ') + n + (n === 1 ? ' cobrança por cliente = ' : ' cobranças por cliente = ') + total + (total === 1 ? ' cobrança no total.' : ' cobranças no total.') + ' Os vencimentos seguintes serão mensais.';
  };
  const atualizarSelecaoClientes = function () {
    const selecionados = clienteCheckboxes.filter(function (campo) { return campo.checked; }).length;
    if (resumoClientes) resumoClientes.textContent = selecionados === 0 ? 'Nenhum cliente selecionado' : selecionados === 1 ? '1 cliente selecionado' : selecionados + ' clientes selecionados';
    if (buscaClientes) buscaClientes.setCustomValidity(selecionados ? '' : 'Marque pelo menos um cliente antes de gerar as cobranças.');
    clienteCheckboxes.forEach(function (campo) { const opcao = campo.closest('.opcao-cliente-cobranca'); if (opcao) opcao.classList.toggle('is-selecionado', campo.checked); });
    atualizarPrevisao();
  };
  if (buscaClientes && listaClientes) {
    buscaClientes.addEventListener('input', function () {
      const termo = normalizarBusca(buscaClientes.value.trim());let encontrados=0;
      clienteOptions.forEach(function (opcao) { const corresponde=normalizarBusca(opcao.dataset.busca || '').includes(termo);opcao.hidden=!corresponde;if(corresponde)encontrados++; });
      if(mensagemListaVazia)mensagemListaVazia.hidden=encontrados>0;
      if(marcarVisiveis)marcarVisiveis.disabled=encontrados===0;
    });
  }
  clienteCheckboxes.forEach(function (campo) { campo.addEventListener('change', atualizarSelecaoClientes); });
  if(marcarVisiveis)marcarVisiveis.addEventListener('click',function(){clienteOptions.forEach(function(opcao){if(!opcao.hidden){const campo=opcao.querySelector('input[type="checkbox"]');if(campo&&!campo.checked){campo.checked=true;campo.dispatchEvent(new Event('change',{bubbles:true}));}}});});
  if(limparSelecao)limparSelecao.addEventListener('click',function(){clienteCheckboxes.forEach(function(campo){if(campo.checked){campo.checked=false;campo.dispatchEvent(new Event('change',{bubbles:true}));}});});
  if (quantidade) quantidade.addEventListener('input', atualizarPrevisao);
  atualizarSelecaoClientes();
  if (formulario) formulario.addEventListener('submit', function () {
    const botao = formulario.querySelector('button[type="submit"]');
    if (botao && !botao.disabled) { botao.disabled = true; botao.textContent = 'Gerando...'; }
  });

  document.querySelectorAll('[data-pix-payload]').forEach(function (elemento) {
    if (typeof QRCode === 'undefined') return;
    try { new QRCode(elemento, { text: elemento.dataset.pixPayload, width: 196, height: 196, correctLevel: QRCode.CorrectLevel.M }); }
    catch (erro) { elemento.textContent = 'Não foi possível exibir o QR. Use o Pix copia e cola.'; }
  });

  document.querySelectorAll('.copiar-pix').forEach(function (botao) {
    botao.addEventListener('click', async function () {
      const campo = document.getElementById(botao.dataset.copiarQr || '');
      if (!campo) return;
      try {
        if (navigator.clipboard && window.isSecureContext) await navigator.clipboard.writeText(campo.value);
        else { campo.focus(); campo.select(); document.execCommand('copy'); }
        botao.textContent = 'Copiado';
        window.setTimeout(function () { botao.textContent = 'Copiar Pix copia e cola'; }, 1800);
      } catch (erro) { campo.focus(); campo.select(); }
    });
  });

  const tipoPix = document.getElementById('tipo-chave-pix');
  const chavePix = document.getElementById('chave-pix');
  const nomeRecebedor = document.getElementById('nome-recebedor');
  const cidadeRecebedor = document.getElementById('cidade-recebedor');
  const atualizarMascaraPix = function () {
    if (!tipoPix || !chavePix) return;
    let digitos = chavePix.value.replace(/\D/g, '');
    if (tipoPix.value === 'cpf') {
      digitos = digitos.slice(0, 11);
      digitos = digitos.replace(/^(\d{3})(\d)/, '$1.$2').replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3').replace(/\.(\d{3})(\d)/, '.$1-$2');
      chavePix.type = 'text';
      chavePix.inputMode = 'numeric';
      chavePix.maxLength = 25;
      chavePix.autocomplete = 'off';
      chavePix.placeholder = '000.000.000-00';
      chavePix.value = digitos;
    } else if (tipoPix.value === 'telefone') {
      if ((digitos.length === 12 || digitos.length === 13) && digitos.startsWith('55')) digitos = digitos.slice(2);
      digitos = digitos.slice(0, 11);
      if (digitos.length > 2) digitos = '(' + digitos.slice(0, 2) + ') ' + digitos.slice(2);
      if (digitos.replace(/\D/g, '').length > 7) digitos = digitos.replace(/(\d{5})(\d{1,4})$/, '$1-$2');
      chavePix.type = 'text';
      chavePix.inputMode = 'tel';
      chavePix.maxLength = 25;
      chavePix.autocomplete = 'off';
      chavePix.placeholder = '(11) 99999-9999';
      chavePix.value = digitos;
    } else if (tipoPix.value === 'email') {
      chavePix.type = 'email';
      chavePix.inputMode = 'email';
      chavePix.maxLength = 77;
      chavePix.autocomplete = 'email';
      chavePix.placeholder = 'nome@exemplo.com';
    } else {
      chavePix.type = 'text';
      chavePix.inputMode = 'text';
      chavePix.maxLength = 77;
      chavePix.autocomplete = 'off';
      chavePix.placeholder = 'CPF, celular ou e-mail';
    }
    const exigePix = tipoPix.value !== '';
    chavePix.required = exigePix;
    if (nomeRecebedor) nomeRecebedor.required = exigePix;
    if (cidadeRecebedor) cidadeRecebedor.required = exigePix;
  };
  if (tipoPix && chavePix) {
    tipoPix.addEventListener('change', function () { chavePix.value = ''; atualizarMascaraPix(); });
    chavePix.addEventListener('input', atualizarMascaraPix);
    atualizarMascaraPix();
  }

  const abrirNovoServico = document.getElementById('abrir-novo-servico');
  const painelNovoServico = document.getElementById('novo-servico-inline');
  const nomeNovoServico = document.getElementById('nome-novo-servico');
  const salvarNovoServico = document.getElementById('salvar-novo-servico');
  const cancelarNovoServico = document.getElementById('cancelar-novo-servico');
  const estadoNovoServico = document.getElementById('estado-novo-servico');
  const seletorServico = document.getElementById('tipo-servico-cobranca');
  if (abrirNovoServico && painelNovoServico) abrirNovoServico.addEventListener('click', function () { painelNovoServico.hidden = false; nomeNovoServico.focus(); });
  if (cancelarNovoServico && painelNovoServico) cancelarNovoServico.addEventListener('click', function () { painelNovoServico.hidden = true; if (estadoNovoServico) estadoNovoServico.textContent = ''; });
  if (salvarNovoServico) salvarNovoServico.addEventListener('click', async function () {
    const nome = (nomeNovoServico.value || '').trim();
    if (nome.length < 2) { estadoNovoServico.textContent = 'Informe pelo menos 2 caracteres.'; nomeNovoServico.focus(); return; }
    salvarNovoServico.disabled = true;
    estadoNovoServico.textContent = 'Salvando serviço...';
    const dados = new FormData();
    dados.append('csrf', formulario.querySelector('input[name="csrf"]').value);
    dados.append('nome', nome);
    try {
      const resposta = await fetch('index.php?rota=adicionar_tipo_servico', { method: 'POST', body: dados, credentials: 'same-origin' });
      const resultado = await resposta.json();
      if (!resposta.ok) throw new Error(resultado.erro || 'Não foi possível salvar o serviço.');
      const opcao = document.createElement('option');
      opcao.value = String(resultado.id); opcao.textContent = resultado.nome;
      seletorServico.appendChild(opcao); seletorServico.value = opcao.value;
      estadoNovoServico.textContent = 'Serviço adicionado e selecionado.';
      nomeNovoServico.value = '';
      window.setTimeout(function () { painelNovoServico.hidden = true; estadoNovoServico.textContent = ''; }, 1400);
    } catch (erro) { estadoNovoServico.textContent = erro.message || 'Não foi possível salvar o serviço.'; }
    finally { salvarNovoServico.disabled = false; }
  });
});
</script>
<div class="consulta-overlay" id="consulta-overlay" role="status" aria-live="polite" aria-atomic="true" aria-hidden="true" hidden>
  <div class="consulta-card">
    <div class="consulta-orbita" aria-hidden="true"><span></span></div>
    <p class="eyebrow">PAGUEPROF · CONSULTA EM ANDAMENTO</p>
    <h2>Aguarde… organizando seus números</h2>
    <p>Estamos consultando os dados e atualizando os resultados dos filtros.</p>
    <div class="consulta-progresso" aria-hidden="true"><span></span></div>
    <small>Seu resumo já vem aí.</small>
  </div>
</div>
<script>
(function () {
  const toggle = document.getElementById('filtrar-periodo-comprovantes');
  const dateFields = document.querySelectorAll('[data-periodo-comprovantes]');
  if (toggle) {
    const syncDateFields = function () { dateFields.forEach(function (field) { field.disabled = !toggle.checked; field.required = toggle.checked; }); };
    toggle.addEventListener('change', syncDateFields);
    syncDateFields();
  }
  const pagamentoToggle = document.getElementById('filtrar-vencimento-cliente');
  const vencimentoFields = document.querySelectorAll('[data-vencimento-cliente]');
  if (pagamentoToggle) {
    const syncVencimentoFields = function () { vencimentoFields.forEach(function (field) { field.disabled = !pagamentoToggle.checked; field.required = pagamentoToggle.checked; }); };
    pagamentoToggle.addEventListener('change', syncVencimentoFields);
    syncVencimentoFields();
  }
  const dialog = document.getElementById('comprovante-dialog');
  if (!dialog) return;
  const image = document.getElementById('comprovante-dialog-imagem');
  const pdf = document.getElementById('comprovante-dialog-pdf');
  const fallback = document.getElementById('comprovante-dialog-fallback');
  const title = document.getElementById('comprovante-dialog-titulo');
  const client = document.getElementById('comprovante-dialog-cliente');
  const openInTab = document.getElementById('comprovante-dialog-nova-guia');
  const closeButton = dialog.querySelector('.comprovante-dialog-fechar');
  function openPopup(link) {
    const url = link.dataset.comprovanteUrl;
    const mime = link.dataset.tipoArquivo || '';
    const label = link.dataset.titulo || 'Visualização do comprovante';
    image.hidden = true; pdf.hidden = true; fallback.hidden = true;
    image.removeAttribute('src'); pdf.removeAttribute('src');
    title.textContent = label;
    client.textContent = link.closest('tr')?.querySelector('[data-label="Cliente"]')?.innerText || '';
    openInTab.href = url;
    if (mime.indexOf('image/') === 0) { image.src = url; image.hidden = false; }
    else if (mime === 'application/pdf') { pdf.src = url; pdf.hidden = false; }
    else { fallback.hidden = false; }
    if (typeof dialog.showModal === 'function') dialog.showModal();
    else dialog.setAttribute('open', 'open');
  }
  document.querySelectorAll('.abrir-comprovante-popup').forEach(function (link) {
    link.addEventListener('click', function (event) { event.preventDefault(); openPopup(link); });
  });
  closeButton.addEventListener('click', function () { dialog.close(); });
  dialog.addEventListener('click', function (event) { if (event.target === dialog) dialog.close(); });
  dialog.addEventListener('close', function () { image.removeAttribute('src'); pdf.removeAttribute('src'); });
  const abrirId = parseInt(dialog.dataset.abrirId || '0', 10);
  if (abrirId > 0) {
    const link = dialog.ownerDocument.querySelector('.abrir-comprovante-popup[data-comprovante-id="' + abrirId + '"]');
    if (link) openPopup(link);
  }
})();
</script>
<script>
(function () {
  const overlay = document.getElementById('consulta-overlay');
  if (!overlay) return;
  document.querySelectorAll('form.filtros').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (form.dataset.consultaEnviada === '1') return;
      event.preventDefault();
      if (!form.reportValidity()) return;
      form.dataset.consultaEnviada = '1';
      overlay.hidden = false;
      overlay.setAttribute('aria-hidden', 'false');
      document.body.classList.add('consulta-carregando');
      form.setAttribute('aria-busy', 'true');
      const botao = event.submitter || form.querySelector('button[type="submit"]');
      if (botao) {
        botao.disabled = true;
        botao.textContent = 'Consultando…';
      }
      window.setTimeout(function () {
        HTMLFormElement.prototype.submit.call(form);
      }, 100);
    });
  });
})();
</script>
<?php if(!empty($avisoPerfil)): ?><script>window.addEventListener('load',function(){window.alert(<?= json_encode($avisoPerfil,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>);});</script><?php endif; ?>
</body>
</html>
