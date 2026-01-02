<?php
require 'db.php';
session_start();

$user_id = $_SESSION['login_id'] ?? 1;

// Debug logging
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    error_log("📥 POST RECEIVED: " . print_r($_POST, true));
}

// Delete request
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['created_at'])) {
    $created_at = $_POST['created_at'];
    $stmt = $conn->prepare("DELETE FROM prompt_history WHERE user_id = ? AND created_at = ?");
    $stmt->bind_param("is", $user_id, $created_at);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    exit();
}

// Save prompt/response
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['prompt'], $_POST['response'])) {
    $prompt = trim($_POST['prompt']);
    $response = trim($_POST['response']);
    $model_used = $_POST['model'] ?? 'v2';

    $stmt = $conn->prepare("INSERT INTO prompt_history (user_id, prompt, response, model_used) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $prompt, $response, $model_used);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'prompt' => $prompt, 'response' => $response, 'model' => $model_used]);
    } else {
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }
    $stmt->close();
    $conn->close();
    exit();
}

// Fetch full history
$stmt = $conn->prepare("SELECT prompt, response, model_used, created_at FROM prompt_history WHERE user_id = ? ORDER BY created_at ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$history = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Prompt Page</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
<style>
/* Full viewport & gradient background */
body {
    margin: 0;
    font-family: Arial, sans-serif;
    height: 100vh;
    background: linear-gradient(135deg, #ffecd2, #fcb69f, #6a11cb, #2575fc);
    background-size: 400% 400%;
    animation: gradientBG 15s ease infinite;
    display: flex;
    flex-direction: column;
}

@keyframes gradientBG {
    0% {background-position: 0% 50%;}
    50% {background-position: 100% 50%;}
    100% {background-position: 0% 50%;}
}

/* Top Navigation Bar */
#topnav {
    width: 100%;
    display: flex;
    padding: 10px 20px;
    box-sizing: border-box;
    align-items: center;
    flex-shrink: 0;
}

#topnav .nav-tab {
    color: white;
    text-decoration: none;
    padding: 10px 15px;
    font-weight: bold;
    margin-right: 10px;
    border-radius: 5px;
    transition: 0.3s;
}

#topnav .db-creator { background-color: #1976d2; }  
#topnav .prompt { background-color: #9c27b0; }      
#topnav .verify { background-color: #388e3c; }      
#topnav .logout { background-color: #c62828; margin-left: auto; }  

