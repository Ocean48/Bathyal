<?php
// /register.php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

require_once 'config/database.php';
require_once 'includes/db_query.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $teamName = trim($_POST['team_name'] ?? ''); // Optional: generate new team for them

    if ($name && $email && $password) {
        $db = new DBQueries($pdo);
        
        if ($db->getUserByEmail($email)) {
            $error = 'Email is already registered.';
        } else {
            // Optional: Create a new team if provided
            $teamId = null;
            if ($teamName) {
                $stmt = $pdo->prepare("INSERT INTO teams (name) VALUES (:name)");
                $stmt->execute([':name' => $teamName]);
                $teamId = $pdo->lastInsertId();
            } else {
                // For this demo context, assign to Ocean48 if no team is created so they have data
                $teamId = 1; 
            }

            $userId = $db->createUser([
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'team_id' => $teamId,
                'role' => $teamName ? 'admin' : 'member' // make admin if they created a new team
            ]);

            if ($userId) {
                $_SESSION['user_id'] = $userId;
                $_SESSION['team_id'] = $teamId;
                $_SESSION['role'] = $teamName ? 'admin' : 'member';
                header("Location: index.php");
                exit;
            } else {
                $error = 'Failed to register. Please try again.';
            }
        }
    } else {
        $error = 'Please fill in all required fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Account - Bathyal</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 flex items-center justify-center min-h-screen">
    <div class="w-full max-w-sm bg-white rounded-xl shadow-md border border-slate-200 p-8 my-8">
        <div class="flex items-center justify-center mb-6">
            <svg class="w-8 h-8 text-cyan-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            <span class="font-bold text-2xl text-slate-800 tracking-wide">Bathyal</span>
        </div>
        <h2 class="text-xl font-semibold text-slate-700 text-center mb-6">Create your account</h2>
        
        <?php if ($error): ?>
            <div class="bg-rose-50 text-rose-600 p-3 rounded text-sm mb-4 border border-rose-200 text-center">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="name">Full Name</label>
                <input type="text" id="name" name="name" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:bg-white transition-colors">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="email">Email</label>
                <input type="email" id="email" name="email" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:bg-white transition-colors">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="password">Password</label>
                <input type="password" id="password" name="password" required class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:bg-white transition-colors">
            </div>
            <div class="pt-2 border-t border-slate-100">
                <label class="block text-sm font-medium text-slate-600 mb-1" for="team_name">Team Name <span class="text-slate-400 font-normal">(Optional)</span></label>
                <input type="text" id="team_name" name="team_name" placeholder="Leave blank to join existing instance" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:bg-white transition-colors text-sm">
            </div>
            <button type="submit" class="w-full py-2.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg font-medium shadow-sm transition-colors mt-4">Sign Up</button>
        </form>
        
        <p class="text-center text-sm text-slate-500 mt-6">
            Already have an account? <a href="login.php" class="text-cyan-700 font-medium hover:underline">Sign in</a>
        </p>
    </div>
</body>
</html>