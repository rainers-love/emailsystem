<?php
declare(strict_types=1);

namespace app\service;

use app\database\DatabaseManager;
use app\database\DatabaseAdapterInterface;
use Webman\Http\UploadFile;
use Exception;

class AttachmentService
{
    protected DatabaseAdapterInterface $adapter;
    protected string $storageBase;

    public function __construct(?DatabaseAdapterInterface $adapter = null)
    {
        $this->adapter = $adapter ?? DatabaseManager::getAdapter();
        $this->storageBase = BASE_PATH . '/storage/attachments';
        if (!is_dir($this->storageBase)) {
            mkdir($this->storageBase, 0755, true);
        }
    }

    public function uploadFile(UploadFile $file, int $uploaderId): array
    {
        if (!$file->isValid()) {
            throw new Exception("文件上传失败: " . $file->getUploadErrorMessage());
        }

        $maxSizeMb = 50; // 从 sys_config 或默认 50MB
        if ($file->getSize() > $maxSizeMb * 1024 * 1024) {
            throw new Exception("上传文件大小不能超过 {$maxSizeMb}MB");
        }

        $origName = $file->getUploadName();
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        // 禁止危险脚本文件类型
        $forbiddenExts = ['php', 'phtml', 'sh', 'bat', 'exe', 'com', 'cmd', 'vbs', 'jsp', 'asp', 'aspx'];
        if (in_array($ext, $forbiddenExts)) {
            throw new Exception("禁止上传可执行脚本文件");
        }

        $hash = hash_file('sha256', $file->getPathname());
        $storedName = date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . ($ext ? ".{$ext}" : '');
        $subDir = date('Ym');
        $targetDir = $this->storageBase . '/' . $subDir;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $relPath = $subDir . '/' . $storedName;
        $absPath = $this->storageBase . '/' . $relPath;

        $file->move($absPath);

        $attId = $this->adapter->insert('mail_attachment', [
            'mail_id' => null,
            'uploader_id' => $uploaderId,
            'original_name' => $origName,
            'stored_name' => $storedName,
            'storage_path' => $relPath,
            'mime_type' => $file->getUploadMineType() ?: 'application/octet-stream',
            'file_size' => $file->getSize(),
            'file_hash' => $hash,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'id' => (int)$attId,
            'original_name' => $origName,
            'file_size' => $file->getSize(),
            'file_hash' => $hash,
            'mime_type' => $file->getUploadMineType(),
        ];
    }

    public function getDownloadFile(int $attachmentId, int $userId): array
    {
        $aTbl = $this->adapter->quoteIdentifier('mail_attachment');
        $mTbl = $this->adapter->quoteIdentifier('mail_message');
        $rTbl = $this->adapter->quoteIdentifier('mail_recipient');

        $rows = $this->adapter->query("SELECT * FROM {$aTbl} WHERE id = :id LIMIT 1", ['id' => $attachmentId]);
        if (empty($rows)) {
            throw new Exception("附件不存在");
        }
        $att = $rows[0];

        // 权限校验：如果已关联邮件，用户必须是发件人或收件人；若未关联邮件，必须是上传者
        $mailId = $att['mail_id'];
        if ($mailId) {
            $isSender = !empty($this->adapter->query("SELECT id FROM {$mTbl} WHERE id = :mid AND sender_id = :uid", ['mid' => $mailId, 'uid' => $userId]));
            $isRecip = !empty($this->adapter->query("SELECT id FROM {$rTbl} WHERE mail_id = :mid AND recipient_id = :uid", ['mid' => $mailId, 'uid' => $userId]));
            if (!$isSender && !$isRecip) {
                throw new Exception("无权下载此附件");
            }
        } else {
            if ((int)$att['uploader_id'] !== $userId) {
                throw new Exception("无权下载未关联的附件");
            }
        }

        // 防路径穿越验证
        $filePath = realpath($this->storageBase . '/' . $att['storage_path']);
        if (!$filePath || !str_starts_with($filePath, realpath($this->storageBase))) {
            throw new Exception("附件物理存储路径非法或文件丢失");
        }

        return [
            'path' => $filePath,
            'name' => $att['original_name'],
            'mime' => $att['mime_type'],
        ];
    }
}
