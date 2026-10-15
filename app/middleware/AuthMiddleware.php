<?php
declare(strict_types=1);

namespace app\middleware;

use Webman\MiddlewareInterface;
use Webman\Http\Response;
use Webman\Http\Request;
use app\service\AuthService;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $path = $request->path();
        
        // 白名单接口放行
        if (in_array($path, [
            '/api/auth/login',
            '/api/auth/ping',
            '/api/system/info'
        ]) || str_starts_with($path, '/public/') || $path === '/') {
            return $handler($request);
        }

        $header = $request->header('authorization', '');
        $token = '';
        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            $token = $matches[1];
        }

        if (empty($token)) {
            $token = $request->get('token', '');
        }

        if (empty($token)) {
            return json(['code' => 401, 'msg' => '请先登录系统', 'data' => null]);
        }

        $user = AuthService::verifyToken($token);
        if (!$user) {
            return json(['code' => 401, 'msg' => '登录凭证无效或已过期', 'data' => null]);
        }

        // 挂载到请求属性中
        $request->user = $user;

        return $handler($request);
    }
}
