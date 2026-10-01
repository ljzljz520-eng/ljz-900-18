<?php
declare(strict_types=1);
namespace app\middleware;
use app\model\User;
use Closure;
use think\Request;
use think\Response;

class AuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->getBearerToken($request);
        if (!$token) {
            return api_json(['code' => 401, 'message' => '未登录', 'data' => null], 200);
        }

        $user = User::where('auth_token', $token)
            ->where('role', 'admin')
            ->where('auth_token_expires', '>', date('Y-m-d H:i:s'))
            ->find();

        if (!$user) {
            return api_json(['code' => 401, 'message' => '登录已过期，请重新登录', 'data' => null], 200);
        }

        $request->authUser = $user;
        return $next($request);
    }

    private function getBearerToken(Request $request): ?string
    {
        $header = $request->header('authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return trim($m[1]);
        }
        return null;
    }
}
