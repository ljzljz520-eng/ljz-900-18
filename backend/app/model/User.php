<?php
declare(strict_types=1);
namespace app\model;
use think\Model;
class User extends Model
{
    protected $table = 'users';
    /** 关闭自动时间戳，表结构无 update_time */
    protected $autoWriteTimestamp = false;
    protected $schema = [
        'id'                 => 'int',
        'name'               => 'string',
        'username'           => 'string',
        'password_hash'      => 'string',
        'token'              => 'string',
        'qr_code_url'        => 'string',
        'role'               => 'string',
        'is_active'          => 'int',
        'auth_token'         => 'string',
        'auth_token_expires' => 'datetime',
        'created_at'         => 'datetime',
    ];
    public function records()
    {
        return $this->hasMany(Record::class, 'user_id', 'id');
    }
}
