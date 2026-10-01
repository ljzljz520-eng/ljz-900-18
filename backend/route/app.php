<?php
use think\facade\Route;
use app\controller\AuthController;
use app\controller\UserController;
use app\controller\InspectionItemController;
use app\controller\RecordController;
use app\controller\UploadController;
use app\controller\QrController;
use app\controller\SummaryController;
use app\middleware\AuthMiddleware;

Route::post('/api/auth/login', [AuthController::class, 'login']);
Route::get('/api/auth/me', [AuthController::class, 'me']);
Route::post('/api/auth/logout', [AuthController::class, 'logout']);

// 以下管理端接口需登录后访问
Route::get('/api/users', [UserController::class, 'index'])->middleware(AuthMiddleware::class);
Route::get('/api/users/:id', [UserController::class, 'read'])->middleware(AuthMiddleware::class);
Route::post('/api/users', [UserController::class, 'create'])->middleware(AuthMiddleware::class);
Route::put('/api/users/:id', [UserController::class, 'update'])->middleware(AuthMiddleware::class);
Route::post('/api/users/:id/reset-token', [UserController::class, 'resetToken'])->middleware(AuthMiddleware::class);
Route::post('/api/users/:id/toggle-active', [UserController::class, 'toggleActive'])->middleware(AuthMiddleware::class);
Route::get('/api/inspection-items', [InspectionItemController::class, 'index'])->middleware(AuthMiddleware::class);
// 记录查询需支持员工端通过 token 访问，因此不强制登录
Route::get('/api/records', [RecordController::class, 'index']);
Route::post('/api/records', [RecordController::class, 'save'])->middleware(AuthMiddleware::class);
Route::delete('/api/records/:id', [RecordController::class, 'delete'])->middleware(AuthMiddleware::class);
Route::put('/api/records/:id/fix', [RecordController::class, 'uploadFix']);
// 图片上传同时服务于管理员端与员工整改端，这里放宽登录限制，由业务自身校验
Route::post('/api/upload/image', [UploadController::class, 'image']);
Route::post('/api/qr/generate', [QrController::class, 'generate'])->middleware(AuthMiddleware::class);
Route::get('/api/summary', [SummaryController::class, 'index'])->middleware(AuthMiddleware::class);
Route::get('/', function () {
    return json(['code' => 0, 'message' => 'Hygiene Audit API', 'data' => null]);
});
