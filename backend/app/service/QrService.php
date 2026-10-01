<?php
declare(strict_types=1);

namespace app\service;

use app\model\User;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class QrService
{
    /**
     * 生成带 token 的链接与二维码图片，并写回 users.qr_code_url
     * 返回：['link' => string, 'qr_code_url' => string]
     */
    public function generateForUser(User $user, string $baseUrl): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $link = $baseUrl . '?token=' . urlencode((string) $user->token);

        $dir = public_path() . 'uploads';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = 'qr_' . $user->id . '_' . time() . '.png';
        $path = $dir . DIRECTORY_SEPARATOR . $filename;

        $qrCode = new QrCode($link);
        $qrCode->setSize(300);
        $qrCode->setMargin(10);
        $writer = new PngWriter();
        $result = $writer->write($qrCode);
        file_put_contents($path, $result->getString());

        $url = '/uploads/' . $filename;
        $user->qr_code_url = $url;
        $user->save();

        return [
            'link' => $link,
            'qr_code_url' => $url,
        ];
    }
}

