<?php
// POST {action:list|attach|detach|reorder, owner_type, owner_id, role, …} —
// manage the polymorphic image_links collections (M1).
//
// owner_type resolves ONLY through Registry::ownerTypes() (SEC-01/09/11);
// the owner row and the image row must exist; attach/detach/reorder are
// transactional; every mutation is audited.
require __DIR__ . '/_bootstrap.php';

$in    = api_input();
$act   = (string)($in['action'] ?? '');
$type  = (string)($in['owner_type'] ?? '');
$oid   = (int)($in['owner_id'] ?? 0);
$role  = (string)($in['role'] ?? 'carousel');

$owners = \SJ\Content\Registry::ownerTypes();
if (!isset($owners[$type]) || $oid <= 0) {
    api_fail('Unknown owner');
}
if (!preg_match('/^[a-z0-9_-]{1,24}$/', $role)) {
    api_fail('Bad role');
}

// The owner row must exist (owner table comes from the whitelist, never input).
$ownerTable = $owners[$type];
$st = db()->prepare("SELECT COUNT(*) FROM `$ownerTable` WHERE id = ?");
$st->execute([$oid]);
if (!$st->fetchColumn()) {
    api_fail('Owner not found', 404);
}

/** Current links for this collection, with image data (ordered). */
function link_rows(string $type, int $oid, string $role): array
{
    $st = db()->prepare(
        'SELECT l.id AS link_id, l.image_id, l.position, ' . SJ_IMG_SELECT . '
           FROM image_links l JOIN images i ON i.id = l.image_id
          WHERE l.owner_type = ? AND l.owner_id = ? AND l.role = ?
          ORDER BY l.position, l.id'
    );
    $st->execute([$type, $oid, $role]);
    return array_map('repo_fold_image', $st->fetchAll());
}

switch ($act) {

case 'list':
    $rows = array_map(static function (array $r): array {
        return [
            'link_id'  => (int)$r['link_id'],
            'image_id' => (int)$r['image_id'],
            'position' => (int)$r['position'],
            'thumb'    => $r['image'] ? img_url($r['image'], 'gallery_tile') : null,
            'alt'      => $r['image']['alt_text'] ?? '',
        ];
    }, link_rows($type, $oid, $role));
    api_out(['links' => $rows]);
    // no break — api_out exits

case 'attach':
    $imageId = (int)($in['image_id'] ?? 0);
    $chk = db()->prepare('SELECT COUNT(*) FROM images WHERE id = ?');
    $chk->execute([$imageId]);
    if ($imageId <= 0 || !$chk->fetchColumn()) {
        api_fail('Image not found', 404);
    }
    db()->beginTransaction();
    try {
        $pos = db()->prepare('SELECT COALESCE(MAX(position) + 1, 0) FROM image_links WHERE owner_type = ? AND owner_id = ? AND role = ?');
        $pos->execute([$type, $oid, $role]);
        $next = (int)$pos->fetchColumn();
        db()->prepare('INSERT INTO image_links (owner_type, owner_id, role, image_id, position) VALUES (?,?,?,?,?)')
            ->execute([$type, $oid, $role, $imageId, $next]);
        $linkId = (int)db()->lastInsertId();
        db()->commit();
    } catch (\PDOException $e) {
        db()->rollBack();
        if ((int)$e->errorInfo[1] === 1062) { // uq_member — already attached
            api_fail('That photo is already in this collection');
        }
        throw $e;
    }
    sj_audit('link.attach', $type, $oid, "role=$role image=$imageId");
    api_out(['link_id' => $linkId, 'position' => $next]);

case 'detach':
    $linkId = (int)($in['link_id'] ?? 0);
    $st = db()->prepare('DELETE FROM image_links WHERE id = ? AND owner_type = ? AND owner_id = ? AND role = ?');
    $st->execute([$linkId, $type, $oid, $role]);
    if (!$st->rowCount()) {
        api_fail('Link not found', 404);
    }
    sj_audit('link.detach', $type, $oid, "role=$role link=$linkId");
    api_out();

case 'reorder':
    $ids = $in['link_ids'] ?? null;
    if (!is_array($ids) || !$ids || $ids !== array_values($ids)) {
        api_fail('link_ids must be a list');
    }
    $ids = array_map('intval', $ids);
    // Every id must belong to THIS collection — no cross-collection meddling.
    $inSql = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare("SELECT COUNT(*) FROM image_links WHERE id IN ($inSql) AND owner_type = ? AND owner_id = ? AND role = ?");
    $st->execute([...$ids, $type, $oid, $role]);
    if ((int)$st->fetchColumn() !== count(array_unique($ids))) {
        api_fail('link_ids do not match this collection');
    }
    db()->beginTransaction();
    try {
        $up = db()->prepare('UPDATE image_links SET position = ? WHERE id = ?');
        foreach ($ids as $i => $id) {
            $up->execute([$i, $id]);
        }
        db()->commit();
    } catch (\Throwable $e) {
        db()->rollBack();
        throw $e;
    }
    sj_audit('link.reorder', $type, $oid, "role=$role n=" . count($ids));
    api_out();

default:
    api_fail('Unknown action');
}
