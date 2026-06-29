/* Global namespace, API client and shared UI helpers. */
window.Casino = window.Casino || {};
Casino.games = Casino.games || {};
Casino.state = { user: null, meta: null };

/* ---- API ---- */
Casino.api = (function () {
  async function req(path, method, body) {
    const opts = {
      method: method || 'GET',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
    };
    if (body !== undefined) opts.body = JSON.stringify(body);
    const res = await fetch(path, opts);
    let data = {};
    try { data = await res.json(); } catch (e) { /* empty body */ }
    if (!res.ok) {
      const err = new Error((data && data.message) || ('Request failed (' + res.status + ')'));
      err.code = data && data.error;
      err.status = res.status;
      throw err;
    }
    return data;
  }
  return {
    meta:     () => req('/api/meta'),
    me:       () => req('/api/me'),
    register: (username, password) => req('/api/register', 'POST', { username, password }),
    login:    (username, password) => req('/api/login', 'POST', { username, password }),
    logout:   () => req('/api/logout', 'POST'),
    play:     (payload) => req('/api/play', 'POST', payload),
    rouletteSpin: (bets) => req('/api/roulette', 'POST', { bets }),
    craps:    (payload) => req('/api/craps', 'POST', payload),
    adminPlayers: () => req('/api/admin/players'),
    adminStats:   () => req('/api/admin/stats'),
    adminHistory: (userId) => req('/api/admin/history?userId=' + encodeURIComponent(userId)),
    adminAdjust:  (userId, delta) => req('/api/admin/adjust', 'POST', { userId, delta }),
    adminDelete:  (userId) => req('/api/admin/delete', 'POST', { userId }),
  };
})();

/* ---- Helpers ---- */
Casino.fmt = (n) => Number(n).toLocaleString('en-US');

Casino.el = function (tag, attrs, children) {
  const node = document.createElement(tag);
  if (attrs) {
    for (const k in attrs) {
      if (k === 'class') node.className = attrs[k];
      else if (k === 'html') node.innerHTML = attrs[k];
      else if (k === 'text') node.textContent = attrs[k];
      else if (k.startsWith('on') && typeof attrs[k] === 'function') node.addEventListener(k.slice(2), attrs[k]);
      else if (attrs[k] !== null && attrs[k] !== undefined) node.setAttribute(k, attrs[k]);
    }
  }
  (children || []).forEach((c) => {
    if (c == null) return;
    node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
  });
  return node;
};

let toastTimer = null;
Casino.toast = function (msg, kind) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = 'toast' + (kind ? ' ' + kind : '');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => t.classList.add('hidden'), 2600);
};

/* Update the balance everywhere (header + state). */
Casino.setBalance = function (n) {
  if (Casino.state.user) Casino.state.user.balance = n;
  const b = document.getElementById('balance');
  if (b) b.textContent = Casino.fmt(n);
};

Casino.getBalance = () => (Casino.state.user ? Casino.state.user.balance : 0);

Casino.sleep = (ms) => new Promise((r) => setTimeout(r, ms));

/* Confetti burst — called on every win, in every game. */
Casino.confetti = function () {
  const colors = ['#ec6a4b', '#efb237', '#1f9e92', '#8a4f9e', '#3a6ea5', '#2c8a5f', '#fff'];
  for (let i = 0; i < 70; i++) {
    const p = document.createElement('div');
    p.className = 'confetti-piece';
    p.style.left = Math.random() * 100 + 'vw';
    p.style.background = colors[Math.floor(Math.random() * colors.length)];
    p.style.animationDelay = (Math.random() * 0.3) + 's';
    p.style.animationDuration = (1.8 + Math.random() * 1.6) + 's';
    document.body.appendChild(p);
    setTimeout(() => p.remove(), 3600);
  }
};

/* ---- Reusable game UI bits ---- */
Casino.ui = {
  /* Game header with title + a back-to-lobby button. */
  gameHead(icon, title, ctx) {
    return Casino.el('div', { class: 'game-head' }, [
      Casino.el('h2', { html: '<span class="ic">' + icon + '</span>' + title }),
      Casino.el('button', { class: 'btn-ghost', onclick: ctx.backToLobby }, ['◀ Lobby']),
    ]);
  },

  /* Bet amount control: − / + steppers, quick chips and an All-in button.
     Returns { node, value(), input }. */
  amountControl(meta, defaultVal) {
    const STEP = 10;
    const input = Casino.el('input', {
      class: 'input amt-input', type: 'number', min: meta.minBet, step: 1,
      value: defaultVal || 10, inputmode: 'numeric',
    });
    const cur = () => Math.max(0, Math.floor(Number(input.value) || 0));
    const set = (v) => { input.value = Math.max(meta.minBet, Math.floor(v)); };

    const minus = Casino.el('button', { class: 'amt-step', type: 'button', onclick: () => set(cur() - STEP) }, ['−']);
    const plus  = Casino.el('button', { class: 'amt-step', type: 'button', onclick: () => set(cur() + STEP) }, ['+']);
    const chips = [10, 50, 100].map((v) =>
      Casino.el('button', { class: 'chip-btn', type: 'button', onclick: () => set(v) }, [String(v)]));
    const allin = Casino.el('button', { class: 'chip-btn allin', type: 'button', title: 'Bet everything',
      onclick: () => set(Math.max(meta.minBet, Casino.getBalance())) }, ['ALL']);

    const node = Casino.el('div', { class: 'bet-control' }, [
      Casino.el('label', { text: 'Bet (chips)' }),
      Casino.el('div', { class: 'amt-row' }, [minus, input, plus]),
      Casino.el('div', { class: 'chiprow' }, [...chips, allin]),
    ]);
    return { node, input, value: cur };
  },

  /* Validate a stake. No max cap (all-in allowed) — only min and balance. */
  checkStake(amount, meta) {
    if (!amount || amount < meta.minBet) return 'Minimum bet is ' + meta.minBet + ' chips.';
    if (amount > Casino.getBalance()) return 'Not enough chips for that bet.';
    return null;
  },
};
