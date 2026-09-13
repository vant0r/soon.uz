<?php
/**
 * Security Helper Functions
 * CSRF protection, XSS prevention, input validation
 */

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function escJs($str) {
    return json_encode($str ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
}

function sanitizeInput($str) {
    if ($str === null) {
        return null;
    }
    return trim(strip_tags($str));
}

function isValidPhone($phone) {
    if (empty($phone)) {
        return false;
    }
    $cleaned = preg_replace('/[^\d+]/', '', $phone);
    return (bool) preg_match('/^\+998\d{9}$/', $cleaned);
}

function formatPhone($phone) {
    if (empty($phone)) {
        return '';
    }
    $cleaned = preg_replace('/[^\d]/', '', $phone);
    if (strlen($cleaned) === 12 && substr($cleaned, 0, 3) === '998') {
        return '+998 ' . substr($cleaned, 3, 2) . ' ' . substr($cleaned, 5, 3) . ' ' . substr($cleaned, 8, 2) . ' ' . substr($cleaned, 10, 2);
    }
    return $phone;
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function isValidUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Return a trustworthy client IP.
 * X-Forwarded-For is only honored when the immediate peer is explicitly trusted.
 */
function getClientIp() {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $trustedProxies = defined('TRUSTED_PROXIES') && is_array(TRUSTED_PROXIES) ? TRUSTED_PROXIES : [];

    if (in_array($remote, $trustedProxies, true)) {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($forwarded !== '') {
            $ips = array_map('trim', explode(',', $forwarded));
            foreach ($ips as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        $real = $_SERVER['HTTP_X_REAL_IP'] ?? '';
        if (filter_var($real, FILTER_VALIDATE_IP)) {
            return $real;
        }
    }

    return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
}

/**
 * File-based rate limiting with a lock to avoid concurrent request races.
 */
function checkRateLimit($identifier, $limit, $window = 60) {
    $file = sys_get_temp_dir() . '/soon_ratelimit_' . hash('sha256', (string)$identifier);
    $now = time();
    $data = ['start' => $now, 'count' => 0];

    $handle = @fopen($file, 'c+');
    if (!$handle) {
        return true;
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            return true;
        }

        $contents = stream_get_contents($handle);
        if ($contents !== false && $contents !== '') {
            $decoded = json_decode($contents, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        if (!isset($data['start'], $data['count']) || ($now - (int)$data['start']) >= $window) {
            $data = ['start' => $now, 'count' => 1];
        } elseif ((int)$data['count'] >= $limit) {
            flock($handle, LOCK_UN);
            fclose($handle);
            return false;
        } else {
            $data['count']++;
        }

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($data, JSON_UNESCAPED_SLASHES));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
        return true;
    } catch (Throwable $e) {
        @flock($handle, LOCK_UN);
        @fclose($handle);
        return true;
    }
}

function checkBruteForceLockout($ip) {
    $file = sys_get_temp_dir() . '/soon_bruteforce_' . hash('sha256', (string)$ip);
    $now = time();

    if (file_exists($file)) {
        $data = json_decode((string)@file_get_contents($file), true);
        if ($data && ($now - (int)$data['first_attempt']) < BRUTE_FORCE_LOCKOUT) {
            return (int)$data['attempts'] < BRUTE_FORCE_ATTEMPTS;
        }
        @unlink($file);
    }

    return true;
}

function recordFailedLogin($ip) {
    $file = sys_get_temp_dir() . '/soon_bruteforce_' . hash('sha256', (string)$ip);
    $now = time();
    $data = ['first_attempt' => $now, 'attempts' => 1];

    if (file_exists($file)) {
        $existing = json_decode((string)@file_get_contents($file), true);
        if (is_array($existing) && ($now - (int)$existing['first_attempt']) < BRUTE_FORCE_LOCKOUT) {
            $data = [
                'first_attempt' => (int)$existing['first_attempt'],
                'attempts' => (int)$existing['attempts'] + 1
            ];
        }
    }

    @file_put_contents($file, json_encode($data), LOCK_EX);
}

function clearBruteForceRecord($ip) {
    $file = sys_get_temp_dir() . '/soon_bruteforce_' . hash('sha256', (string)$ip);
    if (file_exists($file)) {
        @unlink($file);
    }
}

function validateMimeType($tmpName, $allowedTypes) {
    if (!file_exists($tmpName)) {
        return false;
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) {
        return false;
    }
    $mimeType = finfo_file($finfo, $tmpName);
    finfo_close($finfo);
    return in_array($mimeType, $allowedTypes, true) ? $mimeType : false;
}

function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_ARGON2ID);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function validatePasswordStrength($password) {
    $errors = [];
    if (strlen($password) < 10) {
        $errors[] = 'Parol kamida 10 belgidan iborat bo\'lishi kerak';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Parolda kamida bitta katta harf bo\'lishi kerak';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Parolda kamida bitta kichik harf bo\'lishi kerak';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Parolda kamida bitta raqam bo\'lishi kerak';
    }
    return ['valid' => empty($errors), 'errors' => $errors];
}
