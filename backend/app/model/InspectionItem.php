<?php
declare(strict_types=1);
namespace app\model;
use think\Model;
class InspectionItem extends Model
{
    protected $table = 'inspection_items';
    protected $schema = [
        'id'    => 'int',
        'name'  => 'string',
        'score' => 'int',
    ];
}
