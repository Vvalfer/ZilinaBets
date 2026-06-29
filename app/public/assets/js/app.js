/* App orchestrator: auth, header, lobby, and mounting games. */
(function () {
  const view = document.getElementById('view');
  const topbar = document.getElementById('topbar');

  const GAMES = [
    { key: 'roulette',  emoji: '🎡', name: 'Roulette',    blurb: 'European wheel. Bet color, number, dozen or parity.', accent: '#ec6a4b' },
    { key: 'slots',     emoji: '🎰', name: 'Lucky Reels', blurb: 'Three reels. Line up three to win up to 400×.',       accent: '#efb237' },
    { key: 'blackjack', emoji: '🃏', name: 'Blackjack',   blurb: 'Beat the dealer to 21. Hit, stand or double down.',   accent: '#1f9e92' },
    { key: 'craps',     emoji: '🎲', name: '421',         blurb: 'Roll 7 or 11 to win. Otherwise chase your point before a 7.', accent: '#8a4f9e' },
    { key: 'duckrace',  emoji: '🦆', name: 'Duck Race',   blurb: 'Five ducks, one winner. Back your bird.',             accent: '#3a6ea5' },
  ];

  function ctx() {
    return {
      api: Casino.api, meta: Casino.state.meta, el: Casino.el, toast: Casino.toast,
      setBalance: Casino.setBalance, getBalance: Casino.getBalance, fmt: Casino.fmt,
      sleep: Casino.sleep, backToLobby: renderLobby,
    };
  }

  /* ---------------- Auth ---------------- */
  function renderAuth() {
    document.body.classList.remove('in-game');
    topbar.classList.add('hidden');
    let mode = 'login';

    const err = Casino.el('div', { class: 'form-error' });
    const userInput = Casino.el('input', { class: 'input', type: 'text', placeholder: 'e.g. dark_émilien', autocomplete: 'username' });
    const passInput = Casino.el('input', { class: 'input', type: 'password', placeholder: '••••••', autocomplete: 'current-password' });
    const submit = Casino.el('button', { class: 'btn btn-pink full', type: 'submit' }, ['Enter']);

    const tabLogin = Casino.el('div', { class: 'tab active' }, ['Log in']);
    const tabReg = Casino.el('div', { class: 'tab' }, ['Sign up']);
    function setMode(m) {
      mode = m;
      tabLogin.classList.toggle('active', m === 'login');
      tabReg.classList.toggle('active', m === 'register');
      submit.textContent = m === 'login' ? 'Enter' : 'Create account';
      err.textContent = '';
    }
    tabLogin.addEventListener('click', () => setMode('login'));
    tabReg.addEventListener('click', () => setMode('register'));

    async function doSubmit(e) {
      e.preventDefault();
      err.textContent = '';
      const u = userInput.value.trim(), p = passInput.value;
      if (!u || !p) { err.textContent = 'Enter a username and password.'; return; }
      submit.disabled = true;
      try {
        const data = mode === 'login' ? await Casino.api.login(u, p) : await Casino.api.register(u, p);
        Casino.state.user = data.user;
        enterApp();
      } catch (ex) {
        err.textContent = ex.message;
      } finally {
        submit.disabled = false;
      }
    }

    const form = Casino.el('form', { onsubmit: doSubmit }, [
      Casino.el('div', { class: 'field' }, [Casino.el('label', { text: 'Username' }), userInput]),
      Casino.el('div', { class: 'field' }, [Casino.el('label', { text: 'Password' }), passInput]),
      err, submit,
    ]);

    const wrap = Casino.el('div', { class: 'auth-wrap' }, [
      Casino.el('div', { class: 'auth-logo', html: '<span class="a">RETRO</span> <span class="b">CASINO</span>' }),
      Casino.el('div', { class: 'auth-tag' }, ['Free virtual chips · no real money · just for fun · est. 1985']),
      Casino.el('div', { class: 'panel' }, [
        Casino.el('div', { class: 'tabs' }, [tabLogin, tabReg]),
        form,
        Casino.el('div', { class: 'hint' }, ['New here? Sign up and get ' + Casino.fmt(Casino.state.meta.startingBalance) + ' free chips.']),
      ]),
    ]);

    view.innerHTML = '';
    view.appendChild(wrap);
    userInput.focus();
  }

  /* ---------------- Lobby ---------------- */
  function renderLobby() {
    document.body.classList.remove('in-game');
    const cards = GAMES.map((g) =>
      Casino.el('div', { class: 'game-card', style: '--accent: ' + g.accent, onclick: () => mountGame(g.key) }, [
        Casino.el('div', { class: 'emoji' }, [g.emoji]),
        Casino.el('h3', {}, [g.name]),
        Casino.el('p', {}, [g.blurb]),
        Casino.el('div', { class: 'play-tag' }, ['▶ Play']),
      ])
    );
    view.innerHTML = '';
    view.appendChild(Casino.el('h1', { class: 'title-xl', html: 'Welcome to the <span class="a">RETRO</span> <span class="b">CASINO</span>' }));
    view.appendChild(Casino.el('p', { class: 'subtitle' }, ['Pick a game. Every outcome is decided server-side — this end is pure showtime.']));
    view.appendChild(Casino.el('div', { class: 'lobby-grid' }, cards));
  }

  function mountGame(key) {
    const game = Casino.games[key];
    view.innerHTML = '';
    if (!game) { Casino.toast('That game is not available.', 'err'); renderLobby(); return; }
    document.body.classList.add('in-game'); // fit to viewport, no scroll (desktop)
    game.render(view, ctx());
  }

  /* ---------------- Admin ---------------- */
  function statsTable(stats) {
    const head = Casino.el('tr', {}, ['Game', 'Rounds', 'Wagered', 'Paid out', 'House', 'RTP'].map((h) => Casino.el('th', {}, [h])));
    const body = stats.map((s) => Casino.el('tr', {}, [
      Casino.el('td', {}, [s.game]),
      Casino.el('td', { class: 'num' }, [String(s.rounds)]),
      Casino.el('td', { class: 'num' }, [Casino.fmt(s.wagered)]),
      Casino.el('td', { class: 'num' }, [Casino.fmt(s.paid)]),
      Casino.el('td', { class: 'num' }, [Casino.fmt(s.house)]),
      Casino.el('td', { class: 'num' }, [s.rtp === null ? '—' : (s.rtp * 100).toFixed(1) + '%']),
    ]));
    return Casino.el('table', { class: 'admin-table' }, [Casino.el('thead', {}, [head]), Casino.el('tbody', {}, body)]);
  }

  function sparkline(points) {
    if (points.length < 2) return Casino.el('p', { class: 'muted' }, ['Not enough activity yet to chart.']);
    const w = 440, h = 170, pad = 26;
    const min = Math.min.apply(null, points), max = Math.max.apply(null, points), range = (max - min) || 1;
    const x = (i) => pad + i * (w - 2 * pad) / (points.length - 1);
    const y = (v) => h - pad - (v - min) / range * (h - 2 * pad);
    const line = points.map((v, i) => x(i).toFixed(1) + ',' + y(v).toFixed(1)).join(' ');
    const area = pad + ',' + (h - pad) + ' ' + line + ' ' + x(points.length - 1).toFixed(1) + ',' + (h - pad);
    const last = points[points.length - 1];
    const svg = '<svg class="spark" viewBox="0 0 ' + w + ' ' + h + '" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg">' +
      '<polygon points="' + area + '" fill="rgba(236,106,75,.18)"/>' +
      '<polyline points="' + line + '" fill="none" stroke="#ec6a4b" stroke-width="2.5" stroke-linejoin="round"/>' +
      '<circle cx="' + x(points.length - 1).toFixed(1) + '" cy="' + y(last).toFixed(1) + '" r="4" fill="#2a2336"/>' +
      '<text x="' + pad + '" y="15" font-size="11" fill="#6b5d55">max ' + Casino.fmt(max) + '</text>' +
      '<text x="' + pad + '" y="' + (h - 8) + '" font-size="11" fill="#6b5d55">min ' + Casino.fmt(min) + '</text>' +
      '</svg>';
    return Casino.el('div', { html: svg });
  }

  async function showHistory(player, graphCard) {
    graphCard.innerHTML = '';
    graphCard.appendChild(Casino.el('h4', {}, ['Balance — ' + player.username]));
    graphCard.appendChild(Casino.el('p', { class: 'muted' }, ['Loading…']));
    try {
      const d = await Casino.api.adminHistory(player.id);
      graphCard.innerHTML = '';
      graphCard.appendChild(Casino.el('h4', {}, ['Balance — ' + player.username]));
      graphCard.appendChild(sparkline(d.history.map((p) => p.balance)));
      graphCard.appendChild(Casino.el('p', { class: 'muted', style: 'margin:.4rem 0 0;font-size:.78rem' }, [
        d.history.length + ' movements · now ' + Casino.fmt(player.balance) + ' chips',
      ]));
    } catch (ex) {
      graphCard.innerHTML = '';
      graphCard.appendChild(Casino.el('p', { class: 'lose' }, [ex.message]));
    }
  }

  function playersTable(players, graphCard, refresh) {
    const head = Casino.el('tr', {}, ['#', 'Player', 'Balance', 'Rounds', 'Wagered', 'Joined', 'Money', ''].map((h) => Casino.el('th', {}, [h])));
    const body = players.map((p) => {
      const amt = Casino.el('input', { class: 'adj-input', type: 'number', min: 1, value: 100 });
      amt.addEventListener('click', (e) => e.stopPropagation());
      const balanceCell = Casino.el('td', { class: 'num' }, [Casino.fmt(p.balance)]);
      async function adjust(sign) {
        const v = Math.floor(Number(amt.value) || 0);
        if (v <= 0) { Casino.toast('Enter an amount.', 'err'); return; }
        try {
          const d = await Casino.api.adminAdjust(p.id, sign * v);
          p.balance = d.balance;
          balanceCell.textContent = Casino.fmt(d.balance);   // update in place, no full reload
          Casino.toast((sign > 0 ? '+' : '−') + Casino.fmt(v) + ' → ' + p.username, 'good');
          if (row.classList.contains('sel')) showHistory(p, graphCard); // refresh graph if open
        } catch (ex) { Casino.toast(ex.message, 'err'); }
      }
      async function del() {
        if (!window.confirm('Delete account "' + p.username + '" and all its data?')) return;
        try { await Casino.api.adminDelete(p.id); Casino.toast('Deleted ' + p.username, 'good'); refresh(); }
        catch (ex) { Casino.toast(ex.message, 'err'); }
      }
      const row = Casino.el('tr', {}, [
        Casino.el('td', { class: 'num' }, [String(p.id)]),
        Casino.el('td', {}, [p.username]),
        balanceCell,
        Casino.el('td', { class: 'num' }, [String(p.rounds)]),
        Casino.el('td', { class: 'num' }, [Casino.fmt(p.wagered)]),
        Casino.el('td', {}, [(p.createdAt || '').replace('T', ' ').slice(0, 16)]),
        Casino.el('td', {}, [Casino.el('div', { class: 'row-actions' }, [
          amt,
          Casino.el('button', { class: 'btn-ghost btn-sm', onclick: (e) => { e.stopPropagation(); adjust(1); } }, ['+']),
          Casino.el('button', { class: 'btn-ghost btn-sm', onclick: (e) => { e.stopPropagation(); adjust(-1); } }, ['−']),
        ])]),
        Casino.el('td', {}, [Casino.el('button', { class: 'btn-ghost btn-sm btn-danger', style: 'color:#fff', onclick: (e) => { e.stopPropagation(); del(); } }, ['Del'])]),
      ]);
      row.addEventListener('click', () => {
        Array.prototype.forEach.call(row.parentNode.children, (r) => r.classList.remove('sel'));
        row.classList.add('sel');
        showHistory(p, graphCard);
      });
      return row;
    });
    return Casino.el('table', { class: 'admin-table' }, [Casino.el('thead', {}, [head]), Casino.el('tbody', {}, body)]);
  }

  async function renderAdmin() {
    document.body.classList.remove('in-game');
    view.innerHTML = '';
    view.appendChild(Casino.ui.gameHead('★', 'Admin', { backToLobby: renderLobby }));
    const wrap = Casino.el('div', { class: 'panel' }, [Casino.el('p', { class: 'muted' }, ['Loading…'])]);
    view.appendChild(wrap);
    try {
      const [pData, sData] = await Promise.all([Casino.api.adminPlayers(), Casino.api.adminStats()]);
      wrap.innerHTML = '';
      const graphCard = Casino.el('div', { class: 'graph-card' }, [
        Casino.el('h4', {}, ['Balance history']),
        Casino.el('p', { class: 'muted' }, ['Click a player row to chart their balance over time.']),
      ]);
      wrap.appendChild(Casino.el('h3', { class: 'admin-h' }, ['Players (' + pData.count + ')']));
      wrap.appendChild(Casino.el('div', { class: 'table-scroll' }, [playersTable(pData.players, graphCard, renderAdmin)]));
      wrap.appendChild(Casino.el('div', { class: 'admin-panels' }, [
        Casino.el('div', {}, [
          Casino.el('h3', { class: 'admin-h' }, ['Per-game activity']),
          sData.stats.length ? statsTable(sData.stats) : Casino.el('p', { class: 'muted' }, ['No rounds played yet.']),
        ]),
        Casino.el('div', {}, [
          Casino.el('h3', { class: 'admin-h' }, ['Player detail']),
          graphCard,
        ]),
      ]));
    } catch (ex) {
      wrap.innerHTML = '';
      wrap.appendChild(Casino.el('p', { class: 'lose' }, [ex.message]));
    }
  }

  /* ---------------- Header / shell ---------------- */
  function enterApp() {
    topbar.classList.remove('hidden');
    document.getElementById('username').textContent = Casino.state.user.username;
    document.getElementById('adminBtn').classList.toggle('hidden', !Casino.state.user.isAdmin);
    Casino.setBalance(Casino.state.user.balance);
    renderLobby();
  }

  function wireHeader() {
    document.getElementById('lobbyBtn').addEventListener('click', renderLobby);
    document.getElementById('adminBtn').addEventListener('click', renderAdmin);
    document.getElementById('brandHome').addEventListener('click', () => { if (Casino.state.user) renderLobby(); });
    document.getElementById('logoutBtn').addEventListener('click', async () => {
      try { await Casino.api.logout(); } catch (e) {}
      Casino.state.user = null;
      renderAuth();
    });
  }

  /* ---------------- Boot ---------------- */
  async function boot() {
    wireHeader();
    try {
      Casino.state.meta = await Casino.api.meta();
      const me = await Casino.api.me();
      if (me.user) { Casino.state.user = me.user; enterApp(); }
      else { renderAuth(); }
    } catch (ex) {
      view.innerHTML = '';
      view.appendChild(Casino.el('div', { class: 'panel' }, [
        Casino.el('p', { class: 'lose' }, ['Could not reach the server. Is the PHP server running?']),
        Casino.el('p', { class: 'muted' }, [String(ex.message || ex)]),
      ]));
    }
  }

  document.addEventListener('DOMContentLoaded', boot);
})();
