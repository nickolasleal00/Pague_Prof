<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $conexao = null;
    private static ?array $variaveisArquivo = null;

    public static function valor(string $chave, string $padrao = ''): string
    {
        if (isset($_ENV[$chave]) && $_ENV[$chave] !== '') return (string) $_ENV[$chave];
        if (isset($_SERVER[$chave]) && $_SERVER[$chave] !== '') return (string) $_SERVER[$chave];
        try {
            $ambiente = getenv($chave);
            if ($ambiente !== false && $ambiente !== '') return $ambiente;
        } catch (Throwable $erro) {
            // Algumas hospedagens restringem getenv(); usa o arquivo local abaixo.
        }
        $arquivo = self::lerArquivoEnv();
        return $arquivo[$chave] ?? $padrao;
    }

    public static function conexao(): PDO
    {
        if (self::$conexao instanceof PDO) return self::$conexao;

        $host = self::valor('DB_HOST', 'localhost');
        $nome = self::valor('DB_NAME');
        $usuario = self::valor('DB_USER');
        $senha = self::valor('DB_PASS');
        $charset = self::valor('DB_CHARSET', 'utf8mb4');
        if ($nome === '' || $usuario === '') throw new RuntimeException('Configure DB_NAME e DB_USER no arquivo .env.');

        $dsn = "mysql:host={$host};dbname={$nome};charset={$charset}";
        self::$conexao = new PDO($dsn, $usuario, $senha, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return self::$conexao;
    }

    private static function lerArquivoEnv(): array
    {
        if (self::$variaveisArquivo !== null) return self::$variaveisArquivo;
        $arquivoEnv = dirname(__DIR__, 2) . '/.env';
        $variaveis = [];
        if (is_file($arquivoEnv) && is_readable($arquivoEnv)) {
            foreach (file($arquivoEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
                $linha = trim($linha);
                if ($linha === '' || $linha[0] === '#' || strpos($linha, '=') === false) continue;
                [$chave, $valor] = explode('=', $linha, 2);
                $variaveis[trim($chave)] = trim(trim($valor), "\"'");
            }
        }
        self::$variaveisArquivo = $variaveis;
        return $variaveis;
    }
}
