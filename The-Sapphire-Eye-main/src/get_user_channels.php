<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'You must be logged in']);
    exit;
}

// Include database connection
require_once 'db.php';

// Get user ID from session
$user_id = $_SESSION['user_id'];

try {
    // Get channels where the user is a member
    $query = "SELECT bc.id, bc.name, bc.description, bc.created_at, bc.creator_id, 
            (bc.creator_id = :user_id) as is_creator
            FROM user_management.broadcast_channels bc 
            JOIN user_management.channel_members cm ON bc.id = cm.channel_id 
            WHERE cm.user_id = :user_id
            ORDER BY bc.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $channels = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $channels[] = [
            'id' => $row['id'],
            'name' => htmlspecialchars($row['name']),
            'description' => htmlspecialchars($row['description'] ?? ''),
            'created_at' => $row['created_at'],
            'is_creator' => (bool)$row['is_creator']
        ];
    }
    
    // Return the channels as JSON
    header('Content-Type: application/json');
    echo json_encode($channels);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>