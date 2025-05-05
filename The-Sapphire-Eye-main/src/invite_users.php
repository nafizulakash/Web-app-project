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

// Check if channel ID is provided
if (!isset($_GET['channel_id']) || !is_numeric($_GET['channel_id'])) {
    header("Location: manage_channels.html?error=Invalid channel ID");
    exit;
}

$channel_id = (int)$_GET['channel_id'];

try {
    // Check if the user is the creator of this channel
    $stmt = pg_prepare($conn, "check_creator", "SELECT * FROM user_management.broadcast_channels 
                               WHERE id = $1 AND creator_id = $2");
    $result = pg_execute($conn, "check_creator", [$channel_id, $user_id]);
    
    if (pg_num_rows($result) === 0) {
        header("Location: manage_channels.html?error=You are not the creator of this channel");
        exit;
    }
    
    // Get channel details
    $stmt = pg_prepare($conn, "get_channel", "SELECT * FROM user_management.broadcast_channels WHERE id = $1");
    $result = pg_execute($conn, "get_channel", [$channel_id]);
    $channel = pg_fetch_assoc($result);
    
    // Process invitation form submission
    $message = '';
    $error = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invite_username'])) {
        $invite_username = trim($_POST['invite_username']);
        
        if (empty($invite_username)) {
            $error = "Username cannot be empty";
        } else {
            // Check if user exists
            $stmt = pg_prepare($conn, "find_user", "SELECT id FROM user_management.users WHERE username = $1");
            $result = pg_execute($conn, "find_user", [$invite_username]);
            $invite_user = pg_fetch_assoc($result);
            
            if (!$invite_user) {
                $error = "User not found";
            } else {
                $invite_user_id = $invite_user['id'];
                
                // Check if user is already a member
                $stmt = pg_prepare($conn, "check_member", "SELECT id FROM user_management.channel_members 
                                           WHERE channel_id = $1 AND user_id = $2");
                $result = pg_execute($conn, "check_member", [$channel_id, $invite_user_id]);
                
                if (pg_num_rows($result) > 0) {
                    $error = "User is already a member of this channel";
                } else {
                    // Check if invitation already exists
                    $stmt = pg_prepare($conn, "check_invitation", "SELECT id FROM user_management.channel_invitations 
                                               WHERE channel_id = $1 AND user_id = $2 AND status = 'pending'");
                    $result = pg_execute($conn, "check_invitation", [$channel_id, $invite_user_id]);
                    
                    if (pg_num_rows($result) > 0) {
                        $error = "User has already been invited to this channel";
                    } else {
                        // Create invitation
                        $stmt = pg_prepare($conn, "create_invitation", "INSERT INTO user_management.channel_invitations 
                                                   (channel_id, user_id, invited_by) 
                                                   VALUES ($1, $2, $3)");
                        pg_execute($conn, "create_invitation", [
                            $channel_id,
                            $invite_user_id,
                            $user_id
                        ]);
                        
                        $message = "Invitation sent to " . htmlspecialchars($invite_username);
                    }
                }
            }
        }
    }
    
    // Get current members
    $stmt = pg_prepare($conn, "get_members", "SELECT u.id, u.username 
                               FROM user_management.channel_members cm 
                               JOIN user_management.users u ON cm.user_id = u.id 
                               WHERE cm.channel_id = $1");
    $result = pg_execute($conn, "get_members", [$channel_id]);
    $members = [];
    while ($row = pg_fetch_assoc($result)) {
        $members[] = $row;
    }
    
    // Get pending invitations
    $stmt = pg_prepare($conn, "get_invitations", "SELECT ci.id, u.username, ci.created_at 
                               FROM user_management.channel_invitations ci 
                               JOIN user_management.users u ON ci.user_id = u.id 
                               WHERE ci.channel_id = $1 AND ci.status = 'pending'");
    $result = pg_execute($conn, "get_invitations", [$channel_id]);
    $invitations = [];
    while ($row = pg_fetch_assoc($result)) {
        $invitations[] = $row;
    }
    
} catch (Exception $e) {
    header("Location: manage_channels.html?error=" . urlencode("Database error: " . $e->getMessage()));
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Invite Users - <?php echo htmlspecialchars($channel['name']); ?></title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #f2f2f2;
      padding: 40px;
    }
    .container {
      max-width: 800px;
      margin: auto;
      background: white;
      padding: 30px;
      border-radius: 8px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    h2 {
      text-align: center;
      margin-bottom: 5px;
    }
    .channel-name {
      text-align: center;
      color: #666;
      margin-bottom: 20px;
    }
    .back-link {
      display: block;
      margin-bottom: 20px;
      color: #007BFF;
      text-decoration: none;
    }
    .back-link:hover {
      text-decoration: underline;
    }
    .form-group {
      margin-bottom: 20px;
    }
    label {
      display: block;
      margin-bottom: 5px;
      font-weight: bold;
    }
    input[type="text"] {
      width: 100%;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 4px;
      box-sizing: border-box;
    }
    .btn {
      padding: 10px 20px;
      background: #007BFF;
      color: white;
      border: none;
      border-radius: 4px;
      font-size: 16px;
      cursor: pointer;
    }
    .btn:hover {
      background: #0056b3;
    }
    .message {
      padding: 10px;
      margin-bottom: 20px;
      border-radius: 4px;
    }
    .message.success {
      background-color: #d4edda;
      color: #155724;
    }
    .message.error {
      background-color: #f8d7da;
      color: #721c24;
    }
    .section {
      margin-top: 30px;
    }
    .list {
      border: 1px solid #ddd;
      border-radius: 4px;
    }
    .list-item {
      padding: 15px;
      border-bottom: 1px solid #ddd;
    }
    .list-item:last-child {
      border-bottom: none;
    }
    .list-item-actions {
      float: right;
    }
    .btn-small {
      padding: 5px 10px;
      font-size: 14px;
    }
    .btn-danger {
      background: #dc3545;
    }
    .empty-list {
      padding: 15px;
      text-align: center;
      color: #666;
    }
  </style>
</head>
<body>

<div class="container">
  <a href="view_channel.php?id=<?php echo $channel_id; ?>" class="back-link">← Back to Channel</a>
  
  <h2>Invite Users</h2>
  <div class="channel-name">Channel: <?php echo htmlspecialchars($channel['name']); ?></div>
  
  <?php if (!empty($message)): ?>
    <div class="message success"><?php echo $message; ?></div>
  <?php endif; ?>
  
  <?php if (!empty($error)): ?>
    <div class="message error"><?php echo $error; ?></div>
  <?php endif; ?>
  
  <form method="POST">
    <div class="form-group">
      <label for="invite_username">Invite User (by username):</label>
      <input type="text" id="invite_username" name="invite_username" required>
    </div>
    <button type="submit" class="btn">Send Invitation</button>
  </form>
  
  <div class="section">
    <h3>Current Members (<?php echo count($members); ?>)</h3>
    <div class="list">
      <?php if (empty($members)): ?>
        <div class="empty-list">No members</div>
      <?php else: ?>
        <?php foreach ($members as $member): ?>
          <div class="list-item">
            <?php echo htmlspecialchars($member['username']); ?>
            <?php if ($member['id'] == $user_id): ?>
              <span> (you - creator)</span>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
  
  <div class="section">
    <h3>Pending Invitations (<?php echo count($invitations); ?>)</h3>
    <div class="list">
      <?php if (empty($invitations)): ?>
        <div class="empty-list">No pending invitations</div>
      <?php else: ?>
        <?php foreach ($invitations as $invitation): ?>
          <div class="list-item">
            <?php echo htmlspecialchars($invitation['username']); ?>
            <span class="list-item-actions">
              <form method="POST" action="cancel_invitation.php" style="display: inline;">
                <input type="hidden" name="invitation_id" value="<?php echo $invitation['id']; ?>">
                <input type="hidden" name="channel_id" value="<?php echo $channel_id; ?>">
                <button type="submit" class="btn-small btn-danger">Cancel</button>
              </form>
            </span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

</body>
</html>