<?php
declare(strict_types=1);

namespace app\controller\api;

use support\Request;
use support\Response;
use app\service\MailService;
use Exception;

class MailController
{
    public function inbox(Request $request): Response
    {
        $userId = (int)$request->user['sub'];
        $service = new MailService();
        $data = $service->getInbox($userId, $request->get());
        return json(['code' => 200, 'msg' => 'ok', 'data' => $data]);
    }

    public function sent(Request $request): Response
    {
        $userId = (int)$request->user['sub'];
        $service = new MailService();
        $data = $service->getSentBox($userId, $request->get());
        return json(['code' => 200, 'msg' => 'ok', 'data' => $data]);
    }

    public function drafts(Request $request): Response
    {
        $userId = (int)$request->user['sub'];
        $service = new MailService();
        $data = $service->getDrafts($userId, $request->get());
        return json(['code' => 200, 'msg' => 'ok', 'data' => $data]);
    }

    public function detail(Request $request, string $id): Response
    {
        $userId = (int)$request->user['sub'];
        $service = new MailService();
        try {
            $data = $service->getMailDetail((int)$id, $userId);
            return json(['code' => 200, 'msg' => 'ok', 'data' => $data]);
        } catch (Exception $e) {
            return json(['code' => 403, 'msg' => $e->getMessage()]);
        }
    }

    public function send(Request $request): Response
    {
        $userId = (int)$request->user['sub'];
        $service = new MailService();
        try {
            $result = $service->sendMail($userId, $request->post());
            $isPending = !empty($result['requires_audit']);
            $msg = $isPending ? '邮件已提交审核，待管理员审批通过后将自动外发' : '邮件发送成功';
            return json(['code' => 200, 'msg' => $msg, 'data' => $result]);
        } catch (Exception $e) {
            return json(['code' => 400, 'msg' => $e->getMessage()]);
        }
    }

    public function pendingAudits(Request $request): Response
    {
        $userId = (int)$request->user['sub'];
        $service = new MailService();
        $deptIds = $service->getManagerDepartmentIds($userId);
        if (empty($deptIds)) {
            return json(['code' => 403, 'msg' => '只有部门经理有权访问下属邮件审批中心', 'data' => ['total' => 0, 'list' => []]]);
        }
        $data = $service->getPendingAudits($userId, $request->get());
        return json(['code' => 200, 'msg' => 'ok', 'data' => $data]);
    }

    public function approve(Request $request, string $id): Response
    {
        $userId = (int)$request->user['sub'];
        $remark = trim($request->post('remark', '部门经理审批通过，准予外发'));
        $service = new MailService();
        try {
            $service->approveMail((int)$id, $userId, $remark);
            return json(['code' => 200, 'msg' => '邮件已审批通过并已投递至对方收件箱']);
        } catch (Exception $e) {
            return json(['code' => 400, 'msg' => $e->getMessage()]);
        }
    }

    public function reject(Request $request, string $id): Response
    {
        $userId = (int)$request->user['sub'];
        $remark = trim($request->post('remark', '部门经理审批驳回'));
        $service = new MailService();
        try {
            $service->rejectMail((int)$id, $userId, $remark);
            return json(['code' => 200, 'msg' => '邮件已驳回']);
        } catch (Exception $e) {
            return json(['code' => 400, 'msg' => $e->getMessage()]);
        }
    }

    public function read(Request $request, string $id): Response
    {
        $userId = (int)$request->user['sub'];
        $service = new MailService();
        $service->markAsRead((int)$id, $userId);
        return json(['code' => 200, 'msg' => '已标记为已读']);
    }

    public function unread(Request $request, string $id): Response
    {
        $userId = (int)$request->user['sub'];
        $service = new MailService();
        $service->markAsUnread((int)$id, $userId);
        return json(['code' => 200, 'msg' => '已标记为未读']);
    }

    public function star(Request $request, string $id): Response
    {
        $userId = (int)$request->user['sub'];
        $isSender = (bool)$request->post('is_sender', false);
        $service = new MailService();
        $status = $service->toggleStar((int)$id, $userId, $isSender);
        return json(['code' => 200, 'msg' => '操作成功', 'data' => ['is_starred' => $status]]);
    }

    public function delete(Request $request, string $id): Response
    {
        $userId = (int)$request->user['sub'];
        $service = new MailService();
        $service->deleteMail((int)$id, $userId);
        return json(['code' => 200, 'msg' => '邮件已删除']);
    }
}
