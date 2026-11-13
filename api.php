<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';
$a = $_GET['a'] ?? '';
$in = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals(csrf(), $_SERVER['HTTP_X_CSRF'] ?? '')) json_out(['error' => 'Bad CSRF token'], 403);
$u = user(); $pdo = db();
function need(string ...$r): array { global $u; if (!$u || !in_array($u['role'], $r, true)) json_out(['error' => 'Forbidden'], 403); return $u; }
function my_restaurant(PDO $pdo, int $uid): array {
    $s = $pdo->prepare('SELECT * FROM restaurants WHERE owner_id=?'); $s->execute([$uid]);
    return $s->fetch() ?: json_out(['error' => 'No restaurant'], 404);
}
function with_items(PDO $pdo, array $orders): array {
    $s = $pdo->prepare('SELECT item_name,qty,unit_price FROM order_items WHERE order_id=?');
    foreach ($orders as &$o) { $s->execute([$o['id']]); $o['items'] = $s->fetchAll(); }
    return $orders;
}
function settle(PDO $pdo, int $oid, string $status): void {   // escrow release / refund (call inside a transaction)
    $pdo->prepare('UPDATE escrow_transactions SET status=?, settled_at=NOW() WHERE order_id=? AND status="held"')->execute([$status, $oid]);
}

function wallet(PDO $pdo, array $u): array {   // escrow-derived balances for vendor / courier
    if ($u['role'] === 'vendor') { $col = 'e.vendor_amount'; $j = 'JOIN restaurants r ON r.id=o.restaurant_id'; $w = 'r.owner_id=?'; }
    else { $col = 'e.courier_amount'; $j = ''; $w = 'o.courier_id=?'; }
    $s = $pdo->prepare("SELECT e.status, COALESCE(SUM($col),0) t FROM escrow_transactions e JOIN orders o ON o.id=e.order_id $j WHERE $w GROUP BY e.status");
    $s->execute([$u['id']]); $t = array_column($s->fetchAll(), 't', 'status');
    $x = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE user_id=?'); $x->execute([$u['id']]);
    $wd = (float)$x->fetchColumn(); $rel = (float)($t['released'] ?? 0);
    return ['held' => (float)($t['held'] ?? 0), 'released' => $rel, 'withdrawn' => $wd, 'available' => round($rel - $wd, 2)];
}

