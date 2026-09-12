<?php
/**
 * Security Helper Functions
 * CSRF protection, XSS prevention, input validation
 */

/**
 * Generate CSRF token for forms
 * @return string CSRF token
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token Token to verify
 * @return bool True if valid
 */
function verifyCsrfToken($token) {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Escape output for HTML context
 * @param string $str String to escape
 * @return string Escaped string
 */
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Escape for JavaScript context
 * @param string $str String to escape
 * @return string Escaped string
 */
function escJs($str) {
    return json_encode($str ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
}

/**
 * Sanitize input string
 * @param string $str Input string
 * @return string Sanitized string
 */
function sanitizeInput($str) {
    if ($str === null) {
        return null;
    }
    return trim(strip_tags($str));
}

/**
 * Validate phone number (Uzbek format: +998XXXXXXXXX)
 * @param string $phone Phone number to validate
 * @return bool True if valid
 */
function isValidPhone($phone) {
    if (empty($phone)) {
        return false;
    }
    // Remove any non-digit characters except +
    $cleaned = preg_replace('/[^\d+]/', '', $phone);
    // Check for Uzbek format: +998 followed by 9 digits
    return (bool) preg_match('/^\+998\d{9}$/', $cleaned);
}

/**
 * Format phone number for display
 * @param string $phone Raw phone number
 * @return string Formatted phone number
 */
function formatPhone($phone) {
    if (empty($phone)) {
        return '';
    }
    $cleaned = preg_replace('/[^\d]/', '', $phone);
    if (strlen($cleaned) === 12 && substr($cleaned, 0, 3) === '998') {
        // Format: +998 XX XXX XX XX
        return '+998 ' . substr($cleaned, 3, 2) . ' ' . substr($cleaned, 5, 3) . ' ' . 
               substr($cleaned, 8, 2) . ' ' . substr($cleaned, 10, 2);
    }
    return $phone;
}

/**
 * Validate email address
 * @param string $email Email to validate
 * @return bool True if valid
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate URL
 * @param string $url URL to validate
 * @return bool True if valid
 */
function isValidUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Get client IP address
 * @return string IP address
 */
function getClientIp() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Rate limiting check
 * @param string $identifier Unique identifier (IP or user ID)
 * @param int $limit Max requests allowed
 * @param int $window Time window in seconds
 * @return bool True if within limit, false if rate limited
 */
function checkRateLimit($identifier, $limit, $window = 60) {
    $file = sys_get_temp_dir() . '/webhub_ratelimit_' . md5($identifier);
    $now = time();
    
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if ($data && ($now - $data['start']) < $window) {
            if ($data['count'] >= $limit) {
                return false;
            }
            $data['count']++;
            file_put_contents($file, json_encode($data));
            return true;
        }
    }
    
    file_put_contents($file, json_encode(['start' => $now, 'count' => 1]));
    return true;
}

/**
 * Brute force protection for admin login
 * @param string $ip IP address
 * @return bool True if allowed, false if locked out
 */
function checkBruteForceLockout($ip) {
    $file = sys_get_temp_dir() . '/webhub_bruteforce_' . md5($ip);
    $now = time();
    
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if ($data && ($now - $data['first_attempt']) < BRUTE_FORCE_LOCKOUT) {
            if ($data['attempts'] >= BRUTE_FORCE_ATTEMPTS) {
                return false;
            }
            $data['attempts']++;
            file_put_contents($file, json_encode($data));
            return true;
        } else {
            // Reset after lockout period
            unlink($file);
            return true;
        }
    }
    
    file_put_contents($file, json_encode(['first_attempt' => $now, 'attempts' => 1]));
    return true;
}

/**
 * Record failed login attempt
 * @param string $ip IP address
 */
function recordFailedLogin($ip) {
    $file = sys_get_temp_dir() . '/webhub_bruteforce_' . md5($ip);
    $now = time();
    
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if ($data) {
            $data['attempts']++;
            file_put_contents($file, json_encode($data));
        }
    } else {
        file_put_contents($file, json_encode(['first_attempt' => $now, 'attempts' => 1]));
    }
}

/**
 * Clear brute force record on successful login
 * @param string $ip IP address
 */
function clearBruteForceRecord($ip) {
    $file = sys_get_temp_dir() . '/webhub_bruteforce_' . md5($ip);
    if (file_exists($file)) {
        unlink($file);
    }
}

/**
 * Validate MIME type for file uploads
 * @param string $tmpName Temporary file path
 * @param array $allowedTypes Allowed MIME types
 * @return string|false Valid MIME type or false
 */
function validateMimeType($tmpName, $allowedTypes) {
    if (!file_exists($tmpName)) {
        return false;
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $tmpName);
    finfo_close($finfo);
    
    if (in_array($mimeType, $allowedTypes, true)) {
        return $mimeType;
    }
    
    return false;
}

/**
 * Generate secure random token
 * @param int $length Token length in bytes
 * @return string Hex-encoded token
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Hash password securely
 * @param string $password Plain text password
 * @return string Hashed password
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_ARGON2ID);
}

/**
 * Verify password against hash
 * @param string $password Plain text password
 * @param string $hash Password hash
 * @return bool True if password matches
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Validate password strength (min 10 chars, uppercase, lowercase, digit)
 * @param string $password Password to validate
 * @return array ['valid' => bool, 'errors' => array]
 */
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
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}
