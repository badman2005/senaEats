<?php
require __DIR__ . '/lib.php'; require_role('admin'); page_head('Admin'); ?>
<h1>Escrow ledger</h1><div class="card"><table id="t"></table></div>
<?php page_foot(); ?>
<script>api('admin_escrow').then(r => document.getElementById('t').innerHTML = '<tr><th>Order.</th><th>Ref</th><th>Held</th><th>Vendor</th><th>Courier</th><th>Fee</th><th>Escrow</th><th>Order</th></tr>' +
 r.map(x => `<tr><td><a href="track.php?id=${x.order_id}">#${x.order_id}</a></td><td>${esc(x.reference)}</td><td>${money(x.amount_held)}</td><td>${money(x.vendor_amount)}</td><td>${money(x.courier_amount)}</td><td>${money(x.platform_fee)}</td><td>${x.status}</td><td>${x.order_status}</td></tr>`).join(''));</script></body></html>
