<?php
use Webman\Route;
use app\controller\api\AuthController;
use app\controller\api\MailController;
use app\controller\api\AttachmentController;
use app\controller\api\ContactController;
use app\controller\api\DatabaseAdminController;

// 1. 认证接口
Route::post('/api/auth/login', [AuthController::class, 'login']);
Route::get('/api/auth/userinfo', [AuthController::class, 'userinfo']);

// 2. 邮件接口
Route::get('/api/mail/inbox', [MailController::class, 'inbox']);
Route::get('/api/mail/sent', [MailController::class, 'sent']);
Route::get('/api/mail/drafts', [MailController::class, 'drafts']);
Route::get('/api/mail/audits/pending', [MailController::class, 'pendingAudits']);
Route::post('/api/mail/{id}/approve', [MailController::class, 'approve']);
Route::post('/api/mail/{id}/reject', [MailController::class, 'reject']);
Route::get('/api/mail/{id}', [MailController::class, 'detail']);
Route::post('/api/mail', [MailController::class, 'send']);
Route::post('/api/mail/{id}/read', [MailController::class, 'read']);
Route::post('/api/mail/{id}/unread', [MailController::class, 'unread']);
Route::post('/api/mail/{id}/star', [MailController::class, 'star']);
Route::post('/api/mail/{id}/delete', [MailController::class, 'delete']);

// 3. 附件接口
Route::post('/api/attachments/upload', [AttachmentController::class, 'upload']);
Route::get('/api/attachments/{id}/download', [AttachmentController::class, 'download']);

// 4. 通讯录接口
Route::get('/api/contacts', [ContactController::class, 'index']);
Route::get('/api/departments', [ContactController::class, 'departments']);

// 5. 数据库管理与迁移接口
Route::get('/api/admin/database/connections', [DatabaseAdminController::class, 'connections']);
Route::post('/api/admin/database/test', [DatabaseAdminController::class, 'testConnection']);
Route::get('/api/admin/database/schema-preview', [DatabaseAdminController::class, 'previewSchema']);
Route::post('/api/admin/database/migration', [DatabaseAdminController::class, 'createMigration']);
Route::post('/api/admin/database/migration/{uuid}/execute', [DatabaseAdminController::class, 'executeMigration']);
Route::get('/api/admin/database/migration/tasks', [DatabaseAdminController::class, 'tasks']);

// 静态前端 SPA 回退
Route::fallback(function () {
    $indexHtml = public_path() . '/index.html';
    if (file_exists($indexHtml)) {
        return response(file_get_contents($indexHtml))->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
    return json(['code' => 404, 'msg' => 'API Not Found']);
});
