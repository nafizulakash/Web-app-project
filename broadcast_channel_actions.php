<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'You must be logged in to perform this action'
    ]);
    exit;
}

require_once 'db.php';  // Using the existing PDO connection

$user_id = $_SESSION['user_id'];
$action = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    $action = $_GET['action'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
}

switch ($action) {
    case 'create_channel':
        createChannel($conn, $user_id);
        break;
    case 'get_channels':
        getChannels($conn, $user_id);
        break;
    case 'get_invitations':
        getInvitations($conn, $user_id);
        break;
    case 'respond_invitation':
        respondToInvitation($conn, $user_id);
        break;
    default:
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid action'
        ]);
        break;
}

function createChannel($conn, $user_id) {
    if (!isset($_POST['channel_name']) || empty(trim($_POST['channel_name']))) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Channel name is required'
        ]);
        return;
    }

    $channel_name = trim($_POST['channel_name']);
    $channel_description = isset($_POST['channel_description']) ? trim($_POST['channel_description']) : '';

    try {
        $conn->beginTransaction();

        // Insert channel with RETURNING clause to get the new ID
        $stmt = $conn->prepare("
            INSERT INTO user_management.broadcast_channels (name, description, creator_id) 
            VALUES (:name, :description, :creator_id) RETURNING id
        ");
        
        $stmt->bindParam(':name', $channel_name);
        $stmt->bindParam(':description', $channel_description);
        $stmt->bindParam(':creator_id', $user_id);
        $stmt->execute();
        
        $channel_id = $stmt->fetchColumn();

        // Insert admin member
        $stmt = $conn->prepare("
            INSERT INTO user_management.channel_members (channel_id, user_id, is_admin) 
            VALUES (:channel_id, :user_id, TRUE)
        ");
        
        $stmt->bindParam(':channel_id', $channel_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        $conn->commit();

        echo json_encode([
            'status' => 'success',
            'message' => 'Channel created successfully',
            'channel_id' => $channel_id
        ]);
    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to create channel: ' . $e->getMessage()
        ]);
    }
}

function getChannels($conn, $user_id) {
    try {
        $query = "
            SELECT 
                bc.id, 
                bc.name, 
                bc.description, 
                bc.created_at,
                bc.creator_id, 
                (SELECT COUNT(*) FROM user_management.channel_members WHERE channel_id = bc.id) as member_count,
                (bc.creator_id = :user_id) as is_creator
            FROM 
                user_management.broadcast_channels bc 
            JOIN 
                user_management.channel_members cm ON bc.id = cm.channel_id 
            WHERE 
                cm.user_id = :user_id
            ORDER BY 
                bc.created_at DESC
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $channels = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $channels[] = [
                'id' => $row['id'],
                'name' => htmlspecialchars($row['name']),
                'description' => htmlspecialchars($row['description']),
                'created_at' => $row['created_at'],
                'member_count' => $row['member_count'],
                'is_creator' => (bool)$row['is_creator']
            ];
        }
        
        echo json_encode($channels);
    } catch (PDOException $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to retrieve channels: ' . $e->getMessage()
        ]);
    }
}

function getInvitations($conn, $user_id) {
    try {
        $query = "
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
                ci.user_id = :user_id AND ci.status = 'pending'
            ORDER BY 
                ci.created_at DESC
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $invitations = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $invitations[] = [
                'invitation_id' => $row['invitation_id'],
                'channel_id' => $row['channel_id'],
                'name' => htmlspecialchars($row['name']),
                'description' => htmlspecialchars($row['description']),
                'invited_by_username' => htmlspecialchars($row['invited_by_username'])
            ];
        }
        
        echo json_encode($invitations);
    } catch (PDOException $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to retrieve invitations: ' . $e->getMessage()
        ]);
    }
}

function respondToInvitation($conn, $user_id) {
    if (!isset($_POST['invitation_id']) || !isset($_POST['response'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid request parameters'
        ]);
        return;
    }

    $invitation_id = (int)$_POST['invitation_id'];
    $response = $_POST['response'];

    if ($response !== 'accept' && $response !== 'decline') {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid response'
        ]);
        return;
    }

    try {
        $conn->beginTransaction();

        // Check invitation validity
        $stmt = $conn->prepare("
            SELECT channel_id FROM user_management.channel_invitations 
            WHERE id = :invitation_id AND user_id = :user_id AND status = 'pending'
        ");
        
        $stmt->bindParam(':invitation_id', $invitation_id);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invitation not found or already processed'
            ]);
            $conn->rollBack();
            return;
        }

        $channel_id = $stmt->fetchColumn();

        // Update invitation
        $status = ($response === 'accept') ? 'accepted' : 'declined';
        $stmt = $conn->prepare("
            UPDATE user_management.channel_invitations 
            SET status = :status 
            WHERE id = :invitation_id
        ");
        
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':invitation_id', $invitation_id);
        $stmt->execute();

        if ($response === 'accept') {
            // Check existing membership
            $stmt = $conn->prepare("
                SELECT id FROM user_management.channel_members 
                WHERE channel_id = :channel_id AND user_id = :user_id
            ");
            
            $stmt->bindParam(':channel_id', $channel_id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                $stmt = $conn->prepare("
                    INSERT INTO user_management.channel_members (channel_id, user_id, is_admin) 
                    VALUES (:channel_id, :user_id, FALSE)
                ");
                
                $stmt->bindParam(':channel_id', $channel_id);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
            }
        }

        $conn->commit();

        $message = ($response === 'accept') ? 'You have joined the channel' : 'Invitation declined';
        echo json_encode([
            'status' => 'success',
            'message' => $message
        ]);
    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to process invitation: ' . $e->getMessage()
        ]);
    }
}