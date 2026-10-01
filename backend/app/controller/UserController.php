<?php
declare(strict_types=1);
namespace app\controller;
use app\model\User;
use think\facade\Log;
use think\facade\Request;
use think\Response;
class UserController
{
    private function randomToken(int $bytes = 16): string
    {
        return bin2hex(random_bytes($bytes));
    }

    private function uniqueEmployeeToken(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $token = $this->randomToken(16);
            if (!User::where('token', $token)->find()) {
                return $token;
            }
        }
        // 极小概率冲突，兜底加时间
        return $this->randomToken(16) . dechex(time());
    }

    public function index(): Response
    {
        try {
            $list = User::where('role', 'employee')->select();
            $data = $list->isEmpty() ? [] : $list->toArray();
            return api_json(['code' => 0, 'message' => 'ok', 'data' => $data]);
        } catch (\Throwable $e) {
            Log::error('UserController@index: ' . $e->getMessage() . ' ' . $e->getTraceAsString());
            return api_json(['code' => 500, 'message' => '服务器错误', 'data' => null]);
        }
    }
    public function read(int $id): Response
    {
        try {
            $user = User::find($id);
            if (!$user) {
                return api_json(['code' => 404, 'message' => '用户不存在', 'data' => null]);
            }
            return api_json(['code' => 0, 'message' => 'ok', 'data' => $user->toArray()]);
        } catch (\Throwable $e) {
            Log::error('UserController@read: ' . $e->getMessage());
            return api_json(['code' => 500, 'message' => '服务器错误', 'data' => null]);
        }
    }

    public function create(): Response
    {
        try {
            $name = trim((string) Request::param('name', ''));
            if ($name === '') {
                return api_json(['code' => 400, 'message' => '缺少 name', 'data' => null]);
            }
            $user = User::create([
                'name' => $name,
                'role' => 'employee',
                'token' => $this->uniqueEmployeeToken(),
                'is_active' => 1,
            ]);
            return api_json(['code' => 0, 'message' => 'ok', 'data' => $user->toArray()]);
        } catch (\Throwable $e) {
            Log::error('UserController@create: ' . $e->getMessage() . ' ' . $e->getTraceAsString());
            return api_json(['code' => 500, 'message' => '服务器错误', 'data' => null]);
        }
    }

    public function update(int $id): Response
    {
        try {
            $user = User::find($id);
            if (!$user) {
                return api_json(['code' => 404, 'message' => '用户不存在', 'data' => null]);
            }
            if ($user->role !== 'employee') {
                return api_json(['code' => 400, 'message' => '仅支持编辑员工账号', 'data' => null]);
            }
            $name = trim((string) Request::param('name', ''));
            if ($name !== '') {
                $user->name = $name;
            }
            if (Request::has('is_active')) {
                $user->is_active = (int) Request::param('is_active') ? 1 : 0;
            }
            $user->save();
            return api_json(['code' => 0, 'message' => 'ok', 'data' => $user->toArray()]);
        } catch (\Throwable $e) {
            Log::error('UserController@update: ' . $e->getMessage());
            return api_json(['code' => 500, 'message' => '服务器错误', 'data' => null]);
        }
    }

    public function resetToken(int $id): Response
    {
        try {
            $user = User::find($id);
            if (!$user) {
                return api_json(['code' => 404, 'message' => '用户不存在', 'data' => null]);
            }
            if ($user->role !== 'employee') {
                return api_json(['code' => 400, 'message' => '仅支持重置员工 token', 'data' => null]);
            }
            $user->token = $this->uniqueEmployeeToken();
            $user->qr_code_url = null;
            $user->save();
            return api_json(['code' => 0, 'message' => 'ok', 'data' => $user->toArray()]);
        } catch (\Throwable $e) {
            Log::error('UserController@resetToken: ' . $e->getMessage());
            return api_json(['code' => 500, 'message' => '服务器错误', 'data' => null]);
        }
    }

    public function toggleActive(int $id): Response
    {
        try {
            $user = User::find($id);
            if (!$user) {
                return api_json(['code' => 404, 'message' => '用户不存在', 'data' => null]);
            }
            if ($user->role !== 'employee') {
                return api_json(['code' => 400, 'message' => '仅支持禁用/启用员工账号', 'data' => null]);
            }
            $user->is_active = (int) $user->is_active ? 0 : 1;
            $user->save();
            return api_json(['code' => 0, 'message' => 'ok', 'data' => $user->toArray()]);
        } catch (\Throwable $e) {
            Log::error('UserController@toggleActive: ' . $e->getMessage());
            return api_json(['code' => 500, 'message' => '服务器错误', 'data' => null]);
        }
    }
}
