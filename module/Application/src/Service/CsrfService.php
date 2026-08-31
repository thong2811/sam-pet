<?php

declare(strict_types=1);

namespace Application\Service;

/**
 * CSRF token service — generate và validate token lưu trong PHP session.
 *
 * Sử dụng:
 *   // Tạo token (trong Controller hoặc View):
 *   $token = CsrfService::getToken();
 *
 *   // Validate (trong action POST):
 *   CsrfService::validateOrFail($request->getPost('_csrf') ?? $request->getHeader('X-CSRF-Token'));
 */
class CsrfService
{
    private const SESSION_KEY = '_csrf_token';
    private const HEADER_NAME = 'X-CSRF-Token';
    private const POST_FIELD  = '_csrf';

    /**
     * Lấy token hiện tại, tạo mới nếu chưa có.
     */
    public static function getToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Kiểm tra token từ POST field hoặc HTTP header.
     * Throw RuntimeException nếu không hợp lệ.
     *
     * @throws \RuntimeException
     */
    public static function validateOrFail(string $token): void
    {
        $expected = self::getToken();

        if (!hash_equals($expected, $token)) {
            throw new \RuntimeException('Yêu cầu không hợp lệ (CSRF token không khớp). Vui lòng tải lại trang và thử lại.');
        }
    }

    /**
     * Tiện ích: lấy token từ Laminas request (POST field hoặc Header).
     *
     * @param \Laminas\Http\PhpEnvironment\Request $request
     * @return string
     */
    public static function getTokenFromRequest($request): string
    {
        // Ưu tiên lấy từ POST field
        $token = (string) ($request->getPost(self::POST_FIELD) ?? '');

        // Fallback: lấy từ HTTP header (AJAX calls)
        if ($token === '') {
            $header = $request->getHeader(self::HEADER_NAME);
            $token  = $header ? (string) $header->getFieldValue() : '';
        }

        return $token;
    }
}
