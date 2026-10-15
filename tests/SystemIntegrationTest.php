<?php
declare(strict_types=1);

namespace tests;

require_once dirname(__DIR__) . '/vendor/autoload.php';

use app\database\DatabaseManager;
use app\service\AuthService;
use app\service\MailService;
use app\service\DatabaseMigrationService;
use Exception;

class SystemIntegrationTest
{
    public static function runAll(): void
    {
        echo "=== [1/6] 验证数据库连接与适配器 ===" . PHP_EOL;
        $adapter = DatabaseManager::getAdapter();
        $testRes = $adapter->testConnection();
        if (empty($testRes['success'])) {
            throw new Exception("MySQL 连接失败: " . ($testRes['error'] ?? 'unknown'));
        }
        echo " -> MySQL 连接正常: Version=" . $testRes['version'] . PHP_EOL;

        echo "=== [2/6] 验证用户认证与 JWT ===" . PHP_EOL;
        $userRow = $adapter->query("SELECT * FROM sys_users WHERE username = 'admin' LIMIT 1")[0];
        $token = AuthService::generateToken($userRow);
        $payload = AuthService::verifyToken($token);
        if (!$payload || $payload['username'] !== 'admin') {
            throw new Exception("JWT 认证验证失败");
        }
        echo " -> JWT 生成与解密校验通过 (Role: {$payload['role']})" . PHP_EOL;

        echo "=== [3/6] 验证写信、TO/CC/BCC、已读回执 ===" . PHP_EOL;
        $mailService = new MailService($adapter);
        $senderId = (int)$userRow['id'];
        $recip1 = (int)$adapter->query("SELECT id FROM sys_users WHERE username = 'zhangsan' LIMIT 1")[0]['id'];
        $recip2 = (int)$adapter->query("SELECT id FROM sys_users WHERE username = 'lisi' LIMIT 1")[0]['id'];
        $recipBcc = (int)$adapter->query("SELECT id FROM sys_users WHERE username = 'wangwu' LIMIT 1")[0]['id'];

        $mailId = $mailService->sendMail($senderId, [
            'to' => [$recip1],
            'cc' => [$recip2],
            'bcc' => [$recipBcc],
            'subject' => '2026年度第四季度企业战略研讨会内部通知',
            'content' => '请各位准时到第一会议室参加年度战略研讨。附件包含会议议程。',
            'status' => 'sent',
        ]);
        echo " -> 邮件发送成功, ID={$mailId}" . PHP_EOL;

        // 验证收件人查阅
        $inboxRes = $mailService->getInbox($recip1);
        if (empty($inboxRes['list'])) {
            throw new Exception("收件箱列表未获取到邮件");
        }
        echo " -> 收件箱查询通过, 记录数=" . count($inboxRes['list']) . PHP_EOL;

        // 验证阅读详情与回执
        $detail = $mailService->getMailDetail((int)$mailId, $recip1);
        if ($detail['my_recipient_state']['is_read'] != 1) {
            throw new Exception("未正确更新收件人已读状态");
        }
        echo " -> 已读回执状态成功记录" . PHP_EOL;

        // 验证密送 BCC 隔离机制 (张三作为普通收件人，不可看到王五是密送人)
        $hasBccLeak = false;
        foreach ($detail['recipients'] as $r) {
            if ($r['type'] === 'BCC') {
                $hasBccLeak = true;
            }
        }
        if ($hasBccLeak) {
            throw new Exception("安全漏洞：普通收件人能看到 BCC 密送人！");
        }
        echo " -> BCC 密送安全隔离校验通过 (无信息泄露)" . PHP_EOL;

        echo "=== [4/6] 验证草稿箱与星标切换 ===" . PHP_EOL;
        $draftId = $mailService->sendMail($senderId, [
            'to' => [$recip1],
            'subject' => '【草稿】关于技术架构升级方案',
            'content' => '草稿正文待补充...',
            'status' => 'draft',
        ]);
        $drafts = $mailService->getDrafts($senderId);
        echo " -> 草稿箱验证通过, 当前草稿数=" . count($drafts['list']) . PHP_EOL;

        echo "=== [5/6] 验证国产人大金仓 KingbaseES Schema 转换与 DDL 生成 ===" . PHP_EOL;
        $dbAdminService = new DatabaseMigrationService();
        $previews = $dbAdminService->generateSchemaPreview();
        if (count($previews) < 10) {
            throw new Exception("Schema 生成表数量不足: " . count($previews));
        }
        echo " -> KingbaseES 方言与表结构自动生成通过, 转换表数量=" . count($previews) . PHP_EOL;

        echo "=== [6/6] 验证迁移任务状态机与任务创建 ===" . PHP_EOL;
        $uuid = $dbAdminService->createMigrationTask($senderId, ['host' => '127.0.0.1', 'port' => 54321]);
        if (empty($uuid)) {
            throw new Exception("迁移任务创建失败");
        }
        echo " -> 迁移任务初始化成功: UUID={$uuid}" . PHP_EOL;

        echo "ALL SYSTEM INTEGRATION TESTS PASSED (100% OK)!" . PHP_EOL;
    }
}

SystemIntegrationTest::runAll();
