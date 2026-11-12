<?php
declare(strict_types=1);

final class Financeiro
{
    private function db(): PDO { return Database::conexao(); }

    public function configuracao(int $profissionalId): array
    {
        $s = $this->db()->prepare('INSERT IGNORE INTO configuracoes_profissional (profissional_id) VALUES (:id)');
        $s->execute(['id' => $profissionalId]);
        $s = $this->db()->prepare('SELECT * FROM configuracoes_profissional WHERE profissional_id=:id');
        $s->execute(['id' => $profissionalId]);
        return $s->fetch() ?: ['instrucoes_pagamento'=>'','nome_recebedor'=>null,'chave_pix_tipo'=>null,'chave_pix'=>null,'cidade_recebedor'=>null];
    }

    public function salvarConfiguracao(int $profissionalId, string $instrucoes, string $nomeRecebedor, string $cidade, string $tipoChave, string $chave): void
    {
        $instrucoes=trim($instrucoes);
        if(strlen($instrucoes)>1000)throw new InvalidArgumentException('As instruções devem ter até 1000 caracteres.');
        $tipoChave=strtolower(trim($tipoChave));$chave=trim($chave);
        $nome=null;$cidadeNormalizada=null;$chaveNormalizada=null;
        if($tipoChave!==''){
            $nome=Pix::normalizarNome($nomeRecebedor);
            $cidadeNormalizada=Pix::normalizarCidade($cidade);
            $chaveNormalizada=Pix::normalizarChave($tipoChave,$chave);
        }elseif($chave!==''){
            throw new InvalidArgumentException('Selecione se a chave Pix é CPF, telefone ou e-mail.');
        }
        $sql = 'INSERT INTO configuracoes_profissional (profissional_id,instrucoes_pagamento,nome_recebedor,chave_pix_tipo,chave_pix,cidade_recebedor,atualizado_em)
                VALUES (:id,:instr,:nome,:tipo,:chave,:cidade,NOW()) ON DUPLICATE KEY UPDATE instrucoes_pagamento=VALUES(instrucoes_pagamento),nome_recebedor=VALUES(nome_recebedor),chave_pix_tipo=VALUES(chave_pix_tipo),chave_pix=VALUES(chave_pix),cidade_recebedor=VALUES(cidade_recebedor),atualizado_em=NOW()';
        $this->db()->prepare($sql)->execute(['id'=>$profissionalId,'instr'=>$instrucoes,'nome'=>$nome,'tipo'=>$tipoChave!==''?$tipoChave:null,'chave'=>$chaveNormalizada,'cidade'=>$cidadeNormalizada]);
    }

    public function clientes(int $profissionalId,bool $incluirInativos=false): array
    {
        $filtroAtivo=$incluirInativos?'':' AND a.ativo=1';
        $sql = 'SELECT a.*, (SELECT COUNT(*) FROM cobrancas c WHERE c.cliente_id=a.id AND c.profissional_id=a.profissional_id AND c.status IN ("pendente","recusada","em_analise") AND c.data_vencimento<CURDATE()) AS dividas_vencidas,
                (SELECT MAX(c.data_vencimento) FROM cobrancas c WHERE c.cliente_id=a.id AND c.profissional_id=a.profissional_id) AS ultimo_vencimento
                FROM clientes a WHERE a.profissional_id=:p'.$filtroAtivo.' ORDER BY a.nome';
        $s=$this->db()->prepare($sql); $s->execute(['p'=>$profissionalId]); return $s->fetchAll();
    }

    private function vincularUsuarioComoCliente(PDO $pdo,int $usuarioId,int $profissionalId,bool $novoVinculo=true): void
    {
        $consulta=$pdo->prepare('SELECT p.nome FROM usuarios_perfis up JOIN perfis p ON p.id=up.perfil_id WHERE up.usuario_id=:id');
        $consulta->execute(['id'=>$usuarioId]);$perfis=array_map('strval',$consulta->fetchAll(PDO::FETCH_COLUMN));
        $consulta=$pdo->prepare('SELECT id FROM perfis WHERE nome="cliente" LIMIT 1');$consulta->execute();$perfilCliente=$consulta->fetchColumn();
        if(!$perfilCliente)throw new RuntimeException('Perfil cliente indisponível.');
        $vinculo=$pdo->prepare('INSERT IGNORE INTO usuarios_perfis (usuario_id,perfil_id) VALUES (:usuario,:perfil)');
        $vinculo->execute(['usuario'=>$usuarioId,'perfil'=>$perfilCliente]);$perfilAdicionado=$vinculo->rowCount()>0;
        if(in_array('profissional',$perfis,true)&&($novoVinculo||$perfilAdicionado)){
            $tipoAviso=in_array('cliente',$perfis,true)?'cliente_existente':'cliente';
            $pdo->prepare('INSERT INTO avisos_perfil (usuario_id,tipo,profissional_id) VALUES (:usuario,:tipo,:profissional)')->execute(['usuario'=>$usuarioId,'tipo'=>$tipoAviso,'profissional'=>$profissionalId]);
        }
    }

