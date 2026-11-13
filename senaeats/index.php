<?php
require __DIR__ . '/lib.php'; $u = user(); $role = $u['role'] ?? null;
page_head('Order food', true); ?>
<?php if (!$u): ?>
<section class="hero"><h1>Order from the kitchens on your street.</h1>
<p>Your money stays in escrow until the courier enters the 4-digit code at your door.</p>
<div class="roles">
 <div class="card"><h2>I'm hungry</h2><p>Browse menus, save favourites, follow your order on the map.</p><a class="btn" href="auth.php?role=customer">Sign up as customer</a></div>
 <div class="card"><h2>I cook</h2><p>List your menu, accept orders, withdraw once delivery is confirmed.</p><a class="btn" href="auth.php?role=vendor">Register my restaurant</a></div>
 <div class="card"><h2>I deliver</h2><p>Take delivery gigs and get paid for every confirmed drop-off.</p><a class="btn" href="auth.php?role=courier">Join as courier</a></div>
</div><p>Already registered? <a href="auth.php">Log in</a></p></section>
<?php elseif ($role !== 'customer'): ?>
<div class="card">Signed in as <?= e($role) ?>. <a class="btn sm" href="<?= e(home_for($role)) ?>">Open my dashboard</a></div>
<?php else: ?>
<div class="card"><a class="btn sm" href="dashboard.php">My orders, escrow and favourites</a></div>
<?php endif; ?>

<h2>Restaurants near you</h2><div id="rests" class="strip"></div>
<h2>Delivery riders</h2><div id="riders" class="strip"></div>

<div class="shop" id="catalog">
<section><h2>Menu catalog</h2>
 <div class="row"><input id="q" placeholder="Search dishes" aria-label="Search dishes"><select id="cat" aria-label="Category"><option value="">All categories</option></select><select id="prov" aria-label="Provider"><option value="">All providers</option></select></div>
 <?php if ($role === 'customer'): ?><label><input type="checkbox" id="favonly" style="width:auto"> Favourite restaurants only</label><?php endif; ?>
 <div id="menu"></div></section>
<aside class="card"><h2>Your cart</h2><div id="cart"></div>
 <?php if ($role === 'customer'): ?>
 <form id="co" hidden>
  <label>Phone <input name="phone" required placeholder="+260 97 000 0000"></label>
  <label>Delivery address <input name="address" required maxlength="255" placeholder="House number, street, landmark"></label>
  <p class="muted">Tap the map to drop your delivery pin.</p><div id="pick" class="map small"></div>
  <label>Pay with <select name="method"><option value="mobile_money">Mobile money (held in escrow)</option><option value="cash">Cash deposit (held in escrow)</option></select></label>
  <button class="btn">Place order and hold payment</button></form>
 <?php elseif (!$u): ?><p><a class="btn" href="auth.php?role=customer">Log in to check out</a></p><?php endif; ?>
</aside></div>
<?php page_foot(); ?>
<script>
const ROLE = <?= json_encode($role) ?>, $ = id => document.getElementById(id);
let rests = [], items = [], pin = null, pm = null, cart = null;
try { cart = JSON.parse(localStorage.getItem('se_cart')); } catch (e) {}
if (!cart || !cart.lines) cart = { rid: 0, rname: '', lines: {} };
const save = () => { try { localStorage.setItem('se_cart', JSON.stringify(cart)); } catch (e) {} };
const heart = (t, id, on) => ROLE === 'customer' ? `<button class="fav ${on ? 'on' : ''}" data-fav="${t}" data-id="${id}" aria-label="Toggle favourite">${on ? '♥' : '♡'}</button>` : '';

