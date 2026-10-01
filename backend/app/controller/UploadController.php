<?php
declare(strict_types=1);
namespace app\controller;
use app\model\User;
use think\facade\Log;
use think\facade\Request;
use think\Response;
class UploadController
{
    private function getBearerToken(): ?string
    {
        $header = (string) Request::header('authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    public function image(): Response
    {
        try {
            // 必须是：管理员登录态（Bearer auth_token）或员工 token（二选一），禁止匿名上传
            $adminToken = $this->getBearerToken();
            $employeeToken = (string) Request::param('token');
            $authedUser = null;
            $scope = null;
            if ($adminToken) {
                $authedUser = User::where('auth_token', $adminToken)
                    ->where('role', 'admin')
                    ->where('auth_token_expires', '>', date('Y-m-d H:i:s'))
                    ->find();
                if ($authedUser) {
                    $scope = 'admin';
                }
            }
            if (!$authedUser && $employeeToken) {
                $authedUser = User::where('token', $employeeToken)
                    ->where('role', 'employee')
                    ->find();
                if ($authedUser) {
                    if (isset($authedUser->is_active) && (int) $authedUser->is_active !== 1) {
                        return api_json(['code' => 403, 'message' => '账号已禁用', 'data' => null], 200);
                    }
                    $scope = 'employee';
                }
            }
            if (!$authedUser) {
                return api_json(['code' => 401, 'message' => '未授权上传', 'data' => null], 200);
            }

            $file = Request::file('file');
            if (!$file) {
                return api_json(['code' => 400, 'message' => '请选择文件', 'data' => null]);
            }
            $baseDir = public_path() . 'uploads';
            $dir = $baseDir;
            if ($scope === 'employee') {
                $dir = $baseDir . DIRECTORY_SEPARATOR . 'employees' . DIRECTORY_SEPARATOR . (string) $authedUser->id;
            } elseif ($scope === 'admin') {
                $dir = $baseDir . DIRECTORY_SEPARATOR . 'admin';
            }
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $ext = strtolower($file->extension());
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) {
                return api_json(['code' => 400, 'message' => '仅支持 jpg/png/gif', 'data' => null]);
            }
            $name = date('YmdHis') . '_' . uniqid() . '.' . $ext;
            $path = $dir . DIRECTORY_SEPARATOR . $name;
            $tmp = $file->getRealPath() ?: $file->getPathname();
            if ($tmp && is_uploaded_file($tmp)) {
                move_uploaded_file($tmp, $path);
            } else {
                @rename($tmp, $path);
            }
            $url = '/uploads/' . ($scope === 'employee' ? ('employees/' . $authedUser->id . '/') : ($scope === 'admin' ? 'admin/' : '')) . $name;
            return api_json(['code' => 0, 'message' => 'ok', 'data' => ['url' => $url, 'path' => $url]]);
        } catch (\Throwable $e) {
            Log::error('UploadController@image: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return api_json(['code' => 500, 'message' => '服务器错误', 'data' => null]);
        }
    }
}
