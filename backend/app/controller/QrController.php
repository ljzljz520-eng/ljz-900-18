<?php
declare(strict_types=1);
namespace app\controller;
use app\model\User;
use app\service\QrService;
use think\facade\Log;
use think\facade\Request;
use think\Response;
class QrController
{
    public function generate(): Response
    {
        try {
            $userId = (int) Request::param('user_id');
            $baseUrl = rtrim((string) Request::param('base_url', ''), '/');
            if (!$userId || !$baseUrl) {
                return api_json(['code' => 400, 'message' => '缺少 user_id 或 base_url', 'data' => null]);
            }
            $user = User::find($userId);
            if (!$user) {
                return api_json(['code' => 404, 'message' => '用户不存在', 'data' => null]);
            }
            $data = (new QrService())->generateForUser($user, $baseUrl);
            return api_json([
                'code' => 0,
                'message' => 'ok',
                'data' => [
                    'link' => $data['link'],
                    'qr_code_url' => $data['qr_code_url'],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('QrController@generate: ' . $e->getMessage());
            return api_json(['code' => 500, 'message' => '服务器错误', 'data' => null]);
        }
    }
}
