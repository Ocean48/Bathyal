<?php
// Removed duplicate session_start to avoid PHP Notices corrupting JSON
require_once '../core/database.php';
require_once '../core/auth_check.php';
require_once '../core/db_query.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) {
            echo json_encode(['success' => false, 'error' => 'Team name is required.']);
            exit;
        }
        try {
            $teamId = $db->createTeam($name, $currentUser['id']);
            echo json_encode(['success' => true, 'team_id' => $teamId, 'name' => $name]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Failed to create team.', 'details' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($action === 'add_member') {
        $teamId = (int)($_POST['team_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);
        $role = $_POST['role'] ?? 'member';

        $systemRole = $currentUser['role'] ?? 'data_analyst';
        
        // System admin has global access, otherwise check team permissions
        if ($systemRole !== 'admin') {
            $currentUserRole = $db->getTeamMemberRole($teamId, $currentUser['id']);
            if (!in_array($currentUserRole, ['owner', 'admin'])) {
                echo json_encode(['success' => false, 'error' => 'You do not have permission to add members to this team.']);
                exit;
            }
        }
        
        // Add member
        if ($db->addTeamMember($teamId, $userId, $role)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'User is already a member or failed to add.']);
        }
        exit;
    }
    
    if ($action === 'remove_member') {
        $teamId = (int)($_POST['team_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);
        
        $systemRole = $currentUser['role'] ?? 'data_analyst';
        $currentUserRole = $db->getTeamMemberRole($teamId, $currentUser['id']);
        $targetUserRole = $db->getTeamMemberRole($teamId, $userId);

        // Check permissions (Global admin, or team owner/admin, or self)
        if ($systemRole !== 'admin') {
            if ($currentUser['id'] !== $userId) {
                if (!in_array($currentUserRole, ['owner', 'admin'])) {
                    echo json_encode(['success' => false, 'error' => 'You do not have permission to remove members from this team.']);
                    exit;
                }
                if ($targetUserRole === 'owner') {
                    echo json_encode(['success' => false, 'error' => 'You cannot remove an owner of the team.']);
                    exit;
                }
            }
        }

        // Prevent removing the last owner
        if ($targetUserRole === 'owner') {
            $members = $db->getTeamMembersWithRoles($teamId);
            $ownerCount = count(array_filter($members, fn($m) => $m['team_role'] === 'owner'));
            if ($ownerCount <= 1) {
                 echo json_encode(['success' => false, 'error' => 'Cannot remove the last owner of the team.']);
                 exit;
            }
        }
        
        if ($db->removeTeamMember($teamId, $userId)) {
            echo json_encode(['success' => true]);
        } else {
             echo json_encode(['success' => false, 'error' => 'Failed to remove member.']);
        }
        exit;
    }

    if ($action === 'update_role') {
        $teamId = (int)($_POST['team_id'] ?? 0);
        $userId = (int)($_POST['user_id'] ?? 0);
        $newRole = $_POST['role'] ?? 'member';

        if (!in_array($newRole, ['member', 'admin', 'owner'])) {
            echo json_encode(['success' => false, 'error' => 'Invalid role specified.']);
            exit;
        }

        $systemRole = $currentUser['role'] ?? 'data_analyst';

        // Add permission check
        if ($systemRole !== 'admin') {
            $currentUserRole = $db->getTeamMemberRole($teamId, $currentUser['id']);
            if (!in_array($currentUserRole, ['owner', 'admin'])) {
                echo json_encode(['success' => false, 'error' => 'You do not have permission to change member roles.']);
                exit;
            }
            $targetUserRole = $db->getTeamMemberRole($teamId, $userId);
            if ($targetUserRole === 'owner' && $currentUserRole !== 'owner') {
                echo json_encode(['success' => false, 'error' => 'You do not have permission to change an owner\'s role.']);
                exit;
            }
        }

        // Prevent demoting the last owner
        $currentTargetRole = $db->getTeamMemberRole($teamId, $userId);
        if ($currentTargetRole === 'owner' && $newRole !== 'owner') {
            $members = $db->getTeamMembersWithRoles($teamId);
            $ownerCount = count(array_filter($members, fn($m) => $m['team_role'] === 'owner'));
            if ($ownerCount <= 1) {
                 echo json_encode(['success' => false, 'error' => 'Cannot change the role of the last owner of the team.']);
                 exit;
            }
        }

        if ($db->updateTeamMemberRole($teamId, $userId, $newRole)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update member role.']);
        }
        exit;
    }

    if ($action === 'delete') {
        $teamId = (int)($_POST['team_id'] ?? 0);
        $systemRole = $currentUser['role'] ?? 'data_analyst';

        $team = $db->getTeamById($teamId);
        if (!$team) {
            echo json_encode(['success' => false, 'error' => 'Team not found.']);
            exit;
        }

        // Only system admins or the user who created the team can delete it
        if (!in_array($systemRole, ['admin']) && $team['created_by'] != $currentUser['id']) {
            echo json_encode(['success' => false, 'error' => 'You do not have permission to delete this team. Only the creator or a system admin can delete it.']);
            exit;
        }

        if ($db->deleteTeam($teamId)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to delete team.']);
        }
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'list') {
        $systemRole = $currentUser['role'] ?? 'data_analyst';
        $teams = $db->getTeamsForUser($currentUser['id'], $systemRole);
        echo json_encode(['success' => true, 'teams' => $teams]);
        exit;
    }
    
    if ($action === 'members') {
        $teamId = (int)($_GET['team_id'] ?? 0);
        
        $members = $db->getTeamMembersWithRoles($teamId);
        echo json_encode(['success' => true, 'members' => $members]);
        exit;
    }
    
    if ($action === 'search_users') {
        $query = $_GET['q'] ?? '';
        $teamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : null;
        $users = $db->searchUsers($query, null, $teamId);
        echo json_encode(['success' => true, 'users' => $users]);
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
