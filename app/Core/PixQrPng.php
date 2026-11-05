<?php
declare(strict_types=1);

/** Gera a imagem PNG local do QR Pix para incorporação inline em e-mails. */
final class PixQrPng
{
    public static function gerar(string $payload): string
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagepng')) {
            throw new RuntimeException('A extensão GD do PHP é necessária para gerar o QR Pix do e-mail.');
        }

        require_once __DIR__ . '/vendor/qrcode.php';

        $gerador = new QRCode($payload, ['s' => 'qr-m', 'sf' => 8, 'p' => 3]);
        $imagem = $gerador->render_image();
        if (!$imagem) {
            throw new RuntimeException('Não foi possível renderizar o QR Pix.');
        }

        ob_start();
        try {
            imagepng($imagem);
            $png = ob_get_contents();
        } finally {
            ob_end_clean();
            imagedestroy($imagem);
        }

        if (!is_string($png) || !str_starts_with($png, "\x89PNG\r\n\x1a\n")) {
            throw new RuntimeException('A imagem do QR Pix gerada não é um PNG válido.');
        }

        return $png;
    }
}
