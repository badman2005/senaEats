<?php
require __DIR__ . '/lib.php';
$pdo = db(); $u = user(); $type = ($_GET['type'] ?? '') === 'courier' ? 'courier' : 'restaurant'; $id = (int)($_GET['id'] ?? 0);
function stars_html(?string $r): string { if ($r === null) return 'No ratings yet'; $n = (int)round((float)$r); return str_repeat('★', $n) . str_repeat('☆', 5 - $n) . ' ' . number_format((float)$r, 1); }
function heart_btn(PDO $pdo, ?array $u, string $type, int $id): string {
    if (!$u || $u['role'] !== 'customer') return '';
    $s = $pdo->prepare('SELECT 1 FROM favorites WHERE user_id=? AND target_type=? AND target_id=?'); $s->execute([$u['id'], $type, $id]); $on = (bool)$s->fetchColumn();
    return '<button class="fav ' . ($on ? 'on' : '') . '" data-fav="' . $type . '" data-id="' . $id . '" aria-label="Toggle favourite">' . ($on ? '♥' : '♡') . '</button>';
}
if ($type === 'restaurant') {
    $s = $pdo->prepare('SELECT r.*,u.phone AS owner_phone FROM restaurants r JOIN users u ON u.id=r.owner_id WHERE r.id=? AND r.is_active=1'); $s->execute([$id]);
    $p = $s->fetch(); if (!$p) { http_response_code(404); exit('Not found'); }
    $rt = $pdo->prepare('SELECT AVG(restaurant_rating) a, COUNT(*) n FROM reviews WHERE restaurant_id=?'); $rt->execute([$id]); $rt = $rt->fetch();
    $g = $pdo->prepare('SELECT filename,caption FROM gallery_photos WHERE restaurant_id=? ORDER BY id DESC'); $g->execute([$id]); $gal = $g->fetchAll();
    $m = $pdo->prepare('SELECT name,description,price,category FROM menu_items WHERE restaurant_id=? AND is_available=1 ORDER BY category,name'); $m->execute([$id]); $menu = $m->fetchAll();
    $rv = $pdo->prepare('SELECT v.restaurant_rating r,v.comment,v.created_at,u.name FROM reviews v JOIN users u ON u.id=v.customer_id WHERE v.restaurant_id=? ORDER BY v.id DESC LIMIT 5'); $rv->execute([$id]);
    page_head($p['name']); ?>
<div class="between"><h1><?= e($p['name']) ?></h1><?= heart_btn($pdo, $u, 'restaurant', $id) ?></div>
<p class="stars"><?= e(stars_html($rt['a'])) ?> <small>(<?= (int)$rt['n'] ?> reviews)</small></p>
<div class="grid2"><section class="card"><h2>About</h2><p><?= e($p['description'] ?: 'Local kitchen.') ?></p>
<p><b>Cuisine:</b> <?= e($p['cuisine']) ?><br><b>Address:</b> <?= e($p['address'] ?: 'Not listed') ?><br><b>Hours:</b> <?= e($p['hours'] ?: 'Not listed') ?><br>
<b>Phone:</b> <?= e($p['phone'] ?: $p['owner_phone']) ?></p><a class="btn" href="index.php?r=<?= $id ?>#catalog">Order from here</a></section>
<section class="card"><h2>Menu</h2><?php foreach ($menu as $i): ?><div class="item"><div><b><?= e($i['name']) ?></b><br><small><?= e($i['category']) ?>. <?= e($i['description']) ?></small></div><span>K<?= number_format((float)$i['price'], 2) ?></span></div><?php endforeach; if (!$menu) echo '<p class="muted">No dishes listed.</p>'; ?></section></div>
<?php if ($gal): ?><h2>Photos</h2><div class="gallery"><?php foreach ($gal as $ph): ?><img loading="lazy" src="uploads/<?= e($ph['filename']) ?>" alt="<?= e($ph['caption'] ?: 'Dish from ' . $p['name']) ?>"><?php endforeach; ?></div><?php endif; ?>
<h2>Recent reviews</h2><?php foreach ($rv->fetchAll() as $r): ?><div class="card"><span class="stars"><?= e(stars_html((string)$r['r'])) ?></span> <small><?= e(explode(' ', $r['name'])[0]) ?></small><p><?= e($r['comment']) ?></p></div><?php endforeach;
} else {
    $s = $pdo->prepare("SELECT u.id,u.name,u.created_at,cp.vehicle,cp.bio FROM users u LEFT JOIN courier_profiles cp ON cp.user_id=u.id WHERE u.id=? AND u.role='courier'"); $s->execute([$id]);
    $p = $s->fetch(); if (!$p) { http_response_code(404); exit('Not found'); }
    $d = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE courier_id=? AND status='completed'"); $d->execute([$id]);
    $rt = $pdo->prepare('SELECT AVG(courier_rating) a, COUNT(*) n FROM reviews WHERE courier_id=?'); $rt->execute([$id]); $rt = $rt->fetch();
    $rv = $pdo->prepare('SELECT v.courier_rating r,v.comment,u.name FROM reviews v JOIN users u ON u.id=v.customer_id WHERE v.courier_id=? ORDER BY v.id DESC LIMIT 8'); $rv->execute([$id]);
    page_head($p['name']); ?>
<div class="between"><h1><?= e($p['name']) ?></h1><?= heart_btn($pdo, $u, 'courier', $id) ?></div>
<div class="stats"><div class="stat"><b><?= (int)$d->fetchColumn() ?></b><small>Completed deliveries</small></div>
<div class="stat"><b class="stars"><?= e(stars_html($rt['a'])) ?></b><small><?= (int)$rt['n'] ?> customer ratings</small></div>
<div class="stat"><b><?= e(date('M Y', strtotime($p['created_at']))) ?></b><small>Riding with senaEats since</small></div></div>
<div class="card"><p><b>Vehicle:</b> <?= e($p['vehicle'] ?: 'Not listed') ?></p><p><?= e($p['bio'] ?: 'This rider has not added a bio yet.') ?></p></div>
<h2>Customer reviews</h2><?php foreach ($rv->fetchAll() as $r): ?><div class="card"><span class="stars"><?= e(stars_html((string)$r['r'])) ?></span> <small><?= e(explode(' ', $r['name'])[0]) ?></small><p><?= e($r['comment']) ?></p></div><?php endforeach;
}
page_foot(); ?></body></html>
