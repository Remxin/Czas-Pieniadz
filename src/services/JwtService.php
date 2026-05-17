<?php

require_once __DIR__ . '/../../config.php';

class JwtService
{
    private static function secret(): string
    {
        if (!defined('JWT_SECRET') || JWT_SECRET === '') {
            throw new RuntimeException('JWT_SECRET must be set in config.php');
        }
        return JWT_SECRET;
    }

    private static function ttlSeconds(): int
    {
        return defined('JWT_TTL') ? (int) JWT_TTL : 604800;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $padding = strlen($data) % 4;
        if ($padding > 0) {
            $data .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        return $decoded === false ? '' : $decoded;
    }

    /**
     * @param array<string, mixed> $payload Must include claims you need, e.g. sub, email
     */
    public static function encode(array $payload): string
    {
        $now = time();
        $payload['iat'] = $now;
        $payload['exp'] = $now + self::ttlSeconds();

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $headerEncoded = self::base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $signingInput = $headerEncoded . '.' . $payloadEncoded;
        $signature = hash_hmac('sha256', $signingInput, self::secret(), true);
        $signatureEncoded = self::base64UrlEncode($signature);

        return $signingInput . '.' . $signatureEncoded;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function decode(string $jwt): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;
        $signingInput = $headerEncoded . '.' . $payloadEncoded;
        $expectedSig = self::base64UrlEncode(
            hash_hmac('sha256', $signingInput, self::secret(), true)
        );

        if (!hash_equals($expectedSig, $signatureEncoded)) {
            return null;
        }

        $payloadJson = self::base64UrlDecode($payloadEncoded);
        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return null;
        }

        if (isset($payload['exp']) && time() >= (int) $payload['exp']) {
            return null;
        }

        return $payload;
    }
}
