<?php
require __DIR__ . '/lib.php'; require_role('customer', 'courier', 'vendor', 'admin');
page_head('Track order', true); $id = (int)($_GET['id'] ?? 0); ?>
<h1>Order #<?= $id ?></h1>
<div class="grid2"><div id="map" class="map"></div>
<section class="card"><h2 id="st">Loading...</h2><p id="info" class="muted"></p><div id="codeBox" hidden>
<p>Give this code to your courier only when you have your food:</p><div class="code" id="code"></div></div></section></div>
<?php page_foot(); ?>
<script>
const id = <?= $id ?>, $ = i => document.getElementById(i);
let map, mc, mr, md, mk, line, fitted = false;
const icon = c => L.divIcon({ className: '', html: `<div class="pin" style="background:${c}"></div>`, iconSize: [18, 18] });
async function tick() {
  try { const t = await api('track&order_id=' + id);
    if (!map) { map = L.map('map').setView([t.r_lat, t.r_lng], 14);
      L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
      mr = L.marker([t.r_lat, t.r_lng], { icon: icon('#0f5b3a') }).addTo(map).bindPopup(esc(t.restaurant));
      md = L.marker([t.dest_lat, t.dest_lng], { icon: icon('#c8382b') }).addTo(map).bindPopup('Delivery point: ' + esc(t.address));
      line = L.polyline([[t.r_lat, t.r_lng], [t.dest_lat, t.dest_lng]], { dashArray: '6 8' }).addTo(map);
      map.fitBounds(line.getBounds().pad(0.3)); }
    if (t.courier) { const p = [t.courier.lat, t.courier.lng];
      mc ? mc.setLatLng(p) : mc = L.marker(p, { icon: icon('#f5a900') }).addTo(map).bindPopup('Courier'); }
    $('st').textContent = LABEL[t.status];
    $('info').textContent = `${t.restaurant}, ${money(t.total)} via ${t.method.replace('_', ' ')}. Escrow: ${t.escrow}.`;
    $('codeBox').hidden = !(t.code && t.status !== 'completed' && t.status !== 'rejected');
    if (t.code) $('code').textContent = t.code;
    if (t.role === 'courier' && navigator.geolocation && t.status === 'out_for_delivery')
      navigator.geolocation.getCurrentPosition(p => api('courier_loc', { lat: p.coords.latitude, lng: p.coords.longitude }).catch(() => {}));
  } catch (e) { $('st').textContent = e.message; }
}
tick(); setInterval(tick, 5000);
</script></body></html>
