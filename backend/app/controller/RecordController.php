<?php
declare(strict_types=1);
namespace app\controller;
use app\model\InspectionItem;
use app\model\Record;
use app\model\User;
use app\service\QrService;
use app\service\RecordSequenceService;
use think\facade\Db;
use think\facade\Log;
use think\facade\Request;
use think\Request as HttpRequest;
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
    /**
     * 读取请求参数（兼容 JSON body 与 form）
     */
    private function bodyParams(HttpRequest $request): array
    {
        $params = $request->param();
        if (stripos((string) $request->header('content-type', ''), 'application/json') !== false) {
            $raw = $request->getContent() ?: '';
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $params = array_merge($params, $decoded);
                }
            }
        }
        return $params;
    }

    /**
     * 删除上传的图片文件（问题图/整改图），仅允许删除 public 目录内的 uploads 文件；
     * 删除失败不阻断主流程，仅记录错误日志。
     */
    private function unlinkUploaded(?string $path): void
    {
        if (!$path) {
            return;
        }
        $path = trim($path);
        if ($path === '' || str_contains($path, '..') || preg_match('#^https?://#i', $path)) {
            return;
        }
        $full = realpath(public_path() . ltrim($path, '/'));
        $root = realpath(public_path() . 'uploads');
        if ($full === false || $root === false || !str_starts_with($full, $root . DIRECTORY_SEPARATOR) || !is_file($full)) {
            return;
        }
        if (!@unlink($full)) {
            Log::warning('RecordController@delete unlink failed: ' . $full);
        }
    }

    /**
     * 获取当前登录管理员（中间件已注入 authUser，此处再兜底按 Bearer token 查询）
     */
    private function resolveAdmin(HttpRequest $request): ?User
    {
        $authUser = $request->authUser ?? null;
        if ($authUser instanceof User) {
            return $authUser;
        }
        $header = (string) $request->header('authorization', '');
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return User::where('auth_token', trim($m[1]))
                ->where('role', 'admin')
                ->where('auth_token_expires', '>', date('Y-m-d H:i:s'))
                ->find();
        }
        return null;
    }

    public function delete(int $id, HttpRequest $httpRequest): Response
    {
        try {
            $record = Record::find($id);
            if (!$record) {
                return api_json(['code' => 404, 'message' => '记录不存在', 'data' => null]);
            }

            $params = $this->bodyParams($httpRequest);
            $reason = trim((string) ($params['reason'] ?? ''));
            $hasFix = !empty($record->fix_image);

            // 问题图与整改图成对存在：删除问题图会连同整改图一起移除，
            // 必须由前端显式确认级联删除（confirm_cascade=1）
            if ($hasFix && (string) ($params['confirm_cascade'] ?? '') !== '1') {
                return api_json([
                    'code' => 409,
                    'message' => '该问题图已有整改图，删除后整改图将不可见并会随问题图一起移除，请确认后重试',
                    'data' => ['has_fix_image' => true],
                ]);
            }
            if ($reason === '') {
                $reason = '管理员删除问题图';
            }

            $admin = $this->resolveAdmin($httpRequest);
            $operatorName = $admin
                ? ((string) ($admin->name ?: $admin->username))
                : 'unknown_admin';

            $userId = (int) $record->user_id;
            $seqKey = (int) $record->sequence_key;
            $checkDate = $record->check_date ? (string) $record->check_date : null;
            $issueImage = (string) ($record->issue_image ?? '');
            $fixImage = $hasFix ? (string) $record->fix_image : '';
            $itemName = (string) ($record->item_name_snapshot ?? '');

            // 删除与该员工展示顺序重算需原子完成，避免出现序号空洞
            Db::transaction(function () use ($record, $userId, $seqKey, $checkDate) {
                $record->delete();
                $this->seq()->reorderAfterDelete($userId, $seqKey, $checkDate);
            });

            // 成对关系处理：整改图随问题图一起移除（含物理文件）
            $this->unlinkUploaded($fixImage);
            $this->unlinkUploaded($issueImage);

            Log::info('[admin-delete-record] ' . json_encode([
                'operator'   => $operatorName,
                'operator_id' => $admin ? (int) $admin->id : null,
                'reason'     => $reason,
                'record_id'  => $id,
                'employee_user_id' => $userId,
                'sequence_key' => $seqKey,
                'check_date' => $checkDate,
                'item_name_snapshot' => $itemName,
                'issue_image' => $issueImage,
                'fix_image'  => $fixImage,
                'fix_removed_together' => $hasFix,
            ], JSON_UNESCAPED_UNICODE));

            return api_json(['code' => 0, 'message' => 'ok', 'data' => null]);
        } catch (\Throwable $e) {
            Log::error('RecordController@delete: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return api_json(['code' => 500, 'message' => '服务器错误', 'data' => null]);
        }
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
