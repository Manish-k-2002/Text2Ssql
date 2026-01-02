<?php
require 'db.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT id, password FROM login WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($id, $stored_password);
        $stmt->fetch();

        // ✅ Since signup hashes password, verify it
        if (password_verify($password, $stored_password)) {
            $_SESSION['user_id'] = $id;
            header("Location: select_table.php");
            exit;
        }
    }

    $error = "Invalid credentials";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            height: 100vh;
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #4b6cb7, #182848);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(12px);
            border-radius: 14px;
            padding: 2rem;
            width: 100%;
            max-width: 400px;
            color: #fff;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.2);
            animation: fadeIn 0.6s ease;
        }
        .card h4 {
            text-align: center;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .form-label {
            color: #fff;
            font-weight: 500;
        }
        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: #fff;
            transition: all 0.3s ease;
        }
        .form-control::placeholder {
            color: #ddd;
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 0 0 2px rgba(255,255,255,0.4);
            color: #fff;
        }
        .btn-gradient {
            background: linear-gradient(135deg, #6a11cb, #2575fc);
            border: none;
            color: #fff;
            width: 100%;
            padding: 0.7rem;
            font-weight: 600;
            border-radius: 8px;
            margin-top: 0.5rem;
            transition: all 0.3s;
        }
        .btn-gradient:hover {
            transform: scale(1.03);
            background: linear-gradient(135deg, #5c0ecb, #1b65e8);
        }
        .text-small {
            color: #ddd;
            text-align: center;
            margin-top: 1rem;
        }
        .text-small a {
            color: #fff;
            text-decoration: underline;
            transition: 0.3s;
        }
        .text-small a:hover {
            color: #a5b4fc;
        }
        .alert {
            border: none;
            border-radius: 6px;
            background: rgba(255, 69, 58, 0.9);
            color: #fff;
            text-align: center;
            padding: 0.7rem;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="card">
        <h4>Login</h4>

        <?php if (!empty($error)) : ?>
            <div class="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" placeholder="Enter your username" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
            </div>
            <button type="submit" class="btn-gradient">Login</button>
        </form>

        <p class="text-small">
            Don’t have an account? <a href="sign.php">Sign up here</a>
        </p>
    </div>
</body>
</html>