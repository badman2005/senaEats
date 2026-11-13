<?php
require __DIR__ . '/lib.php'; require_role('customer');
page_head('My orders'); ?>
<h1>My dashboard</h1>
<div class="stats" id="sum"></div>
<div class="grid2"><section><h2>Active orders</h2><div id="active"></div><h2>Past orders</h2><div id="past"></div></section>
<section><h2>Favourite restaurants</h2><div id="fr"></div><h2>Favourite riders</h2><div id="fc"></div></section></div>
<?php page_foot(); ?>
<script>
const $ = id => document.getElementById(id);
const STEPS = ['pending', 'accepted', 'cooking', 'ready', 'out_for_delivery', 'completed'];
const ESC = { held: 'Held safely in escrow', released: 'Released to restaurant and rider', refunded: 'Refunded to you' };
const opts = '<option value="5">5 - Excellent</option><option value="4">4</option><option value="3">3</option><option value="2">2</option><option value="1">1 - Poor</option>';
async function load() {
  try {
    const [o, f] = await Promise.all([api('orders_full'), api('my_favs')]);
    const sum = s => o.filter(x => x.escrow === s).reduce((a, x) => a + +x.total, 0);
    $('sum').innerHTML = [['held', 'In escrow now'], ['released', 'Paid out after delivery'], ['refunded', 'Refunded']].map(([s, l]) => `<div class="stat"><b>${money(sum(s))}</b><small>${l}</small></div>`).join('');
    const act = o.filter(x => !['completed', 'rejected'].includes(x.status)), past = o.filter(x => !act.includes(x));
    $('active').innerHTML = act.map(x => { const i = STEPS.indexOf(x.status);
      return `<div class="card"><div class="between"><b>#${x.id} ${esc(x.restaurant)}</b><a class="btn sm" href="track.php?id=${x.id}">Track on map</a></div>
      <ol class="steps">${STEPS.map((s, k) => `<li class="${k <= i ? 'done' : ''}">${LABEL[s]}</li>`).join('')}</ol>
      <p>${money(x.total)}: <b>${ESC[x.escrow]}</b>.${x.courier ? ` Rider: <a href="profile.php?type=courier&id=${x.courier_id}">${esc(x.courier)}</a>.` : ''}</p>
      ${x.delivery_code ? `<p>Give this code to your rider only when you hold your food: <span class="code">${esc(x.delivery_code)}</span></p>` : ''}</div>`; }).join('') || '<p class="muted">No active orders.</p>';
    $('past').innerHTML = past.map(x => `<div class="card"><b>#${x.id} ${esc(x.restaurant)}</b> <span class="tag">${LABEL[x.status]}</span> ${money(x.total)}<br><small>${ESC[x.escrow]}</small>
      ${x.status === 'completed' && !+x.reviewed ? `<form data-review="${x.id}"><label>Restaurant <select name="restaurant_rating">${opts}</select></label><label>Rider <select name="courier_rating">${opts}</select></label>
      <label>Comment <textarea name="comment" rows="2" maxlength="500"></textarea></label><button class="btn sm">Submit review</button></form>` : ''}</div>`).join('') || '<p class="muted">Nothing yet.</p>';
    const row = (t, a) => `<div class="item"><a href="profile.php?type=${t}&id=${a.id}">${esc(a.name)}</a><button class="btn sm bad" data-fav="${t}" data-id="${a.id}">Remove</button></div>`;
    $('fr').innerHTML = f.restaurants.map(a => row('restaurant', a)).join('') || '<p class="muted">Tap the heart on a restaurant to save it.</p>';
    $('fc').innerHTML = f.couriers.map(a => row('courier', a)).join('') || '<p class="muted">Tap the heart on a rider to save them.</p>';
  } catch (e) { toast(e.message); }
}
document.addEventListener('favchanged', load);
document.addEventListener('submit', async e => { const id = e.target.dataset.review; if (!id) return; e.preventDefault();
  try { await api('review', { order_id: +id, ...Object.fromEntries(new FormData(e.target)) }); toast('Thanks for your review'); load(); } catch (x) { toast(x.message); } });
load(); setInterval(load, 8000);
</script></body></html>
