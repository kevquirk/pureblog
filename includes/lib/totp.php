<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Pure Blog TOTP (RFC 6238 / RFC 4226) & Multi-Factor Authentication Helpers
// ---------------------------------------------------------------------------

const TOTP_BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

/**
 * Generate a cryptographically secure random Base32 secret key.
 */
function totp_generate_secret(int $length = 16): string
{
    $secret = '';
    $alphabet = TOTP_BASE32_ALPHABET;
    $alphabetLength = strlen($alphabet);

    for ($i = 0; $i < $length; $i++) {
        $secret .= $alphabet[random_int(0, $alphabetLength - 1)];
    }

    return $secret;
}

/**
 * Decode a Base32 string to binary data.
 */
function totp_base32_decode(string $b32): string
{
    $b32 = preg_replace('/[^A-Z2-7]/', '', strtoupper($b32)) ?? '';
    if ($b32 === '') {
        return '';
    }

    $buffer = 0;
    $bitsLeft = 0;
    $binary = '';
    $alphabet = TOTP_BASE32_ALPHABET;

    for ($i = 0, $len = strlen($b32); $i < $len; $i++) {
        $val = strpos($alphabet, $b32[$i]);
        if ($val === false) {
            continue;
        }

        $buffer = ($buffer << 5) | $val;
        $bitsLeft += 5;

        if ($bitsLeft >= 8) {
            $bitsLeft -= 8;
            $binary .= chr(($buffer >> $bitsLeft) & 0xFF);
        }
    }

    return $binary;
}

/**
 * Encode binary data to a Base32 string.
 */
function totp_base32_encode(string $data): string
{
    if ($data === '') {
        return '';
    }

    $alphabet = TOTP_BASE32_ALPHABET;
    $buffer = 0;
    $bitsLeft = 0;
    $output = '';

    for ($i = 0, $len = strlen($data); $i < $len; $i++) {
        $buffer = ($buffer << 8) | ord($data[$i]);
        $bitsLeft += 8;

        while ($bitsLeft >= 5) {
            $bitsLeft -= 5;
            $output .= $alphabet[($buffer >> $bitsLeft) & 0x1F];
        }
    }

    if ($bitsLeft > 0) {
        $output .= $alphabet[($buffer << (5 - $bitsLeft)) & 0x1F];
    }

    return $output;
}

/**
 * Calculate the 6-digit TOTP code for a given timestamp and secret.
 */
function totp_calculate_code(string $secret, ?int $timestamp = null, int $timeStep = 30, int $digits = 6): string
{
    $binarySecret = totp_base32_decode($secret);
    if ($binarySecret === '') {
        return '';
    }

    $timestamp = $timestamp ?? time();
    $counter = (int) floor($timestamp / $timeStep);

    // Pack 64-bit integer into big-endian binary (counter)
    $packedCounter = pack('N*', 0) . pack('N*', $counter);

    $hash = hash_hmac('sha1', $packedCounter, $binarySecret, true);
    $offset = ord(substr($hash, -1)) & 0x0F;

    $unpacked = unpack('N', substr($hash, $offset, 4));
    if (!$unpacked || !isset($unpacked[1])) {
        return '';
    }

    $truncated = $unpacked[1] & 0x7FFFFFFF;
    $code = (string) ($truncated % (10 ** $digits));

    return str_pad($code, $digits, '0', STR_PAD_LEFT);
}

/**
 * Verify a TOTP code within a window of discrepancy (±1 step by default).
 */
function totp_verify_code(string $secret, string $code, int $discrepancy = 1, ?int $timestamp = null, int $timeStep = 30, int $digits = 6): bool
{
    $code = trim($code);
    if (strlen($code) !== $digits || !ctype_digit($code)) {
        return false;
    }

    $timestamp = $timestamp ?? time();

    for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
        $stepTime = $timestamp + ($i * $timeStep);
        $expected = totp_calculate_code($secret, $stepTime, $timeStep, $digits);
        if ($expected !== '' && hash_equals($expected, $code)) {
            return true;
        }
    }

    return false;
}

/**
 * Generate human-readable single-use backup recovery codes.
 * Returns an array of formatted codes like ['a1b2-c3d4', ...].
 */
function totp_generate_backup_codes(int $count = 8): array
{
    $codes = [];
    $chars = '0123456789abcdefghijklmnopqrstuvwxyz';
    $charsLen = strlen($chars);

    for ($i = 0; $i < $count; $i++) {
        $part1 = '';
        $part2 = '';
        for ($j = 0; $j < 4; $j++) {
            $part1 .= $chars[random_int(0, $charsLen - 1)];
            $part2 .= $chars[random_int(0, $charsLen - 1)];
        }
        $codes[] = $part1 . '-' . $part2;
    }

    return $codes;
}

/**
 * Normalise a backup code for comparison (strip whitespace, hyphens, and lowercase).
 */
function totp_normalise_backup_code(string $code): string
{
    return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $code) ?? '');
}

/**
 * Verify a backup code against an array of hashed backup codes.
 * Returns the index of the matching code if found, or null if invalid.
 */
function totp_verify_backup_code(string $code, array $hashedCodes): ?int
{
    $clean = totp_normalise_backup_code($code);
    if ($clean === '' || strlen($clean) !== 8) {
        return null;
    }

    foreach ($hashedCodes as $index => $hash) {
        if (is_string($hash) && password_verify($clean, $hash)) {
            return (int) $index;
        }
    }

    return null;
}

/**
 * Hash an array of plain backup codes for storage.
 */
function totp_hash_backup_codes(array $plainCodes): array
{
    $hashed = [];
    foreach ($plainCodes as $code) {
        $clean = totp_normalise_backup_code((string) $code);
        if ($clean !== '') {
            $hashed[] = password_hash($clean, PASSWORD_DEFAULT);
        }
    }
    return $hashed;
}

/**
 * Generate an otpauth:// URI for authenticator applications.
 */
function totp_get_provisioning_uri(string $secret, string $accountName, string $issuer): string
{
    $label = $issuer !== '' ? rawurlencode($issuer) . ':' . rawurlencode($accountName) : rawurlencode($accountName);
    $params = [
        'secret'    => $secret,
        'issuer'    => $issuer,
        'algorithm' => 'SHA1',
        'digits'    => '6',
        'period'    => '30',
    ];

    return 'otpauth://totp/' . $label . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}