    public function criarCliente(int $profissionalId, string $nome, string $email, string $telefone, string $area_atuacao): int
    {
        $pdo=$this->db();
        $pdo->beginTransaction();
        try {
            $usuarioId=null;
            $s=$pdo->prepare('SELECT id FROM usuarios WHERE email=:email LIMIT 1');
            $s->execute(['email'=>mb_strtolower(trim($email))]);
            $existente=$s->fetch();
            if ($existente) {
                $usuarioId=(int)$existente['id'];
                $this->vincularUsuarioComoCliente($pdo,$usuarioId,$profissionalId);
            }
            $s=$pdo->prepare('INSERT INTO clientes (profissional_id,usuario_id,nome,email,telefone,area_atuacao) VALUES (:p,:u,:n,:e,:t,:m)');
            $s->execute(['p'=>$profissionalId,'u'=>$usuarioId,'n'=>trim($nome),'e'=>mb_strtolower(trim($email)),'t'=>trim($telefone) ?: null,'m'=>trim($area_atuacao) ?: null]);
            $id=(int)$pdo->lastInsertId();
            $pdo->commit(); return $id;
        } catch (Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
    }

    public function atualizarCliente(int $profissionalId,int $clienteId,string $nome,string $email,string $telefone,string $area_atuacao): void
    {
        $pdo=$this->db();$pdo->beginTransaction();
        try{
            $s=$pdo->prepare('SELECT * FROM clientes WHERE id=:id AND profissional_id=:p AND ativo=1 FOR UPDATE');
            $s->execute(['id'=>$clienteId,'p'=>$profissionalId]);$cliente=$s->fetch();
            if(!$cliente)throw new RuntimeException('Cliente não encontrado.');
            $s=$pdo->prepare('SELECT id FROM usuarios WHERE email=:email LIMIT 1');
            $s->execute(['email'=>mb_strtolower(trim($email))]);$usuarioExistente=$s->fetchColumn();
            $usuarioId=$usuarioExistente!==false?(int)$usuarioExistente:($cliente['usuario_id']?(int)$cliente['usuario_id']:null);
            if($usuarioExistente!==false){
                $novoVinculo=(int)($cliente['usuario_id']??0)!==(int)$usuarioId;
                $this->vincularUsuarioComoCliente($pdo,(int)$usuarioId,$profissionalId,$novoVinculo);
            }
            $s=$pdo->prepare('UPDATE clientes SET usuario_id=:u,nome=:n,email=:e,telefone=:t,area_atuacao=:a,atualizado_em=NOW() WHERE id=:id AND profissional_id=:p');
            $s->execute(['u'=>$usuarioId,'n'=>trim($nome),'e'=>mb_strtolower(trim($email)),'t'=>trim($telefone)?:null,'a'=>trim($area_atuacao)?:null,'id'=>$clienteId,'p'=>$profissionalId]);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public function desativarCliente(int $profissionalId,int $clienteId): void
    {
        $pdo=$this->db();$pdo->beginTransaction();
        try{
            $s=$pdo->prepare('UPDATE clientes SET ativo=0,atualizado_em=NOW() WHERE id=:id AND profissional_id=:p AND ativo=1');
            $s->execute(['id'=>$clienteId,'p'=>$profissionalId]);
            if($s->rowCount()!==1)throw new RuntimeException('Cliente não encontrado ou já desativado.');
            $pdo->prepare('UPDATE planos_cobranca SET ativo=0,atualizado_em=NOW() WHERE cliente_id=:id AND profissional_id=:p AND ativo=1')->execute(['id'=>$clienteId,'p'=>$profissionalId]);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public function clienteDoProfissional(int $profissionalId, int $clienteId): ?array
    {
        $s=$this->db()->prepare('SELECT * FROM clientes WHERE profissional_id=:p AND id=:a AND ativo=1');
        $s->execute(['p'=>$profissionalId,'a'=>$clienteId]); return $s->fetch() ?: null;
    }

    public function planos(int $profissionalId): array
    {
        $sql='SELECT pc.*,a.nome AS cliente_nome,a.email AS cliente_email,ts.nome AS tipo_servico_nome FROM planos_cobranca pc JOIN clientes a ON a.id=pc.cliente_id LEFT JOIN tipos_servico ts ON ts.id=pc.tipo_servico_id WHERE pc.profissional_id=:p AND pc.ativo=1 ORDER BY a.nome,pc.id DESC';
        $s=$this->db()->prepare($sql);$s->execute(['p'=>$profissionalId]);return $s->fetchAll();
    }

    public function criarPlano(int $profissionalId,int $clienteId,string $modalidade,float $valor,string $frequencia,int $intervalo,int $tipoServicoId=0): int
    {
        if (!$this->clienteDoProfissional($profissionalId,$clienteId)) throw new RuntimeException('Cliente não encontrado.');
        if (!in_array($modalidade,['mensal','por_atividade'],true) || !in_array($frequencia,['mensal'],true) || $valor<=0 || $intervalo<1 || $intervalo>12 || ($modalidade==='por_atividade' && !$this->tipoServicoDoProfissional($profissionalId,$tipoServicoId))) throw new InvalidArgumentException('Dados de plano inválidos.');
        $s=$this->db()->prepare('INSERT INTO planos_cobranca (profissional_id,cliente_id,modalidade,valor,frequencia,intervalo,tipo_servico_id) VALUES (:p,:a,:m,:v,:f,:i,:ts)');
        $s->execute(['p'=>$profissionalId,'a'=>$clienteId,'m'=>$modalidade,'v'=>number_format($valor,2,'.',''),'f'=>$frequencia,'i'=>$intervalo,'ts'=>$tipoServicoId?:null]);return (int)$this->db()->lastInsertId();
    }

    public function gerarCobrancas(int $profissionalId,int $planoId,string $dataInicial,int $quantidade,int $tipoServicoId=0,int $quantidadeAtividades=1,?float $valorSugerido=null): int
    {
        if(trim((string)($this->configuracao($profissionalId)['instrucoes_pagamento']??''))==='')throw new RuntimeException('Cadastre as instruções de pagamento antes de gerar cobranças.');
        $s=$this->db()->prepare('SELECT pc.*,a.nome FROM planos_cobranca pc JOIN clientes a ON a.id=pc.cliente_id WHERE pc.id=:id AND pc.profissional_id=:p AND pc.ativo=1');
        $s->execute(['id'=>$planoId,'p'=>$profissionalId]);$plano=$s->fetch();if(!$plano)throw new RuntimeException('Série não encontrada.');
        $data=DateTimeImmutable::createFromFormat('!Y-m-d',$dataInicial);
        if(!$data||$data->format('Y-m-d')!==$dataInicial||$quantidade<1||$quantidade>36)throw new InvalidArgumentException('Data ou quantidade inválida.');
        $modalidade=(string)$plano['modalidade'];$frequencia=(string)$plano['frequencia'];$intervalo=max(1,(int)$plano['intervalo']);$valorCobranca=(float)$plano['valor'];
        if($valorSugerido!==null){if($modalidade!=='mensal'||!is_finite($valorSugerido)||$valorSugerido<=0||$valorSugerido>99999999.99)throw new InvalidArgumentException('Informe um valor mensal válido.');$valorCobranca=$valorSugerido;}
        if($modalidade==='por_atividade'){
            if(!$this->tipoServicoDoProfissional($profissionalId,$tipoServicoId)||$quantidadeAtividades<1||$quantidadeAtividades>1000)throw new InvalidArgumentException('Tipo de serviço ou quantidade inválida.');
            $quantidade=min($quantidade,1);
        }else{
            $tipoServicoId=(int)($plano['tipo_servico_id']??0);$quantidadeAtividades=1;
        }
        $diaReferencia=(int)$data->format('d');$pdo=$this->db();$criados=0;$pdo->beginTransaction();
        try{
            for($i=0;$i<$quantidade;$i++){
                $venc=$data->format('Y-m-d');
                $dup=$pdo->prepare('SELECT id FROM cobrancas WHERE plano_id=:plano AND data_vencimento=:data LIMIT 1');$dup->execute(['plano'=>$planoId,'data'=>$venc]);
                if(!$dup->fetch()){
                    $ref='pp-'.$planoId.'-'.bin2hex(random_bytes(12));
                    $valor=$modalidade==='por_atividade'?number_format((float)$plano['valor']*$quantidadeAtividades,2,'.',''):number_format($valorCobranca,2,'.','');
                    $ins=$pdo->prepare('INSERT INTO cobrancas (referencia_externa,profissional_id,cliente_id,plano_id,valor,data_vencimento,status,tipo_servico_id,quantidade_atividades) VALUES (:ref,:p,:a,:pl,:v,:d,"pendente",:ts,:qa)');
                    $ins->execute(['ref'=>$ref,'p'=>$profissionalId,'a'=>$plano['cliente_id'],'pl'=>$planoId,'v'=>$valor,'d'=>$venc,'ts'=>$tipoServicoId?:null,'qa'=>$modalidade==='por_atividade'?$quantidadeAtividades:null]);$cobrancaId=(int)$pdo->lastInsertId();
                    $notificacao=$pdo->prepare('INSERT IGNORE INTO notificacoes_email (cobranca_id,tipo,status,criado_em) VALUES (:id,"cobranca_gerada",IF(:ordem=1,"pendente","aguardando_envio_profissional"),NOW())');$notificacao->execute(['id'=>$cobrancaId,'ordem'=>$criados+1]);$criados++;
                }
                if($frequencia==='mensal'){$proximo=$data->modify('first day of this month')->modify('+'.$intervalo.' months');$data=$proximo->setDate((int)$proximo->format('Y'),(int)$proximo->format('m'),min($diaReferencia,(int)$proximo->format('t')));}
                elseif($frequencia==='quinzenal')$data=$data->modify('+'.(15*$intervalo).' days');
                elseif($frequencia==='semanal')$data=$data->modify('+'.(7*$intervalo).' days');
                else $data=$data->modify('+'.$intervalo.' days');
            }
            if($criados>0&&$valorSugerido!==null){$pdo->prepare('UPDATE planos_cobranca SET valor=:valor,atualizado_em=NOW() WHERE id=:id AND profissional_id=:p')->execute(['valor'=>number_format($valorSugerido,2,'.',''),'id'=>$planoId,'p'=>$profissionalId]);}
            $pdo->commit();return $criados;
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public function gerarCobrancasDiretas(int $profissionalId,int $clienteId,int $tipoServicoId,float $valor,string $primeiroVencimento,int $quantidade): array
    {
        $lotes=$this->gerarCobrancasDiretasEmLote($profissionalId,[$clienteId],$tipoServicoId,$valor,$primeiroVencimento,$quantidade);
        return $lotes[0];
    }

    public function gerarCobrancasDiretasEmLote(int $profissionalId,array $clienteIds,int $tipoServicoId,float $valor,string $primeiroVencimento,int $quantidade): array
    {
        if(trim((string)($this->configuracao($profissionalId)['instrucoes_pagamento']??''))==='')throw new RuntimeException('Cadastre as instruções de pagamento antes de gerar cobranças.');
        $ids=[];
        foreach($clienteIds as $id){
            if(!is_scalar($id)||!preg_match('/^[1-9]\d*$/D',(string)$id))throw new InvalidArgumentException('Seleção de clientes inválida.');
            $ids[]=(int)$id;
        }
        $ids=array_values(array_unique($ids));
        if(!$ids)throw new InvalidArgumentException('Selecione pelo menos um cliente.');
        foreach($ids as $clienteId)if(!$this->clienteDoProfissional($profissionalId,$clienteId))throw new RuntimeException('Um ou mais clientes selecionados estão inativos ou não pertencem a este profissional.');
        if(!$this->tipoServicoDoProfissional($profissionalId,$tipoServicoId))throw new RuntimeException('Tipo de serviço inválido.');
        if(!is_finite($valor)||$valor<=0||$valor>99999999.99)throw new InvalidArgumentException('Valor de serviço inválido.');
        $dataInicial=DateTimeImmutable::createFromFormat('!Y-m-d',$primeiroVencimento);
        if(!$dataInicial||$dataInicial->format('Y-m-d')!==$primeiroVencimento||$quantidade<1||$quantidade>36)throw new InvalidArgumentException('Vencimento ou quantidade inválida.');

        $pdo=$this->db();$pdo->beginTransaction();$lotes=[];
        try{
            $inserirPlano=$pdo->prepare('INSERT INTO planos_cobranca (profissional_id,cliente_id,modalidade,valor,frequencia,intervalo,tipo_servico_id) VALUES (:profissional,:cliente,"mensal",:valor,"mensal",1,:servico)');
            $inserirCobranca=$pdo->prepare('INSERT INTO cobrancas (referencia_externa,profissional_id,cliente_id,plano_id,valor,data_vencimento,status,tipo_servico_id,quantidade_atividades) VALUES (:ref,:profissional,:cliente,:plano,:valor,:vencimento,"pendente",:servico,NULL)');
            $enfileirar=$pdo->prepare('INSERT INTO notificacoes_email (cobranca_id,tipo,status,criado_em) VALUES (:cobranca,"cobranca_gerada",IF(:ordem=1,"pendente","aguardando_envio_profissional"),NOW())');
            foreach($ids as $clienteId){
                $inserirPlano->execute(['profissional'=>$profissionalId,'cliente'=>$clienteId,'valor'=>number_format($valor,2,'.',''),'servico'=>$tipoServicoId]);
                $planoId=(int)$pdo->lastInsertId();$data=$dataInicial;$diaReferencia=(int)$dataInicial->format('d');$notificacaoInicialId=0;
                for($i=0;$i<$quantidade;$i++){
                    $referencia='pp-'.$planoId.'-'.bin2hex(random_bytes(12));
                    $inserirCobranca->execute(['ref'=>$referencia,'profissional'=>$profissionalId,'cliente'=>$clienteId,'plano'=>$planoId,'valor'=>number_format($valor,2,'.',''),'vencimento'=>$data->format('Y-m-d'),'servico'=>$tipoServicoId]);
                    $cobrancaId=(int)$pdo->lastInsertId();
                    $enfileirar->execute(['cobranca'=>$cobrancaId,'ordem'=>$i+1]);
                    if($i===0)$notificacaoInicialId=(int)$pdo->lastInsertId();
                    $proximo=$data->modify('first day of this month')->modify('+1 month');
                    $data=$proximo->setDate((int)$proximo->format('Y'),(int)$proximo->format('m'),min($diaReferencia,(int)$proximo->format('t')));
                }
                $lotes[]=['cliente_id'=>$clienteId,'quantidade'=>$quantidade,'notificacao_inicial_id'=>$notificacaoInicialId];
            }
            $pdo->commit();return $lotes;
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public function cobrancasProfissional(int $profissionalId, string $de='', string $ate='', string $status='',int $clienteId=0,string $ordenar='vencimento_desc'): array
    {
        $where=['c.profissional_id=:p'];$args=['p'=>$profissionalId];
        if($de!=='' && $this->dataValida($de)){$where[]='c.data_vencimento>=:de';$args['de']=$de;}
        if($ate!=='' && $this->dataValida($ate)){$where[]='c.data_vencimento<=:ate';$args['ate']=$ate;}
        if(in_array($status,['pendente','paga','em_analise','recusada','cancelada'],true)){$where[]='c.status=:status';$args['status']=$status;}
        if($clienteId>0){$where[]='c.cliente_id=:cliente';$args['cliente']=$clienteId;}
        $ordens=['vencimento_desc'=>'c.data_vencimento DESC,c.id DESC','vencimento_asc'=>'c.data_vencimento ASC,c.id ASC','cliente_asc'=>'a.nome ASC,c.data_vencimento DESC','cliente_desc'=>'a.nome DESC,c.data_vencimento DESC','valor_desc'=>'c.valor DESC,c.data_vencimento DESC','valor_asc'=>'c.valor ASC,c.data_vencimento DESC','status'=>'CASE c.status WHEN "pendente" THEN 1 WHEN "em_analise" THEN 2 WHEN "recusada" THEN 3 WHEN "paga" THEN 4 ELSE 5 END,c.data_vencimento DESC'];
        if(!isset($ordens[$ordenar]))$ordenar='vencimento_desc';
        $sql='SELECT c.*,a.nome AS cliente_nome,a.email AS cliente_email,pc.modalidade,pc.frequencia,ts.nome AS tipo_servico_nome,n.id AS notificacao_id,n.status AS notificacao_status,n.tentativas AS notificacao_tentativas,
                     (SELECT COUNT(*) FROM notificacoes_email nx WHERE nx.cobranca_id=c.id AND nx.tipo IN ("cobranca_gerada","lembrete_vencimento") AND nx.status="enviada") AS emails_enviados,
                     (SELECT COALESCE(SUM(nx.tentativas),0) FROM notificacoes_email nx WHERE nx.cobranca_id=c.id AND nx.tipo IN ("cobranca_gerada","lembrete_vencimento")) AS emails_tentativas,
                     (SELECT cp.id FROM comprovantes cp WHERE cp.cobranca_id=c.id ORDER BY cp.enviado_em DESC,cp.id DESC LIMIT 1) AS comprovante_id,
                     (SELECT COUNT(*) FROM comprovantes cp WHERE cp.cobranca_id=c.id) AS comprovantes_enviados
              FROM cobrancas c JOIN clientes a ON a.id=c.cliente_id LEFT JOIN planos_cobranca pc ON pc.id=c.plano_id LEFT JOIN tipos_servico ts ON ts.id=c.tipo_servico_id LEFT JOIN notificacoes_email n ON n.cobranca_id=c.id AND n.tipo="cobranca_gerada" WHERE '.implode(' AND ',$where).' ORDER BY '.$ordens[$ordenar];
        $s=$this->db()->prepare($sql);$s->execute($args);return $s->fetchAll();
    }

    public function cobrancaParaEdicao(int $profissionalId,int $cobrancaId): ?array
    {
        $sql='SELECT c.*,a.nome AS cliente_nome,ts.nome AS tipo_servico_nome,
                     (SELECT COUNT(*) FROM notificacoes_email nx WHERE nx.cobranca_id=c.id AND nx.tipo IN ("cobranca_gerada","lembrete_vencimento") AND nx.status="enviada") AS emails_enviados,
                     (SELECT COALESCE(SUM(nx.tentativas),0) FROM notificacoes_email nx WHERE nx.cobranca_id=c.id AND nx.tipo IN ("cobranca_gerada","lembrete_vencimento")) AS emails_tentativas,
                     (SELECT COUNT(*) FROM comprovantes cp WHERE cp.cobranca_id=c.id) AS comprovantes_enviados
              FROM cobrancas c JOIN clientes a ON a.id=c.cliente_id LEFT JOIN tipos_servico ts ON ts.id=c.tipo_servico_id
              WHERE c.id=:id AND c.profissional_id=:p LIMIT 1';
        $s=$this->db()->prepare($sql);$s->execute(['id'=>$cobrancaId,'p'=>$profissionalId]);$c=$s->fetch();
        return $c&&$this->cobrancaPodeSerEditada($c)?$c:null;
    }

    public function atualizarCobrancaNaoEnviada(int $profissionalId,int $cobrancaId,float $valor,string $dataVencimento,int $tipoServicoId): void
    {
        $data=DateTimeImmutable::createFromFormat('!Y-m-d',$dataVencimento);
        if(!is_finite($valor)||$valor<=0||$valor>99999999.99||!$data||$data->format('Y-m-d')!==$dataVencimento)throw new InvalidArgumentException('Valor ou vencimento inválido.');
        $pdo=$this->db();$pdo->beginTransaction();
        try{
            $s=$pdo->prepare('SELECT id,status,plano_id,data_vencimento,tipo_servico_id FROM cobrancas WHERE id=:id AND profissional_id=:p FOR UPDATE');
            $s->execute(['id'=>$cobrancaId,'p'=>$profissionalId]);$c=$s->fetch();
            if(!$c)throw new RuntimeException('Cobrança não encontrada.');
            if($c['status']!=='pendente')throw new RuntimeException('Somente cobranças pendentes podem ser editadas.');
            $s=$pdo->prepare('SELECT status,tentativas FROM notificacoes_email WHERE cobranca_id=:id AND tipo IN ("cobranca_gerada","lembrete_vencimento") FOR UPDATE');
            $s->execute(['id'=>$cobrancaId]);$notificacoes=$s->fetchAll();
            foreach($notificacoes as $n){if($n['status']==='enviada'||$n['status']==='enviando'||(int)$n['tentativas']>0)throw new RuntimeException('Uma notificação desta cobrança já foi iniciada ou enviada.');}
            $s=$pdo->prepare('SELECT id FROM comprovantes WHERE cobranca_id=:id LIMIT 1 FOR UPDATE');$s->execute(['id'=>$cobrancaId]);
            if($s->fetch())throw new RuntimeException('Cobranças com comprovante não podem ser editadas.');
            if($tipoServicoId>0&&!$this->tipoServicoDoProfissional($profissionalId,$tipoServicoId)&&(int)($c['tipo_servico_id']??0)!==$tipoServicoId)throw new RuntimeException('Tipo de serviço inválido.');
            if($c['plano_id']!==null){
                $s=$pdo->prepare('SELECT id FROM cobrancas WHERE plano_id=:plano AND data_vencimento=:data AND id<>:id LIMIT 1');
                $s->execute(['plano'=>$c['plano_id'],'data'=>$dataVencimento,'id'=>$cobrancaId]);
                if($s->fetch())throw new RuntimeException('Já existe uma cobrança desta série com esse vencimento.');
            }
            $s=$pdo->prepare('UPDATE cobrancas SET valor=:valor,data_vencimento=:data,tipo_servico_id=:servico,atualizado_em=NOW() WHERE id=:id AND profissional_id=:p');
            $s->execute(['valor'=>number_format($valor,2,'.',''),'data'=>$dataVencimento,'servico'=>$tipoServicoId?:null,'id'=>$cobrancaId,'p'=>$profissionalId]);
            if($c['data_vencimento']!==$dataVencimento&&$dataVencimento!==(new DateTimeImmutable('tomorrow'))->format('Y-m-d')){
                $pdo->prepare('DELETE FROM notificacoes_email WHERE cobranca_id=:id AND tipo="lembrete_vencimento" AND tentativas=0 AND status IN ("pendente","falhou")')->execute(['id'=>$cobrancaId]);
            }
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    private function cobrancaPodeSerEditada(array $c): bool
    {
        return ($c['status']??'')==='pendente'
            &&(int)($c['emails_enviados']??0)===0
            &&(int)($c['emails_tentativas']??0)===0
            &&(int)($c['comprovantes_enviados']??0)===0;
    }

    public function cancelarCobranca(int $profissionalId,int $cobrancaId): void
    {
        $pdo=$this->db();$pdo->beginTransaction();
        try{
            $s=$pdo->prepare('SELECT status FROM cobrancas WHERE id=:id AND profissional_id=:p FOR UPDATE');
            $s->execute(['id'=>$cobrancaId,'p'=>$profissionalId]);$status=$s->fetchColumn();
            if($status===false)throw new RuntimeException('Cobrança não encontrada.');
            if($status==='paga')throw new RuntimeException('Uma cobrança paga não pode ser cancelada.');
            if($status==='cancelada')throw new RuntimeException('A cobrança já está cancelada.');
            $pdo->prepare('UPDATE cobrancas SET status="cancelada",atualizado_em=NOW() WHERE id=:id AND profissional_id=:p')->execute(['id'=>$cobrancaId,'p'=>$profissionalId]);
            $pdo->prepare('UPDATE notificacoes_email SET status="ignorada",ultimo_erro="Cobrança cancelada pelo profissional" WHERE cobranca_id=:id AND status IN ("pendente","falhou","aguardando_envio_profissional")')->execute(['id'=>$cobrancaId]);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public function notificacaoInicialRecente(int $profissionalId,int $planoId): ?int
    {
        $s=$this->db()->prepare('SELECT n.id FROM notificacoes_email n JOIN cobrancas c ON c.id=n.cobranca_id WHERE c.profissional_id=:p AND c.plano_id=:pl AND n.tipo="cobranca_gerada" AND n.status="pendente" ORDER BY c.id DESC LIMIT 1');
        $s->execute(['p'=>$profissionalId,'pl'=>$planoId]);$id=$s->fetchColumn();return $id===false?null:(int)$id;
    }

    public function profissionaisDoCliente(int $usuarioId): array
    {
        $sql='SELECT DISTINCT u.id,u.nome FROM clientes a JOIN usuarios u ON u.id=a.profissional_id WHERE a.usuario_id=:u ORDER BY u.nome,u.id';
        $s=$this->db()->prepare($sql);$s->execute(['u'=>$usuarioId]);return $s->fetchAll();
    }

    public function cobrancasCliente(int $usuarioId,int $profissionalId=0,string $vencimentoInicial='',string $vencimentoFinal='',string $status='a_pagar'): array
    {
        $where=['a.usuario_id=:u'];$args=['u'=>$usuarioId];
        if($profissionalId>0){$where[]='c.profissional_id=:profissional';$args['profissional']=$profissionalId;}
        if($vencimentoInicial!==''&&$this->dataValida($vencimentoInicial)){$where[]='c.data_vencimento>=:vencimento_inicial';$args['vencimento_inicial']=$vencimentoInicial;}
        if($vencimentoFinal!==''&&$this->dataValida($vencimentoFinal)){$where[]='c.data_vencimento<=:vencimento_final';$args['vencimento_final']=$vencimentoFinal;}
        $hoje=date('Y-m-d');
        switch($status){
            case 'a_pagar':$where[]='c.status="pendente"';break;
            case 'em_analise':$where[]='c.status="em_analise"';break;
            case 'vencida':$where[]='c.status IN ("pendente","em_analise") AND c.data_vencimento<:hoje';$args['hoje']=$hoje;break;
            case 'paga':$where[]='c.status="paga"';break;
            case 'recusada':$where[]='c.status="recusada"';break;
            case 'cancelada':$where[]='c.status="cancelada"';break;
        }
        $sql='SELECT c.*,a.profissional_id,a.email AS email_cliente,a.nome AS cliente_nome,u.nome AS profissional_nome,cp.instrucoes_pagamento,cp.nome_recebedor,cp.chave_pix_tipo,cp.chave_pix,cp.cidade_recebedor,ts.nome AS tipo_servico_nome
              FROM cobrancas c JOIN clientes a ON a.id=c.cliente_id JOIN usuarios u ON u.id=c.profissional_id
              LEFT JOIN configuracoes_profissional cp ON cp.profissional_id=c.profissional_id LEFT JOIN tipos_servico ts ON ts.id=c.tipo_servico_id
              WHERE '.implode(' AND ',$where).' ORDER BY c.data_vencimento DESC,c.id DESC';
        $s=$this->db()->prepare($sql);$s->execute($args);return $s->fetchAll();
    }

    public function cobrancaDoCliente(int $usuarioId,int $cobrancaId): ?array
    {
        $s=$this->db()->prepare('SELECT c.*,a.usuario_id,a.profissional_id,a.email AS email_cliente FROM cobrancas c JOIN clientes a ON a.id=c.cliente_id WHERE c.id=:c AND a.usuario_id=:u');
        $s->execute(['c'=>$cobrancaId,'u'=>$usuarioId]);return $s->fetch()?:null;
    }

    public function comprovanteDoCliente(int $usuarioId,int $cobrancaId,string $arquivo,string $original,string $mime,int $bytes): int
    {
        $c=$this->cobrancaDoCliente($usuarioId,$cobrancaId);if(!$c)throw new RuntimeException('Cobrança não encontrada.');
        if(in_array($c['status'],['paga','cancelada','em_analise'],true))throw new RuntimeException('Esta cobrança não aceita comprovante neste momento.');
        $pdo=$this->db();$pdo->beginTransaction();
        try{
            $s=$pdo->prepare('INSERT INTO comprovantes (cobranca_id,cliente_usuario_id,arquivo,nome_original,tipo_arquivo,tamanho_bytes,status) VALUES (:c,:u,:a,:n,:t,:b,"em_analise")');
            $s->execute(['c'=>$cobrancaId,'u'=>$usuarioId,'a'=>$arquivo,'n'=>substr($original,0,255),'t'=>$mime,'b'=>$bytes]);
            $id=(int)$pdo->lastInsertId();
            $pdo->prepare('UPDATE cobrancas SET status="em_analise",atualizado_em=NOW() WHERE id=:id')->execute(['id'=>$cobrancaId]);
            $tipo='comprovante_'.$id;
            $s=$pdo->prepare('INSERT INTO notificacoes_email (cobranca_id,comprovante_id,tipo,status,criado_em) VALUES (:c,:cp,:tipo,"pendente",NOW())');
            $s->execute(['c'=>$cobrancaId,'cp'=>$id,'tipo'=>$tipo]);
            $pdo->commit();return $id;
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public function comprovantesProfissional(int $profissionalId,string $status='em_analise',string $de='',string $ate=''): array
    {
        $where=['c.profissional_id=:p'];$args=['p'=>$profissionalId];
        if(in_array($status,['em_analise','aprovado','rejeitado'],true)){$where[]='cp.status=:status';$args['status']=$status;}
        if($de!==''&&$this->dataValida($de)){$where[]='cp.enviado_em>=:de';$args['de']=$de.' 00:00:00';}
        if($ate!==''&&$this->dataValida($ate)){$where[]='cp.enviado_em<DATE_ADD(:ate,INTERVAL 1 DAY)';$args['ate']=$ate.' 00:00:00';}
        $sql='SELECT cp.*,c.valor,c.data_vencimento,c.id AS cobranca_id,a.nome AS cliente_nome,a.email AS cliente_email
              FROM comprovantes cp JOIN cobrancas c ON c.id=cp.cobranca_id JOIN clientes a ON a.id=c.cliente_id
              WHERE '.implode(' AND ',$where).' ORDER BY (cp.status="em_analise") DESC,cp.enviado_em DESC,cp.id DESC';
        $s=$this->db()->prepare($sql);$s->execute($args);return $s->fetchAll();
    }

    public function avaliarComprovante(int $profissionalId,int $comprovanteId,string $decisao,string $observacao): void
    {
        if(!in_array($decisao,['aprovado','rejeitado'],true))throw new InvalidArgumentException('Decisão inválida.');
        $pdo=$this->db();$pdo->beginTransaction();
        try{
            $s=$pdo->prepare('SELECT cp.id,cp.cobranca_id,cp.status FROM comprovantes cp JOIN cobrancas c ON c.id=cp.cobranca_id WHERE cp.id=:id AND c.profissional_id=:p FOR UPDATE');
            $s->execute(['id'=>$comprovanteId,'p'=>$profissionalId]);$row=$s->fetch();if(!$row || $row['status']!=='em_analise')throw new RuntimeException('Comprovante não está aguardando análise.');
            $pdo->prepare('UPDATE comprovantes SET status=:s,observacao_profissional=:o,avaliado_em=NOW() WHERE id=:id')->execute(['s'=>$decisao,'o'=>substr(trim($observacao),0,500)?:null,'id'=>$comprovanteId]);
            if($decisao==='aprovado'){$pdo->prepare('UPDATE cobrancas SET status="paga",data_pagamento=NOW(),atualizado_em=NOW() WHERE id=:id')->execute(['id'=>$row['cobranca_id']]);$pdo->prepare('UPDATE notificacoes_email SET status="ignorada",ultimo_erro="Cobrança confirmada como paga" WHERE cobranca_id=:id AND comprovante_id IS NULL AND tipo IN ("cobranca_gerada","lembrete_vencimento") AND status IN ("pendente","falhou","aguardando_envio_profissional")')->execute(['id'=>$row['cobranca_id']]);}
            else $pdo->prepare('UPDATE cobrancas SET status="recusada",atualizado_em=NOW() WHERE id=:id')->execute(['id'=>$row['cobranca_id']]);
            $pdo->commit();
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public function arquivoComprovante(int $profissionalId,int $comprovanteId): ?array
    {
        $s=$this->db()->prepare('SELECT cp.* FROM comprovantes cp JOIN cobrancas c ON c.id=cp.cobranca_id WHERE cp.id=:id AND c.profissional_id=:p');
        $s->execute(['id'=>$comprovanteId,'p'=>$profissionalId]);return $s->fetch()?:null;
    }

    public function resumo(int $profissionalId,string $de,string $ate,int $clienteId=0): array
    {
        $s=$this->db()->prepare('SELECT COUNT(*) FROM clientes WHERE profissional_id=:p AND ativo=1');$s->execute(['p'=>$profissionalId]);
        $clientesAtivos=(int)$s->fetchColumn();$where='profissional_id=:p';$args=['p'=>$profissionalId];
        if($clienteId>0){$where.=' AND cliente_id=:cliente';$args['cliente']=$clienteId;}
        $condicaoRecebido='status="paga"';$condicaoAberto='status IN ("pendente","em_analise","recusada")';$condicaoVencido='status IN ("pendente","em_analise") AND data_vencimento<CURDATE()';$condicaoRejeitado='status="recusada"';
        if($de!==''&&$this->dataValida($de)){$condicaoRecebido.=' AND data_pagamento>=:de_pago';$condicaoAberto.=' AND data_vencimento>=:de_aberto';$condicaoVencido.=' AND data_vencimento>=:de_vencido';$condicaoRejeitado.=' AND data_vencimento>=:de_rejeitado';$args+=['de_pago'=>$de,'de_aberto'=>$de,'de_vencido'=>$de,'de_rejeitado'=>$de];}
        if($ate!==''&&$this->dataValida($ate)){$condicaoRecebido.=' AND data_pagamento<DATE_ADD(:ate_pago,INTERVAL 1 DAY)';$condicaoAberto.=' AND data_vencimento<=:ate_aberto';$condicaoVencido.=' AND data_vencimento<=:ate_vencido';$condicaoRejeitado.=' AND data_vencimento<=:ate_rejeitado';$args+=['ate_pago'=>$ate,'ate_aberto'=>$ate,'ate_vencido'=>$ate,'ate_rejeitado'=>$ate];}
        $sql='SELECT COALESCE(SUM(CASE WHEN '.$condicaoRecebido.' THEN valor ELSE 0 END),0) recebido,
            COALESCE(SUM(CASE WHEN '.$condicaoAberto.' THEN valor ELSE 0 END),0) aberto,
            COALESCE(SUM(CASE WHEN '.$condicaoVencido.' THEN valor ELSE 0 END),0) vencido,
            COALESCE(SUM(CASE WHEN '.$condicaoRejeitado.' THEN valor ELSE 0 END),0) rejeitado
            FROM cobrancas WHERE '.$where;
        $s=$this->db()->prepare($sql);$s->execute($args);$r=$s->fetch()?:[];$r['total_geral']=round((float)($r['recebido']??0)+(float)($r['aberto']??0),2);$r['clientes_ativos']=$clientesAtivos;$r['total_clientes']=$clientesAtivos;return $r;
    }

    public function clientesComCobrancaPeriodo(int $profissionalId,string $de,string $ate,int $clienteId=0,string $contexto='relatorios',string $status=''): int
    {
        if(($de!==''&&!$this->dataValida($de))||($ate!==''&&!$this->dataValida($ate)))return 0;
        if($contexto!=='financeiro'&&(!$this->dataValida($de)||!$this->dataValida($ate)))return 0;
        $where=['c.profissional_id=:p','a.ativo=1'];$args=['p'=>$profissionalId];
        if($clienteId>0){$where[]='c.cliente_id=:cliente';$args['cliente']=$clienteId;}
        if($contexto==='financeiro'){
            if($de!==''){$where[]='c.data_vencimento>=:de';$args['de']=$de;}
            if($ate!==''){$where[]='c.data_vencimento<=:ate';$args['ate']=$ate;}
            if(in_array($status,['pendente','paga','em_analise','recusada','cancelada'],true)){$where[]='c.status=:status';$args['status']=$status;}
        }elseif($contexto==='calendario'){
            $where[]='c.data_vencimento>=:de';$where[]='c.data_vencimento<=:ate';$where[]='c.status<>"cancelada"';$args['de']=$de;$args['ate']=$ate;
        }else{
            $where[]='((c.status="paga" AND c.data_pagamento>=:de_pago AND c.data_pagamento<DATE_ADD(:ate_pago,INTERVAL 1 DAY)) OR (c.status IN ("pendente","em_analise","recusada") AND c.data_vencimento BETWEEN :de_venc AND :ate_venc))';
            $args+=['de_pago'=>$de,'ate_pago'=>$ate,'de_venc'=>$de,'ate_venc'=>$ate];
        }
        $sql='SELECT COUNT(DISTINCT c.cliente_id) FROM cobrancas c JOIN clientes a ON a.id=c.cliente_id WHERE '.implode(' AND ',$where);
        $s=$this->db()->prepare($sql);$s->execute($args);return (int)$s->fetchColumn();
    }

    public function serieMensal(int $profissionalId,string $de,string $ate,int $clienteId=0): array
    {
        $filtroRecebido='';$filtroAberto='';$args=['p1'=>$profissionalId,'de1'=>$de,'ate1'=>$ate,'p2'=>$profissionalId,'de2'=>$de,'ate2'=>$ate];
        if($clienteId>0){$filtroRecebido=' AND cliente_id=:cliente1';$filtroAberto=' AND cliente_id=:cliente2';$args['cliente1']=$clienteId;$args['cliente2']=$clienteId;}
        $sql='SELECT DATE_FORMAT(mes,"%Y-%m") AS mes, SUM(recebido) AS recebido, SUM(aberto) AS aberto, SUM(vencido) AS vencido FROM (SELECT DATE_FORMAT(data_pagamento,"%Y-%m-01") mes,valor recebido,0 aberto,0 vencido FROM cobrancas WHERE profissional_id=:p1 AND status="paga" AND data_pagamento>=:de1 AND data_pagamento<DATE_ADD(:ate1,INTERVAL 1 DAY)'.$filtroRecebido.' UNION ALL SELECT DATE_FORMAT(data_vencimento,"%Y-%m-01"),0,valor,IF(data_vencimento<CURDATE() AND status IN ("pendente","em_analise"),valor,0) FROM cobrancas WHERE profissional_id=:p2 AND status IN ("pendente","em_analise","recusada") AND data_vencimento BETWEEN :de2 AND :ate2'.$filtroAberto.') x GROUP BY mes ORDER BY mes';
        $s=$this->db()->prepare($sql);$s->execute($args);return $s->fetchAll();
    }

    public function comprovantesNoFinanceiro(int $profissionalId,int $clienteId=0): array
    {
        $sql='SELECT cp.*,c.id AS cobranca_id,c.valor,c.data_vencimento,c.status AS cobranca_status,a.nome AS cliente_nome FROM comprovantes cp JOIN cobrancas c ON c.id=cp.cobranca_id JOIN clientes a ON a.id=c.cliente_id WHERE c.profissional_id=:p';$args=['p'=>$profissionalId];if($clienteId>0){$sql.=' AND c.cliente_id=:cliente';$args['cliente']=$clienteId;}$sql.=' ORDER BY cp.enviado_em DESC';$s=$this->db()->prepare($sql);$s->execute($args);return $s->fetchAll();
    }

    public function cobrancasRelatorio(int $profissionalId,string $de,string $ate,int $clienteId=0): array
    {
        $filtroCliente='';$args=['p'=>$profissionalId,'de_pago'=>$de,'ate_pago'=>$ate,'de_venc'=>$de,'ate_venc'=>$ate];
        if($clienteId>0){$filtroCliente=' AND c.cliente_id=:cliente';$args['cliente']=$clienteId;}
        $sql='SELECT c.*,a.nome AS cliente_nome,a.email AS cliente_email,pc.modalidade,pc.frequencia FROM cobrancas c JOIN clientes a ON a.id=c.cliente_id LEFT JOIN planos_cobranca pc ON pc.id=c.plano_id
              WHERE c.profissional_id=:p'.$filtroCliente.' AND ((c.status="paga" AND c.data_pagamento>=:de_pago AND c.data_pagamento<DATE_ADD(:ate_pago,INTERVAL 1 DAY)) OR (c.status IN ("pendente","em_analise","recusada") AND c.data_vencimento BETWEEN :de_venc AND :ate_venc)) ORDER BY COALESCE(c.data_pagamento,c.data_vencimento) DESC,c.id DESC';
        $s=$this->db()->prepare($sql);$s->execute($args);return $s->fetchAll();
    }

    public function calendarioMensal(int $profissionalId,string $de,string $ate,int $clienteId=0): array
    {
        $filtroCliente='';$args=['p'=>$profissionalId,'de'=>$de,'ate'=>$ate];
        if($clienteId>0){$filtroCliente=' AND c.cliente_id=:cliente';$args['cliente']=$clienteId;}
        $sql='SELECT DAY(c.data_vencimento) AS dia,
                     COALESCE(SUM(CASE WHEN c.status="paga" THEN c.valor ELSE 0 END),0) AS confirmado,
                     COALESCE(SUM(CASE WHEN c.status="em_analise" AND c.data_vencimento>=CURDATE() THEN c.valor ELSE 0 END),0) AS comprovante_enviado,
                     COALESCE(SUM(CASE WHEN c.status="pendente" AND c.data_vencimento>=CURDATE() THEN c.valor ELSE 0 END),0) AS sem_comprovante,
                     COALESCE(SUM(CASE WHEN c.status IN ("pendente","em_analise") AND c.data_vencimento<CURDATE() THEN c.valor ELSE 0 END),0) AS vencido,
                     COALESCE(SUM(CASE WHEN c.status="recusada" THEN c.valor ELSE 0 END),0) AS rejeitado,
                     COALESCE(SUM(c.valor),0) AS total_geral
              FROM cobrancas c
              WHERE c.profissional_id=:p AND c.data_vencimento BETWEEN :de AND :ate AND c.status<>"cancelada"'.$filtroCliente.'
              GROUP BY DAY(c.data_vencimento) ORDER BY dia';
        $s=$this->db()->prepare($sql);$s->execute($args);return $s->fetchAll();
    }

    public function sugestoesRenovacao(int $profissionalId,int $clienteId=0): array
    {
        $planos=$this->planos($profissionalId);$out=[];
        foreach($planos as $p){
            if(($p['modalidade']??'')!=='mensal'||($p['frequencia']??'')!=='mensal')continue;
            if($clienteId>0&&(int)$p['cliente_id']!==$clienteId)continue;
            $s=$this->db()->prepare('SELECT COUNT(*) AS total,SUM(CASE WHEN status="paga" THEN 1 ELSE 0 END) AS pagas,MIN(data_vencimento) AS primeiro_vencimento FROM cobrancas WHERE plano_id=:id');
            $s->execute(['id'=>$p['id']]);$r=$s->fetch();
            if((int)($r['total']??0)<1||(int)($r['total']??0)!==(int)($r['pagas']??0)||empty($r['primeiro_vencimento']))continue;
            $s=$this->db()->prepare('SELECT data_vencimento,valor FROM cobrancas WHERE plano_id=:id AND status="paga" ORDER BY id DESC LIMIT 1');$s->execute(['id'=>$p['id']]);$ultima=$s->fetch();
            if(!$ultima)continue;
            $ultimaPaga=new DateTimeImmutable($ultima['data_vencimento']);$diaReferencia=(int)(new DateTimeImmutable($r['primeiro_vencimento']))->format('d');
            $proximo=$ultimaPaga->modify('first day of this month')->modify('+1 month');
            $p['proximo_vencimento']=$proximo->setDate((int)$proximo->format('Y'),(int)$proximo->format('m'),min($diaReferencia,(int)$proximo->format('t')))->format('Y-m-d');
            $p['ultima_paga']=$ultima['data_vencimento'];$p['valor_sugerido']=(float)$ultima['valor'];$out[]=$p;
        }
        return $out;
    }

    public function removerSugestaoRenovacao(int $profissionalId,int $planoId): void
    {
        $sql='UPDATE planos_cobranca pc SET pc.ativo=0,pc.atualizado_em=NOW()
              WHERE pc.id=:id AND pc.profissional_id=:p AND pc.ativo=1 AND pc.modalidade="mensal" AND pc.frequencia="mensal"
                AND EXISTS (SELECT 1 FROM cobrancas c WHERE c.plano_id=pc.id)
                AND NOT EXISTS (SELECT 1 FROM cobrancas c WHERE c.plano_id=pc.id AND c.status<>"paga")';
        $s=$this->db()->prepare($sql);$s->execute(['id'=>$planoId,'p'=>$profissionalId]);
        if(!$s->rowCount())throw new RuntimeException('A sugestão não está mais disponível para remoção.');
    }

    public function tiposServico(int $profissionalId): array
    {
        $s=$this->db()->prepare('SELECT * FROM tipos_servico WHERE profissional_id=:p AND ativo=1 ORDER BY nome');$s->execute(['p'=>$profissionalId]);return $s->fetchAll();
    }
    public function salvarTipoServico(int $profissionalId,string $nome): int
    {
        $nome=trim($nome);if(mb_strlen($nome)<2||mb_strlen($nome)>100)throw new InvalidArgumentException('Nome inválido.');$s=$this->db()->prepare('INSERT INTO tipos_servico (profissional_id,nome) VALUES (:p,:n)');$s->execute(['p'=>$profissionalId,'n'=>$nome]);return (int)$this->db()->lastInsertId();
    }
    public function excluirTipoServico(int $profissionalId,int $id): void
    {
        $s=$this->db()->prepare('UPDATE tipos_servico SET ativo=0 WHERE id=:id AND profissional_id=:p');$s->execute(['id'=>$id,'p'=>$profissionalId]);if(!$s->rowCount())throw new RuntimeException('Tipo inexistente.');
    }
    private function tipoServicoDoProfissional(int $profissionalId,int $id): bool
    {
        if($id<1)return false;$s=$this->db()->prepare('SELECT id FROM tipos_servico WHERE id=:id AND profissional_id=:p AND ativo=1');$s->execute(['id'=>$id,'p'=>$profissionalId]);return (bool)$s->fetchColumn();
    }

    private function dataValida(string $data): bool
    {
        $d=DateTimeImmutable::createFromFormat('!Y-m-d',$data);return $d && $d->format('Y-m-d')===$data;
    }
}
