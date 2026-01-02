<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include 'db.php';

// STEP 1: Fetch last 10 generated queries
$history_query = "SELECT id, prompt, response AS generated_sql, model_used
                  FROM prompt_history
                  ORDER BY id DESC
                  LIMIT 10";
$history_result = mysqli_query($conn, $history_query);

// STEP 2: Check if a query was manually executed
$execution_result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['query_id'])) {
    $query_id = intval($_POST['query_id']);
    $fetch_sql = "SELECT response AS generated_sql, model_used FROM prompt_history WHERE id = $query_id";
    $query_data = mysqli_fetch_assoc(mysqli_query($conn, $fetch_sql));

    if ($query_data) {
        $sql = trim($query_data['generated_sql'] ?? "");
        $model_used = $query_data['model_used'] ?? "";

        // Detect query type
        $is_select = preg_match('/^\s*SELECT\s/i', $sql);
        $is_dml = preg_match('/^\s*(INSERT|UPDATE|DELETE)\s/i', $sql);
        $is_ddl = preg_match('/^\s*(CREATE|DROP|ALTER|TRUNCATE)\s/i', $sql);

        $success = false;
        $message = "";
        $table_html = "";

        mysqli_report(MYSQLI_REPORT_OFF);

        if ($is_select) {
            $result = @mysqli_query($conn, $sql);
            if ($result && mysqli_num_rows($result) > 0) {
                $success = true;
                $table_html .= "<div style='overflow-x:auto;'><table class='table table-bordered table-sm'><thead><tr>";
                while ($field = mysqli_fetch_field($result)) {
                    $table_html .= "<th>" . htmlspecialchars($field->name ?? "") . "</th>";
                }
                $table_html .= "</tr></thead><tbody>";
                while ($r = mysqli_fetch_assoc($result)) {
                    $table_html .= "<tr>";
                    foreach ($r as $val) {
                        $table_html .= "<td>" . htmlspecialchars($val ?? "") . "</td>";
                    }
                    $table_html .= "</tr>";
                }
                $table_html .= "</tbody></table></div>";
            } else {
                $message = mysqli_error($conn) ?: "Query returned no rows.";
            }

        } elseif ($is_dml || $is_ddl) {
            $run = @mysqli_query($conn, $sql);
            if ($run) {
                $success = true;
                $message = ($is_dml ? "Affected Rows: " . mysqli_affected_rows($conn) : "Query executed successfully.");
            } else {
                $message = mysqli_error($conn);
            }

        } else {
            $message = "Unsupported or invalid query type.";
        }

        $execution_result = [
            'success' => $success,
            'sql' => $sql,
            'model' => $model_used,
            'message' => $message,
            'table' => $table_html
        ];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>SQL Verification</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body { 
            margin:0; 
            display:flex; 
            font-size:14px; 
            background:#f7f9fc; 
            overflow-x: hidden;
        }

        #sidebar { 
            position:fixed; 
            top:0; 
            left:0; 
            width:200px; 
            height:100vh; 
            background:#183047; 
            padding-top:20px; 
            overflow:auto; 
        }

        #sidebar a { 
            color:white; 
            display:block; 
            padding:10px; 
            text-decoration:none; 
            font-weight:bold; 
        }

        #sidebar a:hover { background:#495057; }

        .main-content { 
            margin-left:200px;
            padding:20px;
            width: calc(100% - 200px); 
            overflow-x:auto;
        }

        .card { 
            background:#fff; 
            border-radius:10px; 
            padding:20px; 
            margin-bottom:20px; 
            box-shadow:0 4px 10px rgba(0,0,0,0.1); 
        }

        pre { 
            background:#f7f7f7; 
            padding:10px; 
            border-radius:6px; 
            font-size:13px; 
            white-space: pre-wrap;
        }

        table { 
            font-size:13px; 
            white-space: nowrap;
        }

        h3 { 
            color:#183047; 
            margin-bottom:20px; 
        }
    </style>
</head>
<body>

<div id="sidebar">
    <a href="select_table.php">DB_CREATOR</a>
    <a href="prompt.php">PROMPT</a>
    <a href="verify.php" style="background:#495057;">VERIFY</a>
    <a href="logout.php">LOGOUT</a>
</div>

<div class="main-content">
    <h3>🧠 Generated SQL Queries (Last 10)</h3>

    <?php if (!$history_result || mysqli_num_rows($history_result) == 0): ?>
        <div class="alert alert-danger">No queries found in history.</div>
    <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="table table-striped table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Prompt</th>
                    <th>Generated SQL</th>
                    <th>Model Used</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($row = mysqli_fetch_assoc($history_result)): ?>
                <tr>
                    <td><?= htmlspecialchars($row['id'] ?? "") ?></td>
                    <td><?= htmlspecialchars($row['prompt'] ?? "") ?></td>
                    <td><pre><?= htmlspecialchars($row['generated_sql'] ?? "") ?></pre></td>
                    <td><?= htmlspecialchars($row['model_used'] ?? "") ?></td>
                    <td>
                        <form method="POST">
                            <input type="hidden" name="query_id" value="<?= htmlspecialchars($row['id'] ?? "") ?>">
                            <button type="submit" class="btn btn-sm btn-primary">Run</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>

    <?php if ($execution_result): ?>
        <div class="card border-<?= $execution_result['success'] ? 'success' : 'danger' ?>">
            <h5><?= $execution_result['success'] ? "✅ Query Executed Successfully" : "❌ SQL Execution Failed" ?></h5>
            <p><strong>Model:</strong> <?= htmlspecialchars($execution_result['model'] ?? "") ?></p>
            <p><strong>SQL:</strong></p>
            <pre><?= htmlspecialchars($execution_result['sql'] ?? "") ?></pre>

            <?php if ($execution_result['success']): ?>
                <?= $execution_result['table'] ?: "<div class='alert alert-success'>" . htmlspecialchars($execution_result['message'] ?? "") . "</div>" ?>
            <?php else: ?>
                <div class="alert alert-danger"><?= htmlspecialchars($execution_result['message'] ?? "") ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
