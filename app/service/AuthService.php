<?php
declare(strict_types=1);

namespace app\service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class AuthService
{
    protected static string $secretKey = 'email_system_secure_jwt_secret_key_2026';

    public static function generateToken(array $user): string
    {
        $payload = [
            'iss' => 'email_system',
            'sub' => $user['id'],
            'username' => $user['username'],
            'nickname' => $user['nickname'],
            'email' => $user['email'],
            'role' => $user['role'],
            'department_id' => $user['department_id'],
            'iat' => time(),
            'exp' => time() + (86400 * 7), // 7 天有效
        ];

        return JWT::encode($payload, self::$secretKey, 'HS256');
    }

    public static function verifyToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key(self::$secretKey, 'HS256'));
            return (array)$decoded;
        } catch (Exception $e) {
            return null;
        }
    }
}
