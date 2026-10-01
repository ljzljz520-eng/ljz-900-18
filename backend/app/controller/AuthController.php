<?php
declare(strict_types=1);
namespace app\controller;
use app\model\User;
use think\facade\Config;
use think\facade\Request;
use think\facade\Log;
use think\Response;

class AuthController
{
    private const DEFAULT_ADMIN_PASSWORD = 'admin123';
    private const TOKEN_EXPIRE_HOURS = 24;

    /** 从请求中获取参数（支持 JSON body 和 form） */
    private function getLoginParams(): array
    {
        $params = Request::param();
        $contentType = Request::header('content-type', '');
        if (str_contains($contentType, 'application/json')) {
            $raw = Request::getContent() ?: '';
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $params = array_merge($params, $decoded);
                }
            }
        }
        return $params;
    }

    public function login(): Response
    {
        try {
            $params = $this->getLoginParams();
            $username = trim((string) ($params['username'] ?? ''));
            $password = (string) ($params['password'] ?? '');
            if (!$username || !$password) {
                return api_json(['code' => 400, 'message' => '用户名和密码不能为空', 'data' => null]);
            }

            $user = User::where('username', $username)->where('role', 'admin')->find();
            if (!$user) {
                return api_json(['code' => 401, 'message' => '用户名或密码错误', 'data' => null]);
            }

            $valid = false;
            if (empty($user->password_hash)) {
                if ($password === self::DEFAULT_ADMIN_PASSWORD) {
                    $valid = true;
                    $user->password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $user->save();
                }
            } else {
                $valid = password_verify($password, $user->password_hash);
            }

            if (!$valid) {
                return api_json(['code' => 401, 'message' => '用户名或密码错误', 'data' => null]);
            }

            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+' . self::TOKEN_EXPIRE_HOURS . ' hours'));
            $user->auth_token = $token;
            $user->auth_token_expires = $expires;
            $user->save();

            $data = [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'role' => $user->role,
                ],
                'token' => $token,
                'expires_at' => $expires,
            ];
            return api_json(['code' => 0, 'message' => 'ok', 'data' => $data]);
        } catch (\Throwable $e) {
            Log::error('AuthController@login: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $message = '服务器错误';
            if (Config::get('app.app_debug')) {
                $message = $e->getMessage();
            }
            return api_json(['code' => 500, 'message' => $message, 'data' => null]);
        }
    }

    public function me(): Response
    {
        try {
            $token = $this->getBearerToken();
            if (!$token) {
                return api_json(['code' => 401, 'message' => '未登录', 'data' => null]);
            }

            $user = User::where('auth_token', $token)
                ->where('role', 'admin')
                ->where('auth_token_expires', '>', date('Y-m-d H:i:s'))
                ->find();

            if (!$user) {
                return api_json(['code' => 401, 'message' => '登录已过期', 'data' => null]);
            }

            $data = [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role,
            ];
            return api_json(['code' => 0, 'message' => 'ok', 'data' => $data]);
        } catch (\Throwable $e) {
            Log::error('AuthController@me: ' . $e->getMessage());
            return api_json(['code' => 500, 'message' => '服务器错误', 'data' => null]);
        }
    }

    public function logout(): Response
    {
        try {
            $token = $this->getBearerToken();
            if ($token) {
                User::where('auth_token', $token)->update(['auth_token' => null, 'auth_token_expires' => null]);
            }
            return api_json(['code' => 0, 'message' => 'ok', 'data' => null]);
        } catch (\Throwable $e) {
            Log::error('AuthController@logout: ' . $e->getMessage());
            return api_json(['code' => 500, 'message' => '服务器错误', 'data' => null]);
        }
    }

    private function getBearerToken(): ?string
    {
        $header = Request::header('authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return trim($m[1]);
        }
        return null;
    }
}
