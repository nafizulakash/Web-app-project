<?php
// Database setup script to create broadcast channel tables
// Filename: create_broadcast_tables.php

// Include database connection
require_once 'db.php';  // Using the existing PDO connection

try {
    // Begin transaction
    $conn->beginTransaction();
    
    // First check if the schema exists, if not create it
    $query = "CREATE SCHEMA IF NOT EXISTS user_management;";
    $conn->exec($query);
    
    // Create broadcast_channels table
    $query = "
    CREATE TABLE IF NOT EXISTS user_management.broadcast_channels (
        id SERIAL PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        creator_id INTEGER NOT NULL,
        FOREIGN KEY (creator_id) REFERENCES user_management.users(id) ON DELETE CASCADE
    );";
    
    $conn->exec($query);
    
    // Create channel_members table
    $query = "
    CREATE TABLE IF NOT EXISTS user_management.channel_members (
        id SERIAL PRIMARY KEY,
        channel_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        is_admin BOOLEAN DEFAULT FALSE,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (channel_id) REFERENCES user_management.broadcast_channels(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES user_management.users(id) ON DELETE CASCADE,
        UNIQUE(channel_id, user_id)
    );";
    
    $conn->exec($query);
    
    // Create channel_invitations table
    $query = "
    CREATE TABLE IF NOT EXISTS user_management.channel_invitations (
        id SERIAL PRIMARY KEY,
        channel_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        invited_by INTEGER NOT NULL,
        status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'accepted', 'declined')),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (channel_id) REFERENCES user_management.broadcast_channels(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES user_management.users(id) ON DELETE CASCADE,
        FOREIGN KEY (invited_by) REFERENCES user_management.users(id) ON DELETE CASCADE,
        UNIQUE(channel_id, user_id) WHERE status = 'pending'
    );";
    
    $conn->exec($query);
    
    // Commit transaction
    $conn->commit();
    echo "Success: Broadcast channel tables created successfully.";
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "Error: " . $e->getMessage();
}
?>