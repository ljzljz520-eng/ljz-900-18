<?php
declare(strict_types=1);
namespace app\controller;
class Index
{
    public function index()
    {
        return api_json(['code' => 0, 'message' => 'ok', 'data' => null]);
    }
}
