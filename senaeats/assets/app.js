const CSRF = document.querySelector('meta[name=csrf]')?.content || '';
async function api(action, body) {
  const opt = body ? { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF': CSRF }, body: JSON.stringify(body) } : {};
  const r = await fetch('api.php?a=' + action, opt);
  const d = await r.json().catch(() => ({}));
  if (!r.ok) throw new Error(d.error || 'Request failed');
  return d;
}
const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const money = n => 'K' + Number(n).toFixed(2);
const LABEL = { pending: 'Waiting for restaurant', accepted: 'Accepted', cooking: 'Cooking', ready: 'Ready for pickup', out_for_delivery: 'On the way', completed: 'Delivered', rejected: 'Rejected' };
const toast = m => { const t = document.createElement('div'); t.className = 'toast'; t.textContent = m; document.body.append(t); setTimeout(() => t.remove(), 3500); };
const stars = r => r == null ? 'No ratings yet' : '★'.repeat(Math.round(r)) + '☆'.repeat(5 - Math.round(r)) + ' ' + r;
document.addEventListener('click', async e => {   // favourites toggle (restaurants and couriers)
  const b = e.target.closest('[data-fav]'); if (!b) return;
  try { const r = await api('fav_toggle', { type: b.dataset.fav, id: +b.dataset.id });
    b.classList.toggle('on', r.fav); b.textContent = r.fav ? '♥' : '♡'; document.dispatchEvent(new Event('favchanged'));
  } catch (x) { toast(x.message); }
});
async function loadWallet() {   // vendor / courier funds panel
  const el = document.getElementById('wallet'); if (!el) return;
  const w = await api('wallet');
  el.innerHTML = `<h2>Your funds</h2><div class="stats"><div class="stat"><b>${money(w.held)}</b><small>Held in escrow, locked until the delivery code is confirmed</small></div>
  <div class="stat"><b>${money(w.available)}</b><small>Unlocked and ready to withdraw</small></div></div>
  <form id="wd"><input name="amount" type="number" step="0.01" min="0.01" max="${w.available}" placeholder="Amount (K)" required><input name="phone" placeholder="Mobile money number" required>
  <button class="btn" ${w.available > 0 ? '' : 'disabled'}>Withdraw</button></form>
  ${w.recent.map(r => `<div class="item"><small>${esc(r.created_at)}</small><span>${money(r.amount)} to ${esc(r.phone)}</span></div>`).join('')}`;
  document.getElementById('wd').onsubmit = async e => { e.preventDefault();
    try { await api('withdraw', Object.fromEntries(new FormData(e.target))); toast('Withdrawal sent'); loadWallet(); } catch (x) { toast(x.message); } };
}
if (document.getElementById('wallet')) { loadWallet(); setInterval(loadWallet, 15000); }
