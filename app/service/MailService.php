<?php
declare(strict_types=1);

namespace app\service;

use app\database\DatabaseManager;
use app\database\DatabaseAdapterInterface;
use Exception;

class MailService
{
    protected DatabaseAdapterInterface $adapter;

    public function __construct(?DatabaseAdapterInterface $adapter = null)
    {
        $this->adapter = $adapter ?? DatabaseManager::getAdapter();
    }

    public function sendMail(int $senderId, array $data): int|string
    {
        $subject = trim($data['subject'] ?? '');
        $content = $data['content'] ?? '';
        $contentType = $data['content_type'] ?? 'html';
        $status = $data['status'] ?? 'sent'; // 'sent' or 'draft'
        $attachmentIds = $data['attachment_ids'] ?? [];

        $toUsers = $data['to'] ?? [];   // array of user ids
        $ccUsers = $data['cc'] ?? [];   // array of user ids
        $bccUsers = $data['bcc'] ?? []; // array of user ids

        if ($status === 'sent' && empty($toUsers)) {
            throw new Exception("请至少选择一位收件人(TO)");
        }
        if (empty($subject)) {
            throw new Exception("邮件主题不能为空");
        }

        $draftId = !empty($data['draft_id']) ? (int)$data['draft_id'] : 0;

        // 检查发信人角色或系统配置是否需要审批
        $userRow = $this->adapter->query("SELECT role FROM sys_users WHERE id = :id LIMIT 1", ['id' => $senderId]);
        $userRole = $userRow[0]['role'] ?? 'user';
        
        // 普通用户发信需要审批，admin / dbadmin 可免审或系统统一配置
        $auditConfig = $this->adapter->query("SELECT key_value FROM sys_config WHERE key_name = 'mail_audit_required' LIMIT 1");
        $needAuditConfig = !empty($auditConfig) ? ($auditConfig[0]['key_value'] === '1') : true;

        $requiresAudit = ($status === 'sent' && $needAuditConfig && $userRole === 'user');
        $auditStatus = $requiresAudit ? 'pending' : 'approved';

        $recipientDataJson = json_encode([
            'to' => $toUsers,
            'cc' => $ccUsers,
            'bcc' => $bccUsers,
        ], JSON_UNESCAPED_UNICODE);

        $this->adapter->beginTransaction();
        try {
            $mailData = [
                'sender_id'      => $senderId,
                'subject'        => $subject,
                'content_type'   => $contentType,
                'content'        => $content,
                'status'         => $status,
                'audit_status'   => $auditStatus,
                'recipient_data' => $recipientDataJson,
                'has_attachment' => !empty($attachmentIds) ? 1 : 0,
                'is_deleted'     => 0,
                'sent_at'        => ($status === 'sent' && !$requiresAudit) ? date('Y-m-d H:i:s') : null,
                'updated_at'     => date('Y-m-d H:i:s'),
            ];

            if ($draftId > 0) {
                // 更新现有草稿
                $mTbl = $this->adapter->quoteIdentifier('mail_message');
                $this->adapter->execute(
                    "UPDATE {$mTbl} SET subject = :subject, content = :content, content_type = :content_type, 
                     status = :status, audit_status = :audit_status, recipient_data = :recipient_data,
                     has_attachment = :has_attachment, sent_at = :sent_at, updated_at = :updated_at 
                     WHERE id = :id AND sender_id = :sender_id",
                    [
                        'subject'        => $subject,
                        'content'        => $content,
                        'content_type'   => $contentType,
                        'status'         => $status,
                        'audit_status'   => $auditStatus,
                        'recipient_data' => $recipientDataJson,
                        'has_attachment' => !empty($attachmentIds) ? 1 : 0,
                        'sent_at'        => ($status === 'sent' && !$requiresAudit) ? date('Y-m-d H:i:s') : null,
                        'updated_at'     => date('Y-m-d H:i:s'),
                        'id'             => $draftId,
                        'sender_id'      => $senderId,
                    ]
                );
                $mailId = $draftId;
                // 清理旧收件人记录（若有）
                $rTbl = $this->adapter->quoteIdentifier('mail_recipient');
                $this->adapter->execute("DELETE FROM {$rTbl} WHERE mail_id = :mid", ['mid' => $mailId]);
            } else {
                $mailData['is_starred'] = 0;
                $mailData['created_at'] = date('Y-m-d H:i:s');
                $mailId = $this->adapter->insert('mail_message', $mailData);
            }

            // 2. 写入收件人记录：只有当免审批或直接审批通过时，才进入收件人收件箱
            if ($status === 'sent' && !$requiresAudit) {
                $this->deliverRecipients((int)$mailId, $toUsers, $ccUsers, $bccUsers);
            }

            // 3. 关联附件
            if (!empty($attachmentIds)) {
                $attTable = $this->adapter->quoteIdentifier('mail_attachment');
                $idCol = $this->adapter->quoteIdentifier('id');
                $mailIdCol = $this->adapter->quoteIdentifier('mail_id');
                $inIds = implode(',', array_map('intval', $attachmentIds));
                $this->adapter->execute("UPDATE {$attTable} SET {$mailIdCol} = :mid WHERE {$idCol} IN ({$inIds})", ['mid' => $mailId]);
            }

            // 4. 记录日志
            $logAction = $requiresAudit ? 'submit_for_audit' : ($status === 'sent' ? 'send_mail' : 'save_draft');
            $this->logOperation($senderId, $logAction, (int)$mailId);

            $this->adapter->commit();
            return [
                'mail_id' => $mailId,
                'requires_audit' => $requiresAudit,
                'audit_status' => $auditStatus,
            ];
        } catch (Exception $e) {
            $this->adapter->rollBack();
            throw $e;
        }
    }

