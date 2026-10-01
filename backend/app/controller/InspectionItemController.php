<?php
declare(strict_types=1);
namespace app\controller;
use app\model\InspectionItem;
use think\facade\Log;
use think\Response;
class InspectionItemController
{
    public function index(): Response
    {
        try {
            $list = InspectionItem::select();
            $data = $list->isEmpty() ? [] : $list->toArray();
            return api_json(['code' => 0, 'message' => 'ok', 'data' => $data]);
        } catch (\Throwable $e) {
            Log::error('InspectionItemController@index: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return api_json(['code' => 500, 'message' => '服务器错误', 'data' => null]);
        }
    }
}
