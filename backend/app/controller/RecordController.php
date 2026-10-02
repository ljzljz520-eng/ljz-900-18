<?php
declare(strict_types=1);
namespace app\controller;
use app\model\InspectionItem;
use app\model\Record;
use app\model\User;
use app\service\QrService;
use app\service\RecordSequenceService;
use think\facade\Log;
use think\facade\Request;
use think\facade\Db;
use think\Response;
class RecordController
{
    protected function seq(): RecordSequenceService
    {
        return new RecordSequenceService();
    }

    private function normalizeCheckDate($checkDate): ?string
    {
        if (!$checkDate) {
            return null;
        }
        $checkDate = (string) $checkDate;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $checkDate)) {
            return '__INVALID__';
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $checkDate);
        if (!$dt || $dt->format('Y-m-d') !== $checkDate) {
            return '__INVALID__';
        }
        return $checkDate;
    }

    public function index(): Response
    {
        try {
            $userId = Request::param('user_id');
            $token = Request::param('token');
            $checkDate = $this->normalizeCheckDate(Request::param('check_date'));
            $status = Request::param('status');
            if ($token) {
                $user = User::where('token', $token)->find();
                if (!$user) {
                    return api_json(['code' => 404, 'message' => '无效的 token', 'data' => null]);
                }
                if (isset($user->is_active) && (int) $user->is_active !== 1) {
                    return api_json(['code' => 403, 'message' => '账号已禁用', 'data' => null]);
                }
                $userId = $user->id;
            }
            if (!$userId) {
                return api_json(['code' => 400, 'message' => '缺少 user_id 或 token', 'data' => null]);
            }
            if ($checkDate === '__INVALID__') {
                return api_json(['code' => 400, 'message' => 'check_date 格式错误（应为 YYYY-MM-DD）', 'data' => null]);
            }
            $query = Record::with(['item'])->where('user_id', (int) $userId);
            if ($checkDate) {
                $query->where('check_date', $checkDate);
            }
            if ($status) {
                $query->where('status', $status);
            }
            $list = $query->order('sequence_key', 'asc')->select();
            return api_json(['code' => 0, 'message' => 'ok', 'data' => $list->toArray()]);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            Log::error('RecordController@index: ' . $msg . "\n" . $e->getTraceAsString());
            if (stripos($msg, 'Unknown column') !== false && stripos($msg, 'check_date') !== false) {
                return api_json(['code' => 500, 'message' => '数据库缺少 records.check_date 字段，请执行 migrate_add_check_date.sql', 'data' => null]);
            }
            return api_json(['code' => 500, 'message' => '服务器错误', 'data' => null]);
        }
    }
    public function save(): Response
    {
        try {
            $userId = (int) Request::param('user_id');
            $items = Request::param('items'); // [{ item_id, issue_image }]
            $baseUrl = trim((string) Request::param('base_url', ''));
            if (!$userId || !is_array($items) || empty($items)) {
                return api_json(['code' => 400, 'message' => '参数错误', 'data' => null]);
            }
            $user = User::find($userId);
            if (!$user) {
                return api_json(['code' => 404, 'message' => '用户不存在', 'data' => null]);
            }
            $checkDate = (string) Request::param('check_date') ?: date('Y-m-d');
            $startKey = $this->seq()->getNextSequenceKey($userId, $checkDate);

            // 预取检查项，用于写入快照，避免后续修改 inspection_items 造成历史漂移
            $itemIds = [];
            foreach ($items as $item) {
                $itemId = (int) ($item['item_id'] ?? 0);
                if ($itemId) {
                    $itemIds[] = $itemId;
                }
            }
            $itemMap = [];
            if (!empty($itemIds)) {
                $rows = InspectionItem::whereIn('id', array_values(array_unique($itemIds)))->select();
                foreach ($rows as $row) {
                    $itemMap[(int) $row->id] = $row;
                }
            }

            $created = [];
            foreach ($items as $i => $item) {
                $itemId = (int) ($item['item_id'] ?? 0);
                $issueImage = (string) ($item['issue_image'] ?? '');
                if (!$itemId || !$issueImage) {
                    continue;
                }
                $snapName = null;
                $snapScore = null;
                if (isset($itemMap[$itemId])) {
                    $snapName = (string) $itemMap[$itemId]->name;
                    $snapScore = (int) $itemMap[$itemId]->score;
                }
                $record = Record::create([
                    'user_id'      => $userId,
                    'item_id'      => $itemId,
                    'item_name_snapshot'  => $snapName,
                    'item_score_snapshot' => $snapScore,
                    'sequence_key' => $startKey + $i,
                    'issue_image'  => $issueImage,
                    'status'       => 'pending',
                    'check_date'   => $checkDate,
                ]);
                $created[] = Record::with(['item'])->find($record->id)->toArray();
            }

            // 可选：同一步生成“带 token 链接 + 唯一二维码”
            if ($baseUrl !== '') {
                $qr = (new QrService())->generateForUser($user, $baseUrl);
                return api_json([
                    'code' => 0,
                    'message' => 'ok',
                    'data' => [
                        'records' => $created,
                        'link' => $qr['link'],
                        'qr_code_url' => $qr['qr_code_url'],
                    ],
                ]);
            }

            return api_json(['code' => 0, 'message' => 'ok', 'data' => $created]);
        } catch (\Throwable $e) {
            Log::error('RecordController@save: ' . $e->getMessage());
            return api_json(['code' => 500, 'message' => '服务器错误', 'data' => null]);
        }
    }
    public function delete(int $id): Response
    {
        try {
            $record = Record::find($id);
            if (!$record) {
                return api_json(['code' => 404, 'message' => '记录不存在', 'data' => null]);
            }
            $userId = (int) $record->user_id;
            $seqKey = (int) $record->sequence_key;
            $checkDate = $record->check_date ? (string) $record->check_date : null;
            $hasFix = !empty($record->fix_image);

            // 成对关系保护：该问题图已有整改图时，必须显式确认（整改图将随问题图一起移除）
            if ($hasFix) {
                $confirmed = Request::param('confirm_with_fix');
                if (!$this->isTruthy($confirmed)) {
                    return api_json([
                        'code' => 409,
                        'message' => '该问题图已有关联整改图，删除问题图后整改图将一并移除，请二次确认',
                        'data' => ['has_fix_image' => true],
                    ]);
                }
            }

            // 删除原因必填，便于审计追溯
            $reason = trim((string) Request::param('reason', ''));
            if ($reason === '') {
                return api_json(['code' => 400, 'message' => '请填写删除原因', 'data' => null]);
            }
            if (mb_strlen($reason) > 200) {
                return api_json(['code' => 400, 'message' => '删除原因不能超过 200 字', 'data' => null]);
            }

            // 删除人来自登录中间件注入的管理员身份（兜底再查一次，避免属性取不到）
            $operator = $this->currentAdmin();
            if (!$operator) {
                return api_json(['code' => 401, 'message' => '登录已过期，请重新登录', 'data' => null]);
            }
            $employee = User::find($userId);

            $issueImage = (string) $record->issue_image;
            $fixImage = (string) ($record->fix_image ?? '');
            $itemName = $record->item_name_snapshot;
            if (!$itemName && $record->item) {
                $itemName = $record->item->name;
            }

            Db::transaction(function () use ($record, $userId, $seqKey, $checkDate) {
                $record->delete();
                // 删除后重新计算该员工（同检查日期）的展示顺序，保证 #key 连续无跳跃
                $this->seq()->reorderAfterDelete($userId, $seqKey, $checkDate);
            });

            // 物理文件一并清理：问题图 + 成对整改图（失败不阻断删除，仅记日志）
            $this->safeUnlinkUpload($issueImage);
            if ($hasFix) {
                $this->safeUnlinkUpload($fixImage);
            }

            // 审计日志：删除人、删除原因、被删记录快照、成对整改图处理结果
            Log::info('RecordController@delete 管理员删除问题图', [
                'operator_id'   => (int) $operator->id,
                'operator_name' => (string) $operator->name,
                'reason'        => $reason,
                'record_id'     => (int) $id,
                'employee_id'   => $userId,
                'employee_name' => $employee ? (string) $employee->name : null,
                'item_id'       => (int) $record->item_id,
                'item_name'     => $itemName,
                'sequence_key'  => $seqKey,
                'check_date'    => $checkDate,
                'status'        => (string) $record->status,
                'issue_image'   => $issueImage,
                'fix_image'     => $fixImage,
                'fix_image_removed' => $hasFix,
            ]);

            return api_json(['code' => 0, 'message' => 'ok', 'data' => null]);
        } catch (\Throwable $e) {
            Log::error('RecordController@delete: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return api_json(['code' => 500, 'message' => '服务器错误', 'data' => null]);
        }
    }

    private function safeUnlinkUpload(string $relativePath): void
    {
        $relativePath = trim($relativePath);
        if ($relativePath === '' || !str_starts_with($relativePath, '/uploads/')) {
            return;
        }
        $full = public_path() . ltrim($relativePath, '/');
        $real = realpath($full);
        $baseReal = realpath(public_path() . 'uploads');
        if ($real === false || $baseReal === false || !str_starts_with($real, $baseReal)) {
            return; // 路径越界或不存在，跳过
        }
        if (!@unlink($real)) {
            Log::warning('RecordController@delete 上传文件删除失败', ['path' => $relativePath]);
        }
    }

    private function isTruthy($value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'on';
    }

    private function currentAdmin(): ?User
    {
        // AuthMiddleware 已通过 $request->authUser 注入登录管理员
        $request = request();
        $authUser = $request ? ($request->authUser ?? null) : null;
        if ($authUser instanceof User) {
            return $authUser;
        }
        // 兜底：按 Bearer token 再查一次
        $header = (string) Request::header('authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return User::where('auth_token', trim($m[1]))
                ->where('role', 'admin')
                ->where('auth_token_expires', '>', date('Y-m-d H:i:s'))
                ->find() ?: null;
        }
        return null;
    }
    public function uploadFix(int $id): Response
    {
        try {
            $record = Record::find($id);
            if (!$record) {
                return api_json(['code' => 404, 'message' => '记录不存在', 'data' => null]);
            }
            $token = (string) Request::param('token');
            if (!$token) {
                return api_json(['code' => 401, 'message' => '缺少 token', 'data' => null]);
            }
            $user = User::where('token', $token)->find();
            if (!$user) {
                return api_json(['code' => 401, 'message' => '无效的 token', 'data' => null]);
            }
            if ((int) $record->user_id !== (int) $user->id) {
                return api_json(['code' => 403, 'message' => '无权操作该记录', 'data' => null]);
            }
            $fixImage = Request::param('fix_image');
            if (!$fixImage) {
                return api_json(['code' => 400, 'message' => '缺少 fix_image', 'data' => null]);
            }
            $record->fix_image = $fixImage;
            $record->status = 'completed';
            $record->save();
            $record = Record::with(['item'])->find($record->id)->toArray();
            return api_json(['code' => 0, 'message' => 'ok', 'data' => $record]);
        } catch (\Throwable $e) {
            Log::error('RecordController@uploadFix: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return api_json(['code' => 500, 'message' => '服务器错误', 'data' => null]);
        }
    }
}