    protected function deliverRecipients(int $mailId, array $toUsers, array $ccUsers, array $bccUsers): void
    {
        $recipientRows = [];
        $added = [];

        foreach ($toUsers as $uid) {
            $uid = (int)$uid;
            if ($uid > 0 && !isset($added[$uid])) {
                $added[$uid] = true;
                $recipientRows[] = [
                    'mail_id'      => $mailId,
                    'recipient_id' => $uid,
                    'type'         => 'TO',
                    'is_read'      => 0,
                    'is_deleted'   => 0,
                    'is_starred'   => 0,
                    'created_at'   => date('Y-m-d H:i:s'),
                ];
            }
        }

        foreach ($ccUsers as $uid) {
            $uid = (int)$uid;
            if ($uid > 0 && !isset($added[$uid])) {
                $added[$uid] = true;
                $recipientRows[] = [
                    'mail_id'      => $mailId,
                    'recipient_id' => $uid,
                    'type'         => 'CC',
                    'is_read'      => 0,
                    'is_deleted'   => 0,
                    'is_starred'   => 0,
                    'created_at'   => date('Y-m-d H:i:s'),
                ];
            }
        }

        foreach ($bccUsers as $uid) {
            $uid = (int)$uid;
            if ($uid > 0 && !isset($added[$uid])) {
                $added[$uid] = true;
                $recipientRows[] = [
                    'mail_id'      => $mailId,
                    'recipient_id' => $uid,
                    'type'         => 'BCC',
                    'is_read'      => 0,
                    'is_deleted'   => 0,
                    'is_starred'   => 0,
                    'created_at'   => date('Y-m-d H:i:s'),
                ];
            }
        }

        if (!empty($recipientRows)) {
            $this->adapter->batchInsert('mail_recipient', $recipientRows);
        }
    }

    public function getManagerDepartmentIds(int $managerId): array
    {
        $dTbl = $this->adapter->quoteIdentifier('sys_departments');
        $rows = $this->adapter->query("SELECT id FROM {$dTbl} WHERE leader_id = :lid", ['lid' => $managerId]);
        return array_map(fn($r) => (int)$r['id'], $rows);
    }

