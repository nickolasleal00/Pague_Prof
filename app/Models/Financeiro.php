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
        return $s->fetch() ?: ['instrucoes_pagamento'=>''];
    }

    public function salvarConfiguracao(int $profissionalId, string $instrucoes): void
    {
        $sql = 'INSERT INTO configuracoes_profissional (profissional_id,instrucoes_pagamento,atualizado_em)
                VALUES (:id,:instr,NOW()) ON DUPLICATE KEY UPDATE instrucoes_pagamento=VALUES(instrucoes_pagamento), atualizado_em=NOW()';
        $this->db()->prepare($sql)->execute(['id'=>$profissionalId,'instr'=>substr(trim($instrucoes),0,1000)]);
    }

    public function clientes(int $profissionalId,bool $incluirInativos=false): array
    {
        $filtroAtivo=$incluirInativos?'':' AND a.ativo=1';
        $sql = 'SELECT a.*, (SELECT COUNT(*) FROM cobrancas c WHERE c.cliente_id=a.id AND c.profissional_id=a.profissional_id AND c.status IN ("pendente","recusada","em_analise") AND c.data_vencimento<CURDATE()) AS dividas_vencidas,
                (SELECT MAX(c.data_vencimento) FROM cobrancas c WHERE c.cliente_id=a.id AND c.profissional_id=a.profissional_id) AS ultimo_vencimento
                FROM clientes a WHERE a.profissional_id=:p'.$filtroAtivo.' ORDER BY a.nome';
        $s=$this->db()->prepare($sql); $s->execute(['p'=>$profissionalId]); return $s->fetchAll();
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
                $perfil=$pdo->prepare('SELECT id FROM perfis WHERE nome="cliente"');$perfil->execute();$perfilId=$perfil->fetchColumn();
                if($perfilId)$pdo->prepare('INSERT IGNORE INTO usuarios_perfis (usuario_id,perfil_id) VALUES (:u,:p)')->execute(['u'=>$usuarioId,'p'=>$perfilId]);
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
                $s=$pdo->prepare('SELECT id FROM perfis WHERE nome="cliente"');$s->execute();$perfilId=$s->fetchColumn();
                if($perfilId)$pdo->prepare('INSERT IGNORE INTO usuarios_perfis (usuario_id,perfil_id) VALUES (:u,:p)')->execute(['u'=>$usuarioId,'p'=>$perfilId]);
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

    public function gerarCobrancas(int $profissionalId,int $planoId,string $dataInicial,int $quantidade,int $tipoServicoId=0,int $quantidadeAtividades=1): int
    {
        if(trim((string)($this->configuracao($profissionalId)['instrucoes_pagamento']??''))==='')throw new RuntimeException('Cadastre as instruções de pagamento antes de gerar cobranças.');
        $s=$this->db()->prepare('SELECT pc.*,a.nome FROM planos_cobranca pc JOIN clientes a ON a.id=pc.cliente_id WHERE pc.id=:id AND pc.profissional_id=:p AND pc.ativo=1');
        $s->execute(['id'=>$planoId,'p'=>$profissionalId]);$plano=$s->fetch();if(!$plano)throw new RuntimeException('Plano não encontrado.');
        $data=DateTimeImmutable::createFromFormat('!Y-m-d',$dataInicial);
        if(!$data || $data->format('Y-m-d')!==$dataInicial || $quantidade<1 || $quantidade>12)throw new InvalidArgumentException('Data ou quantidade inválida.');
        $freq=$plano['frequencia'];$intervalo=max(1,(int)$plano['intervalo']);
        if($plano['modalidade']==='por_atividade'){if(!$this->tipoServicoDoProfissional($profissionalId,$tipoServicoId)||$quantidadeAtividades<1||$quantidadeAtividades>1000)throw new InvalidArgumentException('Tipo de serviço ou quantidade inválida.');$quantidade=min($quantidade,1);}else{$tipoServicoId=0;$quantidadeAtividades=1;}
        $diaReferencia=(int)$data->format('d');
        $pdo=$this->db();$criados=0;$pdo->beginTransaction();
        try {
            for($i=0;$i<$quantidade;$i++){
                $venc=$data->format('Y-m-d');
                $dup=$pdo->prepare('SELECT id FROM cobrancas WHERE plano_id=:plano AND data_vencimento=:data LIMIT 1');$dup->execute(['plano'=>$planoId,'data'=>$venc]);
                if(!$dup->fetch()){
                    $ref='pp-'.$planoId.'-'.bin2hex(random_bytes(12));
                    $ins=$pdo->prepare('INSERT INTO cobrancas (referencia_externa,profissional_id,cliente_id,plano_id,valor,data_vencimento,status,tipo_servico_id,quantidade_atividades) VALUES (:ref,:p,:a,:pl,:v,:d,"pendente",:ts,:qa)');
                    $ins->execute(['ref'=>$ref,'p'=>$profissionalId,'a'=>$plano['cliente_id'],'pl'=>$planoId,'v'=>$plano['modalidade']==='por_atividade'?number_format((float)$plano['valor']*$quantidadeAtividades,2,'.',''):$plano['valor'],'d'=>$venc,'ts'=>$plano['modalidade']==='por_atividade'?$tipoServicoId:null,'qa'=>$plano['modalidade']==='por_atividade'?$quantidadeAtividades:null]);$cobrancaId=(int)$pdo->lastInsertId();
                    $notificacao=$pdo->prepare('INSERT IGNORE INTO notificacoes_email (cobranca_id,tipo,status,criado_em) VALUES (:id,"cobranca_gerada",IF(:ordem=1,"pendente","aguardando_envio_profissional"),NOW())');$notificacao->execute(['id'=>$cobrancaId,'ordem'=>$criados+1]);$criados++;
                }
                if($freq==='mensal'){$proximo=$data->modify('first day of this month')->modify('+'.$intervalo.' months');$data=$proximo->setDate((int)$proximo->format('Y'),(int)$proximo->format('m'),min($diaReferencia,(int)$proximo->format('t')));}
                elseif($freq==='quinzenal')$data=$data->modify('+'.(15*$intervalo).' days');
                elseif($freq==='semanal')$data=$data->modify('+'.(7*$intervalo).' days');
                else $data=$data->modify('+'.$intervalo.' days');
            }
            $pdo->commit();return $criados;
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public function cobrancasProfissional(int $profissionalId, string $de='', string $ate='', string $status='',int $clienteId=0): array
    {
        $where=['c.profissional_id=:p'];$args=['p'=>$profissionalId];
        if($de!=='' && $this->dataValida($de)){$where[]='c.data_vencimento>=:de';$args['de']=$de;}
        if($ate!=='' && $this->dataValida($ate)){$where[]='c.data_vencimento<=:ate';$args['ate']=$ate;}
        if(in_array($status,['pendente','paga','em_analise','recusada','cancelada'],true)){$where[]='c.status=:status';$args['status']=$status;}
        if($clienteId>0){$where[]='c.cliente_id=:cliente';$args['cliente']=$clienteId;}
        $sql='SELECT c.*,a.nome AS cliente_nome,a.email AS cliente_email,pc.modalidade,pc.frequencia,ts.nome AS tipo_servico_nome,n.id AS notificacao_id,n.status AS notificacao_status,n.tentativas AS notificacao_tentativas FROM cobrancas c JOIN clientes a ON a.id=c.cliente_id LEFT JOIN planos_cobranca pc ON pc.id=c.plano_id LEFT JOIN tipos_servico ts ON ts.id=c.tipo_servico_id LEFT JOIN notificacoes_email n ON n.cobranca_id=c.id AND n.tipo="cobranca_gerada" WHERE '.implode(' AND ',$where).' ORDER BY c.data_vencimento DESC,c.id DESC';
        $s=$this->db()->prepare($sql);$s->execute($args);return $s->fetchAll();
    }

    public function cancelarCobranca(int $profissionalId,int $cobrancaId): void
    {
        $pdo=$this->db();$pdo->beginTransaction();
        try{
            $s=$pdo->prepare('SELECT status FROM cobrancas WHERE id=:id AND profissional_id=:p FOR UPDATE');
            $s->execute(['id'=>$cobrancaId,'p'=>$profissionalId]);$status=$s->fetchColumn();
            if($status===false)throw new RuntimeException('Cobrança não encontrada.');
            if($status==='paga')throw new RuntimeException('Uma cobrança paga não pode ser excluída.');
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

    public function cobrancasCliente(int $usuarioId): array
    {
        $sql='SELECT c.*,a.profissional_id,a.email AS email_cliente,a.nome AS cliente_nome,u.nome AS profissional_nome,cp.instrucoes_pagamento
              FROM cobrancas c JOIN clientes a ON a.id=c.cliente_id JOIN usuarios u ON u.id=c.profissional_id
              LEFT JOIN configuracoes_profissional cp ON cp.profissional_id=c.profissional_id
              WHERE a.usuario_id=:u ORDER BY c.data_vencimento DESC,c.id DESC';
        $s=$this->db()->prepare($sql);$s->execute(['u'=>$usuarioId]);return $s->fetchAll();
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

    public function comprovantesProfissional(int $profissionalId): array
    {
        $sql='SELECT cp.*,c.valor,c.data_vencimento,c.id AS cobranca_id,a.nome AS cliente_nome,a.email AS cliente_email
              FROM comprovantes cp JOIN cobrancas c ON c.id=cp.cobranca_id JOIN clientes a ON a.id=c.cliente_id
              WHERE c.profissional_id=:p ORDER BY (cp.status="em_analise") DESC,cp.enviado_em DESC';
        $s=$this->db()->prepare($sql);$s->execute(['p'=>$profissionalId]);return $s->fetchAll();
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
        if($clienteId>0){$s=$this->db()->prepare('SELECT COUNT(*) FROM clientes WHERE profissional_id=:p AND id=:cliente');$s->execute(['p'=>$profissionalId,'cliente'=>$clienteId]);}
        else{$s=$this->db()->prepare('SELECT COUNT(*) FROM clientes WHERE profissional_id=:p AND ativo=1');$s->execute(['p'=>$profissionalId]);}
        $clientes=(int)$s->fetchColumn();$where='profissional_id=:p';$args=['p'=>$profissionalId];
        if($clienteId>0){$where.=' AND cliente_id=:cliente';$args['cliente']=$clienteId;}
        $sql='SELECT COALESCE(SUM(CASE WHEN status="paga" AND data_pagamento>=:de_pago AND data_pagamento<DATE_ADD(:ate_pago,INTERVAL 1 DAY) THEN valor ELSE 0 END),0) recebido,
            COALESCE(SUM(CASE WHEN status IN ("pendente","em_analise","recusada") AND data_vencimento BETWEEN :de_aberto AND :ate_aberto THEN valor ELSE 0 END),0) aberto,
            COALESCE(SUM(CASE WHEN status IN ("pendente","recusada") AND data_vencimento BETWEEN :de_vencido AND :ate_vencido AND data_vencimento<CURDATE() THEN valor ELSE 0 END),0) vencido
            FROM cobrancas WHERE '.$where;
        $s=$this->db()->prepare($sql);$s->execute($args+['de_pago'=>$de,'ate_pago'=>$ate,'de_aberto'=>$de,'ate_aberto'=>$ate,'de_vencido'=>$de,'ate_vencido'=>$ate]);$r=$s->fetch()?:[];$r['total_clientes']=$clientes;return $r;
    }

    public function serieMensal(int $profissionalId,string $de,string $ate,int $clienteId=0): array
    {
        $filtroRecebido='';$filtroAberto='';$args=['p1'=>$profissionalId,'de1'=>$de,'ate1'=>$ate,'p2'=>$profissionalId,'de2'=>$de,'ate2'=>$ate];
        if($clienteId>0){$filtroRecebido=' AND cliente_id=:cliente1';$filtroAberto=' AND cliente_id=:cliente2';$args['cliente1']=$clienteId;$args['cliente2']=$clienteId;}
        $sql='SELECT DATE_FORMAT(mes,"%Y-%m") AS mes, SUM(recebido) AS recebido, SUM(aberto) AS aberto, SUM(vencido) AS vencido FROM (SELECT DATE_FORMAT(data_pagamento,"%Y-%m-01") mes,valor recebido,0 aberto,0 vencido FROM cobrancas WHERE profissional_id=:p1 AND status="paga" AND data_pagamento>=:de1 AND data_pagamento<DATE_ADD(:ate1,INTERVAL 1 DAY)'.$filtroRecebido.' UNION ALL SELECT DATE_FORMAT(data_vencimento,"%Y-%m-01"),0,valor,IF(data_vencimento<CURDATE() AND status IN ("pendente","recusada"),valor,0) FROM cobrancas WHERE profissional_id=:p2 AND status IN ("pendente","em_analise","recusada") AND data_vencimento BETWEEN :de2 AND :ate2'.$filtroAberto.') x GROUP BY mes ORDER BY mes';
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
                     COALESCE(SUM(CASE WHEN c.status IN ("em_analise","recusada") THEN c.valor ELSE 0 END),0) AS comprovante_enviado,
                     COALESCE(SUM(CASE WHEN c.status="pendente" THEN c.valor ELSE 0 END),0) AS sem_comprovante
              FROM cobrancas c
              WHERE c.profissional_id=:p AND c.data_vencimento BETWEEN :de AND :ate AND c.status<>"cancelada"'.$filtroCliente.'
              GROUP BY DAY(c.data_vencimento) ORDER BY dia';
        $s=$this->db()->prepare($sql);$s->execute($args);return $s->fetchAll();
    }

    public function sugestoesRenovacao(int $profissionalId): array
    {
        $planos=$this->planos($profissionalId);$out=[];
        foreach($planos as $p){
            if(($p['modalidade']??'')!=='mensal')continue;
            $s=$this->db()->prepare('SELECT SUM(CASE WHEN data_vencimento>=CURDATE() AND status IN ("pendente","em_analise","recusada") THEN 1 ELSE 0 END) futuras, MAX(CASE WHEN status="paga" THEN data_vencimento ELSE NULL END) ultima_paga FROM cobrancas WHERE plano_id=:id');
            $s->execute(['id'=>$p['id']]);$r=$s->fetch();
            if((int)($r['futuras']??0)===0 && !empty($r['ultima_paga'])){
                $p['ultima_paga']=$r['ultima_paga'];$base=new DateTimeImmutable($r['ultima_paga']);$passo=max(1,(int)$p['intervalo']);$diaReferencia=(int)$base->format('d');
                if($p['frequencia']==='mensal'){$proximo=$base->modify('first day of this month')->modify('+'.$passo.' months');$base=$proximo->setDate((int)$proximo->format('Y'),(int)$proximo->format('m'),min($diaReferencia,(int)$proximo->format('t')));}
                elseif($p['frequencia']==='quinzenal')$base=$base->modify('+'.(15*$passo).' days');
                elseif($p['frequencia']==='semanal')$base=$base->modify('+'.(7*$passo).' days');
                else $base=$base->modify('+'.$passo.' days');
                $p['proximo_vencimento']=$base->format('Y-m-d');$out[]=$p;
            }
        }
        return $out;
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
