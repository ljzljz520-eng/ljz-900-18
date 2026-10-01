<?php
declare(strict_types=1);
namespace app\controller;
use app\model\Record;
use app\model\User;
use think\facade\Log;
use think\Response;
class SummaryController
{
    public function index(): Response
    {
        try {
            $users = User::where('role', 'employee')->with(['records' => function ($q) {
                $q->with('item')->order('sequence_key', 'asc');
            }])->select();
            $data = [];
            foreach ($users as $user) {
                $records = $user->records;
                $total = 0;
                $completed = 0;
                $totalScore = 0;
                foreach ($records as $r) {
                    $total++;
                    if ($r->status === 'completed') {
                        $completed++;
                    }
                    $totalScore += (int) ($r->item_score_snapshot ?? ($r->item->score ?? 0));
                }
                $data[] = [
                    'user' => $user,
                    'records' => $records,
                    'total' => $total,
                    'completed' => $completed,
                    'progress' => $total > 0 ? round($completed / $total * 100, 1) : 0,
                    'total_score' => $totalScore,
                ];
            }
            return api_json(['code' => 0, 'message' => 'ok', 'data' => $data]);
        } catch (\Throwable $e) {
            Log::error('SummaryController@index: ' . $e->getMessage());
            return api_json(['code' => 500, 'message' => '服务器错误', 'data' => null]);
        }
    }
}
