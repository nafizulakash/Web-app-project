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
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['invitation_id']) || !isset($_POST['channel_id'])) {
    header("Location: manage_channels.html?error=Invalid request");
    exit;
}

$invitation_id = (int)$_POST['invitation_id'];
$channel_id = (int)$_POST['channel_id'];

try {
    // First, check if the user is the creator of the channel
    $stmt = $conn->prepare("SELECT creator_id FROM user_management.broadcast_channels WHERE id = $1");
    $stmt->execute([$channel_id]);
    $channel = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$channel || $channel['creator_id'] != $user_id) {
        header("Location: manage_channels.html?error=You are not authorized to cancel this invitation");
        exit;
    }
    
    // Check if the invitation exists
    $stmt = $conn->prepare("SELECT id FROM user_management.channel_invitations 
                           WHERE id = $1 AND channel_id = $2");
    $stmt->execute([$invitation_id, $channel_id]);
    
    if ($stmt->rowCount() === 0) {
        header("Location: invite_users.php?channel_id=" . $channel_id . "&error=Invitation not found");
        exit;
    }
    
    // Delete the invitation
    $stmt = $conn->prepare("DELETE FROM user_management.channel_invitations WHERE id = $1");
    $stmt->execute([$invitation_id]);
    
    // Redirect back to the invite users page with success message
    header("Location: invite_users.php?channel_id=" . $channel_id . "&message=Invitation cancelled successfully");
    
} catch (PDOException $e) {
    header("Location: invite_users.php?channel_id=" . $channel_id . "&error=" . urlencode("Database error: " . $e->getMessage()));
}
?>