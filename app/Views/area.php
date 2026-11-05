<?php
function pagueprofEsc($v): string { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function pagueprofDinheiro($v): string { return 'R$ '.number_format((float)$v,2,',','.'); }
function pagueprofData($v): string { return $v ? date('d/m/Y',strtotime((string)$v)) : '—'; }
function pagueprofStatus(array $c): array {
    $s=(string)($c['status']??'pendente');
    if(in_array($s,['pendente','recusada'],true) && !empty($c['data_vencimento']) && $c['data_vencimento']<date('Y-m-d'))return ['Vencida','vermelho'];
    $rotulos=['pendente'=>'A pagar','paga'=>'Paga','em_analise'=>'Em análise','recusada'=>'Comprovante recusado','cancelada'=>'Cancelada'];
    $classes=['pendente'=>'amarelo','paga'=>'verde','em_analise'=>'azul','recusada'=>'vermelho','cancelada'=>'neutro'];
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
  <title>PagueProf | <?= pagueprofEsc($pagina==='planos'?'Cobranças':ucfirst(str_replace('_',' ',$pagina))) ?></title>
  <link rel="stylesheet" href="assets/app.css">
</head>
<body class="app-body">
<header class="barra-app">
  <a class="marca" href="index.php?rota=<?= $temProfissional?'relatorios':'minhas_cobrancas' ?>"><span class="marca-icone">P</span><span>Pague<strong>Prof</strong></span></a>
  <div class="topo-direita"><span class="nome-sessao"><?= pagueprofEsc($nomeSessao) ?><?= $rotuloPerfis?' ('.pagueprofEsc($rotuloPerfis).')':'' ?></span><a class="sair" href="index.php?rota=sair">Sair</a></div>
</header>
<div class="app-shell">
  <nav class="menu-app" aria-label="Menu principal">
    <?php if($temProfissional): ?>
      <a class="<?= $pagina==='clientes'?'ativo':'' ?>" href="index.php?rota=clientes">Clientes</a>
      <a class="<?= $pagina==='planos'?'ativo':'' ?>" href="index.php?rota=nova_cobranca">Cobranças</a>
      <a class="<?= $pagina==='cobrancas'?'ativo':'' ?>" href="index.php?rota=cobrancas">Financeiro</a>
      <a class="<?= $pagina==='comprovantes'?'ativo':'' ?>" href="index.php?rota=comprovantes">Comprovantes</a>
      <a class="<?= $pagina==='relatorios'?'ativo':'' ?>" href="index.php?rota=relatorios">Relatórios</a>
      <a class="<?= $pagina==='calendario'?'ativo':'' ?>" href="index.php?rota=calendario">Calendário mensal</a>
      <a class="<?= $pagina==='configuracoes'?'ativo':'' ?>" href="index.php?rota=configuracoes">Recebimentos</a>
    <?php endif; ?>
    <?php if($temCliente): ?><a class="<?= $pagina==='minhas_cobrancas'?'ativo':'' ?>" href="index.php?rota=minhas_cobrancas">Meus pagamentos</a><?php endif; ?>
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
      <div class="cabecalho-pagina"><div><p class="eyebrow">PROFISSIONAL</p><h1>Cobranças</h1><p>Gere cobranças diretamente para um cliente, sem cadastrar um plano.</p></div><a class="botao secundario" href="index.php?rota=cobrancas">Abrir financeiro</a></div>
      <?php if(empty($temInstrucoesPagamento)): ?><div class="alerta erro">Antes de gerar cobranças, cadastre as instruções de pagamento. <a class="link-acao" href="index.php?rota=configuracoes">Cadastrar instruções</a></div><?php endif; ?>
      <?php if(empty($clientes)): ?>
        <section class="bloco"><p class="vazio">Cadastre um cliente antes de gerar uma cobrança. <a class="link-acao" href="index.php?rota=clientes">Cadastrar cliente</a></p></section>
      <?php else: ?>
        <section class="bloco"><h2>Nova geração</h2><form method="post" action="index.php?rota=gerar_cobrancas" class="form-grid" id="form-cobrancas">
          <input type="hidden" name="csrf" value="<?= $csrfValor ?>"><input type="hidden" name="token_cobranca_direta" value="<?= pagueprofEsc($tokenCobrancaDireta) ?>">
          <label>Cliente<select name="cliente_id" required><option value="">Selecione um cliente</option><?php foreach($clientes as $cliente): ?><option value="<?= (int)$cliente['id'] ?>"><?= pagueprofEsc($cliente['nome']) ?></option><?php endforeach; ?></select></label>
          <div class="campo-servico-wrap"><div class="campo-servico-controle"><label for="tipo-servico-cobranca">Tipo de serviço<select id="tipo-servico-cobranca" name="tipo_servico_id" required><option value="">Selecione ou adicione um serviço</option><?php foreach($tipos as $tipo): ?><option value="<?= (int)$tipo['id'] ?>"><?= pagueprofEsc($tipo['nome']) ?></option><?php endforeach; ?></select></label><button class="botao pequeno secundario" id="abrir-novo-servico" type="button">+ Novo serviço</button></div>
            <div class="novo-servico-inline" id="novo-servico-inline" hidden><label for="nome-novo-servico">Nome do serviço<input id="nome-novo-servico" maxlength="100" placeholder="Ex.: Aula de piano, manutenção"></label><div class="form-acoes"><button class="botao pequeno" id="salvar-novo-servico" type="button">Adicionar serviço</button><button class="botao pequeno secundario" id="cancelar-novo-servico" type="button">Cancelar</button></div><p id="estado-novo-servico" class="texto-secundario" aria-live="polite"></p></div>
          </div>
          <label>Valor do serviço por cobrança (R$)<input name="valor_servico" type="number" min="0.01" max="99999999.99" step="0.01" inputmode="decimal" placeholder="0,00" required></label>
          <div class="form-inline geracao-cobrancas">
            <label>Quantidade de cobranças<input id="quantidade-cobrancas" name="quantidade" type="number" min="1" max="36" value="1" required><small>De 1 a 36 cobranças, geradas mensalmente.</small></label>
            <label>Primeiro vencimento<input name="data_vencimento" type="date" value="<?= pagueprofEsc(date('Y-m-d',strtotime('+1 month'))) ?>" required></label>
            <button class="botao" type="submit" <?= empty($temInstrucoesPagamento)?'disabled':'' ?>>Gerar cobranças</button>
          </div>
          <p class="texto-secundario previsao-cobrancas" id="previsao-cobrancas" aria-live="polite">Será gerada 1 cobrança. Os vencimentos seguintes serão mensais.</p>
        </form></section>
        <section class="bloco"><p class="texto-secundario">O valor e o serviço selecionados serão aplicados a cada cobrança. A primeira notificação por e-mail é tentada após a geração; as demais ficam disponíveis para envio individual no Financeiro.</p></section>
      <?php endif; ?>
    <?php elseif($pagina==='cobrancas'): ?>
      <div class="cabecalho-pagina"><div><p class="eyebrow">PROFISSIONAL</p><h1>Financeiro</h1><p>Consulte débitos, vencimentos e pagamentos recebidos.</p></div></div>
      <section class="bloco"><form method="get" class="filtros"><input type="hidden" name="rota" value="cobrancas">
        <label>Cliente<select name="cliente_id"><option value="0">Todos</option><?php foreach($clientes as $cliente): ?><option value="<?= (int)$cliente['id'] ?>" <?= (int)$clienteId===(int)$cliente['id']?'selected':'' ?>><?= pagueprofEsc($cliente['nome'].((int)($cliente['ativo']??0)===1?'':' (inativo)')) ?></option><?php endforeach; ?></select></label>
        <label>De<input type="date" name="de" value="<?= pagueprofEsc($de) ?>"></label>
        <label>Até<input type="date" name="ate" value="<?= pagueprofEsc($ate) ?>"></label>
        <label>Situação<select name="status"><option value="">Todas</option><?php foreach(['pendente'=>'A pagar','paga'=>'Paga','em_analise'=>'Em análise','recusada'=>'Recusada','cancelada'=>'Cancelada'] as $v=>$t): ?><option value="<?= $v ?>" <?= $status===$v?'selected':'' ?>><?= $t ?></option><?php endforeach; ?></select></label>
        <label>Organizar por<select name="ordenar"><?php foreach(['vencimento_desc'=>'Vencimento: mais recente','vencimento_asc'=>'Vencimento: mais antigo','cliente_asc'=>'Cliente: A a Z','cliente_desc'=>'Cliente: Z a A','valor_desc'=>'Valor: maior primeiro','valor_asc'=>'Valor: menor primeiro','status'=>'Situação'] as $v=>$t): ?><option value="<?= $v ?>" <?= ($ordenar??'vencimento_desc')===$v?'selected':'' ?>><?= $t ?></option><?php endforeach; ?></select></label>
        <button class="botao secundario" type="submit">Aplicar</button>
      </form></section>
      <section class="bloco"><div class="bloco-cabecalho"><div><h2>Cobranças</h2><p><?= count($cobrancas) ?> registro(s) neste resultado.</p></div><a class="botao pequeno" href="index.php?rota=nova_cobranca">Nova cobrança</a></div>
        <?php if(empty($cobrancas)): ?><p class="vazio">Nenhuma cobrança neste filtro. Use “Cobranças” para gerar uma nova.</p><?php else: ?>
          <div class="tabela-scroll"><table class="tabela-financeiro"><thead><tr><th scope="col">Cliente</th><th scope="col">Serviço</th><th scope="col">Vencimento</th><th scope="col">Valor</th><th scope="col">Situação</th><th scope="col">E-mail</th><th scope="col">Ações</th></tr></thead><tbody>
          <?php foreach($cobrancas as $c): [$label,$classe]=pagueprofStatus($c); $statusEmail=(string)($c['notificacao_status']??''); ?>
            <tr>
              <td><strong><?= pagueprofEsc($c['cliente_nome']) ?></strong></td>
              <td><?= pagueprofEsc($c['tipo_servico_nome']??'Serviço') ?></td>
              <td><?= pagueprofData($c['data_vencimento']) ?></td>
              <td class="coluna-valor"><?= pagueprofDinheiro($c['valor']) ?></td>
              <td><span class="estado <?= $classe ?>"><?= pagueprofEsc($label) ?></span></td>
              <td><?php if(in_array($statusEmail,['aguardando_envio_profissional','falhou'],true)&&!empty($c['notificacao_id'])&&(int)($c['notificacao_tentativas']??0)<5): ?><form method="post" action="index.php?rota=enviar_notificacao" class="form-acao-tabela"><input type="hidden" name="csrf" value="<?= $csrfValor ?>"><input type="hidden" name="notificacao_id" value="<?= (int)$c['notificacao_id'] ?>"><button class="botao pequeno secundario" type="submit"><?= $statusEmail==='falhou'?'Tentar novamente':'Enviar e-mail' ?></button></form><?php elseif($statusEmail==='enviada'): ?><span class="estado verde">Enviado</span><?php elseif($statusEmail==='falhou'): ?><span class="estado vermelho" title="Limite de tentativas atingido">Falha</span><?php elseif($statusEmail==='aguardando_envio_profissional'): ?><span class="estado neutro">Na fila</span><?php elseif($statusEmail==='pendente'): ?><span class="estado neutro">Pendente</span><?php elseif($statusEmail==='ignorada'): ?><span class="estado neutro">Ignorado</span><?php else: ?><span class="texto-secundario">—</span><?php endif; ?></td>
              <td><div class="acoes-tabela"><?php if(!in_array($c['status'],['paga','cancelada'],true)): ?><form method="post" action="index.php?rota=excluir_cobranca" onsubmit="return confirm('Cancelar esta cobrança? O registro e os comprovantes serão preservados.');"><input type="hidden" name="csrf" value="<?= $csrfValor ?>"><input type="hidden" name="cobranca_id" value="<?= (int)$c['id'] ?>"><button class="botao pequeno perigo" type="submit">Cancelar</button></form><?php else: ?><span class="texto-secundario">—</span><?php endif; ?></div></td>
            </tr>
          <?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
      </section><?php if(!empty($comprovantes)): ?><section class="bloco"><h2>Comprovantes</h2><?php foreach($comprovantes as $cp): ?><article id="comprovante-<?= (int)$cp['id'] ?>" class="linha-dados comprovante-item"><div><strong><?= pagueprofEsc($cp['cliente_nome']??'Cliente') ?></strong><span><?= pagueprofDinheiro($cp['valor']) ?> · <?= pagueprofData($cp['enviado_em']) ?> · <?= pagueprofEsc(ucfirst(str_replace('_',' ',$cp['status']))) ?></span><a class="link-acao" href="index.php?rota=baixar_comprovante&amp;id=<?= (int)$cp['id'] ?>">Abrir comprovante</a></div><?php if($cp['status']==='em_analise'): ?><form method="post" action="index.php?rota=avaliar_comprovante" class="acoes-comprovante"><input type="hidden" name="csrf" value="<?= $csrfValor ?>"><input type="hidden" name="comprovante_id" value="<?= (int)$cp['id'] ?>"><button class="botao pequeno" name="decisao" value="aprovado" type="submit">Confirmar</button><button class="botao pequeno perigo" name="decisao" value="rejeitado" type="submit">Recusar</button></form><?php endif; ?></article><?php endforeach; ?></section><?php endif; ?>
    <?php elseif($pagina==='minhas_cobrancas'): ?>
      <div class="cabecalho-pagina"><div><p class="eyebrow">CLIENTE</p><h1>Meus pagamentos</h1><p>Consulte cobranças e envie comprovantes para conferência.</p></div></div>
      <?php if(empty($cobrancas)): ?><section class="bloco"><p class="vazio">Nenhuma cobrança vinculada a esta conta. Confira se o profissional cadastrou este mesmo e-mail.</p></section><?php else: foreach($cobrancas as $c): [$label,$classe]=pagueprofStatus($c); ?><section class="bloco pagamento-cliente"><div class="pagamento-topo"><div><p class="eyebrow">PROFISSIONAL</p><h2><?= pagueprofEsc($c['profissional_nome']) ?></h2><p>Vencimento: <?= pagueprofData($c['data_vencimento']) ?></p></div><div class="pagamento-valor"><strong><?= pagueprofDinheiro($c['valor']) ?></strong><span class="estado <?= $classe ?>"><?= pagueprofEsc($label) ?></span></div></div>
        <?php if(!empty($c['tipo_servico_id'])): ?><p><?= pagueprofEsc($c['tipo_servico_nome']??'Serviço') ?><?php if(!empty($c['quantidade_atividades'])): ?> · <?= (int)$c['quantidade_atividades'] ?> atividade(s)<?php endif; ?></p><?php endif; ?><?php if($c['status']==='paga'): ?><p class="alerta sucesso">Pagamento confirmado em <?= pagueprofData($c['data_pagamento']) ?>.</p><?php elseif($c['status']==='em_analise'): ?><p class="alerta informacao">Seu comprovante foi enviado e aguarda conferência do profissional.</p><?php else: ?>
          <div class="manual-area"><h3>Enviar comprovante</h3><?php if(!empty($c['instrucoes_pagamento'])): ?><p><?= nl2br(pagueprofEsc($c['instrucoes_pagamento'])) ?></p><?php else: ?><p>Faça o pagamento conforme combinado com o profissional e anexe o comprovante para conferência.</p><?php endif; ?>
            <?php if(!empty($c['pix_payload'])): ?><div class="pix-area"><div class="pix-qr" data-pix-payload="<?= pagueprofEsc($c['pix_payload']) ?>" role="img" aria-label="QR Code Pix estático sem valor"></div><div class="campo-codigo"><p><strong>Pix para <?= pagueprofEsc($c['nome_recebedor']) ?></strong></p><p>Chave Pix (<?= $c['chave_pix_tipo']==='cpf'?'CPF':'telefone' ?>): <?= pagueprofEsc(Pix::formatarChave((string)$c['chave_pix_tipo'],(string)$c['chave_pix'])) ?></p><label>Pix copia e cola<textarea id="pix-copia-cola-<?= (int)$c['id'] ?>" readonly><?= pagueprofEsc($c['pix_payload']) ?></textarea></label><button class="botao pequeno secundario copiar-pix" type="button" data-copiar-qr="pix-copia-cola-<?= (int)$c['id'] ?>">Copiar Pix copia e cola</button><small>QR estático sem valor. Confira e informe o valor da cobrança no aplicativo do banco. O pagamento ainda será confirmado pelo profissional após conferir o comprovante.</small></div></div><?php endif; ?>
            <form method="post" enctype="multipart/form-data" action="index.php?rota=enviar_comprovante" class="form-inline"><input type="hidden" name="csrf" value="<?= $csrfValor ?>"><input type="hidden" name="cobranca_id" value="<?= (int)$c['id'] ?>"><label>Arquivo (PDF, JPG ou PNG; até 5 MB)<input type="file" name="comprovante" accept="application/pdf,image/jpeg,image/png" required></label><button class="botao secundario" type="submit">Enviar para análise</button></form></div>
        <?php endif; ?></section><?php endforeach; endif; ?>
    <?php elseif($pagina==='comprovantes'): ?>
      <div class="cabecalho-pagina"><div><p class="eyebrow">PROFISSIONAL</p><h1>Comprovantes</h1><p>Confira os anexos e confirme ou recuse o pagamento.</p></div></div>
      <?php if(empty($comprovantes)): ?><section class="bloco"><p class="vazio">Nenhum comprovante recebido.</p></section><?php else: foreach($comprovantes as $cp): ?><article id="comprovante-<?= (int)$cp['id'] ?>" class="bloco comprovante-item"><div class="bloco-cabecalho"><div><h2><?= pagueprofEsc($cp['cliente_nome']) ?></h2><p><?= pagueprofDinheiro($cp['valor']) ?> · vencimento <?= pagueprofData($cp['data_vencimento']) ?> · enviado <?= pagueprofData($cp['enviado_em']) ?></p></div><span class="estado <?= $cp['status']==='em_analise'?'azul':($cp['status']==='aprovado'?'verde':'vermelho') ?>"><?= pagueprofEsc(ucfirst(str_replace('_',' ',$cp['status']))) ?></span></div><a class="link-acao" target="_blank" rel="noopener" href="index.php?rota=baixar_comprovante&amp;id=<?= (int)$cp['id'] ?>">Abrir comprovante (<?= pagueprofEsc(strtoupper(pathinfo($cp['nome_original'],PATHINFO_EXTENSION))) ?>)</a><?php if($cp['status']==='em_analise'): ?><form method="post" action="index.php?rota=avaliar_comprovante" class="form-grid"><input type="hidden" name="csrf" value="<?= $csrfValor ?>"><input type="hidden" name="comprovante_id" value="<?= (int)$cp['id'] ?>"><label>Observação opcional<input name="observacao" maxlength="500" placeholder="Ex.: comprovante ilegível"></label><div class="form-acoes"><button class="botao" name="decisao" value="aprovado" type="submit">Confirmar pagamento</button><button class="botao perigo" name="decisao" value="rejeitado" type="submit">Recusar</button></div></form><?php elseif($cp['observacao_profissional']): ?><p class="texto-secundario">Observação: <?= pagueprofEsc($cp['observacao_profissional']) ?></p><?php endif; ?></article><?php endforeach; endif; ?>
    <?php elseif($pagina==='calendario'): ?>
      <div class="cabecalho-pagina"><div><p class="eyebrow">PROFISSIONAL</p><h1>Calendário mensal</h1><p>Consulte os valores das cobranças por cliente e data de vencimento.</p></div></div>
      <section class="bloco"><form method="get" class="filtros"><input type="hidden" name="rota" value="calendario"><label>Cliente<select name="cliente_id"><option value="0" <?= (int)$clienteId===0?'selected':'' ?>>TODOS</option><?php foreach($clientes as $cliente): ?><option value="<?= (int)$cliente['id'] ?>" <?= (int)$clienteId===(int)$cliente['id']?'selected':'' ?>><?= pagueprofEsc($cliente['nome'].((int)($cliente['ativo']??0)===1?'':' (inativo)')) ?></option><?php endforeach; ?></select></label><label>Mês<input type="month" name="mes" value="<?= pagueprofEsc($mesCalendario) ?>" required></label><button class="botao" type="submit">Aplicar filtros</button></form></section>
      <section class="bloco calendario-mensal">
        <div class="calendario-cabecalho"><div><h2><?= pagueprofEsc(date('m/Y',strtotime($mesCalendario.'-01'))) ?></h2><p>Valores agrupados pela data de vencimento. Os valores exibidos estão em reais.</p></div></div>
        <div class="legenda-calendario"><span><i class="marcador-legenda legenda-azul"></i>Comprovante enviado, ainda não confirmado (inclui recusado)</span><span><i class="marcador-legenda legenda-verde"></i>Pagamento confirmado</span><span><i class="marcador-legenda legenda-preta"></i>Sem comprovante enviado</span></div>
        <div class="totalizadores-calendario" aria-label="Totais do mês selecionado"><article class="totalizador-calendario totalizador-azul"><span>Comprovante enviado</span><strong><?= pagueprofDinheiro($totaisCalendario['comprovante_enviado']??0) ?></strong></article><article class="totalizador-calendario totalizador-verde"><span>Pago e confirmado</span><strong><?= pagueprofDinheiro($totaisCalendario['confirmado']??0) ?></strong></article><article class="totalizador-calendario totalizador-preto"><span>Sem comprovante</span><strong><?= pagueprofDinheiro($totaisCalendario['sem_comprovante']??0) ?></strong></article></div>
        <div class="grade-calendario" aria-label="Calendário financeiro mensal">
          <?php foreach(['Seg','Ter','Qua','Qui','Sex','Sáb','Dom'] as $diaSemana): ?><span class="calendario-dia-semana"><?= $diaSemana ?></span><?php endforeach; ?>
          <?php for($vazio=0;$vazio<$diaInicialSemana;$vazio++): ?><span class="calendario-celula-vazia" aria-hidden="true"></span><?php endfor; ?>
          <?php for($dia=1;$dia<=$diasNoMes;$dia++): $valoresDia=(array)($calendarioMensal[$dia]??[]); ?>
            <article class="calendario-dia"><span class="calendario-numero"><?= $dia ?></span><div class="calendario-valores">
              <?php if((float)($valoresDia['comprovante_enviado']??0)>0): ?><span class="valor-dia valor-dia-azul" title="Comprovante enviado, ainda não confirmado"><?= number_format((float)$valoresDia['comprovante_enviado'],2,',','.') ?></span><?php endif; ?>
              <?php if((float)($valoresDia['confirmado']??0)>0): ?><span class="valor-dia valor-dia-verde" title="Pagamento confirmado"><?= number_format((float)$valoresDia['confirmado'],2,',','.') ?></span><?php endif; ?>
              <?php if((float)($valoresDia['sem_comprovante']??0)>0): ?><span class="valor-dia valor-dia-preto" title="Cobrança sem comprovante enviado"><?= number_format((float)$valoresDia['sem_comprovante'],2,',','.') ?></span><?php endif; ?>
            </div></article>
          <?php endfor; ?>
          <?php for($vazio=$diaInicialSemana+$diasNoMes;$vazio<$celulasCalendario;$vazio++): ?><span class="calendario-celula-vazia" aria-hidden="true"></span><?php endfor; ?>
        </div>
      </section>
    <?php elseif($pagina==='relatorios'): ?>
      <div class="cabecalho-pagina"><div><p class="eyebrow">PROFISSIONAL</p><h1>Relatórios</h1><p>Resumo financeiro, indicadores, gráficos e movimentações do período selecionado.</p></div></div>
      <section class="bloco"><form method="get" class="filtros"><input type="hidden" name="rota" value="relatorios"><label>Cliente<select name="cliente_id"><option value="0" <?= (int)$clienteId===0?'selected':'' ?>>TODOS</option><?php foreach($clientes as $cliente): ?><option value="<?= (int)$cliente['id'] ?>" <?= (int)$clienteId===(int)$cliente['id']?'selected':'' ?>><?= pagueprofEsc($cliente['nome'].((int)($cliente['ativo']??0)===1?'':' (inativo)')) ?></option><?php endforeach; ?></select></label><label>De<input type="date" name="de" value="<?= pagueprofEsc($de) ?>" required></label><label>Até<input type="date" name="ate" value="<?= pagueprofEsc($ate) ?>" required></label><button class="botao" type="submit">Aplicar filtros</button></form></section>
      <section class="grade-resumo"><article class="cartao-resumo"><span>Recebido</span><strong><?= pagueprofDinheiro($resumo['recebido']??0) ?></strong></article><article class="cartao-resumo"><span>Em aberto</span><strong><?= pagueprofDinheiro($resumo['aberto']??0) ?></strong></article><article class="cartao-resumo alerta-resumo"><span>Vencido</span><strong><?= pagueprofDinheiro($resumo['vencido']??0) ?></strong></article><article class="cartao-resumo"><span><?= $clienteId>0?'Cliente selecionado':'Clientes ativos' ?></span><strong><?= (int)($resumo['total_clientes']??0) ?></strong></article></section>
      <?php if(!empty($sugestoes)): ?>
        <section class="bloco sugestoes-serie"><div class="bloco-cabecalho"><div><h2>Nova série sugerida</h2><p>As cobranças da série anterior foram quitadas. Revise os dados antes de gerar outra.</p></div></div>
          <?php if(empty($temInstrucoesPagamento)): ?><div class="alerta erro">Cadastre as instruções de pagamento para liberar a geração. <a class="link-acao" href="index.php?rota=configuracoes">Cadastrar instruções</a></div><?php endif; ?>
          <?php foreach($sugestoes as $s): ?><article class="cartao-item"><div><strong><?= pagueprofEsc($s['cliente_nome']) ?></strong><span><?= pagueprofEsc($s['tipo_servico_nome']??'Serviço') ?> · <?= pagueprofDinheiro($s['valor']) ?> por cobrança · última série quitada em <?= pagueprofData($s['ultima_paga']) ?></span></div>
            <form method="post" action="index.php?rota=gerar_cobrancas" class="form-inline"><input type="hidden" name="csrf" value="<?= $csrfValor ?>"><input type="hidden" name="plano_id" value="<?= (int)$s['id'] ?>"><label>Próximo vencimento<input type="date" name="data_vencimento" value="<?= pagueprofEsc($s['proximo_vencimento']) ?>" required></label><label>Quantidade de cobranças<input type="number" name="quantidade" min="1" max="36" value="3" required></label><button class="botao pequeno" type="submit" <?= empty($temInstrucoesPagamento)?'disabled':'' ?>>Gerar nova série</button></form>
          </article><?php endforeach; ?>
        </section>
      <?php endif; ?>
      <?php $dadosGrafico=(array)($graficoMensal??[]); $maxGraf=1.0; foreach($dadosGrafico as $gm){$maxGraf=max($maxGraf,(float)($gm['recebido']??0),(float)($gm['pendente']??0));} $totalProporcao=(float)($resumo['recebido']??0)+(float)($resumo['aberto']??0); $pctRecebido=$totalProporcao>0?100*(float)($resumo['recebido']??0)/$totalProporcao:0; $passoGraf=count($dadosGrafico)>0?min(70,550/count($dadosGrafico)):70; $larguraBarra=min(22,max(8,($passoGraf-4)/2)); ?><section class="grade-graficos"><article class="bloco grafico"><h2>Recebidos vs pendentes por mês</h2><?php if(empty($dadosGrafico)): ?><p class="vazio">Ainda não há movimentações no período para desenhar o gráfico.</p><?php else: ?><div class="grafico-scroll"><svg viewBox="0 0 640 240" role="img" aria-label="Gráfico mensal de valores recebidos e pendentes"><line x1="40" y1="205" x2="625" y2="205" stroke="#aebdce"/><?php foreach($dadosGrafico as $i=>$gm): $x=50+$i*$passoGraf; $rh=150*(float)($gm['recebido']??0)/$maxGraf; $ph=150*(float)($gm['pendente']??0)/$maxGraf; ?><rect x="<?= $x ?>" y="<?= 200-$rh ?>" width="<?= $larguraBarra ?>" height="<?= max(1,$rh) ?>" fill="#2673c9"><title>Recebido <?= pagueprofEsc($gm['recebido']??0) ?></title></rect><rect x="<?= $x+$larguraBarra+3 ?>" y="<?= 200-$ph ?>" width="<?= $larguraBarra ?>" height="<?= max(1,$ph) ?>" fill="#e6a629"><title>Pendente <?= pagueprofEsc($gm['pendente']??0) ?></title></rect><text x="<?= $x ?>" y="220"><?= pagueprofEsc($gm['mes']??$gm['label']??'') ?></text><?php endforeach; ?></svg></div><p>Azul: recebido · Amarelo: pendente</p><?php endif; ?></article><article class="bloco grafico"><h2>Proporção recebida e em aberto</h2><div class="barra-proporcao" role="img" aria-label="<?= number_format($pctRecebido,1,',','.') ?>% recebido"><span style="width:<?= max(0,min(100,$pctRecebido)) ?>%"></span></div><p>Recebido <?= number_format($pctRecebido,1,',','.') ?>% · Em aberto <?= number_format(100-$pctRecebido,1,',','.') ?>%</p></article></section>
      <section class="bloco"><h2>Movimentações do período</h2><?php if(empty($cobrancas)): ?><p class="vazio">Nenhuma cobrança para o período selecionado.</p><?php else: foreach($cobrancas as $c): [$label,$classe]=pagueprofStatus($c); ?><article class="linha-dados"><div><strong><?= pagueprofEsc($c['cliente_nome']) ?></strong><span>Vencimento <?= pagueprofData($c['data_vencimento']) ?><?= $c['data_pagamento']?' · Pago em '.pagueprofData($c['data_pagamento']):'' ?></span></div><div class="valor-estado"><strong><?= pagueprofDinheiro($c['valor']) ?></strong><span class="estado <?= $classe ?>"><?= pagueprofEsc($label) ?></span></div></article><?php endforeach; endif; ?></section>
    <?php elseif($pagina==='configuracoes'): ?>
      <div class="cabecalho-pagina"><div><p class="eyebrow">PROFISSIONAL</p><h1>Recebimentos</h1><p>Escolha como seus clientes poderão pagar.</p></div></div>
      <section class="bloco"><h2>Instruções e chave Pix</h2><form method="post" action="index.php?rota=configuracoes" class="form-grid" id="form-recebimentos"><input type="hidden" name="csrf" value="<?= $csrfValor ?>">
        <label class="campo-largo">Orientações para o cliente<textarea name="instrucoes_pagamento" rows="4" maxlength="1000" placeholder="Informe como o cliente deve pagar e enviar o comprovante."><?= pagueprofEsc($configuracao['instrucoes_pagamento']??'') ?></textarea></label>
        <label>Tipo da chave Pix<select name="chave_pix_tipo" id="tipo-chave-pix"><option value="" <?= empty($configuracao['chave_pix_tipo'])?'selected':'' ?>>Não cadastrar chave Pix</option><option value="cpf" <?= ($configuracao['chave_pix_tipo']??'')==='cpf'?'selected':'' ?>>CPF</option><option value="telefone" <?= ($configuracao['chave_pix_tipo']??'')==='telefone'?'selected':'' ?>>Telefone celular</option></select><small>A chave telefônica é salva no padrão internacional +55.</small></label>
        <label>Chave Pix<input name="chave_pix" id="chave-pix" type="text" inputmode="numeric" autocomplete="off" maxlength="25" value="<?= pagueprofEsc($chavePixExibicao) ?>" placeholder="CPF ou celular com DDD"><small id="ajuda-chave-pix">Selecione CPF ou telefone. O sistema aplica a máscara e valida os dados.</small></label>
        <label>Nome do profissional/titular<input name="nome_recebedor" id="nome-recebedor" maxlength="25" value="<?= pagueprofEsc($configuracao['nome_recebedor']??$nomeProfissional??'') ?>" placeholder="Nome como aparece no banco"><small>Será usado no QR Pix; máximo de 25 caracteres após ajuste para o padrão BR Code.</small></label>
        <label>Cidade do recebedor<input name="cidade_recebedor" id="cidade-recebedor" maxlength="15" value="<?= pagueprofEsc($configuracao['cidade_recebedor']??'') ?>" placeholder="Ex.: São Paulo"><small>Máximo de 15 caracteres no QR.</small></label>
        <div class="form-acoes campo-largo"><button class="botao" type="submit">Salvar recebimentos</button></div></form>
        <?php if(!empty($pixPayload)): ?><div class="pix-area"><div class="pix-qr" data-pix-payload="<?= pagueprofEsc($pixPayload) ?>" role="img" aria-label="QR Code Pix estático sem valor"></div><div class="campo-codigo"><h3>Seu QR Pix</h3><p>Recebedor: <?= pagueprofEsc($configuracao['nome_recebedor']) ?> · Chave <?= $configuracao['chave_pix_tipo']==='cpf'?'CPF':'telefone' ?>: <?= pagueprofEsc($chavePixExibicao) ?></p><label>Pix copia e cola<textarea id="pix-copia-cola-config" readonly><?= pagueprofEsc($pixPayload) ?></textarea></label><button class="botao pequeno secundario copiar-pix" type="button" data-copiar-qr="pix-copia-cola-config">Copiar Pix copia e cola</button></div></div><?php endif; ?>
        <p>A chave Pix é opcional. O QR é estático e não contém valor; o cliente informa o valor no banco e envia o comprovante. A confirmação permanece manual, sem consulta automática ao banco.</p></section>
    <?php endif; ?>
  </main>
</div>
<script src="assets/vendor/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const quantidade = document.getElementById('quantidade-cobrancas');
  const previsao = document.getElementById('previsao-cobrancas');
  const formulario = document.getElementById('form-cobrancas');
  if (quantidade && previsao) {
    const atualizarPrevisao = function () {
      const n = Math.max(1, Math.min(36, parseInt(quantidade.value || '1', 10)));
      previsao.textContent = 'Ser' + (n === 1 ? 'á gerada 1 cobrança' : 'ão geradas ' + n + ' cobranças') + '. Os vencimentos seguintes serão mensais.';
    };
    quantidade.addEventListener('input', atualizarPrevisao);
    atualizarPrevisao();
  }
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
      chavePix.inputMode = 'numeric';
    } else if (tipoPix.value === 'telefone') {
      if ((digitos.length === 12 || digitos.length === 13) && digitos.startsWith('55')) digitos = digitos.slice(2);
      digitos = digitos.slice(0, 11);
      if (digitos.length > 2) digitos = '(' + digitos.slice(0, 2) + ') ' + digitos.slice(2);
      if (digitos.replace(/\D/g, '').length > 7) digitos = digitos.replace(/(\d{5})(\d{1,4})$/, '$1-$2');
      chavePix.inputMode = 'tel';
    }
    chavePix.value = digitos;
    const exigePix = tipoPix.value !== '';
    chavePix.required = exigePix;
    if (nomeRecebedor) nomeRecebedor.required = exigePix;
    if (cidadeRecebedor) cidadeRecebedor.required = exigePix;
  };
  if (tipoPix && chavePix) {
    tipoPix.addEventListener('change', function () { if (!tipoPix.value) chavePix.value = ''; atualizarMascaraPix(); });
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
</body>
</html>
