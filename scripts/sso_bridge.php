<?php
declare(strict_types=1);

/**
 * Shared SSO cookie helpers for consumer apps.
 * Keep decode/sign/mfa helpers aligned with elevateher-auth/includes/sso_bridge.php.
 *
 * Env: EH_SSO_SECRET, EH_SSO_DOMAIN, EH_SSO_TTL,
 *      EH_SSO_REQUIRE_MFA, EH_SSO_FORCE_PORTAL, EH_AUTH_PUBLIC_BASE
 */

function eh_sso_config(string $key, string $default): string
{
    $envKey = 'EH_' . strtoupper($key);
    $val = getenv($envKey);
    if ($val !== false && $val !== '') {
        return $val;
    }
    return $default;
}

function eh_sso_cookie_name(): string
{
    return 'eh360_sso';
}

function eh_sso_secret(): string
{
    return eh_sso_config('SSO_SECRET', 'local-dev-change-me-use-openssl-rand-hex-32');
}

function eh_sso_domain(): string
{
    return eh_sso_config('SSO_DOMAIN', '.elevateher360.local');
}

function eh_sso_ttl(): int
{
    $ttl = eh_sso_config('SSO_TTL', '86400');
    return max(300, (int)$ttl);
}

function eh_sso_secure_flag(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    $forwarded = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($forwarded === 'https') {
        return true;
    }
    return (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function eh_sso_sign(string $payload): string
{
    return hash_hmac('sha256', $payload, eh_sso_secret());
}

function eh_sso_base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function eh_sso_base64url_decode(string $data): string|false
{
    $padded = strtr($data, '-_', '+/');
    $padLen = (4 - strlen($padded) % 4) % 4;
    if ($padLen > 0) {
        $padded .= str_repeat('=', $padLen);
    }
    return base64_decode($padded, true);
}

function eh_sso_env_flag(string $key, string $default = '0'): bool
{
    $raw = strtolower(trim(eh_sso_config($key, $default)));
    return in_array($raw, ['1', 'true', 'yes', 'on'], true);
}

function eh_sso_require_mfa_enabled(): bool
{
    return eh_sso_env_flag('SSO_REQUIRE_MFA', '0');
}

function eh_sso_force_portal_enabled(): bool
{
    return eh_sso_env_flag('SSO_FORCE_PORTAL', '0');
}

function eh_sso_has_mfa(?array $sso): bool
{
    if ($sso === null) {
        return false;
    }
    return !empty($sso['mfa']) && (int)$sso['mfa'] === 1;
}

function eh_auth_public_base(): string
{
    $base = eh_sso_config('AUTH_PUBLIC_BASE', '');
    if ($base !== '') {
        return rtrim($base, '/');
    }
    // Mentorship historically used EH_AUTH_PORTAL_BASE including /auth.
    $portal = eh_sso_config('AUTH_PORTAL_BASE', '');
    if ($portal !== '') {
        return rtrim(preg_replace('#/auth/?$#', '', $portal) ?? $portal, '/');
    }
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    if (str_contains($host, 'elevateher360.local')) {
        return 'http://www.elevateher360.local:8090';
    }
    return 'https://www.elevateher360.org';
}

if (!function_exists('eh_auth_portal_login_url')) {
    function eh_auth_portal_login_url(string $returnTo, bool $stepUp = false): string
    {
        $url = eh_auth_public_base() . '/auth/login.php?return_to=' . rawurlencode($returnTo);
        if ($stepUp) {
            $url .= '&step_up=1';
        }
        return $url;
    }
}

/**
 * Origin of the current request, or '' outside a web request.
 * Safe as a return_to base: the portal re-validates hosts against EH_AUTH_RETURN_HOSTS.
 */
function eh_sso_request_origin(): string
{
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return '';
    }
    return (eh_sso_secure_flag() ? 'https' : 'http') . '://' . $host;
}

function eh_sso_current_url(): string
{
    $origin = eh_sso_request_origin();
    if ($origin === '') {
        $origin = 'http://localhost';
    }
    return $origin . (string)($_SERVER['REQUEST_URI'] ?? '/');
}

function eh_sso_redirect_step_up(): never
{
    header('Location: ' . eh_auth_portal_login_url(eh_sso_current_url(), true));
    exit;
}

function eh_sso_redirect_force_portal(): never
{
    header('Location: ' . eh_auth_portal_login_url(eh_sso_current_url(), false));
    exit;
}

function eh_sso_user(): ?array
{
    $token = $_COOKIE[eh_sso_cookie_name()] ?? '';
    if ($token === '' || !str_contains($token, '.')) {
        return null;
    }

    [$payload, $sig] = explode('.', $token, 2);
    if (!hash_equals(eh_sso_sign($payload), $sig)) {
        return null;
    }

    $raw = eh_sso_base64url_decode($payload);
    if ($raw === false) {
        // Compatibility with older standard-base64 cookies during rollout.
        $raw = base64_decode($payload, true);
    }
    if ($raw === false) {
        return null;
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['email']) || empty($data['exp'])) {
        return null;
    }
    if ((int)$data['exp'] < time()) {
        return null;
    }

    return $data;
}

function eh_sso_clear(): void
{
    setcookie(eh_sso_cookie_name(), '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'domain'   => eh_sso_domain(),
        'secure'   => eh_sso_secure_flag(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}