#topnav .nav-tab:hover {
    opacity: 0.8;
}

        #topnav a:hover {
            background-color: #495057;
        }

        /* Main content below navbar */
       body {
    margin: 0;
    font-family: Arial, sans-serif;
    /* Artistic gradient background for entire page */
    background: linear-gradient(135deg, #ffecd2, #fcb69f, #6a11cb, #2575fc);
    background-size: 400% 400%;
    animation: gradientBG 15s ease infinite;
}

/* Chat wrapper filling remaining viewport */
.chat-wrapper {
    display: flex;
    flex-direction: column;
    flex-grow: 1;
    margin: 20px;
    max-height: calc(100vh - 80px);
}

/* Chat header */
.chat-header {
    text-align: center;
    padding: 15px;
    display: flex;
    justify-content: center;
    align-items: center;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    font-weight: bold;
    border-radius: 20px 20px 0 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
}

/* Scrollable chat body only */
.chat-body {
    flex-grow: 1;
    overflow-y: auto;
    padding: 20px;
    background: rgba(255,255,255,0.85);
    border-radius: 0 0 20px 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

/* Chat messages */
.chat-msg { display: flex; max-width: 75%; }
.chat-msg.user { justify-content: flex-end; margin-left: auto; }
.chat-msg.system { justify-content: flex-start; margin-right: auto; }

.chat-msg .bubble {
    padding: 12px 25px;
    border-radius: 50px; /* oval shape */
    word-wrap: break-word;
    box-shadow: 1px 1px 5px rgba(0,0,0,0.1);
}

.chat-msg.user .bubble { background: #1976d2; color: white; }
.chat-msg.system .bubble { background: #f1f1f1; color: #000; }

/* Chat footer fixed at bottom */
.chat-footer {
    padding: 10px;
    display: flex;
    background: linear-gradient(90deg, #43cea2, #185a9d);
    border-radius: 20px 20px 20px 20px;
    flex-shrink: 0;
}

.chat-footer form { display: flex; width: 100%; gap: 10px; }

.chat-footer input {
    flex: 1;
    padding: 10px 20px;
    border-radius: 50px; /* oval shape */
    border: none;
    outline: none;
}

.chat-footer select {
    padding: 8px 15px;
    border-radius: 50px; /* oval shape */
    border: none;
}

.chat-footer button {
    padding: 10px 20px;
    border: none;
    border-radius: 50px; /* oval shape */
    background: #1976d2;
    color: #fff;
    font-weight: bold;
    cursor: pointer;
    transition: 0.3s;
}

.chat-footer button:hover { background: #0d47a1; }

</style>
</head>
<body>

<!-- Top Navigation Bar -->
<div id="topnav">
    <a href="select_table.php" class="nav-tab db-creator">DB_CREATOR</a>
    <a href="prompt.php" class="nav-tab prompt">PROMPT</a>
    <a href="verify.php" class="nav-tab verify">VERIFY</a>
    <a href="logout.php" class="nav-tab logout">LOGOUT</a>
</div>

<!-- Chat -->
<div class="chat-wrapper">
    <div class="chat-header"><h5 class="m-0">Natural Language → SQL</h5></div>
    <div class="chat-body" id="chatBody">
        <?php foreach ($history as $row): ?>
            <div class="chat-msg user"><div class="bubble"><?= htmlspecialchars($row['prompt']) ?></div></div>
            <div class="chat-msg system"><div class="bubble"><?= htmlspecialchars($row['response']) ?></div></div>
        <?php endforeach; ?>
    </div>

    <div class="chat-footer">
        <form id="promptForm">
            <input type="text" id="promptInput" placeholder="Type your question..." required autocomplete="off" />
            <select id="modelSelect">
                <option value="v2" selected>v2</option>
                <option value="v1">v1</option>
            </select>
            <button type="submit">Send</button>
        </form>
    </div>
</div>

<script>
const chatBody = document.getElementById("chatBody");
window.addEventListener("load", () => { chatBody.scrollTop = chatBody.scrollHeight; });

document.getElementById("promptForm").addEventListener("submit", function(e) {
    e.preventDefault();
    const inputEl = document.getElementById("promptInput");
    const modelEl = document.getElementById("modelSelect");
    const prompt = inputEl.value.trim();
    const model = modelEl.value;
    if (!prompt) return;
    inputEl.value = "";

    const userDiv = document.createElement("div");
    userDiv.classList.add("chat-msg", "user");
    userDiv.innerHTML = `<div class="bubble">${prompt}</div>`;
    chatBody.appendChild(userDiv);
    chatBody.scrollTop = chatBody.scrollHeight;

    fetch("http://127.0.0.1:5000/nl2sql", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ query: prompt, model: model })
    })
    .then(res => res.json())
    .then(data => {
        const response = data.sql || "⚠️ No SQL generated";
        fetch("./prompt.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: new URLSearchParams({ prompt: prompt, response: response, model: model })
        });

        const sysDiv = document.createElement("div");
        sysDiv.classList.add("chat-msg", "system");
        sysDiv.innerHTML = `<div class="bubble">${response}</div>`;
        chatBody.appendChild(sysDiv);
        chatBody.scrollTop = chatBody.scrollHeight;
    })
    .catch(err => { alert("❌ Flask API error: " + err.message); });
});
</script>

</body>
</html>