async function loadProviders() {
  rests = await api('providers');
  $('rests').innerHTML = rests.map(r => `<div class="rcard"><div class="between"><a href="profile.php?type=restaurant&id=${r.id}"><b>${esc(r.name)}</b></a>${heart('restaurant', r.id, +r.fav)}</div>
    <small>${esc(r.cuisine)}, ${esc(r.hours || 'hours not set')}</small><small class="stars">${stars(r.rating)}</small><button class="btn sm" data-show="${r.id}">Show menu</button></div>`).join('') || '<p class="muted">No restaurants yet.</p>';
  const cur = $('prov').value;
  $('prov').innerHTML = '<option value="">All providers</option>' + rests.map(r => `<option value="${r.id}">${esc(r.name)}</option>`).join(''); $('prov').value = cur;
  drawMenu();
}
async function loadRiders() {
  const c = await api('couriers');
  $('riders').innerHTML = c.map(r => `<div class="rcard"><div class="between"><a href="profile.php?type=courier&id=${r.id}"><b>${esc(r.name)}</b></a>${heart('courier', r.id, +r.fav)}</div>
    <small>${esc(r.vehicle || 'Rider')}, ${r.deliveries} deliveries</small><small class="stars">${stars(r.rating)}</small></div>`).join('') || '<p class="muted">No riders yet.</p>';
}
async function init() {
  items = await api('catalog');
  $('cat').innerHTML = '<option value="">All categories</option>' + [...new Set(items.map(i => i.category))].map(c => `<option>${esc(c)}</option>`).join('');
  await Promise.all([loadProviders(), loadRiders()]);
  const r = new URLSearchParams(location.search).get('r'); if (r) { $('prov').value = r; drawMenu(); }
  drawCart();
}
function drawMenu() {
  const q = $('q').value.toLowerCase(), c = $('cat').value, p = $('prov').value, fo = $('favonly')?.checked;
  const favIds = new Set(rests.filter(r => +r.fav).map(r => +r.id));
  $('menu').innerHTML = items.filter(i => (!c || i.category === c) && (!p || i.rid == p) && (!fo || favIds.has(+i.rid)) && (i.name + i.description).toLowerCase().includes(q)).map(i =>
    `<div class="item"><div><b>${esc(i.name)}</b><br><small>${esc(i.description)}</small><br><a href="profile.php?type=restaurant&id=${i.rid}"><small>${esc(i.rname)}</small></a></div>
     <div>${money(i.price)}<br><input class="qty" id="qty${i.id}" type="number" min="1" max="20" value="1" aria-label="Quantity"> <button class="btn sm" data-add="${i.id}">Add</button></div></div>`).join('') || '<p class="muted">No dishes match.</p>';
}
['q', 'cat', 'prov', 'favonly'].forEach(id => $(id)?.addEventListener('input', drawMenu));
document.addEventListener('favchanged', () => { loadProviders(); loadRiders(); });
$('rests').onclick = e => { const id = e.target.dataset.show; if (id) { $('prov').value = id; drawMenu(); $('catalog').scrollIntoView(); } };
$('menu').onclick = e => {
  const id = e.target.dataset.add; if (!id) return;
  const it = items.find(i => i.id == id), n = Math.max(1, Math.min(20, +$('qty' + id).value || 1));
  if (cart.rid && cart.rid != it.rid) { if (!confirm(`Your cart has food from ${cart.rname}. Start a new cart from ${it.rname}?`)) return; cart = { rid: 0, rname: '', lines: {} }; }
  cart.rid = +it.rid; cart.rname = it.rname;
  const l = cart.lines[id] ||= { name: it.name, price: +it.price, qty: 0 }; l.qty = Math.min(20, l.qty + n);
  save(); drawCart(); toast(`${n} x ${it.name} added`);
};
function drawCart() {
  const ids = Object.keys(cart.lines), co = $('co'); let sub = 0;
  if (!ids.length) { $('cart').innerHTML = '<p class="muted">Your cart is empty. It is saved on this phone until you order.</p>'; if (co) co.hidden = true; return; }
  $('cart').innerHTML = `<p><small>From ${esc(cart.rname)}</small></p>` + ids.map(id => { const l = cart.lines[id]; sub += l.price * l.qty;
    return `<div class="item"><span>${esc(l.name)}</span><span><button class="btn sm" data-d="${id}">-</button> ${l.qty} <button class="btn sm" data-i="${id}">+</button></span></div>`; }).join('') +
    `<p><b>Food: ${money(sub)}</b><br><small>Delivery fee is added at checkout.</small></p>`;
  if (co) { co.hidden = false; initPick(); }
}
$('cart').onclick = e => { const d = e.target.dataset, l = cart.lines;
  if (d.i && l[d.i]) l[d.i].qty = Math.min(20, l[d.i].qty + 1);
  if (d.d && l[d.d] && --l[d.d].qty <= 0) delete l[d.d];
  if (!Object.keys(l).length) cart = { rid: 0, rname: '', lines: {} };
  save(); drawCart(); };
function initPick() {
  if (pm) { pm.invalidateSize(); return; }
  const r = rests.find(x => x.id == cart.rid);
  pm = L.map('pick').setView([-14.4469, 28.4464], 14);
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(pm);
  pm.on('click', e => { pin ? pin.setLatLng(e.latlng) : pin = L.marker(e.latlng).addTo(pm); });
  navigator.geolocation?.getCurrentPosition(p => pm.setView([p.coords.latitude, p.coords.longitude], 16));
}
$('co')?.addEventListener('submit', async e => {
  e.preventDefault(); if (!pin) return toast('Tap the map to set your delivery location.');
  const f = new FormData(e.target);
  try { const r = await api('place_order', { restaurant_id: cart.rid, items: Object.entries(cart.lines).map(([id, l]) => ({ id: +id, qty: l.qty })),
      phone: f.get('phone'), address: f.get('address'), method: f.get('method'), lat: pin.getLatLng().lat, lng: pin.getLatLng().lng });
    cart = { rid: 0, rname: '', lines: {} }; save(); location.href = 'track.php?id=' + r.order_id;
  } catch (x) { toast(x.message); }
});
init();
</script></body></html>
