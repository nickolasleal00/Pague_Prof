<?php
declare(strict_types=1);

final class AuthController
{
    private Usuario $usuarios;

    public function __construct()
    {
        $this->usuarios = new Usuario();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
    }

    public function mostrarLogin(): void
    {
        if (!empty($_SESSION['usuario'])) {
            $this->redirecionar('painel');
        }
        $erro = $_SESSION['erro'] ?? '';
        $mensagem = $_SESSION['mensagem'] ?? '';
        unset($_SESSION['erro'],$_SESSION['mensagem']);
        require __DIR__ . '/../Views/auth/login.php';
    }

    public function entrar(): void
    {
        $this->validarCsrf();
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $senha = (string) ($_POST['senha'] ?? '');
        if (!$email || $senha === '') {
            $_SESSION['erro'] = 'Informe um e-mail válido e sua senha.';
            $this->redirecionar('login');
        }
        try {
            $usuario = $this->usuarios->buscarPorEmail($email);
        } catch (Throwable $erro) {
            error_log($erro->getMessage());
            $_SESSION['erro'] = 'Não foi possível conectar ao sistema. Confira a configuração do banco.';
            $this->redirecionar('login');
        }
        if (!$usuario || !(bool) $usuario['ativo'] || !password_verify($senha, $usuario['senha'])) {
            $_SESSION['erro'] = 'E-mail ou senha inválidos.';
            $this->redirecionar('login');
        }
        session_regenerate_id(true);
        $_SESSION['usuario'] = [
            'id' => (int) $usuario['id'],
            'nome' => $usuario['nome'],
            'email' => $usuario['email'],
            'perfis' => array_filter(explode(',', (string) $usuario['perfis'])),
        ];
        $arquivoId=(int)($_SESSION['abrir_arquivo_comprovante_apos_login']??0);
        $revisaoId=(int)($_SESSION['abrir_revisao_comprovante_apos_login']??0);
        unset($_SESSION['abrir_arquivo_comprovante_apos_login'],$_SESSION['abrir_revisao_comprovante_apos_login']);
        if($arquivoId>0){header('Location: index.php?rota=baixar_comprovante&id='.$arquivoId);exit;}
        if($revisaoId>0){header('Location: index.php?rota=abrir_comprovante&id='.$revisaoId.'#comprovante-'.$revisaoId);exit;}
        $this->redirecionar('painel');
    }

    public function mostrarCadastro(): void
    {
        if (!empty($_SESSION['usuario'])) {
            $this->redirecionar('painel');
        }
        $erro = $_SESSION['erro'] ?? '';
        unset($_SESSION['erro']);
        require __DIR__ . '/../Views/auth/cadastro.php';
    }

    public function cadastrar(): void
    {
        $this->validarCsrf();
        $nome = trim($_POST['nome'] ?? '');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $senha = (string) ($_POST['senha'] ?? '');
        $perfisPermitidos = ['profissional', 'cliente'];
        $perfis = array_values(array_intersect($perfisPermitidos, (array) ($_POST['perfis'] ?? [])));

        if (mb_strlen($nome) < 2 || !$email || strlen($senha) < 8 || !$perfis) {
            $_SESSION['erro'] = 'Confira os dados. A senha precisa ter ao menos 8 caracteres e selecione um ou mais acessos.';
            $this->redirecionar('cadastro');
        }
        try {
            if ($this->usuarios->buscarPorEmail($email)) {
                $_SESSION['erro'] = 'Já existe uma conta com esse e-mail.';
                $this->redirecionar('cadastro');
            }
            $this->usuarios->cadastrar($nome, $email, $senha, $perfis);
            $_SESSION['mensagem'] = 'Conta criada. Entre com seu e-mail e senha.';
            $this->redirecionar('login');
        } catch (Throwable $erro) {
            error_log($erro->getMessage());
            $_SESSION['erro'] = 'Não foi possível criar a conta. Confira a conexão e tente novamente.';
            $this->redirecionar('cadastro');
        }
    }

    public function mostrarRecuperarSenha(): void
    {
        $erro=$_SESSION['erro']??'';$mensagem=$_SESSION['mensagem']??'';unset($_SESSION['erro'],$_SESSION['mensagem']);
        require __DIR__.'/../Views/auth/recuperar.php';
    }

