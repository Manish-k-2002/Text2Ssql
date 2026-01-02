<?php
require 'db.php';
session_start();

$usedTable = '';
$msg = '';

// DEFAULT DATABASE
$defaultDB = "chatbox_db";
$_POST['Database'] = $_POST['Database'] ?? $defaultDB;

// ========================== DROP TABLE PROCESS ==========================
if (isset($_POST['drop_table']) && !empty($_POST['drop_table_name']) && !empty($_POST['Database'])) {
    $selectedDB = $_POST['Database'];
    $dropTable = $_POST['drop_table_name'];

    mysqli_select_db($conn, $selectedDB);
    if ($conn->query("DROP TABLE `$dropTable`")) {
        $msg = "🗑️ Table '$dropTable' dropped successfully.";
    } else {
        $msg = "❌ Failed to drop table: " . $conn->error;
    }
}

// ========================== CREATE TABLE PROCESS ==========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['Table'], $_POST['Database']) && !isset($_POST['drop_table'])) {
    $selectedTable = trim($_POST['Table']);
    $selectedDB = trim($_POST['Database']);

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $selectedTable)) {
        $msg = "❌ Invalid table name.";
    } else {
        mysqli_select_db($conn, $selectedDB);

        $exists = $conn->query("SHOW TABLES LIKE '$selectedTable'");
        if ($exists->num_rows === 0) {
            $columnsSQL = [];
            if (!empty($_POST['column_name'])) {
                $colNames = $_POST['column_name'];
                $colTypes = $_POST['column_type'];
                $colLengths = $_POST['column_length'];
                $primaryKeyIndex = isset($_POST['primary_key']) ? (int) $_POST['primary_key'] : -1;
                $notNulls = $_POST['not_null'] ?? [];
                $uniques = $_POST['unique'] ?? [];
                $autoIncrements = $_POST['auto_increment'] ?? [];

                for ($i = 0; $i < count($colNames); $i++) {
                    $colName = trim($colNames[$i]);
                    $colType = strtoupper(trim($colTypes[$i]));
                    $length = trim($colLengths[$i]);
                    $isPrimary = ($primaryKeyIndex === $i);
                    $isNotNull = in_array($i, $notNulls);
                    $isUnique = in_array($i, $uniques);
                    $isAI = in_array($i, $autoIncrements);

                    if ($colName !== '') {
                        $sqlType = $colType;
                        if (($colType === 'VARCHAR' || $colType === 'INT') && $length !== '') {
                            $sqlType .= "($length)";
                        }

                        $column = "`$colName` $sqlType";
                        if ($isNotNull) $column .= " NOT NULL";
                        if ($isAI) $column .= " AUTO_INCREMENT";
                        if ($isUnique) $column .= " UNIQUE";
                        if ($isPrimary) $column .= " PRIMARY KEY";
                        $columnsSQL[] = $column;
                    }
                }
            }

            if (!empty($columnsSQL)) {
                $columnsSQLStr = implode(", ", $columnsSQL);
                $createSQL = "CREATE TABLE `$selectedTable` ($columnsSQLStr)";
                if ($conn->query($createSQL)) {
                    $msg = "✅ Table '$selectedTable' created successfully.";
                    $usedTable = $selectedTable;
                } else {
                    $msg = "❌ Failed to create table: " . $conn->error;
                }
            } else {
                $msg = "❌ No valid columns provided.";
            }
        } else {
            $msg = "ℹ️ Table '$selectedTable' already exists.";
            $usedTable = $selectedTable;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Select Database & Tables</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        #topnav { width: 100%; background-color: #183047; display: flex; padding: 10px 20px; box-sizing: border-box; align-items: center; }
        #topnav .nav-tab { color: white; text-decoration: none; padding: 10px 15px; font-weight: bold; margin-right: 10px; border-radius: 5px; transition: 0.3s; }
        #topnav .db-creator { background-color: #1976d2; }
        #topnav .prompt { background-color: #9c27b0; }
        #topnav .verify { background-color: #388e3c; }
        #topnav .logout { background-color: #c62828; margin-left: auto; }
        #topnav .nav-tab:hover { opacity: 0.8; }
        body { margin: 0; font-family: Arial, sans-serif; background: linear-gradient(135deg, #ffecd2, #fcb69f, #6a11cb, #2575fc); background-size: 400% 400%; animation: gradientBG 15s ease infinite; }
        @keyframes gradientBG { 0% {background-position: 0% 50%;} 50% {background-position: 100% 50%;} 100% {background-position: 0% 50%;} }
        .main-content { margin-top: 90px; padding: 20px; background: rgba(255, 255, 255, 0.85); border-radius: 15px; box-shadow: 0 6px 15px rgba(0,0,0,0.1); }
    </style>
</head>

<body>
<div id="topnav">
    <a href="select_table.php" class="nav-tab db-creator">DB_CREATOR</a>
    <a href="prompt.php" class="nav-tab prompt">PROMPT</a>
    <a href="verify.php" class="nav-tab verify">VERIFY</a>
    <a href="logout.php" class="nav-tab logout">LOGOUT</a>
</div>

<div class="main-content">
    <h2>Select Database and Tables</h2>
    <form action="select_table.php" method="post">
        <div class="row mb-4">
            <div class="col-md-4 mt-4">
                <label class="form-label">Select Database</label>
                <select name="Database" class="form-control" required onchange="this.form.submit()">
                    <option value="">Select Database</option>
                    <?php
                    $queryDB = mysqli_query($conn, "SELECT * FROM create_db");
                    while ($rowDB = mysqli_fetch_array($queryDB)) { ?>
                        <option value="<?= $rowDB['db_name'] ?>" <?= ($_POST['Database'] == $rowDB['db_name']) ? 'selected' : '' ?>>
                            <?= $rowDB['db_name'] ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <div class="col-md-2 d-flex align-items-end gap-2 mt-4">
                <a href="add_database.php" class="btn btn-primary me-1"><i class="bi bi-plus"></i></a>
                <a href="delete_database.php" class="btn btn-danger"><i class="bi bi-dash"></i></a>
            </div>

            <div class="col-md-4 mt-4">
                <label class="form-label">Select Table</label>
                <select name="Table" class="form-control" required>
                    <option value="">Select Table</option>
                    <?php
                    $query1 = mysqli_query($conn, "SELECT * FROM language_category");
                    while ($row1 = mysqli_fetch_array($query1)) { ?>
                        <option value="<?= $row1['category_name'] ?>"><?= $row1['category_name'] ?></option>
                    <?php } ?>
                </select>
            </div>

            <div class="col-md-2 d-flex align-items-end gap-2 mt-4">
                <a href="add_table.php" class="btn btn-primary me-1"><i class="bi bi-plus"></i></a>
                <a href="delete_table.php" class="btn btn-danger"><i class="bi bi-dash"></i></a>
            </div>
        </div>

        <!-- Columns -->
        <h5 style="margin-top: 20px;">Add Table Columns</h5>
        <table border="1" cellpadding="10" cellspacing="0" style="width: 100%; margin-bottom: 10px;" id="columnTable">
            <thead style="background-color: #f2f2f2;">
            <tr>
                <th>Column Name</th>
                <th>Type</th>
                <th>Length</th>
                <th>Not Null</th>
                <th>Primary</th>
                <th>Auto Increment</th>
                <th>Unique</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td><input type="text" name="column_name[]" placeholder="Enter column name"></td>
                <td>
                    <select name="column_type[]">
                        <option value="INT">INT</option>
                        <option value="VARCHAR">VARCHAR</option>
                        <option value="TEXT">TEXT</option>
                        <option value="DATE">DATE</option>
                        <option value="TIME">TIME</option>
                    </select>
                </td>
                <td><input type="text" name="column_length[]" placeholder="Length (e.g. 50)"></td>
                <td style="text-align: center;"><input type="checkbox" name="not_null[]" value="0"></td>
                <td style="text-align: center;"><input type="radio" name="primary_key" value="0"></td>
                <td style="text-align: center;"><input type="checkbox" name="auto_increment[]" value="0"></td>
                <td style="text-align: center;"><input type="checkbox" name="unique[]" value="0"></td>
                <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">Remove</button></td>
            </tr>
            </tbody>
        </table>

        <div style="display: flex; justify-content: flex-end; margin-bottom: 10px;">
            <button type="button" onclick="addRow()">+ Add Column</button>
        </div>

        <div class="mt-1 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Create Table</button>

            <!-- Drop Table Button -->
            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#dropTableModal">
                Drop Table
            </button>
        </div>
    </form>

    <?php
    if (!empty($usedTable)) {
        echo "<h4 class='mt-5'>Structure of table <strong>$usedTable</strong></h4>";
        mysqli_select_db($conn, $_POST['Database']);
        $structure = $conn->query("DESCRIBE `$usedTable`");
        echo "<table class='table table-sm'><thead><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr></thead><tbody>";
        while ($row = $structure->fetch_assoc()) {
            echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td></tr>";
        }
        echo "</tbody></table>";
    }

    if (!empty($msg)) {
        echo "<div class='alert alert-info mt-3'>$msg</div>";
    }
    ?>
</div>

<!-- DROP TABLE MODAL -->
<div class="modal fade" id="dropTableModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="select_table.php" method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Drop Table</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Select Table to Drop</label>
                    <select name="drop_table_name" class="form-control" required>
                        <option value="">Select Table</option>
                        <?php
                        $activeDB = $_POST['Database'] ?? 'chatbox_db';
                        mysqli_select_db($conn, $activeDB);
                        $tables = mysqli_query($conn, "SHOW TABLES");
                        while ($tbl = mysqli_fetch_array($tables)) {
                            echo "<option value='{$tbl[0]}'>{$tbl[0]}</option>";
                        }
                        ?>
                    </select>
                    <input type="hidden" name="Database" value="<?= $_POST['Database'] ?? 'chatbox_db' ?>">
                </div>
                <div class="modal-footer">
                    <button type="submit" name="drop_table" class="btn btn-danger">Drop</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function addRow() {
    const table = document.getElementById("columnTable").getElementsByTagName('tbody')[0];
    const rowCount = table.rows.length;
    const row = table.insertRow();

    row.innerHTML = `
        <td><input type="text" name="column_name[]" placeholder="Enter column name" required></td>
        <td>
            <select name="column_type[]">
                <option value="INT">INT</option>
                <option value="VARCHAR">VARCHAR</option>
                <option value="TEXT">TEXT</option>
                <option value="DATE">DATE</option>
                <option value="TIME">TIME</option>
            </select>
        </td>
        <td><input type="text" name="column_length[]" placeholder="Length (e.g. 50)"></td>
        <td style="text-align: center;"><input type="checkbox" name="not_null[]" value="${rowCount}"></td>
        <td style="text-align: center;"><input type="radio" name="primary_key" value="${rowCount}"></td>
        <td style="text-align: center;"><input type="checkbox" name="auto_increment[]" value="${rowCount}"></td>
        <td style="text-align: center;"><input type="checkbox" name="unique[]" value="${rowCount}"></td>
        <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">Remove</button></td>
    `;
}

function removeRow(button) {
    const row = button.parentNode.parentNode;
    row.parentNode.removeChild(row);
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>