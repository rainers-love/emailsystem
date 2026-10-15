<?php
declare(strict_types=1);

namespace app\controller\api;

use support\Request;
use support\Response;
use app\service\AttachmentService;
use Exception;

class AttachmentController
{
    public function upload(Request $request): Response
    {
        $userId = (int)$request->user['sub'];
        $file = $request->file('file');
        if (!$file) {
            return json(['code' => 400, 'msg' => '未找到上传文件']);
        }

        $service = new AttachmentService();
        try {
            $info = $service->uploadFile($file, $userId);
            return json(['code' => 200, 'msg' => '上传成功', 'data' => $info]);
        } catch (Exception $e) {
            return json(['code' => 400, 'msg' => $e->getMessage()]);
        }
    }

    public function download(Request $request, string $id): Response
    {
        $userId = (int)$request->user['sub'];
        $service = new AttachmentService();
        try {
            $data = $service->getDownloadFile((int)$id, $userId);
            return response()->download($data['path'], $data['name']);
        } catch (Exception $e) {
            return json(['code' => 403, 'msg' => $e->getMessage()]);
        }
    }
}
