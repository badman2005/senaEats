<?php
require __DIR__ . '/lib.php'; require_role('vendor');
page_head('Kitchen', true); ?>
<h1>Kitchen dashboard</h1>
<div class="grid2">
<section><h2>Incoming orders</h2><div id="queue"></div></section>
<section class="card"><h2>Menu</h2>
<form id="add"><input name="name" placeholder="Dish name" required><input name="price" type="number" step="0.01" min="0.01" placeholder="Price (K)" required>
<input name="category" placeholder="Category (e.g. Mains)"><input name="description" placeholder="Short description"><button class="btn">Add dish</button></form>
<div id="items"></div>
<h3>Restaurant location</h3><p class="muted">Tap the map to place your pin. Customers' delivery fees are based on it.</p>
<div id="lm" class="map small"></div><button class="btn" id="saveLoc">Save location</button></section></div>
<div class="grid2"><section class="card"><h2>Restaurant profile</h2><form id="prof">
<label>Cuisine <input name="cuisine" maxlength="60"></label><label>Address <input name="address" maxlength="255"></label>
<label>Contact phone <input name="phone" maxlength="20"></label><label>Opening hours <input name="hours" maxlength="80" placeholder="Mon-Sat 08:00-20:00"></label>
<label>About your kitchen <textarea name="description" maxlength="500" rows="3"></textarea></label><button class="btn">Save profile</button></form>
<h3>Photo gallery</h3><form action="upload.php" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required><input name="caption" maxlength="120" placeholder="Caption (optional)"><button class="btn">Upload photo (max 2 MB)</button></form></section>
<section class="card" id="wallet"></section></div>
<?php page_foot(); ?>
<script>
const $ = id => document.getElementById(id);
const NEXT = { pending: [['accepted', 'Accept'], ['rejected', 'Reject']], accepted: [['cooking', 'Start cooking']], cooking: [['ready', 'Mark ready']] };
async function loadQ() {
  try { const o = await api('vendor_orders');
    $('queue').innerHTML = o.map(x => `<div class="card"><b>#${x.id}</b> ${esc(x.customer)} <span class="tag">${LABEL[x.status]}</span>
      <ul>${x.items.map(i => `<li>${i.qty} x ${esc(i.item_name)}</li>`).join('')}</ul><b>${money(x.subtotal)}</b><div>
      ${(NEXT[x.status] || []).map(([s, l]) => `<button class="btn sm ${s === 'rejected' ? 'bad' : ''}" data-o="${x.id}" data-s="${s}">${l}</button>`).join('')}</div></div>`).join('') || '<p class="muted">No active orders. New orders appear here automatically.</p>';
  } catch (e) { toast(e.message); }
}
$('queue').onclick = async e => { const d = e.target.dataset; if (!d.o) return;
  try { await api('vendor_update', { order_id: +d.o, status: d.s }); loadQ(); } catch (x) { toast(x.message); } };
async function loadMenu() {
  const d = await api('menu_all'); window.R = d.restaurant;
  $('items').innerHTML = d.items.map(i => `<div class="item"><span>${esc(i.name)} ${money(i.price)}</span><button class="btn sm" data-t="${i.id}">${i.is_available == 1 ? 'Available' : 'Sold out'}</button></div>`).join('');
  if (!window.M) { M = L.map('lm').setView([R.lat, R.lng], 15);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(M);
    P = L.marker([R.lat, R.lng]).addTo(M); M.on('click', e => P.setLatLng(e.latlng)); }
}
$('items').onclick = async e => { if (e.target.dataset.t) { await api('toggle_item', { id: +e.target.dataset.t }); loadMenu(); } };
$('add').onsubmit = async e => { e.preventDefault(); const f = Object.fromEntries(new FormData(e.target));
  try { await api('add_item', f); e.target.reset(); loadMenu(); } catch (x) { toast(x.message); } };
$('saveLoc').onclick = async () => { const p = P.getLatLng(); try { await api('set_location', { lat: p.lat, lng: p.lng, address: R.address }); toast('Location saved'); } catch (x) { toast(x.message); } };
loadMenu(); loadQ(); setInterval(loadQ, 5000);

api('menu_all').then(d => { const f = $('prof'); for (const k of ['cuisine','address','phone','hours','description']) f.elements[k].value = d.restaurant[k] || ''; });
$('prof').onsubmit = async e => { e.preventDefault(); try { await api('save_profile', Object.fromEntries(new FormData(e.target))); toast('Profile saved'); } catch (x) { toast(x.message); } };
const msg = new URLSearchParams(location.search).get('msg'); if (msg) toast(msg);
</script></body></html>
