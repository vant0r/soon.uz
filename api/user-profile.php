<?php
/**
 * GET /api/user/profile - Get current user's profile
 * PUT /api/user/profile - Update profile
 * Rate limit: 60 requests/minute per token
 */

require_once __DIR__ . '/config.php';

// Rate limiting
if (!checkRateLimit(60, 60)) {
    apiError('Juda ko\'p so\'rovlar. Iltimos biroz kuting.', 429);
}

$userId = requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get user profile
    $user = dbFetchOne("SELECT id, google_id, name, email, phone, avatar, status, created_at 
                        FROM users WHERE id = ?", [$userId]);
    
    if (!$user) {
        apiError('Foydalanuvchi topilmadi.', 404);
    }
    
    $baseUrl = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    
    apiResponse([
        'success' => true,
        'data' => [
            'id' => (int)$user['id'],
            'google_id' => $user['google_id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'avatar' => $user['avatar'],
            'status' => $user['status'],
            'created_at' => $user['created_at']
        ]
    ]);
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Update profile
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        apiError('Noto\'g\'ri so\'rov formati.', 400);
    }
    
    $errors = [];
    $updateData = [];
    
    // Validate and update name
    if (isset($data['name'])) {
        $name = trim($data['name']);
        if (strlen($name) < 2) {
            $errors['name'] = 'Ism juda qisqa (min 2 belgi).';
        } else {
            $updateData['name'] = $name;
        }
    }
    
    // Validate and update phone
    if (isset($data['phone'])) {
        $phone = trim($data['phone'] ?? '');
        if ($phone !== '' && !isValidPhone($phone)) {
            $errors['phone'] = 'Telefon raqam +998XXXXXXXXX formatida bo\'lishi kerak.';
        } else {
            $updateData['phone'] = $phone === '' ? null : $phone;
        }
    }
    
    if (!empty($errors)) {
        apiError('Validatsiya xatosi.', 422, $errors);
    }
    
    if (empty($updateData)) {
        apiError('Yangilanishi kerak bo\'lgan ma\'lumot yo\'q.', 400);
    }
    
    dbUpdate('users', $updateData, 'id = ?', ['id' => $userId]);
    
    // Get updated user data
    $user = dbFetchOne("SELECT id, google_id, name, email, phone, avatar, status, created_at 
                        FROM users WHERE id = ?", [$userId]);
    
    apiResponse([
        'success' => true,
        'data' => [
            'id' => (int)$user['id'],
            'google_id' => $user['google_id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'],
            'avatar' => $user['avatar'],
            'status' => $user['status'],
            'created_at' => $user['created_at']
        ],
        'message' => 'Profil muvaffaqiyatli yangilandi'
    ]);
    
} else {
    apiError('Faqat GET va PUT so\'rovlari qabul qilinadi.', 405);
}
