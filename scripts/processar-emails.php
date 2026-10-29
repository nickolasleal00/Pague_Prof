<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$raiz=dirname(__DIR__);
require_once $raiz.'/app/Core/Database.php';
require_once $raiz.'/app/Core/Email.php';
require_once $raiz.'/app/Core/NotificacoesEmail.php';

$lock=fopen(sys_get_temp_dir().'/pagueprof-email-'.sha1($raiz).'.lock','c');
if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){fwrite(STDOUT,"Outro processamento de e-mail já está em andamento.\n");exit(0);}
try{
    $fila=new NotificacoesEmail();
    $novos=$fila->agendarLembretes();
    $resultado=$fila->processar(100);
    fwrite(STDOUT,sprintf("Fila PagueProf: lembretes novos=%d; enviados=%d; falhas=%d; ignorados=%d\n",$novos,$resultado['enviados'],$resultado['falhas'],$resultado['ignorados']));
    if($resultado['falhas']>0)exit(1);
} catch(Throwable $e){error_log('PagueProf cron de e-mail: '.$e->getMessage());fwrite(STDERR,"Falha ao processar fila de e-mail. Confira o log PHP sem expor credenciais.\n");exit(1);} finally {flock($lock,LOCK_UN);fclose($lock);}
