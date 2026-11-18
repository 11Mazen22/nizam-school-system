<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Nizam -- RFC 6238 time-based one-time passwords (SHA-1, 30 seconds, 6
 * digits), compatible with Google Authenticator, Authy, 1Password and any
 * other standard authenticator app.
 *
 * The implementation is deliberately local: the platform has no dependency on
 * an external identity provider, and the secret never leaves the school's
 * database except in the enrolment QR/secret shown once to its owner.
 */
final class TotpService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const PERIOD = 30;
    private const DIGITS = 6;

    /** 160-bit secret, as recommended by RFC 4226 for HMAC-SHA1. */
    public function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public function code(string $secret, ?int $timestamp = null): string
    {
        $counter = intdiv($timestamp ?? time(), self::PERIOD);
        // 64-bit big-endian counter: high word is zero for any date we will
        // ever see, low word carries the 30-second step.
        $binary = pack('N2', 0, $counter);
        $key = self::base32Decode($secret);
        $hash = hash_hmac('sha1', $binary, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $value = ((ord($hash[$offset]) & 0x7f) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Accept one step either side of now: phone clocks drift, and a code
     * typed a second before the step changes must not be rejected.
     */
    public function verify(string $secret, string $code, ?int $timestamp = null): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $now = $timestamp ?? time();
        foreach ([-1, 0, 1] as $drift) {
            if (hash_equals($this->code($secret, $now + ($drift * self::PERIOD)), $code)) {
                return true;
            }
        }

        return false;
    }

    public function provisioningUri(string $secret, string $account, string $issuer = 'Nizam'): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
            . '?secret=' . rawurlencode($secret)
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;
    }

    public static function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $result = '';
        foreach (str_split($bits, 5) as $chunk) {
            $result .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $result;
    }

    public static function base32Decode(string $encoded): string
    {
        $encoded = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $encoded) ?? '');
        $bits = '';
        foreach (str_split($encoded) as $char) {
            $position = strpos(self::ALPHABET, $char);
            if ($position === false) {
                continue;
            }
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $result = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $result .= chr(bindec($chunk));
            }
        }

        return $result;
    }
}
