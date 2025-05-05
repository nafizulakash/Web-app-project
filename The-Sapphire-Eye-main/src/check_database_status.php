<?php
// Tool to check database status and verify table creation
// This helps diagnose connection issues and table existence

// Set error reporting for better debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once 'db.php';  // Using the existing PDO connection

// Function to check if a table exists
function tableExists($conn, $schema, $table) {
    $query = "SELECT EXISTS (
        SELECT FROM information_schema.tables 
        WHERE table_schema = :schema AND table_name = :table
    )";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':schema', $schema);
    $stmt->bindParam(':table', $table);
    $stmt->execute();
    
    return $stmt->fetchColumn();
}

// Function to check if schema exists
function schemaExists($conn, $schema) {
    $query = "SELECT EXISTS (
        SELECT FROM information_schema.schemata 
        WHERE schema_name = :schema
    )";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':schema', $schema);
    $stmt->execute();
    
    return $stmt->fetchColumn();
}

// Function to get PostgreSQL version
function getPostgresVersion($conn) {
    try {
        $stmt = $conn->query("SELECT version()");
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        return "Error getting version: " . $e->getMessage();
    }
}

// Function to check database connection status
function checkDatabaseConnection($conn) {
    try {
        $conn->query("SELECT 1");
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// Tables to check (in the user_management schema)
$tables = [
    'users',
    'roles',
    'broadcast_channels',
    'channel_members',
    'channel_invitations',
    'broadcast_messages'
];

// Check if the connection is working
$connectionStatus = checkDatabaseConnection($conn) ? "Connected" : "Not Connected";
$connectionClass = checkDatabaseConnection($conn) ? "success" : "error";
$postgresVersion = getPostgresVersion($conn);

// Check if schema exists
$schema = 'user_management';
$schemaExists = schemaExists($conn, $schema);
$schemaClass = $schemaExists ? "success" : "error";

// Get current database name
$dbName = "";
try {
    $stmt = $conn->query("SELECT current_database()");
    $dbName = $stmt->fetchColumn();
} catch (PDOException $e) {
    $dbName = "Error getting database name: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Status Check</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 20px;
            max-width: 800px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin: 20px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .success {
            color: green;
        }
        .error {
            color: red;
        }
        .actions {
            margin-top: 20px;
        }
        .btn {
            display: inline-block;
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 4px;
            margin-right: 10px;
        }
        h2 {
            color: #333;
        }
        .status-section {
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <h1>Database Status Check</h1>
    
    <div class="status-section">
        <h2>Connection Information</h2>
        <table>
            <tr>
                <th>Parameter</th>
                <th>Value</th>
            </tr>
            <tr>
                <td>Connection Status</td>
                <td class="<?php echo $connectionClass; ?>"><?php echo $connectionStatus; ?></td>
            </tr>
            <tr>
                <td>Database Name</td>
                <td><?php echo htmlspecialchars($dbName); ?></td>
            </tr>
            <tr>
                <td>PostgreSQL Version</td>
                <td><?php echo htmlspecialchars($postgresVersion); ?></td>
            </tr>
            <tr>
                <td>Schema (user_management)</td>
                <td class="<?php echo $schemaClass; ?>"><?php echo $schemaExists ? "Exists" : "Does Not Exist"; ?></td>
            </tr>
        </table>
    </div>
    
    <div class="status-section">
        <h2>Table Status</h2>
        <?php if (!$schemaExists): ?>
            <p class="error">The schema 'user_management' does not exist. Tables cannot be checked.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>Table Name</th>
                    <th>Status</th>
                </tr>
                <?php foreach ($tables as $table): ?>
                    <?php $exists = tableExists($conn, 'user_management', $table); ?>
                    <tr>
                        <td>user_management.<?php echo htmlspecialchars($table); ?></td>
                        <td class="<?php echo $exists ? 'success' : 'error'; ?>">
                            <?php echo $exists ? "Exists" : "Does Not Exist"; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
    
    <div class="actions">
        <h2>Actions</h2>
        <a href="run_database_setup.php" class="btn">Run Database Setup</a>
        <a href="index.php" class="btn" style="background-color: #2196F3;">Return to Home</a>
    </div>
    
    <div class="status-section">
        <h2>Troubleshooting</h2>
        <p>If tables are missing:</p>
        <ol>
            <li>Click "Run Database Setup" to create missing tables</li>
            <li>Ensure the 'user_management.users' table exists (it's required for foreign key constraints)</li>
            <li>Check your PostgreSQL user has sufficient privileges</li>
            <li>Verify your database configuration in db.php file</li>
        </ol>
        
        <p>If the schema doesn't exist:</p>
        <ol>
            <li>Run the database setup script to create the schema</li>
            <li>Check if your PostgreSQL user has permission to create schemas</li>