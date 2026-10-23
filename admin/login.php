<?php
// Include Main Configuration
require_once __DIR__ . '/../common/config.php';

// Check if already logged in
if (is_admin_logged_in()) {
    redirect('index.php');
}

$error = '';

// Brute-force protection: initialize session counters
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = time();
}

// Handle Login Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. CSRF Verification
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = "Invalid security token. Please refresh and try again.";
    } else {
        // 2. Brute-force check (5 attempts = 5 mins lockout)
        $time_since_last = time() - $_SESSION['last_attempt_time'];
        if ($_SESSION['login_attempts'] >= 5 && $time_since_last < 300) {
            $error = "Too many failed attempts. Please try again after 5 minutes.";
        } else {
            $username = sanitize($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($password)) {
                $error = "Please enter both username and password.";
            } else {
                $stmt = $conn->prepare("SELECT id, password FROM admin WHERE username = ? LIMIT 1");
                if ($stmt) {
                    $stmt->bind_param("s", $username);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows === 1) {
                        $admin = $result->fetch_assoc();
                        
                        // Verify Password
                        if (password_verify($password, $admin['password'])) {
                            $_SESSION['login_attempts'] = 0; // Reset counter on success
                            session_regenerate_id(true);
                            $_SESSION['admin_logged_in'] = true;
                            $_SESSION['admin_id'] = $admin['id'];
                            
                            redirect('index.php');
                        } else {
                            $_SESSION['login_attempts']++;
                            $_SESSION['last_attempt_time'] = time();
                            $error = "Invalid username or password.";
                        }
                    } else {
                        $_SESSION['login_attempts']++;
                        $_SESSION['last_attempt_time'] = time();
                        $error = "Invalid username or password.";
                    }
                    $stmt->close();
                } else {
                    $error = "System error occurred. Please try again later.";
                }
            }
        }
    }
}

$csrf_token = generate_csrf_token();

// Include Admin Header
require_once __DIR__ . '/common/header.php';
?>

<div class="flex-grow flex items-center justify-center -mt-10">
    <div class="bg-card w-full max-w-md p-8 rounded-2xl shadow-2xl border border-gray-800">
        
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary/20 text-primary mb-4">
                <i class="fa-solid fa-lock text-3xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-white">Admin Login</h1>
            <p class="text-gray-400 mt-2">Secure Dashboard Access</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="bg-red-500/20 text-red-400 border border-red-500/30 p-3 rounded-lg mb-6 text-sm flex items-center gap-2">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Form Enctype is default, avoiding InfinityFree multipart POST block -->
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            
            <div class="mb-5">
                <label for="username" class="block text-sm font-medium text-gray-400 mb-2">Username</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fa-solid fa-user text-gray-500"></i>
                    </div>
                    <input type="text" name="username" id="username" required autocomplete="off"
                           class="w-full pl-10 pr-4 py-3 bg-dark border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                           placeholder="Enter admin username">
                </div>
            </div>

            <div class="mb-6">
                <label for="password" class="block text-sm font-medium text-gray-400 mb-2">Password</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fa-solid fa-key text-gray-500"></i>
                    </div>
                    <input type="password" name="password" id="password" required
                           class="w-full pl-10 pr-4 py-3 bg-dark border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                           placeholder="Enter password">
                </div>
            </div>

            <button type="submit" class="w-full bg-primary hover:bg-blue-600 text-white font-semibold py-3 px-4 rounded-lg transition duration-200 shadow-lg shadow-blue-500/30 flex justify-center items-center gap-2">
                Sign In <i class="fa-solid fa-arrow-right"></i>
            </button>
        </form>
        
        <div class="mt-6 text-center">
            <a href="../index.php" class="text-sm text-gray-500 hover:text-white transition-colors flex items-center justify-center gap-1">
                <i class="fa-solid fa-arrow-left"></i> Back to Homepage
            </a>
        </div>
    </div>
</div>

<?php 
// Include Admin Bottom
require_once __DIR__ . '/common/bottom.php'; 
?>
