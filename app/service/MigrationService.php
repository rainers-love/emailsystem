<?php
declare(strict_types=1);

namespace app\service;

use app\database\DatabaseAdapterInterface;
use app\database\SchemaDefinition;
use app\database\SchemaManager;

class MigrationService
{
    protected DatabaseAdapterInterface $adapter;
    protected SchemaManager $schemaManager;

    public function __construct(DatabaseAdapterInterface $adapter)
    {
        $this->adapter = $adapter;
        $this->schemaManager = new SchemaManager($adapter);
    }

    public function runCoreMigrations(): array
    {
        $this->schemaManager->initMigrationsTable();
        $tables = SchemaDefinition::getTables();
        $executed = [];

        foreach ($tables as $tableName => $def) {
            $version = "v1_create_{$tableName}";
            if ($this->schemaManager->hasMigration($version)) {
                continue;
            }

            $sql = $this->adapter->generateCreateTableSql($tableName, $def['columns'], $def['indexes'] ?? []);
            
            // 某些适配器或复合 SQL 按分号拆分执行
            $sqls = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($sqls as $s) {
                if (!empty($s)) {
                    $this->adapter->execute($s);
                }
            }

            $this->schemaManager->recordMigration($version, "Create table {$tableName}");
            $executed[] = $tableName;
        }

        // 默认初始化管理员与测试部门用户
        $this->seedInitialData();

        return $executed;
    }

    public function seedInitialData(): void
    {
        // 1. 初始化部门
        $deptTable = $this->adapter->quoteIdentifier('sys_departments');
        $depts = $this->adapter->query("SELECT id FROM {$deptTable} LIMIT 1");
        if (empty($depts)) {
            $d1 = $this->adapter->insert('sys_departments', [
                'name' => '总经办',
                'parent_id' => 0,
                'order_num' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $d2 = $this->adapter->insert('sys_departments', [
                'name' => '研发中心',
                'parent_id' => 0,
                'order_num' => 2,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $d3 = $this->adapter->insert('sys_departments', [
                'name' => '运营部',
                'parent_id' => 0,
                'order_num' => 3,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $d1 = 1; $d2 = 2; $d3 = 3;
        }

        // 2. 初始化初始账号 (密码统一为 123456)
        $userTable = $this->adapter->quoteIdentifier('sys_users');
        $users = $this->adapter->query("SELECT id FROM {$userTable} LIMIT 1");
        if (empty($users)) {
            $hash = password_hash('123456', PASSWORD_BCRYPT);
            // 系统管理员
            $this->adapter->insert('sys_users', [
                'username' => 'admin',
                'password' => $hash,
                'nickname' => '系统管理员',
                'email' => 'admin@company.local',
                'department_id' => (int)$d1,
                'position' => '系统运维',
                'phone' => '13800000001',
                'role' => 'admin',
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // 数据库迁移管理员 (专有高权限角色)
            $this->adapter->insert('sys_users', [
                'username' => 'dbadmin',
                'password' => $hash,
                'nickname' => '数据库管理员',
                'email' => 'dbadmin@company.local',
                'department_id' => (int)$d1,
                'position' => 'DBA架构师',
                'phone' => '13800000002',
                'role' => 'db_admin',
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // 普通测试员工1
            $this->adapter->insert('sys_users', [
                'username' => 'zhangsan',
                'password' => $hash,
                'nickname' => '张三',
                'email' => 'zhangsan@company.local',
                'department_id' => (int)$d2,
                'position' => '前端开发工程师',
                'phone' => '13800000003',
                'role' => 'user',
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // 普通测试员工2
            $this->adapter->insert('sys_users', [
                'username' => 'lisi',
                'password' => $hash,
                'nickname' => '李四',
                'email' => 'lisi@company.local',
                'department_id' => (int)$d2,
                'position' => 'PHP后端工程师',
                'phone' => '13800000004',
                'role' => 'user',
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // 普通测试员工3
            $this->adapter->insert('sys_users', [
                'username' => 'wangwu',
                'password' => $hash,
                'nickname' => '王五',
                'email' => 'wangwu@company.local',
                'department_id' => (int)$d3,
                'position' => '运营主管',
                'phone' => '13800000005',
                'role' => 'user',
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // 3. 初始系统配置
        $cfgTable = $this->adapter->quoteIdentifier('sys_config');
        $existing = $this->adapter->query("SELECT id FROM {$cfgTable} WHERE key_name = 'system_name' LIMIT 1");
        if (empty($existing)) {
            $this->adapter->insert('sys_config', [
                'key_name' => 'system_name',
                'key_value' => '企业内部邮件协同办公系统',
                'description' => '系统全局名称',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->adapter->insert('sys_config', [
                'key_name' => 'max_attachment_size_mb',
                'key_value' => '50',
                'description' => '单附件最大限制(MB)',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->adapter->insert('sys_config', [
                'key_name' => 'allowed_extensions',
                'key_value' => 'pdf,doc,docx,xls,xlsx,ppt,pptx,png,jpg,jpeg,gif,zip,rar,7z,txt',
                'description' => '允许的附件扩展名',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->adapter->insert('sys_config', [
                'key_name' => 'maintenance_mode',
                'key_value' => '0',
                'description' => '系统维护/只读模式(0关闭, 1开启)',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