    public function canUserAuditMail(int $userId, int $mailId): bool
    {
        $mTbl = $this->adapter->quoteIdentifier('mail_message');
        $uTbl = $this->adapter->quoteIdentifier('sys_users');
        $dTbl = $this->adapter->quoteIdentifier('sys_departments');

        $rows = $this->adapter->query(
            "SELECT m.id, m.sender_id, u.department_id, d.leader_id 
             FROM {$mTbl} m 
             JOIN {$uTbl} u ON m.sender_id = u.id 
             LEFT JOIN {$dTbl} d ON u.department_id = d.id 
             WHERE m.id = :mid LIMIT 1",
            ['mid' => $mailId]
        );

        if (empty($rows)) {
            return false;
        }

        $row = $rows[0];
        // 只有该员工所在部门的直属主管/经理 (leader_id == $userId) 才有权审批
        // 并且自己发给别人的邮件不能自己审批自己（除非特定免审）
        return ((int)$row['leader_id'] === $userId && (int)$row['sender_id'] !== $userId);
    }

    public function approveMail(int $mailId, int $auditorId, string $remark = ''): void
    {
        if (!$this->canUserAuditMail($auditorId, $mailId)) {
            throw new Exception("权限不足：只有发件人的直属部门经理有权审批该邮件");
        }

        $mTbl = $this->adapter->quoteIdentifier('mail_message');
        $mail = $this->adapter->query("SELECT * FROM {$mTbl} WHERE id = :mid LIMIT 1", ['mid' => $mailId]);
        if (empty($mail)) {
            throw new Exception("邮件不存在");
        }
        $mail = $mail[0];
        if ($mail['audit_status'] !== 'pending') {
            throw new Exception("邮件当前状态不需要审核或已处理");
        }

        $this->adapter->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');
            // 更新邮件为已通过并设定发送时间
            $this->adapter->execute(
                "UPDATE {$mTbl} SET audit_status = 'approved', audit_user_id = :uid, audit_time = :now, 
                 audit_remark = :remark, sent_at = :sent_at, updated_at = :now WHERE id = :mid",
                [
                    'uid' => $auditorId,
                    'now' => $now,
                    'remark' => $remark,
                    'sent_at' => $now,
                    'mid' => $mailId,
                ]
            );

            // 分发收件人
            $recipData = json_decode($mail['recipient_data'] ?? '{}', true) ?: [];
            $toUsers = $recipData['to'] ?? [];
            $ccUsers = $recipData['cc'] ?? [];
            $bccUsers = $recipData['bcc'] ?? [];

            $this->deliverRecipients($mailId, $toUsers, $ccUsers, $bccUsers);
            $this->logOperation($auditorId, 'approve_mail', $mailId);

            $this->adapter->commit();
        } catch (Exception $e) {
            $this->adapter->rollBack();
            throw $e;
        }
    }

    public function rejectMail(int $mailId, int $auditorId, string $remark = ''): void
    {
        if (!$this->canUserAuditMail($auditorId, $mailId)) {
            throw new Exception("权限不足：只有发件人的直属部门经理有权驳回该邮件");
        }

        $mTbl = $this->adapter->quoteIdentifier('mail_message');
        $mail = $this->adapter->query("SELECT * FROM {$mTbl} WHERE id = :mid LIMIT 1", ['mid' => $mailId]);
        if (empty($mail)) {
            throw new Exception("邮件不存在");
        }
        $now = date('Y-m-d H:i:s');
        $this->adapter->execute(
            "UPDATE {$mTbl} SET audit_status = 'rejected', audit_user_id = :uid, audit_time = :now, 
             audit_remark = :remark, updated_at = :now WHERE id = :mid",
            [
                'uid' => $auditorId,
                'now' => $now,
                'remark' => $remark,
                'mid' => $mailId,
            ]
        );
        $this->logOperation($auditorId, 'reject_mail', $mailId);
    }

    public function getPendingAudits(int $managerId, array $params = []): array
    {
        $deptIds = $this->getManagerDepartmentIds($managerId);
        if (empty($deptIds)) {
            return [
                'total' => 0,
                'page' => 1,
                'pageSize' => 15,
                'list' => [],
            ];
        }

        $page = max(1, (int)($params['page'] ?? 1));
        $pageSize = max(1, min(100, (int)($params['pageSize'] ?? 15)));
        $offset = ($page - 1) * $pageSize;

        $mTbl = $this->adapter->quoteIdentifier('mail_message');
        $uTbl = $this->adapter->quoteIdentifier('sys_users');
        $dTbl = $this->adapter->quoteIdentifier('sys_departments');

        $inDepts = implode(',', $deptIds);
        // 只查询该经理管辖部门下属员工提交的待审邮件（排除经理本人）
        $where = "m.status = 'sent' AND m.audit_status = 'pending' AND m.is_deleted = 0 AND u.department_id IN ({$inDepts}) AND u.id != {$managerId}";
        
        $countSql = "SELECT COUNT(*) as total FROM {$mTbl} m JOIN {$uTbl} u ON m.sender_id = u.id WHERE {$where}";
        $total = (int)($this->adapter->query($countSql)[0]['total'] ?? 0);

        $sql = "SELECT m.id as mail_id, m.subject, m.created_at, m.has_attachment, m.content_type, m.audit_status,
                       u.id as sender_id, u.nickname as sender_nickname, u.username as sender_username, u.email as sender_email,
                       d.name as department_name
                FROM {$mTbl} m
                JOIN {$uTbl} u ON m.sender_id = u.id
                LEFT JOIN {$dTbl} d ON u.department_id = d.id
                WHERE {$where}
                ORDER BY m.created_at DESC
                LIMIT {$pageSize} OFFSET {$offset}";

        $list = $this->adapter->query($sql);

        return [
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'list' => $list,
        ];
    }

            // 3. 关联附件
            if (!empty($attachmentIds)) {
                $attTable = $this->adapter->quoteIdentifier('mail_attachment');
                $idCol = $this->adapter->quoteIdentifier('id');
                $mailIdCol = $this->adapter->quoteIdentifier('mail_id');
                $inIds = implode(',', array_map('intval', $attachmentIds));
                $this->adapter->execute("UPDATE {$attTable} SET {$mailIdCol} = :mid WHERE {$idCol} IN ({$inIds})", ['mid' => $mailId]);
            }

            // 4. 记录日志
            $this->logOperation($senderId, $status === 'sent' ? 'send_mail' : 'save_draft', (int)$mailId);

            $this->adapter->commit();
            return $mailId;
        } catch (Exception $e) {
            $this->adapter->rollBack();
            throw $e;
        }
    }

    public function getInbox(int $userId, array $params = []): array
    {
        $page = max(1, (int)($params['page'] ?? 1));
        $pageSize = max(1, min(100, (int)($params['pageSize'] ?? 15)));
        $offset = ($page - 1) * $pageSize;
        $unreadOnly = !empty($params['unread']);
        $keyword = trim($params['keyword'] ?? '');

        $rTbl = $this->adapter->quoteIdentifier('mail_recipient');
        $mTbl = $this->adapter->quoteIdentifier('mail_message');
        $uTbl = $this->adapter->quoteIdentifier('sys_users');

        $where = "r.recipient_id = :uid AND r.is_deleted = 0 AND m.status = 'sent' AND m.is_deleted = 0";
        $binds = ['uid' => $userId];

        if ($unreadOnly) {
            $where .= " AND r.is_read = 0";
        }
        if ($keyword !== '') {
            $where .= " AND (m.subject LIKE :kw OR u.nickname LIKE :kw OR u.username LIKE :kw)";
            $binds['kw'] = "%{$keyword}%";
        }

        $countSql = "SELECT COUNT(*) as total FROM {$rTbl} r 
                     JOIN {$mTbl} m ON r.mail_id = m.id 
                     JOIN {$uTbl} u ON m.sender_id = u.id 
                     WHERE {$where}";
        $totalRes = $this->adapter->query($countSql, $binds);
        $total = (int)($totalRes[0]['total'] ?? 0);

        $sql = "SELECT r.id as recipient_record_id, r.is_read, r.read_at, r.is_starred as recipient_starred, r.type as recipient_type,
                       m.id as mail_id, m.subject, m.sent_at, m.has_attachment, m.content_type,
                       u.id as sender_id, u.nickname as sender_nickname, u.username as sender_username, u.email as sender_email
                FROM {$rTbl} r
                JOIN {$mTbl} m ON r.mail_id = m.id
                JOIN {$uTbl} u ON m.sender_id = u.id
                WHERE {$where}
                ORDER BY m.sent_at DESC
                LIMIT {$pageSize} OFFSET {$offset}";

        $list = $this->adapter->query($sql, $binds);

        return [
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'list' => $list,
        ];
    }

    public function getSentBox(int $userId, array $params = []): array
    {
        $page = max(1, (int)($params['page'] ?? 1));
        $pageSize = max(1, min(100, (int)($params['pageSize'] ?? 15)));
        $offset = ($page - 1) * $pageSize;
        $keyword = trim($params['keyword'] ?? '');

        $mTbl = $this->adapter->quoteIdentifier('mail_message');
        $where = "sender_id = :uid AND status = 'sent' AND is_deleted = 0";
        $binds = ['uid' => $userId];

        if ($keyword !== '') {
            $where .= " AND subject LIKE :kw";
            $binds['kw'] = "%{$keyword}%";
        }

        $countSql = "SELECT COUNT(*) as total FROM {$mTbl} WHERE {$where}";
        $total = (int)($this->adapter->query($countSql, $binds)[0]['total'] ?? 0);

        $sql = "SELECT id as mail_id, subject, sent_at, created_at, has_attachment, is_starred, content_type,
                       audit_status, audit_time, audit_remark
                FROM {$mTbl}
                WHERE {$where}
                ORDER BY created_at DESC
                LIMIT {$pageSize} OFFSET {$offset}";

        $list = $this->adapter->query($sql, $binds);

        return [
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'list' => $list,
        ];
    }

    public function getDrafts(int $userId, array $params = []): array
    {
        $page = max(1, (int)($params['page'] ?? 1));
        $pageSize = max(1, min(100, (int)($params['pageSize'] ?? 15)));
        $offset = ($page - 1) * $pageSize;

        $mTbl = $this->adapter->quoteIdentifier('mail_message');
        $where = "sender_id = :uid AND status = 'draft' AND is_deleted = 0";
        $binds = ['uid' => $userId];

        $countSql = "SELECT COUNT(*) as total FROM {$mTbl} WHERE {$where}";
        $total = (int)($this->adapter->query($countSql, $binds)[0]['total'] ?? 0);

        $sql = "SELECT id as mail_id, subject, created_at, updated_at, has_attachment
                FROM {$mTbl}
                WHERE {$where}
                ORDER BY updated_at DESC
                LIMIT {$pageSize} OFFSET {$offset}";

        $list = $this->adapter->query($sql, $binds);

        return [
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
            'list' => $list,
        ];
    }

    public function getMailDetail(int $mailId, int $userId): array
    {
        $mTbl = $this->adapter->quoteIdentifier('mail_message');
        $uTbl = $this->adapter->quoteIdentifier('sys_users');
        $rTbl = $this->adapter->quoteIdentifier('mail_recipient');
        $aTbl = $this->adapter->quoteIdentifier('mail_attachment');

        // 1. 获取主邮件并确认权限
        $mail = $this->adapter->query(
            "SELECT m.*, u.nickname as sender_nickname, u.username as sender_username, u.email as sender_email
             FROM {$mTbl} m 
             JOIN {$uTbl} u ON m.sender_id = u.id 
             WHERE m.id = :mid LIMIT 1",
            ['mid' => $mailId]
        );

        if (empty($mail)) {
            throw new Exception("邮件不存在或已被删除");
        }
        $mail = $mail[0];

        $isSender = ((int)$mail['sender_id'] === $userId);

        // 检查该用户是否是收件人
        $recipRecord = $this->adapter->query(
            "SELECT * FROM {$rTbl} WHERE mail_id = :mid AND recipient_id = :uid LIMIT 1",
            ['mid' => $mailId, 'uid' => $userId]
        );
        $isRecipient = !empty($recipRecord);

        if (!$isSender && !$isRecipient) {
            throw new Exception("无权查看该邮件");
        }

        // 如果是收件人且未读，自动标记为已读
        if ($isRecipient && empty($recipRecord[0]['is_read'])) {
            $this->markAsRead($mailId, $userId);
            $recipRecord[0]['is_read'] = 1;
            $recipRecord[0]['read_at'] = date('Y-m-d H:i:s');
        }

        // 2. 获取收件人列表（密送 BCC 保护）
        $recipients = $this->adapter->query(
            "SELECT r.id, r.recipient_id, r.type, r.is_read, r.read_at, u.nickname, u.username, u.email
             FROM {$rTbl} r
             JOIN {$uTbl} u ON r.recipient_id = u.id
             WHERE r.mail_id = :mid",
            ['mid' => $mailId]
        );

        $filteredRecipients = [];
        foreach ($recipients as $r) {
            // BCC 密送收件人只有发件人和该 BCC 收件人本人可见
            if ($r['type'] === 'BCC' && !$isSender && (int)$r['recipient_id'] !== $userId) {
                continue;
            }
            $filteredRecipients[] = $r;
        }

        // 3. 获取附件
        $attachments = $this->adapter->query(
            "SELECT id, original_name, mime_type, file_size, created_at FROM {$aTbl} WHERE mail_id = :mid",
            ['mid' => $mailId]
        );

        return [
            'mail' => $mail,
            'is_sender' => $isSender,
            'recipients' => $filteredRecipients,
            'attachments' => $attachments,
            'my_recipient_state' => $recipRecord[0] ?? null,
        ];
    }

    public function markAsRead(int $mailId, int $userId): void
    {
        $rTbl = $this->adapter->quoteIdentifier('mail_recipient');
        $now = date('Y-m-d H:i:s');
        $this->adapter->execute(
            "UPDATE {$rTbl} SET is_read = 1, read_at = :now WHERE mail_id = :mid AND recipient_id = :uid",
            ['now' => $now, 'mid' => $mailId, 'uid' => $userId]
        );
        $this->logOperation($userId, 'read_mail', $mailId);
    }

    public function markAsUnread(int $mailId, int $userId): void
    {
        $rTbl = $this->adapter->quoteIdentifier('mail_recipient');
        $this->adapter->execute(
            "UPDATE {$rTbl} SET is_read = 0, read_at = NULL WHERE mail_id = :mid AND recipient_id = :uid",
            ['mid' => $mailId, 'uid' => $userId]
        );
    }

    public function toggleStar(int $mailId, int $userId, bool $isSender): bool
    {
        if ($isSender) {
            $mTbl = $this->adapter->quoteIdentifier('mail_message');
            $row = $this->adapter->query("SELECT is_starred FROM {$mTbl} WHERE id = :mid AND sender_id = :uid", ['mid' => $mailId, 'uid' => $userId]);
            if (empty($row)) return false;
            $newVal = $row[0]['is_starred'] ? 0 : 1;
            $this->adapter->execute("UPDATE {$mTbl} SET is_starred = :s WHERE id = :mid", ['s' => $newVal, 'mid' => $mailId]);
            return (bool)$newVal;
        } else {
            $rTbl = $this->adapter->quoteIdentifier('mail_recipient');
            $row = $this->adapter->query("SELECT is_starred FROM {$rTbl} WHERE mail_id = :mid AND recipient_id = :uid", ['mid' => $mailId, 'uid' => $userId]);
            if (empty($row)) return false;
            $newVal = $row[0]['is_starred'] ? 0 : 1;
            $this->adapter->execute("UPDATE {$rTbl} SET is_starred = :s WHERE mail_id = :mid AND recipient_id = :uid", ['s' => $newVal, 'mid' => $mailId, 'uid' => $userId]);
            return (bool)$newVal;
        }
    }

    public function deleteMail(int $mailId, int $userId): void
    {
        $mTbl = $this->adapter->quoteIdentifier('mail_message');
        $rTbl = $this->adapter->quoteIdentifier('mail_recipient');

        // 发件箱侧删除
        $this->adapter->execute("UPDATE {$mTbl} SET is_deleted = 1 WHERE id = :mid AND sender_id = :uid", ['mid' => $mailId, 'uid' => $userId]);

        // 收件箱侧删除
        $this->adapter->execute("UPDATE {$rTbl} SET is_deleted = 1 WHERE mail_id = :mid AND recipient_id = :uid", ['mid' => $mailId, 'uid' => $userId]);

        $this->logOperation($userId, 'delete_mail', $mailId);
    }

    public function logOperation(int $userId, string $action, ?int $mailId = null): void
    {
        $this->adapter->insert('mail_operation_log', [
            'user_id' => $userId,
            'action' => $action,
            'mail_id' => $mailId,
            'target_type' => 'mail',
            'target_id' => (string)$mailId,
            'ip' => '127.0.0.1',
            'user_agent' => 'Internal-Client',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
