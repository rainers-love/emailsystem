<?php
declare(strict_types=1);

namespace app\database;

use app\database\DatabaseAdapterInterface;

class SchemaDefinition
{
    public static function getTables(): array
    {
        return [
            // 1. 用户与部门表
            'sys_departments' => [
                'columns' => [
                    'id' => ['type' => 'int(11)', 'auto_increment' => true],
                    'name' => ['type' => 'varchar(100)', 'nullable' => false],
                    'parent_id' => ['type' => 'int(11)', 'nullable' => false, 'default' => 0],
                    'order_num' => ['type' => 'int(11)', 'nullable' => false, 'default' => 0],
                    'created_at' => ['type' => 'datetime', 'nullable' => false, 'default' => 'CURRENT_TIMESTAMP'],
                    'updated_at' => ['type' => 'datetime', 'nullable' => true],
                ],
                'indexes' => [
                    ['name' => 'idx_parent', 'columns' => ['parent_id']],
                ],
            ],

            'sys_users' => [
                'columns' => [
                    'id' => ['type' => 'int(11)', 'auto_increment' => true],
                    'username' => ['type' => 'varchar(50)', 'nullable' => false],
                    'password' => ['type' => 'varchar(255)', 'nullable' => false],
                    'nickname' => ['type' => 'varchar(50)', 'nullable' => false],
                    'email' => ['type' => 'varchar(100)', 'nullable' => false],
                    'department_id' => ['type' => 'int(11)', 'nullable' => false, 'default' => 0],
                    'position' => ['type' => 'varchar(50)', 'nullable' => true],
                    'phone' => ['type' => 'varchar(30)', 'nullable' => true],
                    'role' => ['type' => 'varchar(30)', 'nullable' => false, 'default' => 'user'], // admin, db_admin, user
                    'status' => ['type' => 'tinyint(1)', 'nullable' => false, 'default' => 1],
                    'created_at' => ['type' => 'datetime', 'nullable' => false, 'default' => 'CURRENT_TIMESTAMP'],
                    'updated_at' => ['type' => 'datetime', 'nullable' => true],
                ],
                'indexes' => [
                    ['name' => 'uniq_username', 'columns' => ['username'], 'unique' => true],
                    ['name' => 'uniq_email', 'columns' => ['email'], 'unique' => true],
                    ['name' => 'idx_dept', 'columns' => ['department_id']],
                ],
            ],

            // 2. 邮件主表
            'mail_message' => [
                'columns' => [
                    'id' => ['type' => 'bigint(20)', 'auto_increment' => true],
                    'sender_id' => ['type' => 'int(11)', 'nullable' => false],
                    'subject' => ['type' => 'varchar(255)', 'nullable' => false],
                    'content_type' => ['type' => 'varchar(20)', 'nullable' => false, 'default' => 'html'],
                    'content' => ['type' => 'text', 'nullable' => false],
                    'status' => ['type' => 'varchar(20)', 'nullable' => false, 'default' => 'sent'], // sent, draft
                    'audit_status' => ['type' => 'varchar(20)', 'nullable' => false, 'default' => 'approved'], // none, pending, approved, rejected
                    'audit_user_id' => ['type' => 'int(11)', 'nullable' => true],
                    'audit_time' => ['type' => 'datetime', 'nullable' => true],
                    'audit_remark' => ['type' => 'varchar(255)', 'nullable' => true],
                    'has_attachment' => ['type' => 'tinyint(1)', 'nullable' => false, 'default' => 0],
                    'is_deleted' => ['type' => 'tinyint(1)', 'nullable' => false, 'default' => 0],
                    'is_starred' => ['type' => 'tinyint(1)', 'nullable' => false, 'default' => 0],
                    'sent_at' => ['type' => 'datetime', 'nullable' => true],
                    'created_at' => ['type' => 'datetime', 'nullable' => false, 'default' => 'CURRENT_TIMESTAMP'],
                    'updated_at' => ['type' => 'datetime', 'nullable' => true],
                ],
                'indexes' => [
                    ['name' => 'idx_sender', 'columns' => ['sender_id', 'status', 'is_deleted']],
                    ['name' => 'idx_audit', 'columns' => ['audit_status']],
                    ['name' => 'idx_sent_at', 'columns' => ['sent_at']],
                ],
            ],

            // 3. 邮件收件人表
            'mail_recipient' => [
                'columns' => [
                    'id' => ['type' => 'bigint(20)', 'auto_increment' => true],
                    'mail_id' => ['type' => 'bigint(20)', 'nullable' => false],
                    'recipient_id' => ['type' => 'int(11)', 'nullable' => false],
                    'type' => ['type' => 'varchar(10)', 'nullable' => false, 'default' => 'TO'], // TO, CC, BCC
                    'is_read' => ['type' => 'tinyint(1)', 'nullable' => false, 'default' => 0],
                    'read_at' => ['type' => 'datetime', 'nullable' => true],
                    'is_deleted' => ['type' => 'tinyint(1)', 'nullable' => false, 'default' => 0],
                    'is_starred' => ['type' => 'tinyint(1)', 'nullable' => false, 'default' => 0],
                    'created_at' => ['type' => 'datetime', 'nullable' => false, 'default' => 'CURRENT_TIMESTAMP'],
                ],
                'indexes' => [
                    ['name' => 'idx_recip_box', 'columns' => ['recipient_id', 'is_deleted', 'is_read']],
                    ['name' => 'idx_mail_recip', 'columns' => ['mail_id', 'recipient_id']],
                ],
            ],

            // 4. 邮件附件表
            'mail_attachment' => [
                'columns' => [
                    'id' => ['type' => 'bigint(20)', 'auto_increment' => true],
                    'mail_id' => ['type' => 'bigint(20)', 'nullable' => true], // 可在草稿保存或独立上传时先置空/或关联
                    'uploader_id' => ['type' => 'int(11)', 'nullable' => false],
                    'original_name' => ['type' => 'varchar(255)', 'nullable' => false],
                    'stored_name' => ['type' => 'varchar(255)', 'nullable' => false],
                    'storage_path' => ['type' => 'varchar(255)', 'nullable' => false],
                    'mime_type' => ['type' => 'varchar(100)', 'nullable' => false],
                    'file_size' => ['type' => 'bigint(20)', 'nullable' => false],
                    'file_hash' => ['type' => 'varchar(64)', 'nullable' => false],
                    'created_at' => ['type' => 'datetime', 'nullable' => false, 'default' => 'CURRENT_TIMESTAMP'],
                ],
                'indexes' => [
                    ['name' => 'idx_attach_mail', 'columns' => ['mail_id']],
                    ['name' => 'idx_uploader', 'columns' => ['uploader_id']],
                ],
            ],

            // 5. 邮件操作审计日志
            'mail_operation_log' => [
                'columns' => [
                    'id' => ['type' => 'bigint(20)', 'auto_increment' => true],
                    'user_id' => ['type' => 'int(11)', 'nullable' => false],
                    'action' => ['type' => 'varchar(50)', 'nullable' => false],
                    'mail_id' => ['type' => 'bigint(20)', 'nullable' => true],
                    'target_type' => ['type' => 'varchar(50)', 'nullable' => true],
                    'target_id' => ['type' => 'varchar(50)', 'nullable' => true],
                    'ip' => ['type' => 'varchar(50)', 'nullable' => true],
                    'user_agent' => ['type' => 'varchar(255)', 'nullable' => true],
                    'created_at' => ['type' => 'datetime', 'nullable' => false, 'default' => 'CURRENT_TIMESTAMP'],
                ],
                'indexes' => [
                    ['name' => 'idx_user_action', 'columns' => ['user_id', 'action']],
                    ['name' => 'idx_log_created', 'columns' => ['created_at']],
                ],
            ],

            // 6. 系统与数据库管理配置表
            'sys_config' => [
                'columns' => [
                    'id' => ['type' => 'int(11)', 'auto_increment' => true],
                    'key_name' => ['type' => 'varchar(64)', 'nullable' => false],
                    'key_value' => ['type' => 'text', 'nullable' => true],
                    'description' => ['type' => 'varchar(255)', 'nullable' => true],
                    'updated_at' => ['type' => 'datetime', 'nullable' => false, 'default' => 'CURRENT_TIMESTAMP'],
                ],
                'indexes' => [
                    ['name' => 'uniq_key_name', 'columns' => ['key_name'], 'unique' => true],
                ],
            ],

            // 7. 数据库管理与迁移任务表
            'sys_db_connections' => [
                'columns' => [
                    'id' => ['type' => 'int(11)', 'auto_increment' => true],
                    'name' => ['type' => 'varchar(100)', 'nullable' => false],
                    'driver' => ['type' => 'varchar(30)', 'nullable' => false], // mysql, kingbase
                    'host' => ['type' => 'varchar(100)', 'nullable' => false],
                    'port' => ['type' => 'int(11)', 'nullable' => false],
                    'database' => ['type' => 'varchar(100)', 'nullable' => false],
                    'username' => ['type' => 'varchar(100)', 'nullable' => false],
                    'password_encrypted' => ['type' => 'text', 'nullable' => false],
                    'charset' => ['type' => 'varchar(30)', 'nullable' => false, 'default' => 'utf8mb4'],
                    'is_active' => ['type' => 'tinyint(1)', 'nullable' => false, 'default' => 0],
                    'created_at' => ['type' => 'datetime', 'nullable' => false, 'default' => 'CURRENT_TIMESTAMP'],
                ],
                'indexes' => [
                    ['name' => 'uniq_conn_name', 'columns' => ['name'], 'unique' => true],
                ],
            ],

            'sys_db_migration_tasks' => [
                'columns' => [
                    'id' => ['type' => 'bigint(20)', 'auto_increment' => true],
                    'task_uuid' => ['type' => 'varchar(64)', 'nullable' => false],
                    'source_db' => ['type' => 'varchar(100)', 'nullable' => false],
                    'target_db' => ['type' => 'varchar(100)', 'nullable' => false],
                    'operator_id' => ['type' => 'int(11)', 'nullable' => false],
                    'status' => ['type' => 'varchar(30)', 'nullable' => false, 'default' => 'created'],
                    // created, testing, converting_schema, migrating_data, validating, waiting_cutover, final_sync, switching, completed, failed, rollback_in_progress, rolled_back
                    'total_tables' => ['type' => 'int(11)', 'nullable' => false, 'default' => 0],
                    'total_rows' => ['type' => 'bigint(20)', 'nullable' => false, 'default' => 0],
                    'migrated_rows' => ['type' => 'bigint(20)', 'nullable' => false, 'default' => 0],
                    'failed_rows' => ['type' => 'bigint(20)', 'nullable' => false, 'default' => 0],
                    'error_msg' => ['type' => 'text', 'nullable' => true],
                    'validation_report' => ['type' => 'text', 'nullable' => true],
                    'started_at' => ['type' => 'datetime', 'nullable' => true],
                    'finished_at' => ['type' => 'datetime', 'nullable' => true],
                    'created_at' => ['type' => 'datetime', 'nullable' => false, 'default' => 'CURRENT_TIMESTAMP'],
                ],
                'indexes' => [
                    ['name' => 'uniq_task_uuid', 'columns' => ['task_uuid'], 'unique' => true],
                    ['name' => 'idx_status', 'columns' => ['status']],
                ],
            ],

            'sys_db_migration_tables' => [
                'columns' => [
                    'id' => ['type' => 'bigint(20)', 'auto_increment' => true],
                    'task_uuid' => ['type' => 'varchar(64)', 'nullable' => false],
                    'table_name' => ['type' => 'varchar(100)', 'nullable' => false],
                    'source_rows' => ['type' => 'bigint(20)', 'nullable' => false, 'default' => 0],
                    'target_rows' => ['type' => 'bigint(20)', 'nullable' => false, 'default' => 0],
                    'status' => ['type' => 'varchar(20)', 'nullable' => false, 'default' => 'pending'], // pending, processing, completed, failed
                    'last_migrated_id' => ['type' => 'bigint(20)', 'nullable' => false, 'default' => 0],
                    'error_msg' => ['type' => 'text', 'nullable' => true],
                    'updated_at' => ['type' => 'datetime', 'nullable' => false, 'default' => 'CURRENT_TIMESTAMP'],
                ],
                'indexes' => [
                    ['name' => 'idx_task_tbl', 'columns' => ['task_uuid', 'table_name']],
                ],
            ],
        ];
    }
}
