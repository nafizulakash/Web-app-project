<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Include database connection
require_once 'db.php';

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Check if form was submitted correctly
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['invitation_id']) || !isset($_POST['response'])) {
    header("Location: my_invitations.html?error=Invalid request");
    exit;
}

$invitation_id = (int)$_POST['invitation_id'];
$response = $_POST['response'];

// Validate response
if ($response !== 'accept' && $response !== 'decline') {
    header("Location: my_invitations.html?error=Invalid response");
    exit;
}

try {
    // Begin transaction
    $conn->beginTransaction();
    
    // Check if the invitation exists and belongs to the user
    $stmt = $conn->prepare("
        SELECT ci.channel_id, bc.name
        FROM user_management.channel_invitations ci
        JOIN user_management.broadcast_channels bc ON ci.channel_id = bc.id
        WHERE ci.id = :invitation_id AND ci.user_id = :user_id AND ci.status = 'pending'
    ");
    $stmt->execute([':invitation_id' => $invitation_id, ':user_id' => $user_id]);
    $invitation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$invitation) {
        header("Location: my_invitations.html?error=Invitation not found or already processed");
        exit;
    }
    
    $channel_id = $invitation['channel_id'];
    $channel_name = $invitation['name'];
    
    if ($response === 'accept') {
        // Update invitation status
        $stmt = $conn->prepare("
            UPDATE user_management.channel_invitations
            SET status = 'accepted'
            WHERE id = :invitation_id
        ");
        $stmt->execute([':invitation_id' => $invitation_id]);
        
        // Add user to channel members
        // Using PostgreSQL's ON CONFLICT syntax for upsert operation
        $stmt = $conn->prepare("
            INSERT INTO user_management.channel_members (channel_id, user_id)
            VALUES (:channel_id, :user_id)
            ON CONFLICT (channel_id, user_id) DO NOTHING
        ");
        $stmt->execute([':channel_id' => $channel_id, ':user_id' => $user_id]);
        
        $message = "You have joined the channel: " . htmlspecialchars($channel_name);
    } else {
        // Update invitation status
        $stmt = $conn->prepare("
            UPDATE user_management.channel_invitations
            SET status = 'declined'
            WHERE id = :invitation_id
        ");
        $stmt->execute([':invitation_id' => $invitation_id]);
        
        $message = "You have declined the invitation to join: " . htmlspecialchars($channel_name);
    }
    
    // Commit transaction
    $conn->commit();
    
    // Redirect with success message
    header("Location: my_invitations.html?message=" . urlencode($message));
    
} catch (PDOException $e) {
    // Rollback transaction on error
    $conn->rollBack();
    header("Location: my_invitations.html?error=" . urlencode("Database error: " . $e->getMessage()));
}
?>