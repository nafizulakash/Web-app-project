<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'You must be logged in to perform this action'
    ]);
    exit;
}

require_once 'db_connection.php';  // Uses pg_connect() for PostgreSQL connection

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

    pg_query($conn, "BEGIN");

    try {
        // Insert channel with RETURNING clause to get the new ID
        $result = pg_query_params($conn, 
            "INSERT INTO broadcast_channels (name, description, created_by) 
             VALUES ($1, $2, $3) RETURNING id",
            [$channel_name, $channel_description, $user_id]
        );
        
        if (!$result) {
            throw new Exception(pg_last_error($conn));
        }
        
        $row = pg_fetch_assoc($result);
        $channel_id = $row['id'];

        // Insert admin member
        $result = pg_query_params($conn,
            "INSERT INTO channel_members (channel_id, user_id, is_admin) VALUES ($1, $2, TRUE)",
            [$channel_id, $user_id]
        );
        
        if (!$result) {
            throw new Exception(pg_last_error($conn));
        }

        pg_query($conn, "COMMIT");

        echo json_encode([
            'status' => 'success',
            'message' => 'Channel created successfully',
            'channel_id' => $channel_id
        ]);
    } catch (Exception $e) {
        pg_query($conn, "ROLLBACK");
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to create channel: ' . $e->getMessage()
        ]);
    }
}

function getChannels($conn, $user_id) {
    $query = "
        SELECT 
            bc.id, 
            bc.name, 
            bc.description, 
            bc.created_at,
            bc.created_by, 
            (SELECT COUNT(*) FROM channel_members WHERE channel_id = bc.id) as member_count,
            (bc.created_by = $1) as is_creator
        FROM 
            broadcast_channels bc 
        JOIN 
            channel_members cm ON bc.id = cm.channel_id 
        WHERE 
            cm.user_id = $1
        ORDER BY 
            bc.created_at DESC
    ";
    
    $result = pg_query_params($conn, $query, [$user_id]);
    
    if (!$result) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to retrieve channels: ' . pg_last_error($conn)
        ]);
        return;
    }
    
    $channels = [];
    while ($row = pg_fetch_assoc($result)) {
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
}

function getInvitations($conn, $user_id) {
    $query = "
        SELECT 
            ci.id as invitation_id,
            bc.id as channel_id,
            bc.name,
            bc.description,
            u.username as invited_by_username
        FROM 
            channel_invitations ci
        JOIN 
            broadcast_channels bc ON ci.channel_id = bc.id
        JOIN 
            users u ON ci.invited_by = u.id
        WHERE 
            ci.user_id = $1 AND ci.status = 'pending'
        ORDER BY 
            ci.created_at DESC
    ";
    
    $result = pg_query_params($conn, $query, [$user_id]);
    
    if (!$result) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to retrieve invitations: ' . pg_last_error($conn)
        ]);
        return;
    }
    
    $invitations = [];
    while ($row = pg_fetch_assoc($result)) {
        $invitations[] = [
            'invitation_id' => $row['invitation_id'],
            'channel_id' => $row['channel_id'],
            'name' => htmlspecialchars($row['name']),
            'description' => htmlspecialchars($row['description']),
            'invited_by_username' => htmlspecialchars($row['invited_by_username'])
        ];
    }
    
    echo json_encode($invitations);
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

    pg_query($conn, "BEGIN");

    try {
        // Check invitation validity
        $result = pg_query_params($conn,
            "SELECT channel_id FROM channel_invitations 
             WHERE id = $1 AND user_id = $2 AND status = 'pending'",
            [$invitation_id, $user_id]
        );
        
        if (!$result) {
            throw new Exception(pg_last_error($conn));
        }
        
        if (pg_num_rows($result) === 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invitation not found or already processed'
            ]);
            pg_query($conn, "ROLLBACK");
            return;
        }

        $channel_id = pg_fetch_assoc($result)['channel_id'];

        // Update invitation
        $status = ($response === 'accept') ? 'accepted' : 'declined';
        $result = pg_query_params($conn,
            "UPDATE channel_invitations SET status = $1 WHERE id = $2",
            [$status, $invitation_id]
        );
        
        if (!$result) {
            throw new Exception(pg_last_error($conn));
        }

        if ($response === 'accept') {
            // Check existing membership
            $result = pg_query_params($conn,
                "SELECT id FROM channel_members WHERE channel_id = $1 AND user_id = $2",
                [$channel_id, $user_id]
            );
            
            if (!$result) {
                throw new Exception(pg_last_error($conn));
            }
            
            if (pg_num_rows($result) === 0) {
                $result = pg_query_params($conn,
                    "INSERT INTO channel_members (channel_id, user_id, is_admin) VALUES ($1, $2, FALSE)",
                    [$channel_id, $user_id]
                );
                
                if (!$result) {
                    throw new Exception(pg_last_error($conn));
                }
            }
        }

        pg_query($conn, "COMMIT");

        $message = ($response === 'accept') ? 'You have joined the channel' : 'Invitation declined';
        echo json_encode([
            'status' => 'success',
            'message' => $message
        ]);
    } catch (Exception $e) {
        pg_query($conn, "ROLLBACK");
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to process invitation: ' . $e->getMessage()
        ]);
    }
}