    public function solicitarRecuperacaoSenha(): void
    {
        $this->validarCsrf();
        $email=filter_var(mb_strtolower(trim((string)($_POST['email']??''))),FILTER_VALIDATE_EMAIL);
        if($email){
            try{
                $pdo=Database::conexao();$pdo->exec('DELETE FROM recuperacao_senha WHERE criado_em<DATE_SUB(NOW(),INTERVAL 30 DAY)');$s=$pdo->prepare('SELECT id,nome,email FROM usuarios WHERE email=:email AND ativo=1 LIMIT 1');$s->execute(['email'=>$email]);$usuario=$s->fetch();
                if($usuario){
                    $limite=$pdo->prepare('SELECT COUNT(*) FROM recuperacao_senha WHERE usuario_id=:id AND criado_em>=DATE_SUB(NOW(),INTERVAL 10 MINUTE)');$limite->execute(['id'=>$usuario['id']]);
                    if((int)$limite->fetchColumn()<3){
                        $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
                        $ins=$pdo->prepare('INSERT INTO recuperacao_senha (usuario_id,token_hash,expira_em,criado_em) VALUES (:id,:hash,DATE_ADD(NOW(),INTERVAL 1 HOUR),NOW())');$ins->execute(['id'=>$usuario['id'],'hash'=>$hash]);
                        $base=rtrim(Database::valor('APP_URL'),' /');
                        if($base!==''){
                            $url=$base.'/index.php?rota=nova_senha&token='.rawurlencode($token);
                            try{(new Email())->enviar((string)$usuario['email'],'Redefinição de senha | PagueProf','Olá '.$usuario['nome']."\n\nRecebemos uma solicitação de redefinição de senha para sua conta. Use o link abaixo em até uma hora e apenas uma vez:\n\n".$url."\n\nSe você não solicitou essa alteração, ignore esta mensagem. Sua senha atual continua válida.");}
                            catch(Throwable $erro){$pdo->prepare('UPDATE recuperacao_senha SET usado_em=NOW() WHERE token_hash=:hash')->execute(['hash'=>$hash]);error_log('PagueProf: falha no envio de recuperação de senha.');}
                        }
                    }
                }
            }catch(Throwable $erro){error_log('PagueProf: falha ao processar pedido de recuperação de senha.');}
        }
        $_SESSION['mensagem']='Se o endereço informado estiver cadastrado e puder receber mensagens, enviaremos um link de redefinição.';
        $this->redirecionar('recuperar_senha');
    }

    public function mostrarNovaSenha(): void
    {
        $token=(string)($_GET['token']??'');$erro=$_SESSION['erro']??'';unset($_SESSION['erro']);header('Cache-Control: no-store, max-age=0');header('Referrer-Policy: no-referrer');
        if(!$this->tokenRedefinicaoValido($token))$erro='Este link é inválido, expirou ou já foi utilizado. Solicite outro link.';
        require __DIR__.'/../Views/auth/nova_senha.php';
    }

    public function salvarNovaSenha(): void
    {
        $this->validarCsrf();$token=(string)($_POST['token']??'');$senha=(string)($_POST['senha']??'');$confirmacao=(string)($_POST['confirmacao']??'');
        if(strlen($senha)<8||$senha!==$confirmacao){$_SESSION['erro']=$senha!==$confirmacao?'As senhas informadas não coincidem.':'A senha precisa ter ao menos 8 caracteres.';header('Location: index.php?rota=nova_senha&token='.rawurlencode($token));exit;}
        if(!preg_match('/^[a-f0-9]{64}$/D',$token)){$_SESSION['erro']='Este link é inválido ou expirou. Solicite outro link.';$this->redirecionar('recuperar_senha');}
        try{$pdo=Database::conexao();$pdo->beginTransaction();}catch(Throwable $erro){error_log('PagueProf: banco indisponível ao redefinir senha.');$_SESSION['erro']='Não foi possível concluir agora. Solicite um novo link mais tarde.';$this->redirecionar('recuperar_senha');}
        try{
            $s=$pdo->prepare('SELECT id,usuario_id FROM recuperacao_senha WHERE token_hash=:hash AND usado_em IS NULL AND expira_em>NOW() LIMIT 1 FOR UPDATE');$s->execute(['hash'=>hash('sha256',$token)]);$linha=$s->fetch();
            if(!$linha)throw new RuntimeException('Link de redefinição indisponível.');
            $pdo->prepare('UPDATE usuarios SET senha=:senha,atualizado_em=NOW() WHERE id=:id')->execute(['senha'=>password_hash($senha,PASSWORD_DEFAULT),'id'=>$linha['usuario_id']]);
            $pdo->prepare('UPDATE recuperacao_senha SET usado_em=NOW() WHERE usuario_id=:id AND usado_em IS NULL')->execute(['id'=>$linha['usuario_id']]);$pdo->commit();
            $_SESSION['mensagem']='Senha alterada. Entre com sua nova senha.';$this->redirecionar('login');
        }catch(Throwable $erro){if($pdo->inTransaction())$pdo->rollBack();error_log('PagueProf: link de redefinição recusado.');$_SESSION['erro']='Este link é inválido, expirou ou já foi utilizado. Solicite outro link.';$this->redirecionar('recuperar_senha');}
    }

    private function tokenRedefinicaoValido(string $token): bool
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$token))return false;
        try{$s=Database::conexao()->prepare('SELECT id FROM recuperacao_senha WHERE token_hash=:hash AND usado_em IS NULL AND expira_em>NOW() LIMIT 1');$s->execute(['hash'=>hash('sha256',$token)]);return (bool)$s->fetchColumn();}catch(Throwable $erro){return false;}
    }

    public function painel(): void
    {
        if (empty($_SESSION['usuario'])) {
            $this->redirecionar('login');
        }
        $usuario = $_SESSION['usuario'];
        require __DIR__ . '/../Views/dashboard/index.php';
    }

    public function sair(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $parametros = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $parametros['path'], $parametros['domain'], $parametros['secure'], $parametros['httponly']);
        }
        session_destroy();
        $this->redirecionar('login');
    }

    private function validarCsrf(): void
    {
        $token = (string) ($_POST['csrf'] ?? '');
        if (!hash_equals((string) ($_SESSION['csrf'] ?? ''), $token)) {
            http_response_code(419);
            exit('A sessão expirou. Atualize a página e tente novamente.');
        }
    }

    private function redirecionar(string $rota): void
    {
        header('Location: index.php?rota=' . rawurlencode($rota));
        exit;
    }
}
