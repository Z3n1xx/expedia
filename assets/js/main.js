/* Expedia PH — main.js */

/* Fade-up on scroll */
const io = new IntersectionObserver(entries => {
  entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('vis'); io.unobserve(e.target); } });
}, { threshold: 0.1 });
document.querySelectorAll('.fade-up').forEach((el, i) => {
  el.style.transitionDelay = (i % 4 * 0.1) + 's';
  io.observe(el);
});

/* Page-leave fade */
document.addEventListener('click', e => {
  const a = e.target.closest('a[href]');
  if (!a) return;
  const href = a.getAttribute('href');
  if (!href || href.startsWith('#') || href.startsWith('javascript') ||
      href.startsWith('mailto') || a.target === '_blank' || e.ctrlKey || e.metaKey) return;
  try { if (new URL(href, location.href).origin !== location.origin) return; } catch { return; }
  e.preventDefault();
  document.body.style.transition = 'opacity .22s';
  document.body.style.opacity = '0';
  setTimeout(() => location.href = href, 210);
});

/* Date defaults */
const todayStr = new Date().toISOString().split('T')[0];
document.querySelectorAll('input[type=date]').forEach(el => { if (!el.min) el.min = todayStr; });

/* Check-in / check-out sync */
const ciEl = document.getElementById('check_in');
const coEl = document.getElementById('check_out');
if (ciEl && coEl) {
  ciEl.addEventListener('change', () => {
    coEl.min = ciEl.value;
    if (coEl.value && coEl.value <= ciEl.value) coEl.value = '';
    calcTotal();
  });
  coEl.addEventListener('change', calcTotal);
}

/* Live price calculator */
function calcTotal() {
  const ppp  = parseFloat(document.getElementById('ppp')?.value || 0);
  const ciEl = document.getElementById('check_in');
  const coEl = document.getElementById('check_out');
  const nd   = document.getElementById('nights_display');
  const td   = document.getElementById('total_display');
  const gt   = document.getElementById('grand_total');
  const ni   = document.getElementById('total_nights');
  const ti   = document.getElementById('total_price');

  if (!ciEl || !coEl || !ppp) return;
  const ci = new Date(ciEl.value), co = new Date(coEl.value);
  if (!ciEl.value || !coEl.value || isNaN(ci) || isNaN(co) || co <= ci) {
    [nd, td, gt].forEach(el => { if (el) el.textContent = '—'; });
    if (ni) ni.value = ''; if (ti) ti.value = '';
    return;
  }
  const nights = Math.round((co - ci) / 86400000);
  const total  = nights * ppp;
  const fmt    = n => '₱' + n.toLocaleString();
  if (nd) nd.textContent = nights + (nights === 1 ? ' night' : ' nights');
  if (td) td.textContent = fmt(total);
  if (gt) gt.textContent = fmt(total);
  if (ni) ni.value = nights;
  if (ti) ti.value = total.toFixed(2);
}
calcTotal();

/* Star filter */
document.querySelectorAll('.star-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const active = btn.classList.contains('on');
    document.querySelectorAll('.star-btn').forEach(b => b.classList.remove('on'));
    const inp = document.getElementById('star_filter');
    if (!active) { btn.classList.add('on'); if (inp) inp.value = btn.dataset.s; }
    else { if (inp) inp.value = ''; }
  });
});

/* Payment method switch */
window.switchPay = function(val) {
  document.querySelectorAll('.pay-method').forEach(el => el.classList.remove('sel'));
  document.querySelector(`.pay-method[data-v="${val}"]`)?.classList.add('sel');

  // Hide all panels and disable their inputs so they don't submit
  document.querySelectorAll('.pay-fields').forEach(el => {
    el.classList.remove('show');
    el.querySelectorAll('input,select,textarea').forEach(f => f.disabled = true);
  });

  // Show the target panel (debit_card reuses the credit_card panel)
  const panelId = val === 'debit_card' ? 'pf_credit_card' : 'pf_' + val;
  const panel = document.getElementById(panelId);
  if (panel) {
    panel.classList.add('show');
    panel.querySelectorAll('input,select,textarea').forEach(f => f.disabled = false);
  }

  // Sync the hidden radio
  const radio = document.querySelector(`input[name=pay_method][value="${val}"]`);
  if (radio) radio.checked = true;
};

/* Disable inputs in hidden pay panels on load */
document.querySelectorAll('.pay-fields').forEach(el => {
  if (!el.classList.contains('show')) {
    el.querySelectorAll('input,select,textarea').forEach(f => f.disabled = true);
  }
});

/* Card number auto-space */
const cnEl = document.querySelector('input[name=card_number]');
if (cnEl) cnEl.addEventListener('input', function () {
  let v = this.value.replace(/\D/g, '').substring(0, 16);
  this.value = v.match(/.{1,4}/g)?.join(' ') || v;
});

/* Expiry auto-slash */
const exEl = document.querySelector('input[name=card_expiry]');
if (exEl) exEl.addEventListener('input', function () {
  let v = this.value.replace(/\D/g, '');
  if (v.length >= 2) v = v.substring(0, 2) + '/' + v.substring(2, 6);
  this.value = v;
});

/* Confirm dialogs */
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', e => { if (!confirm(el.dataset.confirm)) e.preventDefault(); });
});

/* Flash auto-dismiss */
const fl = document.getElementById('flashMsg');
if (fl) setTimeout(() => { fl.style.transition = 'opacity .4s'; fl.style.opacity = '0'; setTimeout(() => fl.remove(), 400); }, 5000);
