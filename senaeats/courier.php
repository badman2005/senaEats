<?php
require __DIR__ . '/lib.php'; require_role('courier');
page_head('Dispatch'); ?>
<h1>Courier dispatch</h1>
<p class="muted" id="gps">Share your location so customers can follow you.</p>
<div class="grid2"><section><h2>Ready for pickup</h2><div id="avail"></div></section>
<section><h2>My deliveries</h2><div id="mine"></div></section></div>
<div class="grid2"><section class="card"><h2>My public profile</h2><form id="cprof"><label>Vehicle <input name="vehicle" maxlength="60" placeholder="Bicycle, motorbike..."></label>
<label>About me <textarea name="bio" maxlength="300" rows="3"></textarea></label><button class="btn">Save profile</button></form></section>
<section class="card" id="wallet"></section></div>
<?php page_foot(); ?>
<script>
const $ = id => document.getElementById(id);
async function load() {
  try { const d = await api('courier_board');
    $('avail').innerHTML = d.available.map(o => `<div class="card"><b>#${o.id} ${esc(o.restaurant)}</b><br><small>To: ${esc(o.delivery_address)}</small><br>Earns ${money(o.delivery_fee)}
      <button class="btn sm" data-claim="${o.id}">Take delivery</button></div>`).join('') || '<p class="muted">Nothing ready yet.</p>';
    $('mine').innerHTML = d.mine.map(o => `<div class="card"><b>#${o.id} ${esc(o.restaurant)}</b><br><small>Deliver to: ${esc(o.delivery_address)}</small><br>
      <a class="btn sm" href="track.php?id=${o.id}">Open map</a>
      <form data-verify="${o.id}" class="row"><input name="code" inputmode="numeric" pattern="\\d{4}" maxlength="4" placeholder="4-digit code from customer" required><button class="btn sm">Confirm delivery</button></form></div>`).join('') || '<p class="muted">No active deliveries.</p>';
  } catch (e) { toast(e.message); }
}
$('avail').onclick = async e => { const id = e.target.dataset.claim; if (!id) return;
  try { await api('courier_claim', { order_id: +id }); load(); } catch (x) { toast(x.message); load(); } };
$('mine').onsubmit = async e => { e.preventDefault(); const id = e.target.dataset.verify;
  try { await api('verify', { order_id: +id, code: new FormData(e.target).get('code') }); toast('Delivered. Payment released.'); load(); } catch (x) { toast(x.message); } };
if (navigator.geolocation) navigator.geolocation.watchPosition(p => {
  const t = Date.now(); if (window.last && t - window.last < 8000) return; window.last = t;
  api('courier_loc', { lat: p.coords.latitude, lng: p.coords.longitude }).then(() => $('gps').textContent = 'Location sharing is on.').catch(() => {});
}, () => $('gps').textContent = 'Location is blocked. Allow it in your browser to be tracked.', { enableHighAccuracy: true });
load(); setInterval(load, 6000);

api('courier_profile').then(d => { const f = $('cprof'); f.elements.vehicle.value = d.vehicle; f.elements.bio.value = d.bio; });
$('cprof').onsubmit = async e => { e.preventDefault(); try { await api('save_courier_profile', Object.fromEntries(new FormData(e.target))); toast('Profile saved'); } catch (x) { toast(x.message); } };
</script></body></html>
