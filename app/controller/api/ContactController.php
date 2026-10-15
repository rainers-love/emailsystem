<?php
declare(strict_types=1);

namespace app\controller\api;

use support\Request;
use support\Response;
use app\database\DatabaseManager;

class ContactController
{
    public function index(Request $request): Response
    {
        $adapter = DatabaseManager::getAdapter();
        $uTbl = $adapter->quoteIdentifier('sys_users');
        $dTbl = $adapter->quoteIdentifier('sys_departments');

        $keyword = trim($request->get('keyword', ''));
        $deptId = (int)$request->get('department_id', 0);

        $where = "u.status = 1";
        $binds = [];

        if ($keyword !== '') {
            $where .= " AND (u.nickname LIKE :kw OR u.username LIKE :kw OR u.email LIKE :kw OR u.position LIKE :kw OR u.phone LIKE :kw)";
            $binds['kw'] = "%{$keyword}%";
        }

        if ($deptId > 0) {
            $where .= " AND u.department_id = :dept_id";
            $binds['dept_id'] = $deptId;
        }

        $users = $adapter->query(
            "SELECT u.id, u.username, u.nickname, u.email, u.phone, u.position, u.role, u.department_id, COALESCE(d.name, '未分配') as department_name 
             FROM {$uTbl} u
             LEFT JOIN {$dTbl} d ON u.department_id = d.id
             WHERE {$where}
             ORDER BY u.department_id ASC, u.id ASC",
            $binds
        );

        return json(['code' => 200, 'msg' => 'ok', 'data' => $users]);
    }

    public function departments(Request $request): Response
    {
        $adapter = DatabaseManager::getAdapter();
        $dTbl = $adapter->quoteIdentifier('sys_departments');
        $depts = $adapter->query("SELECT * FROM {$dTbl} ORDER BY order_num ASC, id ASC");
        return json(['code' => 200, 'msg' => 'ok', 'data' => $depts]);
    }
}
