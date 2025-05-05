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
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: manage_channels.html?error=Invalid channel ID");
    exit;
}

$channel_id = (int)$_GET['id'];

try {
    // Check if the user is a member of this channel
    $stmt = $conn->prepare("SELECT * FROM user_management.channel_members 
                           WHERE channel_id = :channel_id AND user_id = :user_id");
    $stmt->bindParam(':channel_id', $channel_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // PostgreSQL doesn't have rowCount() that works reliably, use fetch instead
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$member) {
        header("Location: manage_channels.html?error=You are not a member of this channel");
        exit;
    }
    
    // Get channel details - using CASE for boolean conversion
    $stmt = $conn->prepare("SELECT bc.*, 
                           CASE WHEN bc.creator_id = :user_id THEN TRUE ELSE FALSE END as is_creator 
                           FROM user_management.broadcast_channels bc 
                           WHERE bc.id = :channel_id");
    $stmt->bindParam(':channel_id', $channel_id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $channel = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$channel) {
        header("Location: manage_channels.html?error=Channel not found");
        exit;
    }
    
    // Get channel messages
    $stmt = $conn->prepare("SELECT bm.*, u.username as sender_name 
                           FROM user_management.broadcast_messages bm 
                           JOIN user_management.users u ON bm.sender_id = u.id 
                           WHERE bm.channel_id = :channel_id 
                           ORDER BY bm.created_at DESC");
    $stmt->bindParam(':channel_id', $channel_id, PDO::PARAM_INT);
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    header("Location: manage_channels.html?error=" . urlencode("Database error: " . $e->getMessage()));
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?php echo htmlspecialchars($channel['name']); ?> - Broadcast Channel</title>
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
    .channel-description {
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
    .message-form {
      margin-bottom: 30px;
      display: <?php echo $channel['is_creator'] ? 'block' : 'none'; ?>;
    }
    .message-area {
      width: 100%;
      padding: 12px;
      border: 1px solid #ccc;
      border-radius: 4px;
      resize: vertical;
      min-height: 100px;
      box-sizing: border-box;
      margin-bottom: 10px;
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
    .messages-container {
      margin-top: 30px;
    }
    .message-item {
      border: 1px solid #ddd;
      padding: 15px;
      margin-bottom: 15px;
      border-radius: 4px;
    }
    .message-header {
      display: flex;
      justify-content: space-between;
      margin-bottom: 10px;
      font-size: 14px;
      color: #666;
    }
    .message-content {
      white-space: pre-wrap;
    }
    .message-actions {
      margin-top: 10px;
      text-align: right;
      display: none;
    }
    .message-item[data-sender-id="<?php echo $user_id; ?>"] .message-actions {
      display: block;
    }
    .edit-form {
      display: none;
      margin-top: 10px;
    }
    .edit-textarea {
      width: 100%;
      padding: 8px;
      border: 1px solid #ccc;
      border-radius: 4px;
      resize: vertical;
      min-height: 80px;
      box-sizing: border-box;
      margin-bottom: 10px;
    }
    .edit-actions {
      text-align: right;
    }
    .btn-small {
      padding: 5px 10px;
      font-size: 14px;
    }
    .btn-secondary {
      background: #6c757d;
    }
    .btn-danger {
      background: #dc3545;
    }
    .no-messages {
      text-align: center;
      color: #666;
      padding: 20px;
    }
  </style>
</head>
<body>

<div class="container">
  <a href="manage_channels.html" class="back-link">← Back to Channels</a>
  
  <h2><?php echo htmlspecialchars($channel['name']); ?></h2>
  <?php if (!empty($channel['description'])): ?>
    <div class="channel-description"><?php echo htmlspecialchars($channel['description']); ?></div>
  <?php endif; ?>
  
  <?php if ($channel['is_creator']): ?>
    <div class="message-form">
      <h3>Send Broadcast Message</h3>
      <form id="sendMessageForm">
        <input type="hidden" name="channel_id" value="<?php echo $channel_id; ?>">
        <textarea class="message-area" name="message" placeholder="Type your message here..." required></textarea>
        <button type="submit" class="btn">Send Message</button>
      </form>
    </div>
  <?php endif; ?>
  
  <div class="messages-container">
    <h3>Messages</h3>
    
    <?php if (empty($messages)): ?>
      <div class="no-messages">No messages in this channel yet.</div>
    <?php else: ?>
      <?php foreach ($messages as $message): ?>
        <div class="message-item" data-id="<?php echo $message['id']; ?>" data-sender-id="<?php echo $message['sender_id']; ?>">
          <div class="message-header">
            <span>From: <?php echo htmlspecialchars($message['sender_name']); ?></span>
            <span><?php echo date('M j, Y g:i A', strtotime($message['created_at'])); ?></span>
          </div>
          <div class="message-content"><?php echo htmlspecialchars($message['message']); ?></div>
          
          <?php if ($message['sender_id'] == $user_id): ?>
            <div class="message-actions">
              <button class="btn-small btn-secondary edit-btn">Edit</button>
              <button class="btn-small btn-danger delete-btn">Delete</button>
            </div>
            
            <div class="edit-form">
              <textarea class="edit-textarea"><?php echo htmlspecialchars($message['message']); ?></textarea>
              <div class="edit-actions">
                <button class="btn-small btn-secondary cancel-edit-btn">Cancel</button>
                <button class="btn-small save-edit-btn">Save</button>
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Send message form handling
  const sendMessageForm = document.getElementById('sendMessageForm');
  
  if (sendMessageForm) {
    sendMessageForm.addEventListener('submit', function(e) {
      e.preventDefault();
      
      const formData = new FormData(this);
      formData.append('action', 'send_message');
      
      fetch('broadcast_message_actions.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.status === 'success') {
          // Reload the page to show the new message
          location.reload();
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        alert('Error sending message: ' + error.message);
      });
    });
  }
  
  // Edit message functionality
  document.querySelectorAll('.edit-btn').forEach(button => {
    button.addEventListener('click', function() {
      const messageItem = this.closest('.message-item');
      messageItem.querySelector('.message-actions').style.display = 'none';
      messageItem.querySelector('.edit-form').style.display = 'block';
    });
  });
  
  // Cancel edit
  document.querySelectorAll('.cancel-edit-btn').forEach(button => {
    button.addEventListener('click', function() {
      const messageItem = this.closest('.message-item');
      messageItem.querySelector('.message-actions').style.display = 'block';
      messageItem.querySelector('.edit-form').style.display = 'none';
    });
  });
  
  // Save edit
  document.querySelectorAll('.save-edit-btn').forEach(button => {
    button.addEventListener('click', function() {
      const messageItem = this.closest('.message-item');
      const messageId = messageItem.dataset.id;
      const newMessage = messageItem.querySelector('.edit-textarea').value;
      
      const formData = new FormData();
      formData.append('action', 'edit_message');
      formData.append('message_id', messageId);
      formData.append('message', newMessage);
      
      fetch('broadcast_message_actions.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.status === 'success') {
          // Update the message content
          messageItem.querySelector('.message-content').textContent = newMessage;
          // Hide the edit form
          messageItem.querySelector('.message-actions').style.display = 'block';
          messageItem.querySelector('.edit-form').style.display = 'none';
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        alert('Error updating message: ' + error.message);
      });
    });
  });
  
  // Delete message
  document.querySelectorAll('.delete-btn').forEach(button => {
    button.addEventListener('click', function() {
      if (confirm('Are you sure you want to delete this message?')) {
        const messageItem = this.closest('.message-item');
        const messageId = messageItem.dataset.id;
        
        const formData = new FormData();
        formData.append('action', 'delete_message');
        formData.append('message_id', messageId);
        
        fetch('broadcast_message_actions.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.status === 'success') {
            // Remove the message from the DOM
            messageItem.remove();
          } else {
            alert('Error: ' + data.message);
          }
        })
        .catch(error => {
          alert('Error deleting message: ' + error.message);
        });
      }
    });
  });
});
</script>

</body>
</html>