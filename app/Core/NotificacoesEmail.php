<?php
declare(strict_types=1);

final class NotificacoesEmail
{
    public function __construct(private ?PDO $pdo=null, private ?Email $email=null)
    {
        $this->pdo??=Database::conexao();$this->email??=new Email();
    }

    public function agendarLembretes(): int
    {
        $sql='INSERT IGNORE INTO notificacoes_email (cobranca_id,tipo,status,criado_em)
              SELECT c.id,"lembrete_vencimento","pendente",NOW() FROM cobrancas c
              WHERE c.data_vencimento=DATE_ADD(CURDATE(),INTERVAL 1 DAY)
                AND c.status IN ("pendente","recusada")';
        $this->pdo->exec($sql);return $this->pdo->query('SELECT ROW_COUNT()')->fetchColumn()?:0;
    }

    public function processar(int $limite=100,?string $tipo=null,?int $notificacaoId=null): array
    {
        $this->pdo->exec('UPDATE notificacoes_email SET status="falhou",ultimo_erro="Processamento anterior interrompido" WHERE status="enviando" AND criado_em<DATE_SUB(NOW(),INTERVAL 30 MINUTE)');
        $limite=max(1,min($limite,500));
        $tipoValido=in_array($tipo,['cobranca_gerada','lembrete_vencimento'],true)||($tipo!==null&&preg_match('/^comprovante_[1-9][0-9]*$/D',$tipo));
        $filtro=$tipoValido?' AND n.tipo='.$this->pdo->quote((string)$tipo):'';if($notificacaoId!==null)$filtro.=' AND n.id='.(int)$notificacaoId;
        $consulta='SELECT n.id,n.cobranca_id,n.comprovante_id,n.tipo,n.status AS notificacao_status,c.valor,c.data_vencimento,c.status AS cobranca_status,
                          a.nome AS cliente_nome,a.email AS cliente_email,u.nome AS profissional_nome,u.email AS profissional_email,cp.enviado_em AS comprovante_enviado_em
                   FROM notificacoes_email n
                   JOIN cobrancas c ON c.id=n.cobranca_id
                   JOIN clientes a ON a.id=c.cliente_id
                   JOIN usuarios u ON u.id=c.profissional_id
                   LEFT JOIN comprovantes cp ON cp.id=n.comprovante_id
                   WHERE n.status IN ("pendente","falhou") AND n.tentativas<5'.$filtro.' ORDER BY n.id LIMIT '.$limite;
        $s=$this->pdo->query($consulta);
        $itens=$s->fetchAll();$resultado=['enviados'=>0,'falhas'=>0,'ignorados'=>0];
        foreach($itens as $n){
            $ehComprovante=!empty($n['comprovante_id']);
            $claim=$this->pdo->prepare('UPDATE notificacoes_email n JOIN cobrancas c ON c.id=n.cobranca_id SET n.status="enviando",n.tentativas=n.tentativas+1 WHERE n.id=:id AND n.status IN ("pendente","falhou") AND n.tentativas<5 AND (c.status NOT IN ("paga","cancelada") OR n.comprovante_id IS NOT NULL) AND (n.tipo<>"lembrete_vencimento" OR c.status IN ("pendente","recusada"))');$claim->execute(['id'=>$n['id']]);
            if($claim->rowCount()!==1){if(!$ehComprovante&&(in_array($n['cobranca_status'],['paga','cancelada'],true)||($n['tipo']==='lembrete_vencimento'&&!in_array($n['cobranca_status'],['pendente','recusada'],true)))){$this->marcarIgnorada((int)$n['id']);$resultado['ignorados']++;}continue;}
            $estado=$this->pdo->prepare('SELECT status FROM cobrancas WHERE id=:id');$estado->execute(['id'=>$n['cobranca_id']]);$statusAtual=$estado->fetchColumn();
            if($statusAtual===false||(!$ehComprovante&&in_array($statusAtual,['paga','cancelada'],true))||($n['tipo']==='lembrete_vencimento'&&!in_array($statusAtual,['pendente','recusada'],true))){$this->marcarIgnorada((int)$n['id']);$resultado['ignorados']++;continue;}
            try{
                $assunto=$ehComprovante?'Novo comprovante para analisar | PagueProf':($n['tipo']==='cobranca_gerada'?'Nova cobrança do PagueProf':'Lembrete: cobrança vence amanhã');
                $destinatario=$ehComprovante?(string)$n['profissional_email']:(string)$n['cliente_email'];
                $mensagem=$this->mensagem($n);$this->email->enviar($destinatario,$assunto,$mensagem);
                $u=$this->pdo->prepare('UPDATE notificacoes_email SET status="enviada",enviada_em=NOW(),ultimo_erro=NULL WHERE id=:id');$u->execute(['id'=>$n['id']]);$resultado['enviados']++;
            }catch(Throwable $e){
                $u=$this->pdo->prepare('UPDATE notificacoes_email SET status="falhou",ultimo_erro=:erro WHERE id=:id');$u->execute(['id'=>$n['id'],'erro'=>substr($e->getMessage(),0,250)]);error_log('PagueProf e-mail falhou; notificacao '.$n['id'].': '.$e->getMessage());$resultado['falhas']++;
            }
        }
        return $resultado;
    }

