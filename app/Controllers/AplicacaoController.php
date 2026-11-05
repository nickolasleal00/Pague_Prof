<?php
declare(strict_types=1);

final class AplicacaoController
{
    private Financeiro $financeiro;

    public function __construct()
    {
        $this->financeiro = new Financeiro();
    }

    public function painel(): void
    {
        $this->usuario();
        if($this->temPerfil('profissional'))$this->ir('relatorios');
        if($this->temPerfil('cliente'))$this->ir('minhas_cobrancas');
        $this->ir('login');
    }

    public function clientes(): void
    {
        $p=$this->profissional();$erro='';$clienteEditar=null;$clienteForm=null;$clienteId=(int)($_GET['editar_id']??0);
        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            $this->csrf();$clienteId=(int)($_POST['cliente_id']??0);$nome=trim($_POST['nome']??'');$email=filter_var(trim($_POST['email']??''),FILTER_VALIDATE_EMAIL);
            $telefone=trim($_POST['telefone']??'');$area_atuacao=trim($_POST['area_atuacao']??'');
            $dadosFormulario=['id'=>$clienteId,'nome'=>$nome,'email'=>trim((string)($_POST['email']??'')),'telefone'=>$telefone,'area_atuacao'=>$area_atuacao];
            if($clienteId>0)$clienteEditar=$dadosFormulario;else $clienteForm=$dadosFormulario;
            if(mb_strlen($nome)<2 || !$email){$erro='Informe o nome do cliente e um e-mail válido.';}
            else{try{if($clienteId>0){$this->financeiro->atualizarCliente((int)$p['id'],$clienteId,$nome,$email,$telefone,$area_atuacao);$this->flash('mensagem','Dados do cliente atualizados.');}else{$this->financeiro->criarCliente((int)$p['id'],$nome,$email,$telefone,$area_atuacao);$this->flash('mensagem','Cliente cadastrado. Se já tiver conta PagueProf com esse e-mail, o perfil de cliente foi vinculado.');}$this->ir('clientes');}catch(Throwable $e){error_log($e->getMessage());$erro='Não foi possível salvar. Verifique os dados e se o e-mail já está cadastrado para outro cliente.';}}
        }
        elseif($clienteId>0){$clienteEditar=$this->financeiro->clienteDoProfissional((int)$p['id'],$clienteId);if(!$clienteEditar)$erro='Cliente não encontrado ou indisponível para edição.';}
        $this->render('clientes',['clientes'=>$this->financeiro->clientes((int)$p['id']),'clienteEditar'=>$clienteEditar,'clienteForm'=>$clienteForm,'erro'=>$erro]);
    }

    public function excluirCliente(): void
    {
        $p=$this->profissional();$this->csrf();
        try{$this->financeiro->desativarCliente((int)$p['id'],(int)($_POST['cliente_id']??0));$this->flash('mensagem','Cliente excluído da lista. O cadastro foi desativado e o histórico financeiro foi preservado.');}
        catch(Throwable $e){error_log($e->getMessage());$this->flash('erro','Não foi possível excluir este cliente. Atualize a lista e tente novamente.');}
        $this->ir('clientes');
    }

    public function planos(): void
    {
        $p=$this->profissional();
        $tokensCobranca=(array)($_SESSION['tokens_cobranca_direta']??[]);$agora=time();
        $tokensCobranca=array_filter($tokensCobranca,static fn($criadoEm)=>is_numeric($criadoEm)&&(int)$criadoEm>=$agora-3600);
        $tokenCobrancaDireta=bin2hex(random_bytes(32));$tokensCobranca[$tokenCobrancaDireta]=$agora;
        if(count($tokensCobranca)>10)$tokensCobranca=array_slice($tokensCobranca,-10,null,true);
        $_SESSION['tokens_cobranca_direta']=$tokensCobranca;
        $configuracao=$this->financeiro->configuracao((int)$p['id']);
        $this->render('planos',[
            'clientes'=>$this->financeiro->clientes((int)$p['id']),
            'tipos'=>$this->financeiro->tiposServico((int)$p['id']),
            'tokenCobrancaDireta'=>$tokenCobrancaDireta,
            'temInstrucoesPagamento'=>trim((string)($configuracao['instrucoes_pagamento']??''))!==''
        ]);
    }

    public function gerarCobrancas(): void
    {
        $p=$this->profissional();$this->csrf();
        $configuracao=$this->financeiro->configuracao((int)$p['id']);
        if(trim((string)($configuracao['instrucoes_pagamento']??''))===''){
            $this->flash('erro','Antes de gerar cobranças, cadastre as instruções de pagamento na área Recebimentos.');
            $this->ir('nova_cobranca');
        }
        $planoId=(int)($_POST['plano_id']??0);
        $clienteId=(int)($_POST['cliente_id']??0);
        if($planoId>0){
            try{
                $criados=$this->financeiro->gerarCobrancas((int)$p['id'],$planoId,(string)($_POST['data_vencimento']??''),(int)($_POST['quantidade']??0));
                if($criados<1)throw new RuntimeException('Nenhuma nova cobrança foi criada para as datas informadas.');
                $notificacaoId=$this->financeiro->notificacaoInicialRecente((int)$p['id'],$planoId);
                if($notificacaoId){try{(new NotificacoesEmail())->processar(1,'cobranca_gerada',$notificacaoId);}catch(Throwable $emailErro){error_log('PagueProf envio da primeira cobrança sugerida: '.$emailErro->getMessage());}}
                $this->flash('mensagem',$criados===1?'1 cobrança adicionada à série.':'Nova série gerada com '.$criados.' cobranças.');
                $this->ir('relatorios');
            }catch(Throwable $e){
                error_log($e->getMessage());$this->flash('erro','Não foi possível gerar a nova série. Confira o vencimento, a quantidade entre 1 e 36 e se ainda não existem cobranças nessas datas.');$this->ir('relatorios');
            }
        }
        $tokensCobranca=(array)($_SESSION['tokens_cobranca_direta']??[]);
        $tokenRecebido=(string)($_POST['token_cobranca_direta']??'');
        $tokenValido=isset($tokensCobranca[$tokenRecebido])&&(int)$tokensCobranca[$tokenRecebido]>=time()-3600;
        unset($tokensCobranca[$tokenRecebido]);$_SESSION['tokens_cobranca_direta']=$tokensCobranca;
        if(!$tokenValido){
            $this->flash('erro','Este formulário já foi enviado ou expirou. Confira o Financeiro antes de tentar novamente.');
            $this->ir('cobrancas');
        }
        $valorTexto=trim((string)($_POST['valor_servico']??''));
        try{
            if(!preg_match('/^\d{1,8}(?:[.,]\d{1,2})?$/D',$valorTexto))throw new InvalidArgumentException('Valor inválido.');
            $valor=(float)str_replace(',','.',$valorTexto);
            $resultado=$this->financeiro->gerarCobrancasDiretas(
                (int)$p['id'],
                $clienteId,
                (int)($_POST['tipo_servico_id']??0),
                $valor,
                (string)($_POST['data_vencimento']??''),
                (int)($_POST['quantidade']??0)
            );
            $notificacaoId=(int)($resultado['notificacao_inicial_id']??0);
            if($notificacaoId>0){
                try{(new NotificacoesEmail())->processar(1,'cobranca_gerada',$notificacaoId);}
                catch(Throwable $emailErro){error_log('PagueProf envio do primeiro aviso: '.$emailErro->getMessage());}
            }
            $quantidade=(int)$resultado['quantidade'];
            $substantivo=$quantidade===1?'cobrança gerada':'cobranças geradas';
            $this->flash('mensagem',$quantidade.' '.$substantivo.'. A notificação da primeira foi tentada; as demais aguardam envio manual.');
            header('Location: index.php?rota=cobrancas&cliente_id='.$clienteId);exit;
        }catch(Throwable $e){
            error_log($e->getMessage());
            $this->flash('erro','Não foi possível gerar. Confira cliente, serviço, valor, quantidade entre 1 e 36, vencimento e instruções de pagamento.');
            $this->ir('nova_cobranca');
        }
    }

    public function tiposServico(): void
    {
        $this->profissional();
        $this->ir('nova_cobranca');
    }

    public function adicionarTipoServico(): void
    {
        if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST'){http_response_code(405);header('Content-Type: application/json; charset=UTF-8');echo json_encode(['erro'=>'Método não permitido.'],JSON_UNESCAPED_UNICODE);exit;}
        $p=$this->profissional();$this->csrf();header('Content-Type: application/json; charset=UTF-8');
        $nome=trim((string)($_POST['nome']??''));
        try{$id=$this->financeiro->salvarTipoServico((int)$p['id'],$nome);echo json_encode(['id'=>$id,'nome'=>$nome],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
        catch(InvalidArgumentException $e){http_response_code(422);echo json_encode(['erro'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
        catch(PDOException $e){http_response_code(422);$mensagem=(string)$e->getCode()==='23000'?'Este serviço já está cadastrado.':'Não foi possível cadastrar o serviço.';echo json_encode(['erro'=>$mensagem],JSON_UNESCAPED_UNICODE);}
        catch(Throwable $e){error_log('PagueProf cadastro de tipo de serviço: '.$e->getMessage());http_response_code(500);echo json_encode(['erro'=>'Não foi possível cadastrar o serviço agora.'],JSON_UNESCAPED_UNICODE);}
        exit;
    }

    public function enviarNotificacao(): void
    {
        $p=$this->profissional();$this->csrf();
        try{
            $resultado=(new NotificacoesEmail())->enviarNotificacaoProfissional((int)$p['id'],(int)($_POST['notificacao_id']??0));
            if(($resultado['enviados']??0)>0)$mensagem='Notificação enviada ao cliente.';
            elseif(($resultado['falhas']??0)>0)$mensagem='O envio falhou. A notificação continua na fila para nova tentativa pelo cron (até cinco tentativas). Confira as configurações SMTP e o log de erros do PHP.';
            elseif(($resultado['ignorados']??0)>0)$mensagem='A notificação foi ignorada porque a cobrança já foi paga, cancelada ou não atende aos critérios de envio.';
            else $mensagem='Nenhum e-mail foi enviado. Verifique o estado da cobrança e o limite de tentativas.';
            $this->flash(($resultado['falhas']??0)>0?'erro':'mensagem',$mensagem);
        }catch(Throwable $e){error_log($e->getMessage());$this->flash('erro','Notificação indisponível. Ela pode já ter sido enviada, excedido o limite de tentativas ou não pertencer a esta conta.');}
        $this->ir('cobrancas');
    }

    public function cobrancas(): void
    {
        $p=$this->profissional();
        $de=(string)($_GET['de']??'');$ate=(string)($_GET['ate']??'');$status=(string)($_GET['status']??'');$clienteId=(int)($_GET['cliente_id']??0);
        $ordenar=(string)($_GET['ordenar']??'vencimento_desc');
        $this->render('cobrancas',[
            'cobrancas'=>$this->financeiro->cobrancasProfissional((int)$p['id'],$de,$ate,$status,$clienteId,$ordenar),
            'de'=>$de,'ate'=>$ate,'status'=>$status,'clienteId'=>$clienteId,'ordenar'=>$ordenar,
            'clientes'=>$this->financeiro->clientes((int)$p['id'],true),
            'comprovantes'=>$this->financeiro->comprovantesNoFinanceiro((int)$p['id'],$clienteId)
        ]);
    }

    public function excluirCobranca(): void
    {
        $p=$this->profissional();$this->csrf();
        try{$this->financeiro->cancelarCobranca((int)$p['id'],(int)($_POST['cobranca_id']??0));$this->flash('mensagem','Cobrança cancelada. O registro e o histórico foram preservados.');}
        catch(Throwable $e){error_log($e->getMessage());$this->flash('erro',$e->getMessage()==='Uma cobrança paga não pode ser cancelada.'?'Cobranças pagas não podem ser canceladas.':'Não foi possível cancelar a cobrança. Ela pode já estar cancelada ou não pertencer a esta conta.');}
        $this->ir('cobrancas');
    }

    public function minhasCobrancas(): void
    {
        $a=$this->cliente();$cobrancas=$this->financeiro->cobrancasCliente((int)$a['id']);
        foreach($cobrancas as &$c){
            $c['pix_payload']='';
            if(!in_array((string)$c['status'],['paga','cancelada','em_analise'],true)&&!empty($c['chave_pix_tipo'])&&!empty($c['chave_pix'])&&!empty($c['nome_recebedor'])&&!empty($c['cidade_recebedor'])){
                try{$c['pix_payload']=Pix::gerarPayload((string)$c['nome_recebedor'],(string)$c['cidade_recebedor'],(string)$c['chave_pix_tipo'],(string)$c['chave_pix'],Pix::txidCobranca((int)$c['id']));}catch(Throwable $e){error_log('PagueProf: QR Pix indisponível para cobrança '.$c['id'].'.');}
            }
        }
        unset($c);$this->render('minhas_cobrancas',['cobrancas'=>$cobrancas]);
    }

    public function enviarComprovante(): void
    {
        $this->csrf();$u=$this->cliente();$id=(int)($_POST['cobranca_id']??0);$file=$_FILES['comprovante']??null;
        if(!$file || ($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || (int)$file['size']>5*1024*1024){$this->flash('erro','Selecione um arquivo de até 5 MB.');$this->ir('minhas_cobrancas');}
        $finfo=new finfo(FILEINFO_MIME_TYPE);$mime=$finfo->file($file['tmp_name']);$permitidos=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
        if(!isset($permitidos[$mime])){$this->flash('erro','Envie somente PDF, JPG ou PNG.');$this->ir('minhas_cobrancas');}
        $pasta=dirname(__DIR__,2).'/storage/comprovantes';
        if(!is_dir($pasta) && !mkdir($pasta,0750,true) && !is_dir($pasta)){$this->flash('erro','Não foi possível salvar o arquivo na hospedagem.');$this->ir('minhas_cobrancas');}
        $nome=bin2hex(random_bytes(20)).'.'.$permitidos[$mime];$destino=$pasta.'/'.$nome;
        if(!move_uploaded_file($file['tmp_name'],$destino)){$this->flash('erro','Não foi possível receber o arquivo.');$this->ir('minhas_cobrancas');}
        try{
            $comprovanteId=$this->financeiro->comprovanteDoCliente((int)$u['id'],$id,$nome,basename((string)$file['name']),$mime,(int)$file['size']);
            $mensagem='Comprovante recebido. O sistema avisará o profissional por e-mail.';
            try{$resultado=(new NotificacoesEmail())->processar(1,'comprovante_'.$comprovanteId);if(($resultado['enviados']??0)>0)$mensagem='Comprovante recebido e aviso enviado ao profissional.';elseif(($resultado['falhas']??0)>0)$mensagem='Comprovante recebido. O aviso ficou na fila e será tentado novamente.';}
            catch(Throwable $emailErro){error_log('PagueProf aviso de comprovante: '.$emailErro->getMessage());$mensagem='Comprovante recebido. O aviso ficou na fila para nova tentativa.';}
            $this->flash('mensagem',$mensagem);
        }
        catch(Throwable $e){@unlink($destino);error_log($e->getMessage());$this->flash('erro','Não foi possível anexar o comprovante a esta cobrança.');}
        $this->ir('minhas_cobrancas');
    }

    public function comprovantes(): void
    {
        $p=$this->profissional();$this->render('comprovantes',['comprovantes'=>$this->financeiro->comprovantesProfissional((int)$p['id'])]);
    }

    public function abrirComprovante(): void
    {
        $id=(int)($_GET['id']??0);if($id<1){http_response_code(404);exit('Comprovante não encontrado.');}
        if(empty($_SESSION['usuario'])){unset($_SESSION['abrir_arquivo_comprovante_apos_login']);$_SESSION['abrir_revisao_comprovante_apos_login']=$id;$this->ir('login');}
        $p=$this->profissional();if(!$this->financeiro->arquivoComprovante((int)$p['id'],$id)){http_response_code(404);exit('Comprovante não encontrado.');}
        $this->render('comprovantes',['comprovantes'=>$this->financeiro->comprovantesProfissional((int)$p['id'])]);
    }

    public function avaliarComprovante(): void
    {
        $p=$this->profissional();$this->csrf();
        try{$this->financeiro->avaliarComprovante((int)$p['id'],(int)($_POST['comprovante_id']??0),(string)($_POST['decisao']??''),(string)($_POST['observacao']??''));$this->flash('mensagem','Análise do comprovante registrada.');}
        catch(Throwable $e){error_log($e->getMessage());$this->flash('erro','Não foi possível analisar o comprovante. Atualize a lista e tente novamente.');}
        $this->ir('comprovantes');
    }

    public function baixarComprovante(): void
    {
        $id=(int)($_GET['id']??0);if($id<1){http_response_code(404);exit('Arquivo não encontrado.');}
        if(empty($_SESSION['usuario'])){unset($_SESSION['abrir_revisao_comprovante_apos_login']);$_SESSION['abrir_arquivo_comprovante_apos_login']=$id;$this->ir('login');}
        $p=$this->profissional();$f=$this->financeiro->arquivoComprovante((int)$p['id'],$id);
        if(!$f){http_response_code(404);exit('Arquivo não encontrado.');}
        $path=dirname(__DIR__,2).'/storage/comprovantes/'.basename($f['arquivo']);
        if(!is_file($path)){http_response_code(404);exit('Arquivo não encontrado.');}
        $mime=in_array($f['tipo_arquivo'],['application/pdf','image/jpeg','image/png'],true)?$f['tipo_arquivo']:'application/octet-stream';
        header('Content-Type: '.$mime);header('Content-Length: '.filesize($path));header('X-Content-Type-Options: nosniff');header('Content-Disposition: inline; filename="comprovante-'.$id.'.'.pathinfo($path,PATHINFO_EXTENSION).'"');readfile($path);exit;
    }

    public function relatorios(): void
    {
        $p=$this->profissional();$de=(string)($_GET['de']??date('Y-m-01'));$ate=(string)($_GET['ate']??date('Y-m-t'));
        if(!$this->dataValida($de))$de=date('Y-m-01');if(!$this->dataValida($ate))$ate=date('Y-m-t');
        $clientes=$this->financeiro->clientes((int)$p['id'],true);$clienteId=max(0,(int)($_GET['cliente_id']??0));
        if($clienteId>0&&!in_array($clienteId,array_map('intval',array_column($clientes,'id')),true))$clienteId=0;
        $serie=$this->financeiro->serieMensal((int)$p['id'],$de,$ate,$clienteId);
        $graficoMensal=array_map(static function(array $item): array { return ['mes'=>date('m/Y',strtotime($item['mes'].'-01')),'recebido'=>(float)$item['recebido'],'pendente'=>(float)$item['aberto']]; },$serie);
        $configuracao=$this->financeiro->configuracao((int)$p['id']);
        $this->render('relatorios',['resumo'=>$this->financeiro->resumo((int)$p['id'],$de,$ate,$clienteId),'cobrancas'=>$this->financeiro->cobrancasRelatorio((int)$p['id'],$de,$ate,$clienteId),'graficoMensal'=>$graficoMensal,'clientes'=>$clientes,'clienteId'=>$clienteId,'de'=>$de,'ate'=>$ate,'sugestoes'=>$this->financeiro->sugestoesRenovacao((int)$p['id'],$clienteId),'temInstrucoesPagamento'=>trim((string)($configuracao['instrucoes_pagamento']??''))!=='']);
    }

    public function calendario(): void
    {
        $p=$this->profissional();$clientes=$this->financeiro->clientes((int)$p['id'],true);$clienteId=max(0,(int)($_GET['cliente_id']??0));
        if($clienteId>0&&!in_array($clienteId,array_map('intval',array_column($clientes,'id')),true))$clienteId=0;
        $mesValor=$_GET['mes']??date('Y-m');$mesCalendario=is_string($mesValor)?$mesValor:date('Y-m');$anoMes=(int)substr($mesCalendario,0,4);$numeroMes=(int)substr($mesCalendario,5,2);
        if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$mesCalendario)||!checkdate($numeroMes,1,$anoMes))$mesCalendario=date('Y-m');
        $primeiroDia=new DateTimeImmutable($mesCalendario.'-01');$ultimoDia=$primeiroDia->modify('last day of this month')->format('Y-m-d');$diaInicialSemana=(int)$primeiroDia->format('N')-1;$diasNoMes=(int)$primeiroDia->format('t');$celulasCalendario=(int)(ceil(($diaInicialSemana+$diasNoMes)/7)*7);
        $calendarioMensal=[];$totaisCalendario=['comprovante_enviado'=>0.0,'confirmado'=>0.0,'sem_comprovante'=>0.0];
        foreach($this->financeiro->calendarioMensal((int)$p['id'],$primeiroDia->format('Y-m-d'),$ultimoDia,$clienteId) as $dia){
            $calendarioMensal[(int)$dia['dia']]=$dia;
            foreach($totaisCalendario as $chave=>$total)$totaisCalendario[$chave]=$total+(float)($dia[$chave]??0);
        }
        foreach($totaisCalendario as $chave=>$total)$totaisCalendario[$chave]=round($total,2);
        $this->render('calendario',['clientes'=>$clientes,'clienteId'=>$clienteId,'mesCalendario'=>$mesCalendario,'diaInicialSemana'=>$diaInicialSemana,'diasNoMes'=>$diasNoMes,'celulasCalendario'=>$celulasCalendario,'calendarioMensal'=>$calendarioMensal,'totaisCalendario'=>$totaisCalendario]);
    }

    public function configuracoes(): void
    {
        $p=$this->profissional();$erro='';$configuracao=$this->financeiro->configuracao((int)$p['id']);
        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            $this->csrf();
            $configuracao=['instrucoes_pagamento'=>(string)($_POST['instrucoes_pagamento']??''),'nome_recebedor'=>(string)($_POST['nome_recebedor']??''),'chave_pix_tipo'=>(string)($_POST['chave_pix_tipo']??''),'chave_pix'=>(string)($_POST['chave_pix']??''),'cidade_recebedor'=>(string)($_POST['cidade_recebedor']??'')];
            try{$this->financeiro->salvarConfiguracao((int)$p['id'],$configuracao['instrucoes_pagamento'],$configuracao['nome_recebedor'],$configuracao['cidade_recebedor'],$configuracao['chave_pix_tipo'],$configuracao['chave_pix']);$this->flash('mensagem','Recebimentos salvos.');$this->ir('configuracoes');}
            catch(InvalidArgumentException $e){$erro=$e->getMessage();}
            catch(Throwable $e){error_log('PagueProf salvar recebimentos: '.$e->getMessage());$erro='Não foi possível salvar. Confira os dados e a migração do banco.';}
        }
        if(empty($configuracao['nome_recebedor']))$configuracao['nome_recebedor']=(string)($p['nome']??'');
        $pixPayload='';
        if(!empty($configuracao['chave_pix_tipo'])&&!empty($configuracao['chave_pix'])&&!empty($configuracao['cidade_recebedor'])){
            try{$pixPayload=Pix::gerarPayload((string)$configuracao['nome_recebedor'],(string)$configuracao['cidade_recebedor'],(string)$configuracao['chave_pix_tipo'],(string)$configuracao['chave_pix']);}catch(Throwable $e){$erro=$erro?:'Revise os dados da chave Pix para gerar o QR Code.';}
        }
        $this->render('configuracoes',['configuracao'=>$configuracao,'erro'=>$erro,'pixPayload'=>$pixPayload,'nomeProfissional'=>(string)($p['nome']??'')]);
    }

    private function render(string $pagina,array $dados=[]): void
    {
        $usuario=$this->usuario();$mensagem=$_SESSION['mensagem']??'';$erroFlash=$_SESSION['erro']??'';unset($_SESSION['mensagem'],$_SESSION['erro']);
        extract($dados,EXTR_SKIP);require __DIR__.'/../Views/area.php';
    }

    private function usuario(): array
    {
        if(empty($_SESSION['usuario'])){$this->ir('login');}
        return $_SESSION['usuario'];
    }

    private function profissional(): array
    {
        $u=$this->usuario();if(!$this->temPerfil('profissional')){$this->flash('erro','Este acesso é exclusivo para profissional.');$this->ir('painel');}return $u;
    }

    private function cliente(): array
    {
        $u=$this->usuario();if(!$this->temPerfil('cliente')){$this->flash('erro','Este acesso é exclusivo para cliente.');$this->ir('painel');}return $u;
    }

    private function temPerfil(string $perfil): bool
    {
        return in_array($perfil,(array)($_SESSION['usuario']['perfis']??[]),true);
    }

    private function csrf(): void
    {
        if(!hash_equals((string)($_SESSION['csrf']??''),(string)($_POST['csrf']??''))){http_response_code(419);exit('A sessão expirou. Atualize a página.');}
    }

    private function flash(string $tipo,string $texto): void { $_SESSION[$tipo]=$texto; }
    private function ir(string $rota): void { header('Location: index.php?rota='.rawurlencode($rota));exit; }
    private function dataValida(string $data): bool { $d=DateTimeImmutable::createFromFormat('!Y-m-d',$data);return $d && $d->format('Y-m-d')===$data; }
}
