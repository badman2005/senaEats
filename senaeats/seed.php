<?php
// CLI only:  php seed.php   -> creates demo accounts (password: senaeats123) and sample menu.
if (PHP_SAPI !== 'cli') exit('CLI only');
require __DIR__ . '/db.php'; $pdo = db(); $h = password_hash('senaeats123', PASSWORD_DEFAULT);
$u = $pdo->prepare('INSERT IGNORE INTO users (role,name,email,phone,password_hash) VALUES (?,?,?,?,?)');
foreach ([['admin','Admin','admin@senaeats.test'],['customer','Demo Customer','customer@senaeats.test'],['courier','Demo Courier','courier@senaeats.test'],['vendor','Mama Chola','vendor@senaeats.test']] as $x)
    $u->execute([$x[0], $x[1], $x[2], '+260970000000', $h]);
$vid = $pdo->query("SELECT id FROM users WHERE email='vendor@senaeats.test'")->fetchColumn();
$pdo->prepare('INSERT IGNORE INTO restaurants (owner_id,name,cuisine,address,lat,lng) VALUES (?,?,?,?,?,?)')->execute([$vid, "Mama Chola's Kitchen", 'Zambian', 'Main Market', -14.4469, 28.4464]);
$rid = $pdo->query("SELECT id FROM restaurants WHERE owner_id=$vid")->fetchColumn();
if (!$pdo->query("SELECT COUNT(*) FROM menu_items WHERE restaurant_id=$rid")->fetchColumn()) {
    $m = $pdo->prepare('INSERT INTO menu_items (restaurant_id,name,description,price,category) VALUES (?,?,?,?,?)');
    foreach ([['Nshima & Chicken','Village chicken, rape, nshima',45,'Mains'],['Nshima & Kapenta','Fried kapenta, tomato relish',35,'Mains'],['Rice & Beef Stew','Slow-cooked beef',50,'Mains'],['Fritters (5)','Hot vitumbuwa',10,'Snacks'],['Fanta 500ml','Cold',12,'Drinks']] as $i)
        $m->execute([$rid, $i[0], $i[1], $i[2], $i[3]]);
}
$pdo->prepare("UPDATE restaurants SET phone='+260971111111',hours='Mon-Sat 08:00-20:00',description='Home-style Zambian meals cooked fresh every day.' WHERE id=?")->execute([$rid]);
$cid = $pdo->query("SELECT id FROM users WHERE email='courier@senaeats.test'")->fetchColumn();
$pdo->prepare('INSERT IGNORE INTO courier_profiles (user_id,vehicle,bio) VALUES (?,?,?)')->execute([$cid, 'Bicycle', 'Fast and friendly. I know every street in the compound.']);
echo "Seeded. Log in with admin@/customer@/courier@/vendor@senaeats.test, password senaeats123\n";
