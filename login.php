<?php
// /login.php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: /index.php");
    exit;
}

require_once 'config/database.php';
require_once 'includes/db_query.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $db = new DBQueries($pdo);
        $user = $db->getUserByEmail($email);

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['team_id'] = $user['team_id'];
            $_SESSION['role'] = $user['role'];
            header("Location: /index.php");
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Bathyal</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-sm bg-white rounded-xl shadow-md border border-slate-200 p-8">
        <div class="flex items-center justify-center mb-8">
            <svg class="w-8 h-8 text-teal-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            <span class="font-bold text-2xl text-slate-800 tracking-wide">Bathyal</span>
        </div>
        <h2 class="text-xl font-semibold text-slate-700 text-center mb-6">Welcome back</h2>
        
        <?php if ($error): ?>
            <div class="bg-rose-50 text-rose-600 p-3 rounded text-sm mb-4 border border-rose-200 text-center">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="email">Email</label>
                <input type="email" id="email" name="email" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 focus:bg-white transition-colors">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="password">Password</label>
                <input type="password" id="password" name="password" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal-500 focus:bg-white transition-colors">
            </div>
            <button type="submit" class="w-full py-2.5 bg-teal-500 hover:bg-teal-600 text-white rounded-lg font-medium shadow-sm transition-colors mt-2">Sign In</button>
        </form>
        
        <p class="text-center text-sm text-slate-500 mt-6">
            Don't have an account? <a href="register.php" class="text-teal-600 font-medium hover:underline">Sign up</a>
        </p>
    </div>
</body>
</html>