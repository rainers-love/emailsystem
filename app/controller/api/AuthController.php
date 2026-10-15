<?php
declare(strict_types=1);

namespace app\controller\api;

use support\Request;
use support\Response;
use app\database\DatabaseManager;
use app\service\AuthService;

class AuthController
{
    public function login(Request $request): Response
    {
        $username = trim($request->post('username', ''));
        $password = trim($request->post('password', ''));

        if (empty($username) || empty($password)) {
            return json(['code' => 400, 'msg' => '用户名和密码不能为空']);
        }

        $adapter = DatabaseManager::getAdapter();
        $uTbl = $adapter->quoteIdentifier('sys_users');
        $users = $adapter->query("SELECT * FROM {$uTbl} WHERE username = :u AND status = 1 LIMIT 1", ['u' => $username]);

        if (empty($users) || !password_verify($password, $users[0]['password'])) {
            return json(['code' => 400, 'msg' => '用户名或密码错误']);
        }

        $user = $users[0];
        unset($user['password']);

        // 检查该用户是否是任何部门的主管/经理
        $dTbl = $adapter->quoteIdentifier('sys_departments');
        $ledDepts = $adapter->query("SELECT id FROM {$dTbl} WHERE leader_id = :uid", ['uid' => $user['id']]);
        $user['is_manager'] = !empty($ledDepts);
        $user['managed_dept_ids'] = array_map(fn($r) => (int)$r['id'], $ledDepts);

        $token = AuthService::generateToken($user);

        return json([
            'code' => 200,
            'msg' => '登录成功',
            'data' => [
                'token' => $token,
                'user'  => $user,
            ]
        ]);
    }

    public function userinfo(Request $request): Response
    {
        return json([
            'code' => 200,
            'msg' => 'ok',
            'data' => $request->user ?? null,
        ]);
    }
}
