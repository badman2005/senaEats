<?php
require __DIR__ . '/lib.php';
$err = '';
if (isset($_GET['logout']) && hash_equals(csrf(), $_GET['t'] ?? '')) { session_destroy(); header('Location: auth.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(csrf(), $_POST['csrf'] ?? '')) { $err = 'Session expired. Reload and try again.'; }
    else {
        $pdo = db();
        $email = strtolower(clean($_POST['email'] ?? '', 150));
        $pass = (string)($_POST['password'] ?? '');
        if (($_POST['mode'] ?? '') === 'register') {
            $role = in_array($_POST['role'] ?? '', ['customer', 'vendor', 'courier'], true) ? $_POST['role'] : 'customer';
            $name = clean($_POST['name'] ?? '', 100); $phone = clean($_POST['phone'] ?? '', 20);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8 || $name === '' || !preg_match('/^\+?[0-9 ]{7,20}$/', $phone)) {
                $err = 'Enter a valid name, email, phone and a password of 8+ characters.';
            } else {
                try {
                    $pdo->beginTransaction();
                    $pdo->prepare('INSERT INTO users (role,name,email,phone,password_hash) VALUES (?,?,?,?,?)')
                        ->execute([$role, $name, $email, $phone, password_hash($pass, PASSWORD_DEFAULT)]);
                    $id = (int)$pdo->lastInsertId();
                    if ($role === 'vendor') {
                        $rn = clean($_POST['restaurant'] ?? '', 120) ?: $name . "'s Kitchen";
                        $pdo->prepare('INSERT INTO restaurants (owner_id,name,lat,lng) VALUES (?,?,?,?)')
                            ->execute([$id, $rn, DEFAULT_LAT, DEFAULT_LNG]);
                    }
                    $pdo->commit();
                    $row = ['id' => $id, 'role' => $role, 'name' => $name];
                } catch (PDOException $ex) { $pdo->rollBack(); $err = 'That email is already registered.'; }
            }
        } else {
            $s = $pdo->prepare('SELECT id,role,name,password_hash FROM users WHERE email=?'); $s->execute([$email]);
            $row = $s->fetch();
            if (!$row || !password_verify($pass, $row['password_hash'])) { $err = 'Wrong email or password.'; $row = null; }
        }
        if (!$err && !empty($row)) {
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int)$row['id'], 'role' => $row['role'], 'name' => $row['name']];
            header('Location: ' . home_for($row['role'])); exit;
        }
    }
}
page_head('Log in'); ?>
<div class="grid2">
<form method="post" class="card"><h2>Log in</h2>
<?php if ($err): ?><p class="err"><?= e($err) ?></p><?php endif; ?>
<input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="mode" value="login">
<label>Email <input type="email" name="email" required></label>
<label>Password <input type="password" name="password" required></label>
<button class="btn">Log in</button></form>
<form method="post" class="card"><h2>Create an account</h2>
<input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="mode" value="register">
<label>Full name <input name="name" required maxlength="100"></label>
<label>Email <input type="email" name="email" required></label>
<label>Phone (mobile money number) <input name="phone" required placeholder="+260 97 000 0000"></label>
<label>Password (8+ characters) <input type="password" name="password" minlength="8" required></label>
<label>I am a <select name="role" id="role"><?php $pr = $_GET['role'] ?? 'customer'; foreach (['customer' => 'Customer', 'vendor' => 'Restaurant / vendor', 'courier' => 'Delivery personnel'] as $k => $v): ?><option value="<?= $k ?>" <?= $pr === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></label>
<label id="rn" <?= ($_GET['role'] ?? '') === 'vendor' ? '' : 'hidden' ?>>Restaurant name <input name="restaurant" maxlength="120"></label>
<button class="btn">Create account</button></form></div>
<script>document.getElementById('role').onchange=e=>document.getElementById('rn').hidden=e.target.value!=='vendor';</script>
<?php page_foot();