try { switch ($a) {

case 'restaurants':
    json_out($pdo->query('SELECT id,name,cuisine,address,lat,lng FROM restaurants WHERE is_active=1 ORDER BY name')->fetchAll());

case 'menu':
    $s = $pdo->prepare('SELECT id,name,description,price,category FROM menu_items WHERE restaurant_id=? AND is_available=1 ORDER BY category,name');
    $s->execute([(int)($_GET['restaurant_id'] ?? 0)]); json_out($s->fetchAll());

case 'place_order':
    need('customer');
    $rid = (int)($in['restaurant_id'] ?? 0); $items = $in['items'] ?? [];
    $addr = clean((string)($in['address'] ?? '')); $phone = clean((string)($in['phone'] ?? ''), 20);
    $lat = (float)($in['lat'] ?? 0); $lng = (float)($in['lng'] ?? 0);
    $method = in_array($in['method'] ?? '', ['mobile_money', 'cash'], true) ? $in['method'] : 'mobile_money';
    if (!is_array($items) || !$items || $addr === '' || abs($lat) > 90 || abs($lng) > 180 || ($lat == 0 && $lng == 0) || !preg_match('/^\+?[0-9 ]{7,20}$/', $phone))
        json_out(['error' => 'Add items, a phone number, an address and pick your location on the map.'], 422);
    $r = $pdo->prepare('SELECT lat,lng FROM restaurants WHERE id=? AND is_active=1'); $r->execute([$rid]);
    if (!($rest = $r->fetch())) json_out(['error' => 'Restaurant unavailable'], 422);
    $q = $pdo->prepare('SELECT id,name,price FROM menu_items WHERE id=? AND restaurant_id=? AND is_available=1');
    $sub = 0.0; $lines = [];
    foreach ($items as $it) {
        $qty = max(1, min(20, (int)($it['qty'] ?? 1)));
        $q->execute([(int)($it['id'] ?? 0), $rid]);
        if (!($m = $q->fetch())) json_out(['error' => 'An item is no longer available'], 422);
        $sub += $m['price'] * $qty; $lines[] = [$m['id'], $m['name'], $m['price'], $qty];
    }
    $sub = round($sub, 2);
    $fee = round(10 + 2 * km((float)$rest['lat'], (float)$rest['lng'], $lat, $lng), 2);   // K10 + K2/km
    $total = round($sub + $fee, 2); $platform = round($sub * 0.10, 2);
    $code = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO orders (customer_id,restaurant_id,subtotal,delivery_fee,total,delivery_address,dest_lat,dest_lng,contact_phone,delivery_code) VALUES (?,?,?,?,?,?,?,?,?,?)')
        ->execute([$u['id'], $rid, $sub, $fee, $total, $addr, $lat, $lng, $phone, $code]);
    $oid = (int)$pdo->lastInsertId();
    $li = $pdo->prepare('INSERT INTO order_items (order_id,menu_item_id,item_name,unit_price,qty) VALUES (?,?,?,?,?)');
    foreach ($lines as $l) $li->execute([$oid, $l[0], $l[1], $l[2], $l[3]]);
    // Funds are now HELD in escrow (simulated mobile-money debit / cash deposit).
    $pdo->prepare('INSERT INTO escrow_transactions (order_id,method,reference,amount_held,vendor_amount,courier_amount,platform_fee) VALUES (?,?,?,?,?,?,?)')
        ->execute([$oid, $method, 'SE' . strtoupper(bin2hex(random_bytes(4))), $total, round($sub - $platform, 2), $fee, $platform]);
    $pdo->commit();
    json_out(['order_id' => $oid, 'total' => $total, 'delivery_fee' => $fee]);

case 'my_orders':
    need('customer');
    $s = $pdo->prepare('SELECT o.id,o.status,o.total,o.created_at,r.name AS restaurant,e.status AS escrow FROM orders o JOIN restaurants r ON r.id=o.restaurant_id JOIN escrow_transactions e ON e.order_id=o.id WHERE o.customer_id=? ORDER BY o.id DESC LIMIT 20');
    $s->execute([$u['id']]); json_out($s->fetchAll());

case 'vendor_orders':
    need('vendor'); $r = my_restaurant($pdo, $u['id']);
    $s = $pdo->prepare("SELECT o.id,o.status,o.total,o.subtotal,o.created_at,c.name AS customer FROM orders o JOIN users c ON c.id=o.customer_id WHERE o.restaurant_id=? AND o.status IN ('pending','accepted','cooking','ready','out_for_delivery') ORDER BY o.id");
    $s->execute([$r['id']]); json_out(with_items($pdo, $s->fetchAll()));

case 'vendor_update':   // pending->accepted|rejected, accepted->cooking, cooking->ready
    need('vendor'); $r = my_restaurant($pdo, $u['id']);
    $oid = (int)($in['order_id'] ?? 0); $to = (string)($in['status'] ?? '');
    $allowed = ['pending' => ['accepted', 'rejected'], 'accepted' => ['cooking'], 'cooking' => ['ready']];
    $pdo->beginTransaction();
    $s = $pdo->prepare('SELECT status FROM orders WHERE id=? AND restaurant_id=? FOR UPDATE'); $s->execute([$oid, $r['id']]);
    $cur = $s->fetchColumn();
    if (!$cur || !in_array($to, $allowed[$cur] ?? [], true)) { $pdo->rollBack(); json_out(['error' => 'Invalid status change'], 422); }
    $pdo->prepare('UPDATE orders SET status=? WHERE id=?')->execute([$to, $oid]);
    if ($to === 'rejected') settle($pdo, $oid, 'refunded');   // customer refunded
    $pdo->commit(); json_out(['ok' => true]);

case 'menu_all':
    need('vendor'); $r = my_restaurant($pdo, $u['id']);
    $s = $pdo->prepare('SELECT id,name,price,category,is_available FROM menu_items WHERE restaurant_id=? ORDER BY category,name'); $s->execute([$r['id']]);
    json_out(['restaurant' => $r, 'items' => $s->fetchAll()]);

case 'add_item':
    need('vendor'); $r = my_restaurant($pdo, $u['id']);
    $name = clean((string)($in['name'] ?? ''), 120); $price = round((float)($in['price'] ?? 0), 2);
    if ($name === '' || $price <= 0 || $price > 100000) json_out(['error' => 'Enter a name and a price above 0'], 422);
    $pdo->prepare('INSERT INTO menu_items (restaurant_id,name,description,price,category) VALUES (?,?,?,?,?)')
        ->execute([$r['id'], $name, clean((string)($in['description'] ?? '')), $price, clean((string)($in['category'] ?? 'Mains'), 60) ?: 'Mains']);
    json_out(['ok' => true]);

case 'toggle_item':
    need('vendor'); $r = my_restaurant($pdo, $u['id']);
    $pdo->prepare('UPDATE menu_items SET is_available=1-is_available WHERE id=? AND restaurant_id=?')->execute([(int)($in['id'] ?? 0), $r['id']]);
    json_out(['ok' => true]);

case 'set_location':   // vendor sets restaurant pin
    need('vendor'); $lat = (float)($in['lat'] ?? 0); $lng = (float)($in['lng'] ?? 0);
    if (abs($lat) > 90 || abs($lng) > 180) json_out(['error' => 'Bad coordinates'], 422);
    $pdo->prepare('UPDATE restaurants SET lat=?,lng=? WHERE owner_id=?')->execute([$lat, $lng, $u['id']]);
    json_out(['ok' => true]);

case 'courier_board':
    need('courier');
    $base = 'SELECT o.id,o.status,o.delivery_address,o.delivery_fee,r.name AS restaurant,r.address AS pickup FROM orders o JOIN restaurants r ON r.id=o.restaurant_id ';
    $s = $pdo->query($base . "WHERE o.status='ready' AND o.courier_id IS NULL ORDER BY o.id");
    $m = $pdo->prepare($base . "WHERE o.courier_id=? AND o.status='out_for_delivery' ORDER BY o.id"); $m->execute([$u['id']]);
    json_out(['available' => $s->fetchAll(), 'mine' => $m->fetchAll()]);

case 'courier_claim':   // atomic: only one courier can win
    need('courier');
    $s = $pdo->prepare("UPDATE orders SET courier_id=?, status='out_for_delivery' WHERE id=? AND status='ready' AND courier_id IS NULL");
    $s->execute([$u['id'], (int)($in['order_id'] ?? 0)]);
    $s->rowCount() ? json_out(['ok' => true]) : json_out(['error' => 'Already taken'], 409);

case 'courier_loc':
    need('courier'); $lat = (float)($in['lat'] ?? 0); $lng = (float)($in['lng'] ?? 0);
    if (abs($lat) > 90 || abs($lng) > 180) json_out(['error' => 'Bad coordinates'], 422);
    $pdo->prepare('INSERT INTO courier_locations (courier_id,lat,lng) VALUES (?,?,?) ON DUPLICATE KEY UPDATE lat=VALUES(lat),lng=VALUES(lng)')->execute([$u['id'], $lat, $lng]);
    json_out(['ok' => true]);

case 'verify':   // 4-digit handshake -> completes order and releases escrow
    need('courier'); $oid = (int)($in['order_id'] ?? 0); $code = (string)($in['code'] ?? '');
    $pdo->beginTransaction();
    $s = $pdo->prepare("SELECT delivery_code,code_attempts FROM orders WHERE id=? AND courier_id=? AND status='out_for_delivery' FOR UPDATE");
    $s->execute([$oid, $u['id']]);
    if (!($o = $s->fetch())) { $pdo->rollBack(); json_out(['error' => 'Order not found'], 404); }
    if ($o['code_attempts'] >= 5) { $pdo->rollBack(); json_out(['error' => 'Too many wrong codes. Contact support.'], 423); }
    if (!preg_match('/^\d{4}$/', $code) || !hash_equals($o['delivery_code'], $code)) {
        $pdo->prepare('UPDATE orders SET code_attempts=code_attempts+1 WHERE id=?')->execute([$oid]); $pdo->commit();
        json_out(['error' => 'Wrong code. ' . (4 - $o['code_attempts']) . ' tries left.'], 422);
    }
    $pdo->prepare("UPDATE orders SET status='completed', completed_at=NOW() WHERE id=?")->execute([$oid]);
    settle($pdo, $oid, 'released');   // vendor + courier paid out
    $pdo->commit(); json_out(['ok' => true]);

case 'track':
    $oid = (int)($_GET['order_id'] ?? 0); if (!$u) json_out(['error' => 'Login required'], 401);
    $s = $pdo->prepare('SELECT o.*,r.name AS restaurant,r.lat AS r_lat,r.lng AS r_lng,r.owner_id,e.status AS escrow,e.method FROM orders o JOIN restaurants r ON r.id=o.restaurant_id JOIN escrow_transactions e ON e.order_id=o.id WHERE o.id=?');
    $s->execute([$oid]); $o = $s->fetch();
    $ok = $o && ($u['role'] === 'admin' || (int)$o['customer_id'] === $u['id'] || (int)$o['courier_id'] === $u['id'] || (int)$o['owner_id'] === $u['id']);
    if (!$ok) json_out(['error' => 'Not found'], 404);
    $c = null;
    if ($o['courier_id']) { $l = $pdo->prepare('SELECT lat,lng,updated_at FROM courier_locations WHERE courier_id=?'); $l->execute([$o['courier_id']]); $c = $l->fetch() ?: null; }
    json_out(['id' => $o['id'], 'status' => $o['status'], 'escrow' => $o['escrow'], 'method' => $o['method'], 'total' => $o['total'],
        'restaurant' => $o['restaurant'], 'r_lat' => (float)$o['r_lat'], 'r_lng' => (float)$o['r_lng'],
        'dest_lat' => (float)$o['dest_lat'], 'dest_lng' => (float)$o['dest_lng'], 'address' => $o['delivery_address'],
        'courier' => $c, 'role' => $u['role'],
        'code' => (int)$o['customer_id'] === $u['id'] ? $o['delivery_code'] : null]);   // ONLY the customer sees the code

case 'admin_escrow':
    need('admin');
    json_out($pdo->query('SELECT e.*,o.status AS order_status FROM escrow_transactions e JOIN orders o ON o.id=e.order_id ORDER BY e.id DESC LIMIT 100')->fetchAll());

case 'catalog':
    json_out($pdo->query('SELECT m.id,m.name,m.description,m.price,m.category,r.id AS rid,r.name AS rname FROM menu_items m JOIN restaurants r ON r.id=m.restaurant_id WHERE m.is_available=1 AND r.is_active=1 ORDER BY r.name,m.category,m.name LIMIT 500')->fetchAll());

case 'providers':
    $s = $pdo->prepare("SELECT r.id,r.name,r.cuisine,r.address,r.hours,
      (SELECT ROUND(AVG(restaurant_rating),1) FROM reviews v WHERE v.restaurant_id=r.id) AS rating,
      (SELECT COUNT(*) FROM favorites f WHERE f.user_id=? AND f.target_type='restaurant' AND f.target_id=r.id) AS fav
      FROM restaurants r WHERE r.is_active=1 ORDER BY r.name");
    $s->execute([$u['id'] ?? 0]); json_out($s->fetchAll());

case 'couriers':
    $s = $pdo->prepare("SELECT u.id,u.name,cp.vehicle,
      (SELECT COUNT(*) FROM orders o WHERE o.courier_id=u.id AND o.status='completed') AS deliveries,
      (SELECT ROUND(AVG(courier_rating),1) FROM reviews v WHERE v.courier_id=u.id) AS rating,
      (SELECT COUNT(*) FROM favorites f WHERE f.user_id=? AND f.target_type='courier' AND f.target_id=u.id) AS fav
      FROM users u LEFT JOIN courier_profiles cp ON cp.user_id=u.id WHERE u.role='courier' ORDER BY deliveries DESC, u.name LIMIT 12");
    $s->execute([$u['id'] ?? 0]); json_out($s->fetchAll());

case 'fav_toggle':
    need('customer'); $t = $in['type'] ?? ''; $id = (int)($in['id'] ?? 0);
    if (!in_array($t, ['restaurant', 'courier'], true)) json_out(['error' => 'Bad type'], 422);
    $c = $pdo->prepare($t === 'restaurant' ? 'SELECT 1 FROM restaurants WHERE id=?' : "SELECT 1 FROM users WHERE id=? AND role='courier'");
    $c->execute([$id]); if (!$c->fetchColumn()) json_out(['error' => 'Not found'], 404);
    $d = $pdo->prepare('DELETE FROM favorites WHERE user_id=? AND target_type=? AND target_id=?'); $d->execute([$u['id'], $t, $id]);
    if ($d->rowCount()) json_out(['fav' => false]);
    $pdo->prepare('INSERT IGNORE INTO favorites (user_id,target_type,target_id) VALUES (?,?,?)')->execute([$u['id'], $t, $id]);
    json_out(['fav' => true]);

case 'my_favs':
    need('customer');
    $r = $pdo->prepare("SELECT r.id,r.name,r.cuisine FROM favorites f JOIN restaurants r ON r.id=f.target_id WHERE f.user_id=? AND f.target_type='restaurant'"); $r->execute([$u['id']]);
    $c = $pdo->prepare("SELECT u.id,u.name FROM favorites f JOIN users u ON u.id=f.target_id WHERE f.user_id=? AND f.target_type='courier'"); $c->execute([$u['id']]);
    json_out(['restaurants' => $r->fetchAll(), 'couriers' => $c->fetchAll()]);

case 'orders_full':
    need('customer');
    $s = $pdo->prepare('SELECT o.id,o.status,o.total,o.delivery_fee,o.created_at,o.delivery_code,r.name AS restaurant,o.courier_id,c.name AS courier,e.status AS escrow,e.method,
      (SELECT COUNT(*) FROM reviews v WHERE v.order_id=o.id) AS reviewed
      FROM orders o JOIN restaurants r ON r.id=o.restaurant_id LEFT JOIN users c ON c.id=o.courier_id JOIN escrow_transactions e ON e.order_id=o.id
      WHERE o.customer_id=? ORDER BY o.id DESC LIMIT 50');
    $s->execute([$u['id']]); $rows = $s->fetchAll();
    foreach ($rows as &$o) if (in_array($o['status'], ['completed', 'rejected'], true)) $o['delivery_code'] = null;   // code only while active
    json_out($rows);

case 'review':
    need('customer'); $oid = (int)($in['order_id'] ?? 0); $rr = (int)($in['restaurant_rating'] ?? 0); $cr = (int)($in['courier_rating'] ?? 0);
    if ($rr < 1 || $rr > 5 || $cr < 1 || $cr > 5) json_out(['error' => 'Choose ratings from 1 to 5'], 422);
    $s = $pdo->prepare("SELECT restaurant_id,courier_id FROM orders WHERE id=? AND customer_id=? AND status='completed'"); $s->execute([$oid, $u['id']]);
    if (!($o = $s->fetch())) json_out(['error' => 'Only delivered orders can be reviewed'], 422);
    try {
        $pdo->prepare('INSERT INTO reviews (order_id,customer_id,restaurant_id,courier_id,restaurant_rating,courier_rating,comment) VALUES (?,?,?,?,?,?,?)')
            ->execute([$oid, $u['id'], $o['restaurant_id'], $o['courier_id'], $rr, $cr, clean((string)($in['comment'] ?? ''), 500)]);
    } catch (PDOException $x) { json_out(['error' => 'You already reviewed this order'], 409); }
    json_out(['ok' => true]);

case 'save_profile':
    need('vendor'); $ph = clean((string)($in['phone'] ?? ''), 20);
    if ($ph !== '' && !preg_match('/^\+?[0-9 ]{7,20}$/', $ph)) json_out(['error' => 'Invalid phone number'], 422);
    $pdo->prepare('UPDATE restaurants SET cuisine=?,address=?,phone=?,hours=?,description=? WHERE owner_id=?')
        ->execute([clean((string)($in['cuisine'] ?? 'Local'), 60) ?: 'Local', clean((string)($in['address'] ?? '')), $ph, clean((string)($in['hours'] ?? ''), 80), clean((string)($in['description'] ?? ''), 500), $u['id']]);
    json_out(['ok' => true]);

case 'courier_profile':
    need('courier'); $s = $pdo->prepare('SELECT vehicle,bio FROM courier_profiles WHERE user_id=?'); $s->execute([$u['id']]);
    json_out($s->fetch() ?: ['vehicle' => '', 'bio' => '']);

case 'save_courier_profile':
    need('courier');
    $pdo->prepare('INSERT INTO courier_profiles (user_id,vehicle,bio) VALUES (?,?,?) ON DUPLICATE KEY UPDATE vehicle=VALUES(vehicle),bio=VALUES(bio)')
        ->execute([$u['id'], clean((string)($in['vehicle'] ?? ''), 60), clean((string)($in['bio'] ?? ''), 300)]);
    json_out(['ok' => true]);

case 'wallet':
    need('vendor', 'courier'); $w = wallet($pdo, $u);
    $s = $pdo->prepare('SELECT amount,phone,created_at FROM withdrawals WHERE user_id=? ORDER BY id DESC LIMIT 5'); $s->execute([$u['id']]);
    $w['recent'] = $s->fetchAll(); json_out($w);

case 'withdraw':   // only RELEASED escrow (delivery confirmed by code) can ever be withdrawn
    need('vendor', 'courier'); $amt = round((float)($in['amount'] ?? 0), 2); $ph = clean((string)($in['phone'] ?? ''), 20);
    if ($amt <= 0 || !preg_match('/^\+?[0-9 ]{7,20}$/', $ph)) json_out(['error' => 'Enter an amount and a valid mobile money number'], 422);
    $pdo->beginTransaction();
    $l = $pdo->prepare('SELECT id FROM users WHERE id=? FOR UPDATE'); $l->execute([$u['id']]); $l->fetch();   // serialise concurrent withdrawals
    $w = wallet($pdo, $u);
    if ($amt > $w['available']) { $pdo->rollBack(); json_out(['error' => 'Available to withdraw: K' . number_format($w['available'], 2)], 422); }
    $pdo->prepare('INSERT INTO withdrawals (user_id,amount,phone) VALUES (?,?,?)')->execute([$u['id'], $amt, $ph]);
    $pdo->commit(); json_out(['ok' => true]);

default: json_out(['error' => 'Unknown action'], 404);
} } catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($ex->getMessage()); json_out(['error' => 'Server error'], 500);
}
