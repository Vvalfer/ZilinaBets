/* Roulette — SVG wheel (left) + real-coloured betting table (right).
   Stack several bets on one spin; all resolve server-side against one pocket. */
(function () {
  const ORDER = [0, 32, 15, 19, 4, 21, 2, 25, 17, 34, 6, 27, 13, 36, 11, 30, 8, 23,
    10, 5, 24, 16, 33, 1, 20, 14, 31, 9, 22, 18, 29, 7, 28, 12, 35, 3, 26];
  const N = ORDER.length, STEP = 360 / N;
  const CX = 140, CY = 140, R_OUT = 132, R_NUM = 112, R_BALL = 123;

  // Standard European table layout (top, middle, bottom rows).
  const ROWS = [
    [3, 6, 9, 12, 15, 18, 21, 24, 27, 30, 33, 36],
    [2, 5, 8, 11, 14, 17, 20, 23, 26, 29, 32, 35],
    [1, 4, 7, 10, 13, 16, 19, 22, 25, 28, 31, 34],
  ];

  function polar(r, a) { const t = (a - 90) * Math.PI / 180; return { x: CX + r * Math.cos(t), y: CY + r * Math.sin(t) }; }
  function wedge(r, a0, a1) {
    const p0 = polar(r, a0), p1 = polar(r, a1), large = (a1 - a0) <= 180 ? 0 : 1;
    return 'M' + CX + ',' + CY + ' L' + p0.x.toFixed(2) + ',' + p0.y.toFixed(2) +
      ' A' + r + ',' + r + ' 0 ' + large + ' 1 ' + p1.x.toFixed(2) + ',' + p1.y.toFixed(2) + ' Z';
  }

  Casino.games.roulette = {
    render(root, ctx) {
      const meta = ctx.meta;
      const RED = new Set(meta.rouletteRed || []);
      const colorClass = (n) => (n === 0 ? 'green' : RED.has(n) ? 'red' : 'black');
      const fillOf = (n) => (n === 0 ? '#2c7d63' : RED.has(n) ? '#c8402f' : '#2a2433');

      // --- wheel SVG ---
      let wedges = '', nums = '';
      for (let p = 0; p < N; p++) {
        const n = ORDER[p];
        wedges += '<path d="' + wedge(R_OUT, p * STEP, (p + 1) * STEP) + '" fill="' + fillOf(n) + '" stroke="#15101f" stroke-width="1.5"/>';
        const pos = polar(R_NUM, p * STEP + STEP / 2);
        nums += '<text x="' + pos.x.toFixed(1) + '" y="' + pos.y.toFixed(1) +
          '" fill="#fff" font-size="9" font-family="Space Grotesk, sans-serif" font-weight="700" text-anchor="middle" dominant-baseline="central">' + n + '</text>';
      }
      const ball = polar(R_BALL, 0);
      const svg =
        '<svg id="wheelSvg" viewBox="0 0 280 280" xmlns="http://www.w3.org/2000/svg">' +
        '<circle cx="140" cy="140" r="136" fill="#15101f"/>' +
        '<g id="wheelG">' + wedges +
        '<circle cx="140" cy="140" r="48" fill="#efb237" stroke="#15101f" stroke-width="3"/>' +
        '<circle cx="140" cy="140" r="40" fill="#8a4f9e" stroke="#15101f" stroke-width="2"/>' +
        nums + '</g>' +
        '<g id="ballG"><circle cx="' + ball.x.toFixed(1) + '" cy="' + ball.y.toFixed(1) + '" r="6.5" fill="#fff" stroke="#15101f" stroke-width="1.5"/></g>' +
        '</svg>';

      const wheelWrap = Casino.el('div', { class: 'wheel-wrap', html: '<div class="wheel-pointer"></div>' + svg });
      const badge = Casino.el('div', { class: 'result-badge' }, ['—']);
      const result = Casino.el('div', { class: 'result-line muted' }, ['Place bets, then spin.']);
      const amount = Casino.ui.amountControl(meta);
      const spinBtn = Casino.el('button', { class: 'btn btn-pink' }, ['Spin']);
      const clearBtn = Casino.el('button', { class: 'btn-ghost' }, ['Clear bets']);
      const summary = Casino.el('div', { class: 'roul-summary' }, ['']);

      // --- multi-bet state ---
      const bets = new Map();      // key -> { type, value, amount }
      const badges = {};           // key -> badge element
      const key = (t, v) => t + ':' + v;
      const total = () => { let s = 0; bets.forEach((b) => { s += b.amount; }); return s; };

      function updateBadges() {
        for (const k in badges) {
          const b = bets.get(k);
          badges[k].textContent = b ? b.amount : '';
          badges[k].classList.toggle('on', !!b);
        }
      }
      function updateSummary() {
        summary.innerHTML = bets.size
          ? 'Bets: <b>' + bets.size + '</b> &middot; staked <b>' + Casino.fmt(total()) + '</b> chips'
          : 'Tap the table to bet — you can stack several spots.';
      }
      function addBet(t, v) {
        if (spinning) return;
        const amt = amount.value();
        if (amt < meta.minBet) { Casino.toast('Minimum bet is ' + meta.minBet + '.', 'err'); return; }
        if (total() + amt > Casino.getBalance()) { Casino.toast('Not enough chips for all your bets.', 'err'); return; }
        const k = key(t, v);
        const ex = bets.get(k);
        if (ex) ex.amount += amt; else bets.set(k, { type: t, value: v, amount: amt });
        updateBadges(); updateSummary();
      }
      function clearBets() { if (spinning) return; bets.clear(); updateBadges(); updateSummary(); }

      function makeSpot(cls, label, t, v) {
        const badge = Casino.el('span', { class: 'chip-badge' }, []);
        badges[key(t, v)] = badge;
        const cell = Casino.el('div', { class: cls }, [String(label), badge]);
        cell.addEventListener('click', () => addBet(t, v));
        return cell;
      }

      // zero + number grid
      const zero = makeSpot('rl-zero', '0', 'straight', 0);
      const cells = [];
      ROWS.forEach((row) => row.forEach((n) => cells.push(makeSpot('rl-cell ' + colorClass(n), n, 'straight', n))));
      const board = Casino.el('div', { class: 'rl-board' }, [zero, Casino.el('div', { class: 'rl-grid' }, cells)]);

      // outside bets
      const outside = Casino.el('div', { class: 'rl-outside' }, [
        makeSpot('rl-out', '1st 12', 'dozen', 1),
        makeSpot('rl-out', '2nd 12', 'dozen', 2),
        makeSpot('rl-out', '3rd 12', 'dozen', 3),
        makeSpot('rl-out', '1–18', 'range', 'low'),
        makeSpot('rl-out', 'EVEN', 'parity', 'even'),
        makeSpot('rl-out red', 'RED', 'color', 'red'),
        makeSpot('rl-out', '19–36', 'range', 'high'),
        makeSpot('rl-out', 'ODD', 'parity', 'odd'),
        makeSpot('rl-out black', 'BLACK', 'color', 'black'),
      ]);
      const tapis = Casino.el('div', { class: 'roul-right' }, [board, outside]);

      // --- spin ---
      let spinning = false, wheelG, ballG, container;
      async function spin() {
        if (spinning) return;
        if (bets.size === 0) { Casino.toast('Place at least one bet.', 'err'); return; }
        const list = [];
        bets.forEach((b) => list.push({ type: b.type, value: b.value, amount: b.amount }));

        wheelG = wheelWrap.querySelector('#wheelG');
        ballG = wheelWrap.querySelector('#ballG');
        container = wheelWrap;
        spinning = true; spinBtn.disabled = true; clearBtn.disabled = true;
        result.className = 'result-line muted'; result.textContent = 'No more bets…';

        try {
          const res = await Casino.api.rouletteSpin(list);
          const p = ORDER.indexOf(res.number);
          const center = p * STEP + STEP / 2;
          container.classList.remove('wheel-spinning');
          wheelG.style.transform = 'rotate(0deg)';
          ballG.style.transform = 'rotate(0deg)';
          void container.getBoundingClientRect();
          container.classList.add('wheel-spinning');
          requestAnimationFrame(() => requestAnimationFrame(() => {
            wheelG.style.transform = 'rotate(' + (360 * 5 - center) + 'deg)';
            ballG.style.transform = 'rotate(' + (-360 * 8) + 'deg)';
          }));

          await Casino.sleep(4800);
          Casino.setBalance(res.balance);
          badge.textContent = res.number;
          badge.className = 'result-badge ' + res.color;
          const won = res.results.filter((r) => r.win).length;
          if (res.totalPayout > 0) {
            result.className = 'result-line win';
            result.textContent = '✦ ' + res.number + ' ' + res.color + ' — ' + won + ' bet(s) won · net ' + (res.net >= 0 ? '+' : '') + Casino.fmt(res.net) + ' chips';
            Casino.confetti();
          } else {
            result.className = 'result-line lose';
            result.textContent = res.number + ' ' + res.color + ' — no win. −' + Casino.fmt(res.totalStake) + ' chips';
          }
          clearBets();
        } catch (ex) {
          result.className = 'result-line lose'; result.textContent = ex.message; Casino.toast(ex.message, 'err');
        } finally {
          spinning = false; spinBtn.disabled = false; clearBtn.disabled = false;
        }
      }
      spinBtn.addEventListener('click', spin);
      clearBtn.addEventListener('click', clearBets);
      updateSummary();

      root.appendChild(Casino.ui.gameHead('🎡', 'Roulette', ctx));
      root.appendChild(Casino.el('div', { class: 'panel' }, [
        Casino.el('div', { class: 'roulette-layout' }, [
          Casino.el('div', { class: 'roul-left' }, [wheelWrap, badge, result]),
          tapis,
        ]),
        summary,
        Casino.el('div', { class: 'controls' }, [amount.node, spinBtn, clearBtn]),
      ]));
    },
  };
})();
