<?php
require_once __DIR__ . '/admin-functions.php';

$errors = [];
$email = '';

// Check if already logged in
if (isAdminLoggedIn()) {
    header('Location: admin-dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '') {
        $errors[] = 'Username is required.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    }

    if (empty($errors)) {
        startAdminSession();
        // Check if currently locked out before even trying (DB-backed, per IP/username).
        if (adminLoginLockedOut($username)) {
            $errors[] = 'Too many failed attempts. Please wait 15 minutes before trying again.';
        } elseif (adminLogin($username, $password)) {
            header('Location: admin-dashboard.php');
            exit;
        } else {
            $errors[] = 'Invalid username or password.';
        }
    }

    $email = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Bella CRM</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Montserrat', sans-serif;
        }
        
        .login-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            padding: 3rem 2rem;
            max-width: 400px;
            width: 100%;
            margin: 0 1rem;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h1 {
            color: #1a1a1a;
            font-size: 1.8rem;
            margin: 0 0 0.5rem 0;
            font-weight: 600;
        }
        
        .login-header p {
            color: #888;
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.95rem;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #c9a961;
            box-shadow: 0 0 0 3px rgba(201, 169, 97, 0.1);
        }
        
        .errors {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        
        .errors ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .errors li {
            margin-bottom: 0.5rem;
        }
        
        .errors li:last-child {
            margin-bottom: 0;
        }
        
        .btn-login {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(135deg, #c9a961 0%, #a88a4d 100%);
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(201, 169, 97, 0.3);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
            font-size: 0.85rem;
            color: #888;
        }
        
        .login-footer a {
            color: #c9a961;
            text-decoration: none;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Bella CRM</h1>
            <p>Admin Portal</p>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="errors">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="admin-login.php">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?php echo $email; ?>" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn-login">Sign In</button>
        </form>
        
        <div class="login-footer">
            <!--<p>Demo: Username: <strong>admin</strong> | Password: <strong>admin123</strong></p>-->
            <p><a href="index.php">← Back to Website</a></p>
        </div>
    </div>
</body>
</html>
