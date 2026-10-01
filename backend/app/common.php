<?php
declare(strict_types=1);
/**
 * 统一 API JSON 响应：UTF-8 编码、不转义 Unicode，避免中文乱码
 */
if (!function_exists('api_json')) {
    function api_json($data, int $code = 200): \think\Response
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE);
        return response($body, $code, ['Content-Type' => 'application/json; charset=utf-8']);
    }
}
