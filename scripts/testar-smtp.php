<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/Core/Database.php';

function lerRespostaSmtp($socket): array
{
    $texto = '';
    do {
        $linha = fgets($socket, 515);
        if ($linha === false) {
            throw new RuntimeException('sem_resposta');
        }
        $texto .= $linha;
    } while (strlen($linha) >= 4 && $linha[3] === '-');

    return [(int) substr($texto, 0, 3), $texto];
}

function enviarComandoSmtp($socket, string $comando): array
{
    fwrite($socket, $comando . "\r\n");
    return lerRespostaSmtp($socket);
}

$socket = null;
$etapa = 'configuracao';
try {
    $host = Database::valor('SMTP_HOST');
    $porta = (int) Database::valor('SMTP_PORT', '587');
    $usuario = Database::valor('SMTP_USER');
    $senha = Database::valor('SMTP_PASS');
    $seguranca = strtolower(Database::valor('SMTP_SECURITY', 'tls'));
    $remetenteValido = filter_var(Database::valor('MAIL_FROM'), FILTER_VALIDATE_EMAIL) !== false;

    if ($host === '' || $porta < 1 || $usuario === '' || $senha === '' || !in_array($seguranca, ['tls', 'ssl', 'none'], true)) {
        throw new RuntimeException('configuracao_incompleta');
    }

    $etapa = 'conexao';
    $prefixo = $seguranca === 'ssl' ? 'ssl://' : 'tcp://';
    $socket = @stream_socket_client(
        $prefixo . $host . ':' . $porta,
        $codigoErro,
        $mensagemErro,
        15,
        STREAM_CLIENT_CONNECT,
        stream_context_create(['ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'SNI_enabled' => true,
        ]])
    );
    if (!$socket) {
        throw new RuntimeException('conexao_falhou');
    }
    stream_set_timeout($socket, 15);

    $etapa = 'saudacao';
    [$codigo] = lerRespostaSmtp($socket);
    if ($codigo !== 220) {
        throw new RuntimeException('smtp_' . $codigo);
    }

    $etapa = 'ehlo';
    [$codigo] = enviarComandoSmtp($socket, 'EHLO pagueprof.local');
    if ($codigo !== 250) {
        throw new RuntimeException('smtp_' . $codigo);
    }

    if ($seguranca === 'tls') {
        $etapa = 'starttls';
        [$codigo] = enviarComandoSmtp($socket, 'STARTTLS');
        if ($codigo !== 220) {
            throw new RuntimeException('smtp_' . $codigo);
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('tls_falhou');
        }
        $etapa = 'ehlo_tls';
        [$codigo] = enviarComandoSmtp($socket, 'EHLO pagueprof.local');
        if ($codigo !== 250) {
            throw new RuntimeException('smtp_' . $codigo);
        }
    }

    $etapa = 'autenticacao';
    [$codigo] = enviarComandoSmtp($socket, 'AUTH LOGIN');
    if ($codigo !== 334) {
        throw new RuntimeException('smtp_' . $codigo);
    }
    [$codigo] = enviarComandoSmtp($socket, base64_encode($usuario));
    if ($codigo !== 334) {
        throw new RuntimeException('smtp_' . $codigo);
    }
    [$codigo] = enviarComandoSmtp($socket, base64_encode($senha));
    if ($codigo !== 235) {
        throw new RuntimeException('smtp_' . $codigo);
    }

    enviarComandoSmtp($socket, 'QUIT');
    fclose($socket);
    echo "Conexão e autenticação SMTP: OK\n";
    echo 'Endereço de remetente: ' . ($remetenteValido ? 'válido' : 'inválido') . "\n";
    echo "E-mail enviado: não\n";
} catch (Throwable $erro) {
    if (is_resource($socket)) {
        fclose($socket);
    }
    $mensagem = $erro->getMessage();
    $codigoSmtp = preg_match('/^smtp_(\d{3})$/', $mensagem, $correspondencia) ? $correspondencia[1] : '';
    echo 'Teste SMTP: falhou na etapa ' . $etapa;
    if ($codigoSmtp !== '') {
        echo ' (código SMTP ' . $codigoSmtp . ')';
    }
    echo "\nE-mail enviado: não\n";
    exit(1);
}