    public function enviarNotificacaoProfissional(int $profissionalId,int $notificacaoId): array
    {
        $s=$this->pdo->prepare('SELECT n.id FROM notificacoes_email n JOIN cobrancas c ON c.id=n.cobranca_id WHERE n.id=:n AND c.profissional_id=:p AND n.tipo="cobranca_gerada" AND n.status IN ("aguardando_envio_profissional","falhou") AND n.tentativas<5');$s->execute(['n'=>$notificacaoId,'p'=>$profissionalId]);if(!$s->fetchColumn())throw new RuntimeException('Notificação indisponível ou limite de tentativas atingido.');
        $u=$this->pdo->prepare('UPDATE notificacoes_email SET status="pendente" WHERE id=:id AND status IN ("aguardando_envio_profissional","falhou") AND tentativas<5');$u->execute(['id'=>$notificacaoId]);if($u->rowCount()!==1)throw new RuntimeException('Notificação indisponível ou limite de tentativas atingido.');return $this->processar(1,'cobranca_gerada',$notificacaoId);
    }

    private function mensagem(array $n): string
    {
        $valor='R$ '.number_format((float)$n['valor'],2,',','.');$vencimento=date('d/m/Y',strtotime((string)$n['data_vencimento']));
        if(!empty($n['comprovante_id'])){
            $id=(int)$n['comprovante_id'];$appUrl=rtrim(Database::valor('APP_URL'),' /');
            if($appUrl==='')$appUrl='PagueProf';
            else $appUrl.='/index.php';
            $linkArquivo=$appUrl==='PagueProf'?'PagueProf':$appUrl.'?rota=baixar_comprovante&id='.$id;
            $linkAnalise=$appUrl==='PagueProf'?'PagueProf':$appUrl.'?rota=abrir_comprovante&id='.$id.'#comprovante-'.$id;
            $enviado=empty($n['comprovante_enviado_em'])?'':date('d/m/Y H:i',strtotime((string)$n['comprovante_enviado_em']));
            return 'Olá '.$n['profissional_nome']."\n\nO cliente ".$n['cliente_nome'].' enviou um comprovante para a cobrança abaixo.'
                ."\n\nValor: ".$valor."\nVencimento: ".$vencimento
                .($enviado!==''?"\nEnviado em: ".$enviado:'')
                ."\n\nAbrir o comprovante: ".$linkArquivo."\nAbrir a tela de análise: ".$linkAnalise
                ."\n\nA tela de análise exige login de profissional e permite confirmar ou recusar o pagamento. O link não aprova a cobrança automaticamente."
                ."\n\nEsta mensagem foi enviada automaticamente pelo PagueProf.";
        }
        if($n['tipo']==='cobranca_gerada')$abertura='Olá '.$n['cliente_nome']."\n\nUma nova cobrança foi gerada pelo profissional ".$n['profissional_nome'].'.';
        else $abertura='Olá '.$n['cliente_nome']."\n\nEste é um lembrete de que sua cobrança vence amanhã.";
        $appUrl=rtrim(Database::valor('APP_URL'),' /');$link=$appUrl!==''?$appUrl.'/index.php?rota=login':'PagueProf';
        return $abertura."\n\nValor: ".$valor."\nVencimento: ".$vencimento."\n\nAcesse sua área de cliente no PagueProf para consultar os detalhes e as opções de pagamento:\n".$link."\n\nEsta mensagem foi enviada automaticamente. Não responda a este e-mail.";
    }

    private function marcarIgnorada(int $id): void
    {
        $s=$this->pdo->prepare('UPDATE notificacoes_email SET status="ignorada",ultimo_erro="Cobrança já paga, cancelada ou fora dos critérios" WHERE id=:id AND status IN ("pendente","falhou","enviando")');$s->execute(['id'=>$id]);
    }
}
