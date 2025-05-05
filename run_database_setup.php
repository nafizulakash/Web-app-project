<?php
// Database setup script to create broadcast channel tables
// This script needs to be run before using the broadcast channel functionality

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

// Function to check if user_management schema exists
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

// Function to check if users table exists in the schema
function usersTableExists($conn) {
    return tableExists($conn, 'user_management', 'users');
}

try {
    echo "<h2>Database Setup Process</h2>";
    
    // Start transaction
    $conn->beginTransaction();
    
    // Step 1: Check if user_management schema exists
    $schema = 'user_management';
    if (!schemaExists($conn, $schema)) {
        echo "<p>Creating schema 'user_management'...</p>";
        $conn->exec("CREATE SCHEMA user_management");
        echo "<p>Schema created successfully.</p>";
    } else {
        echo "<p>Schema 'user_management' already exists.</p>";
    }
    
    // Step 2: Check if users table exists (required for foreign keys)
    if (!usersTableExists($conn)) {
        echo "<p style='color: red;'>Error: The 'user_management.users' table doesn't exist. This table is required for the broadcast channel system.</p>";
        echo "<p>Please create the users table first before running this script.</p>";
        $conn->rollBack();
        exit;
    }
    
    // Step 3: Create broadcast_channels table if it doesn't exist
    if (!tableExists($conn, 'user_management', 'broadcast_channels')) {
        echo "<p>Creating 'broadcast_channels' table...</p>";
        $query = "
        CREATE TABLE user_management.broadcast_channels (
            id SERIAL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            creator_id INTEGER NOT NULL,
            FOREIGN KEY (creator_id) REFERENCES user_management.users(id) ON DELETE CASCADE
        )";
        $conn->exec($query);
        echo "<p>Table 'broadcast_channels' created successfully.</p>";
    } else {
        echo "<p>Table 'broadcast_channels' already exists.</p>";
    }
    
    // Step 4: Create channel_members table if it doesn't exist
    if (!tableExists($conn, 'user_management', 'channel_members')) {
        echo "<p>Creating 'channel_members' table...</p>";
        $query = "
        CREATE TABLE user_management.channel_members (
            id SERIAL PRIMARY KEY,
            channel_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            is_admin BOOLEAN DEFAULT FALSE,
            joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (channel_id) REFERENCES user_management.broadcast_channels(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES user_management.users(id) ON DELETE CASCADE,
            UNIQUE(channel_id, user_id)
        )";
        $conn->exec($query);
        echo "<p>Table 'channel_members' created successfully.</p>";
    } else {
        echo "<p>Table 'channel_members' already exists.</p>";
    }
    
    // Step 5: Create channel_invitations table if it doesn't exist
    if (!tableExists($conn, 'user_management', 'channel_invitations')) {
        echo "<p>Creating 'channel_invitations' table...</p>";
        $query = "
        CREATE TABLE user_management.channel_invitations (
            id SERIAL PRIMARY KEY,
            channel_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            invited_by INTEGER NOT NULL,
            status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'accepted', 'declined')),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (channel_id) REFERENCES user_management.broadcast_channels(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES user_management.users(id) ON DELETE CASCADE,
            FOREIGN KEY (invited_by) REFERENCES user_management.users(id) ON DELETE CASCADE
        )";
        $conn->exec($query);
        
        // Create a partial unique index separately after the table creation
        $uniqueIndexQuery = "
        CREATE UNIQUE INDEX unique_pending_invitation 
        ON user_management.channel_invitations (channel_id, user_id) 
        WHERE status = 'pending'";
        $conn->exec($uniqueIndexQuery);
        
        echo "<p>Table 'channel_invitations' created successfully.</p>";
    } else {
        echo "<p>Table 'channel_invitations' already exists.</p>";
    }
    
    // Step 6: Create broadcast_messages table if it doesn't exist
    if (!tableExists($conn, 'user_management', 'broadcast_messages')) {
        echo "<p>Creating 'broadcast_messages' table...</p>";
        $query = "
        CREATE TABLE user_management.broadcast_messages (
            id SERIAL PRIMARY KEY,
            channel_id INTEGER NOT NULL,
            sender_id INTEGER NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_edited BOOLEAN DEFAULT FALSE,
            FOREIGN KEY (channel_id) REFERENCES user_management.broadcast_channels(id) ON DELETE CASCADE,
            FOREIGN KEY (sender_id) REFERENCES user_management.users(id) ON DELETE CASCADE
        )";
        $conn->exec($query);
        echo "<p>Table 'broadcast_messages' created successfully.</p>";
    } else {
        echo "<p>Table 'broadcast_messages' already exists.</p>";
    }
    
    // Commit transaction
    $conn->commit();
    
    echo "<h3 style='color: green;'>Database setup completed successfully!</h3>";
    echo "<p>All required tables have been created or already exist.</p>";
    echo "<p><a href='index.php'>Return to home page</a></p>";
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    
    echo "<h3 style='color: red;'>Error during database setup:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    
    // Provide more detailed troubleshooting info
    echo "<h4>Troubleshooting Suggestions:</h4>";
    echo "<ul>";
    echo "<li>Check if your database user has permission to create schemas and tables</li>";
    echo "<li>Verify that the 'user_management.users' table exists and has the expected structure</li>";
    echo "<li>Check your PostgreSQL server is running properly</li>";
    echo "<li>Review the connection settings in your db.php file</li>";
    echo "</ul>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    line-height: 1.6;
    margin: 20px;
    max-width: 800px;
}
h2 {
    color: #333;
}
p {
    margin-bottom: 10px;
}
.success {
    color: green;
    font-weight: bold;
}
.error {
    color: red;
    font-weight: bold;
}
</style>