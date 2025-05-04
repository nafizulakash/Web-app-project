<?php
// This file should be executed once to create the necessary tables

require 'db.php';

try {
    // Broadcast channels table
    $conn->exec("CREATE TABLE IF NOT EXISTS user_management.broadcast_channels (
        id SERIAL PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        creator_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Channel memberships table
    $conn->exec("CREATE TABLE IF NOT EXISTS user_management.channel_members (
        id SERIAL PRIMARY KEY,
        channel_id INTEGER NOT NULL REFERENCES broadcast_channels(id) ON DELETE CASCADE,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(channel_id, user_id)
    )");

    // Broadcast messages table
    $conn->exec("CREATE TABLE IF NOT EXISTS user_management.broadcast_messages (
        id SERIAL PRIMARY KEY,
        channel_id INTEGER NOT NULL REFERENCES broadcast_channels(id) ON DELETE CASCADE,
        sender_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_edited BOOLEAN DEFAULT FALSE
    )");

    // Invitations table
    $conn->exec("CREATE TABLE IF NOT EXISTS user_management.channel_invitations (
        id SERIAL PRIMARY KEY,
        channel_id INTEGER NOT NULL REFERENCES broadcast_channels(id) ON DELETE CASCADE,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        invited_by INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(20) DEFAULT 'pending',
        UNIQUE(channel_id, user_id)
    )");
    
    echo "Broadcast tables created successfully!";
} catch (PDOException $e) {
    echo "Error creating broadcast tables: " . $e->getMessage();
}
?>