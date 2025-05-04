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
    // Get pending invitations for the user
    $stmt = $conn->prepare("
        SELECT 
            ci.id as invitation_id,
            bc.id as channel_id,
            bc.name,
            bc.description,
            u.username as invited_by_username
        FROM 
            user_management.channel_invitations ci
        JOIN 
            user_management.broadcast_channels bc ON ci.channel_id = bc.id
        JOIN 
            user_management.users u ON ci.invited_by = u.id
        WHERE 
            ci.user_id = $1 AND ci.status = 'pending'
        ORDER BY 
            ci.created_at DESC
    ");
    
    $stmt->execute([$user_id]);
    
    $invitations = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $invitations[] = [
            'invitation_id' => $row['invitation_id'],
            'channel_id' => $row['channel_id'],
            'name' => htmlspecialchars($row['name']),
            'description' => htmlspecialchars($row['description'] ?? ''),
            'invited_by_username' => htmlspecialchars($row['invited_by_username'])
        ];
    }
    
    // Return the invitations as JSON
    header('Content-Type: application/json');
    echo json_encode($invitations);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>