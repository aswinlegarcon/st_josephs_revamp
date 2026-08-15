<?php
// N7: POST ?r=admins — admin-account management, OWNERS ONLY.
// admin_users stays OUT of the content registry (CLAUDE.md): this dedicated
// endpoint is the only web write-path, with its own whitelist of actions.
// Passwords are always server-generated temporaries (shown once, forced
// change on first login) — never chosen by the creator, never audited.
require __DIR__ . '/_bootstrap.php';

if (sj_admin_role() !== 'owner') {
    api_fail('Only owners can manage admin accounts', 403);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_fail('POST only', 405);
}

$in     = api_input();
$action = (string)($in['action'] ?? '');
$meId   = (int)$_SESSION['admin_id'];
$pdo    = db();

/** Row fetch + existence guard. */
function admin_row(int $id): array
{
    $st = db()->prepare('SELECT id, username, role FROM admin_users WHERE id = ?');
    $st->execute([$id]);
    $r = $st->fetch();
    if (!$r) {
        api_fail('No such account', 404);
    }
    return $r;
}

/** True when $id is the only remaining owner. */
function is_last_owner(int $id): bool
{
    $st = db()->prepare("SELECT COUNT(*) FROM admin_users WHERE role = 'owner' AND id <> ?");
    $st->execute([$id]);
    return (int)$st->fetchColumn() === 0;
}

switch ($action) {
    case 'list': {
        $rows = $pdo->query(
            'SELECT id, username, display_name, role, must_change_password,
                    last_login_at, locked_until, created_at
             FROM admin_users ORDER BY role, username'
        )->fetchAll();
        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'id'           => (int)$r['id'],
                'username'     => $r['username'],
                'display_name' => $r['display_name'],
                'role'         => $r['role'],
                'pending'      => (bool)$r['must_change_password'],
                'locked'       => $r['locked_until'] !== null && strtotime($r['locked_until']) > time(),
                'last_login'   => $r['last_login_at'],
                'is_me'        => (int)$r['id'] === $meId,
            ];
        }
        api_out(['items' => $items]);
    }

    case 'create': {
        $username = strtolower(trim((string)($in['username'] ?? '')));
        $display  = trim(strip_tags((string)($in['display_name'] ?? '')));
        $role     = (string)($in['role'] ?? 'editor');
        if (!preg_match('/^[a-z0-9][a-z0-9_.-]{2,29}$/', $username)) {
            api_fail('Username: 3–30 chars, lowercase letters/numbers/._- only');
        }
        if ($display === '' || mb_strlen($display) > 100) {
            api_fail('Display name is required (max 100)');
        }
        if (!in_array($role, ['owner', 'editor'], true)) {
            api_fail('Role must be owner or editor');
        }
        $temp = bin2hex(random_bytes(9)); // shown ONCE below; forced change on first login
        try {
            $pdo->prepare(
                'INSERT INTO admin_users (username, password_hash, display_name, role, must_change_password)
                 VALUES (?,?,?,?,1)'
            )->execute([$username, password_hash($temp, PASSWORD_DEFAULT), $display, $role]);
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                api_fail('That username already exists');
            }
            throw $e;
        }
        sj_audit('admin.create', 'admin_users', (int)$pdo->lastInsertId(), $username . ' (' . $role . ')');
        api_out(['id' => (int)$pdo->lastInsertId(), 'temp_password' => $temp]);
    }

    case 'reset': {
        $r = admin_row((int)($in['id'] ?? 0));
        $temp = bin2hex(random_bytes(9));
        $pdo->prepare(
            'UPDATE admin_users SET password_hash = ?, must_change_password = 1,
             failed_logins = 0, locked_until = NULL WHERE id = ?'
        )->execute([password_hash($temp, PASSWORD_DEFAULT), $r['id']]);
        sj_audit('admin.reset', 'admin_users', (int)$r['id'], $r['username']);
        api_out(['temp_password' => $temp]);
    }

    case 'unlock': {
        $r = admin_row((int)($in['id'] ?? 0));
        $pdo->prepare('UPDATE admin_users SET failed_logins = 0, locked_until = NULL WHERE id = ?')
            ->execute([$r['id']]);
        sj_audit('admin.unlock', 'admin_users', (int)$r['id'], $r['username']);
        api_out();
    }

    case 'role': {
        $r = admin_row((int)($in['id'] ?? 0));
        $role = (string)($in['role'] ?? '');
        if (!in_array($role, ['owner', 'editor'], true)) {
            api_fail('Role must be owner or editor');
        }
        if ((int)$r['id'] === $meId) {
            api_fail('You cannot change your own role');
        }
        if ($r['role'] === 'owner' && $role !== 'owner' && is_last_owner((int)$r['id'])) {
            api_fail('Cannot demote the last owner');
        }
        $pdo->prepare('UPDATE admin_users SET role = ? WHERE id = ?')->execute([$role, $r['id']]);
        sj_audit('admin.role', 'admin_users', (int)$r['id'], $r['username'] . ' → ' . $role);
        api_out();
    }

    case 'delete': {
        $r = admin_row((int)($in['id'] ?? 0));
        if ((int)$r['id'] === $meId) {
            api_fail('You cannot delete your own account');
        }
        if ($r['role'] === 'owner' && is_last_owner((int)$r['id'])) {
            api_fail('Cannot delete the last owner');
        }
        $pdo->prepare('DELETE FROM admin_users WHERE id = ?')->execute([$r['id']]);
        sj_audit('admin.delete', 'admin_users', (int)$r['id'], $r['username']);
        api_out();
    }
}
api_fail('Unknown action');
