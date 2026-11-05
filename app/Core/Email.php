<?php
declare(strict_types=1);

final class Email
{
    public function enviar(string $destinatario, string $assunto, string $mensagem, ?string $pixPayload=null): void
    {
        $host=Database::valor('SMTP_HOST');$porta=(int)Database::valor('SMTP_PORT','587');$usuario=Database::valor('SMTP_USER');$senha=Database::valor('SMTP_PASS');
        $de=Database::valor('MAIL_FROM');$nome=preg_replace('/[\r\n]+/',' ',Database::valor('MAIL_FROM_NAME','PagueProf'));$seguranca=strtolower(Database::valor('SMTP_SECURITY','tls'));
        if($host===''||$de===''||!filter_var($de,FILTER_VALIDATE_EMAIL)||!filter_var($destinatario,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Configuração SMTP ou endereço de e-mail inválido.');
        if(!in_array($seguranca,['tls','ssl','none'],true))throw new RuntimeException('SMTP_SECURITY deve ser tls, ssl ou none.');
        $prefixo=$seguranca==='ssl'?'ssl://':'';$socket=@stream_socket_client($prefixo.$host.':'.$porta,$errno,$errstr,15,STREAM_CLIENT_CONNECT,stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'SNI_enabled'=>true]]));
        if(!$socket)throw new RuntimeException('Não foi possível conectar ao servidor SMTP.');
        stream_set_timeout($socket,20);
        try{
            $this->esperar($socket,[220]);$this->comando($socket,'EHLO pagueprof.local',[250]);
            if($seguranca==='tls'){$this->comando($socket,'STARTTLS',[220]);if(!stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('Não foi possível iniciar TLS com o servidor SMTP.');$this->comando($socket,'EHLO pagueprof.local',[250]);}
            if($usuario!==''){$this->comando($socket,'AUTH LOGIN',[334]);$this->comando($socket,base64_encode($usuario),[334]);$this->comando($socket,base64_encode($senha),[235]);}
            $this->comando($socket,'MAIL FROM:<'.$de.'>',[250]);$this->comando($socket,'RCPT TO:<'.$destinatario.'>',[250,251]);
            $imagemQr=null;
            if($pixPayload!==null){
                try{require_once __DIR__.'/PixQrPng.php';$imagemQr=PixQrPng::gerar($pixPayload);}
                catch(Throwable $e){error_log('PagueProf: QR Pix não incorporado ao e-mail; foi mantido o código copia e cola. '.$e->getMessage());}
            }
            [$cabecalhosMime,$corpo]=$this->construirCorpoMime($mensagem,$imagemQr);
            $assunto='=?UTF-8?B?'.base64_encode($assunto).'?=';$nome='=?UTF-8?B?'.base64_encode($nome).'?=';
            $dados='Date: '.date(DATE_RFC2822)."\r\nFrom: ".$nome.' <'.$de.">\r\nTo: <".$destinatario.">\r\nSubject: ".$assunto."\r\nMIME-Version: 1.0\r\n".$cabecalhosMime."\r\n\r\n".$corpo."\r\n";
            $dados=preg_replace('/(^|\r\n)\./','$1..',$dados)??$dados;
            $this->comando($socket,'DATA',[354]);fwrite($socket,$dados.".\r\n");$this->esperar($socket,[250]);$this->comando($socket,'QUIT',[221]);
        }finally{fclose($socket);}
    }

    private function construirCorpoMime(string $mensagem,?string $imagemQr): array
    {
        if($imagemQr===null){
            $texto=str_replace('[[PIX_QR_IMAGE]]','[QR indisponível como imagem; use o Pix copia e cola.]',$mensagem);
            return ["Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable",$this->quotedPrintable($texto)];
        }

        $texto=str_replace('[[PIX_QR_IMAGE]]','[QR Pix incorporado como imagem neste e-mail.]',$mensagem);
        $html=htmlspecialchars($mensagem,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $imagem='<img src="cid:pagueprof-pix-qr" alt="QR Code Pix estático sem valor" width="240" height="240" style="display:block;width:240px;height:240px;border:0" />';
        $html=nl2br(str_replace('[[PIX_QR_IMAGE]]',$imagem,$html),false);
        $relacionado='pagueprof-rel-'.bin2hex(random_bytes(12));$alternativo='pagueprof-alt-'.bin2hex(random_bytes(12));
        $corpo='--'.$relacionado."\r\nContent-Type: multipart/alternative; boundary=\"".$alternativo."\"\r\n\r\n"
            .'--'.$alternativo."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n".$this->quotedPrintable($texto)."\r\n"
            .'--'.$alternativo."\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n".$this->quotedPrintable($html)."\r\n"
            .'--'.$alternativo."--\r\n"
            .'--'.$relacionado."\r\nContent-Type: image/png; name=\"pix-qrcode.png\"\r\nContent-Transfer-Encoding: base64\r\nContent-ID: <pagueprof-pix-qr>\r\nContent-Disposition: inline; filename=\"pix-qrcode.png\"\r\n\r\n"
            .chunk_split(base64_encode($imagemQr),76,"\r\n")
            .'--'.$relacionado.'--';
        return ['Content-Type: multipart/related; boundary="'.$relacionado.'"',$corpo];
    }

    private function quotedPrintable(string $texto): string
    {
        $codificado=quoted_printable_encode(str_replace(["\r\n","\r"],"\n",$texto));
        $codificado=str_replace(["\r\n","\r"],"\n",$codificado);
        return str_replace("\n","\r\n",$codificado);
    }

    private function comando($socket,string $comando,array $esperados): string
    {
        fwrite($socket,$comando."\r\n");return $this->esperar($socket,$esperados);
    }

    private function esperar($socket,array $esperados): string
    {
        $resposta='';do{$linha=fgets($socket,515);if($linha===false)throw new RuntimeException('Resposta SMTP incompleta.');$resposta.=$linha;}while(strlen($linha)>=4&&$linha[3]==='-');
        $codigo=(int)substr($resposta,0,3);if(!in_array($codigo,$esperados,true))throw new RuntimeException('Servidor SMTP respondeu com erro ('.$codigo.').');return $resposta;
    }
}
