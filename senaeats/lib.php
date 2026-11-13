<?php
declare(strict_types=1);
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
require_once __DIR__ . '/db.php';

const DEFAULT_LAT = -14.4469;  // map centre (Kabwe); change for your township
const DEFAULT_LNG = 28.4464;

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function clean(string $s, int $max = 255): string { return mb_substr(trim(strip_tags($s)), 0, $max); }
function user(): ?array { return $_SESSION['user'] ?? null; }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function json_out($d, int $code = 200): never {
    http_response_code($code); header('Content-Type: application/json'); echo json_encode($d); exit;
}
function require_role(string ...$roles): array {
    $u = user();
    if (!$u || !in_array($u['role'], $roles, true)) { header('Location: auth.php'); exit; }
    return $u;
}
function home_for(string $role): string {
    return ['customer' => 'index.php', 'vendor' => 'vendor.php', 'courier' => 'courier.php', 'admin' => 'admin.php'][$role];
}
function km(float $a, float $b, float $c, float $d): float {   // haversine
    $r = 6371; $p = M_PI / 180;
    $x = sin(($c - $a) * $p / 2) ** 2 + cos($a * $p) * cos($c * $p) * sin(($d - $b) * $p / 2) ** 2;
    return 2 * $r * asin(sqrt($x));
}
function page_head(string $title, bool $leaflet = false): void {
    $u = user(); ?>
<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf" content="<?= e(csrf()) ?>">
<title><?= e($title) ?> | senaEats</title>
<link rel="stylesheet" href="assets/style.css">
<?php if ($leaflet): ?><link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script><?php endif; ?>
</head><body>
<header class="top"><a class="brand" href="index.php">sena<b>Eats</b></a>
<nav><?php if ($u): ?><span><?= e($u['name']) ?> (<?= e($u['role']) ?>)</span>
<a href="<?= e(home_for($u['role'])) ?>">Home</a>
<a href="auth.php?logout=1&t=<?= e(csrf()) ?>">Log out</a>
<?php else: ?><a href="auth.php">Log in</a><?php endif; ?></nav></header><main>
<?php }
function page_foot(): void { echo '</main><script src="assets/app.js"></script>'; }
