<?php
declare(strict_types=1);

/** Gera BR Codes Pix estáticos sem valor, sem integração com API de pagamento. */
final class Pix
{
    public static function normalizarChave(string $tipo, string $chave): string
    {
        $tipo = strtolower(trim($tipo));
        $digitos = preg_replace('/\D+/', '', $chave) ?? '';

        if ($tipo === 'cpf') {
            if (strlen($digitos) !== 11 || preg_match('/^(\d)\1{10}$/', $digitos)) {
                throw new InvalidArgumentException('Informe um CPF válido para a chave Pix.');
            }
            $soma = 0;
            for ($i = 0; $i < 9; $i++) {
                $soma += (int)$digitos[$i] * (10 - $i);
            }
            $resto = $soma % 11;
            $primeiro = $resto < 2 ? 0 : 11 - $resto;
            $soma = 0;
            for ($i = 0; $i < 10; $i++) {
                $soma += (int)$digitos[$i] * (11 - $i);
            }
            $resto = $soma % 11;
            $segundo = $resto < 2 ? 0 : 11 - $resto;
            if ((int)$digitos[9] !== $primeiro || (int)$digitos[10] !== $segundo) {
                throw new InvalidArgumentException('O CPF informado não passou na validação dos dígitos.');
            }
            return $digitos;
        }

        if ($tipo !== 'telefone') {
            throw new InvalidArgumentException('Selecione CPF ou telefone como chave Pix.');
        }
        if (strlen($digitos) === 12 || strlen($digitos) === 13) {
            if (substr($digitos, 0, 2) !== '55') {
                throw new InvalidArgumentException('O telefone deve usar o código internacional +55.');
            }
            $digitos = substr($digitos, 2);
        }
        if (strlen($digitos) !== 11 || (int)substr($digitos, 0, 2) < 11 || (int)substr($digitos, 0, 2) > 99 || $digitos[2] !== '9') {
            throw new InvalidArgumentException('Informe um celular brasileiro com DDD, no formato (11) 99999-9999.');
        }
        return '+55' . $digitos;
    }

    public static function normalizarNome(string $nome): string
    {
        return self::normalizarTextoEmv($nome, 25, 'nome do recebedor');
    }

    public static function normalizarCidade(string $cidade): string
    {
        return self::normalizarTextoEmv($cidade, 15, 'cidade');
    }

    public static function formatarChave(string $tipo, string $chave): string
    {
        $normalizada = self::normalizarChave($tipo, $chave);
        if ($tipo === 'cpf') {
            return substr($normalizada, 0, 3) . '.' . substr($normalizada, 3, 3) . '.' . substr($normalizada, 6, 3) . '-' . substr($normalizada, 9, 2);
        }
        $digitos = substr($normalizada, 3);
        return '+55 (' . substr($digitos, 0, 2) . ') ' . substr($digitos, 2, 5) . '-' . substr($digitos, 7, 4);
    }

    public static function txidCobranca(int $cobrancaId): string
    {
        if ($cobrancaId < 1) {
            throw new InvalidArgumentException('Identificador de cobrança inválido.');
        }
        return 'PP' . strtoupper(dechex($cobrancaId));
    }

    public static function gerarPayload(string $nome, string $cidade, string $tipo, string $chave, string $txid = '***'): string
    {
        $nome = self::normalizarNome($nome);
        $cidade = self::normalizarCidade($cidade);
        $chave = self::normalizarChave($tipo, $chave);
        if ($txid !== '***' && !preg_match('/^[A-Za-z0-9]{1,25}$/D', $txid)) {
            throw new InvalidArgumentException('Identificador Pix inválido.');
        }

        $conta = self::tlv('00', 'br.gov.bcb.pix') . self::tlv('01', $chave);
        $dadosAdicionais = self::tlv('05', $txid);
        $payload = self::tlv('00', '01')
            . self::tlv('26', $conta)
            . self::tlv('52', '0000')
            . self::tlv('53', '986')
            . self::tlv('58', 'BR')
            . self::tlv('59', $nome)
            . self::tlv('60', $cidade)
            . self::tlv('62', $dadosAdicionais)
            . '6304';

        return $payload . self::crc16($payload);
    }

    private static function normalizarTextoEmv(string $texto, int $limite, string $campo): string
    {
        $mapa = [
            'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a','Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E',
            'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i','Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I',
            'ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o','Ó'=>'O','Ò'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O',
            'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U',
            'ç'=>'c','Ç'=>'C','ñ'=>'n','Ñ'=>'N'
        ];
        $valor = strtr(trim($texto), $mapa);
        $valor = preg_replace('/[^A-Za-z0-9 .\'-]/', '', $valor) ?? '';
        $valor = trim(preg_replace('/\s+/', ' ', $valor) ?? '');
        if ($valor === '' || strlen($valor) > $limite) {
            throw new InvalidArgumentException('Informe ' . $campo . ' com até ' . $limite . ' caracteres para o QR Pix.');
        }
        return $valor;
    }

    private static function tlv(string $id, string $valor): string
    {
        $tamanho = strlen($valor);
        if ($tamanho > 99) {
            throw new InvalidArgumentException('Campo Pix excedeu o tamanho permitido.');
        }
        return $id . str_pad((string)$tamanho, 2, '0', STR_PAD_LEFT) . $valor;
    }

    private static function crc16(string $conteudo): string
    {
        $crc = 0xFFFF;
        $tamanho = strlen($conteudo);
        for ($i = 0; $i < $tamanho; $i++) {
            $crc ^= ord($conteudo[$i]) << 8;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) !== 0 ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }
        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
