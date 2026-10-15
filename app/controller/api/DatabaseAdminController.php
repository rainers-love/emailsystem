<?php
declare(strict_types=1);

namespace app\controller\api;

use support\Request;
use support\Response;
use app\database\DatabaseManager;
use app\service\DatabaseMigrationService;
use Exception;

class DatabaseAdminController
{
    protected function checkDbAdminPermission(Request $request): void
    {
        $role = $request->user['role'] ?? 'user';
        if ($role !== 'db_admin' && $role !== 'admin') {
            throw new Exception("权限不足：需要数据库运维管理权限");
        }
    }

    public function connections(Request $request): Response
    {
        try {
            $this->checkDbAdminPermission($request);
            $adapter = DatabaseManager::getAdapter();
            $currConfig = DatabaseManager::getConfig();

            // 脱敏返回当前连接信息
            $safeCurrent = [
                'driver' => $currConfig['driver_type'] ?? 'mysql',
                'host' => $currConfig['host'] ?? '127.0.0.1',
                'port' => $currConfig['port'] ?? 3306,
                'database' => $currConfig['database'] ?? '',
                'username' => $currConfig['username'] ?? '',
                'charset' => $currConfig['charset'] ?? 'utf8mb4',
            ];

            return json([
                'code' => 200,
                'msg' => 'ok',
                'data' => [
                    'active' => $safeCurrent,
                    'supported_targets' => ['kingbase', 'mysql'],
                ]
            ]);
        } catch (Exception $e) {
            return json(['code' => 403, 'msg' => $e->getMessage()]);
        }
    }

    public function testConnection(Request $request): Response
    {
        try {
            $this->checkDbAdminPermission($request);
            $service = new DatabaseMigrationService();
            $params = $request->post();
            $res = $service->testTargetConnection($params);
            return json(['code' => 200, 'msg' => 'ok', 'data' => $res]);
        } catch (Exception $e) {
            return json(['code' => 400, 'msg' => $e->getMessage()]);
        }
    }

    public function previewSchema(Request $request): Response
    {
        try {
            $this->checkDbAdminPermission($request);
            $service = new DatabaseMigrationService();
            $preview = $service->generateSchemaPreview();
            return json(['code' => 200, 'msg' => 'ok', 'data' => $preview]);
        } catch (Exception $e) {
            return json(['code' => 400, 'msg' => $e->getMessage()]);
        }
    }

    public function createMigration(Request $request): Response
    {
        try {
            $this->checkDbAdminPermission($request);
            $operatorId = (int)$request->user['sub'];
            $service = new DatabaseMigrationService();
            $targetConfig = $request->post('target_config', []);
            $uuid = $service->createMigrationTask($operatorId, $targetConfig);
            return json(['code' => 200, 'msg' => '迁移任务已创建', 'data' => ['task_uuid' => $uuid]]);
        } catch (Exception $e) {
            return json(['code' => 400, 'msg' => $e->getMessage()]);
        }
    }

    public function executeMigration(Request $request, string $uuid): Response
    {
        try {
            $this->checkDbAdminPermission($request);
            $service = new DatabaseMigrationService();
            $targetConfig = $request->post('target_config', []);
            $res = $service->executeMigrationStep($uuid, $targetConfig);
            return json(['code' => 200, 'msg' => '迁移与校验执行完成', 'data' => $res]);
        } catch (Exception $e) {
            return json(['code' => 400, 'msg' => $e->getMessage()]);
        }
    }

    public function tasks(Request $request): Response
    {
        try {
            $this->checkDbAdminPermission($request);
            $adapter = DatabaseManager::getAdapter();
            $tTbl = $adapter->quoteIdentifier('sys_db_migration_tasks');
            $tasks = $adapter->query("SELECT * FROM {$tTbl} ORDER BY id DESC LIMIT 20");
            return json(['code' => 200, 'msg' => 'ok', 'data' => $tasks]);
        } catch (Exception $e) {
            return json(['code' => 403, 'msg' => $e->getMessage()]);
        }
    }
}
