<?php
require 'db.php'; // Ensure $conn = new mysqli(...);

$message = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $email === '' || $password === '') {
        $message = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email format.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        // ✅ Use INSERT IGNORE to skip duplicate key errors safely
        $stmt = $conn->prepare("INSERT IGNORE INTO login (username, password, email) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sss", $username, $hashed, $email);

            try {
                if ($stmt->execute()) {
                    // If insert succeeded
                    if ($stmt->affected_rows > 0) {
                        $stmt->close();
                        header("Location: index.php");
                        exit;
                    } else {
                        $message = "User already exists, but duplicates allowed.";
                    }
                } else {
                    $message = "Signup failed. Try again.";
                }
            } catch (mysqli_sql_exception $e) {
                $message = "Database error: " . htmlspecialchars($e->getMessage());
            }
        } else {
            $message = "Database error: " . htmlspecialchars($conn->error);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign Up</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
    height: 100vh;
    margin: 0;
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, #667eea, #764ba2);
    display: flex;
    align-items: center;
    justify-content: center;
}
.card {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(12px);
    border-radius: 12px;
    padding: 2rem;
    width: 100%;
    max-width: 400px;
    color: #fff;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}
.card h4 {
    font-weight: 600;
    text-align: center;
    margin-bottom: 1rem;
}
.form-label { color: #fff; font-weight: 500; }
.form-control {
    background: rgba(255,255,255,0.1);
    border: none;
    color: #fff;
}
.form-control::placeholder { color: #ddd; }
.form-control:focus {
    background: rgba(255,255,255,0.2);
    box-shadow: none;
    color: #fff;
}
.btn-gradient {
    background: linear-gradient(135deg, #6a11cb, #2575fc);
    border: none;
    color: #fff;
    width: 100%;
    padding: 0.7rem;
    font-weight: 600;
    border-radius: 6px;
    margin-top: 0.5rem;
    transition: 0.3s;
}
.btn-gradient:hover {
    background: linear-gradient(135deg, #5c0ecb, #1b65e8);
}
.text-small { color: #e5e5e5; }
.text-small a { color: #fff; text-decoration: underline; }
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
    from { opacity: 0; transform: translateY(-5px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
</head>
<body>
<div class="card">
    <h4>Create Account</h4>

    <?php if ($message): ?>
        <div class="alert"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST" autocomplete="off">
        <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" placeholder="Enter your username" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" placeholder="Enter password" required>
        </div>
        <button type="submit" class="btn-gradient">Sign Up</button>
        <p class="text-center text-small mt-3">
            Already have an account? <a href="index.php">Login here</a>
        </p>
    </form>
</div>
</body>
</html>