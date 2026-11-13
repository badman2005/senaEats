<?php
require __DIR__ . '/lib.php'; $u = require_role('vendor');
function back(string $m): never { header('Location: vendor.php?msg=' . urlencode($m)); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals(csrf(), $_POST['csrf'] ?? '')) back('Bad request');
$f = $_FILES['photo'] ?? null;
if (!$f || $f['error'] !== UPLOAD_ERR_OK || $f['size'] > 2 * 1024 * 1024) back('Choose an image under 2 MB');
$ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name'])] ?? null;   // real content check, not the client's claim
if (!$ext) back('Only JPG, PNG or WebP images');
$pdo = db(); $r = $pdo->prepare('SELECT id FROM restaurants WHERE owner_id=?'); $r->execute([$u['id']]); $rid = (int)$r->fetchColumn();
$c = $pdo->prepare('SELECT COUNT(*) FROM gallery_photos WHERE restaurant_id=?'); $c->execute([$rid]);
if (!$rid || $c->fetchColumn() >= 8) back('Gallery is full (8 photos)');
$name = bin2hex(random_bytes(12)) . '.' . $ext;
if (!move_uploaded_file($f['tmp_name'], __DIR__ . '/uploads/' . $name)) back('Upload failed. Is uploads/ writable?');
$pdo->prepare('INSERT INTO gallery_photos (restaurant_id,filename,caption) VALUES (?,?,?)')->execute([$rid, $name, clean($_POST['caption'] ?? '', 120)]);
back('Photo added');
