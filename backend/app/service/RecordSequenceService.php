<?php
declare(strict_types=1);
namespace app\service;
use app\model\Record;
use think\facade\Db;
class RecordSequenceService
{
    public function getNextSequenceKey(int $userId, ?string $checkDate = null): int
    {
        $query = Record::where('user_id', $userId);
        if ($checkDate) {
            $query->where('check_date', $checkDate);
        }
        $max = $query->max('sequence_key');
        return (int) $max + 1;
    }
    public function reorderAfterDelete(int $userId, int $deletedSequenceKey, ?string $checkDate = null): void
    {
        Db::transaction(function () use ($userId, $deletedSequenceKey, $checkDate) {
            $query = Record::where('user_id', $userId)->where('sequence_key', '>', $deletedSequenceKey);
            if ($checkDate) {
                $query->where('check_date', $checkDate);
            }
            $list = $query->order('sequence_key', 'asc')->select();
            foreach ($list as $r) {
                $r->sequence_key = $r->sequence_key - 1;
                $r->save();
            }
        });
    }
}
