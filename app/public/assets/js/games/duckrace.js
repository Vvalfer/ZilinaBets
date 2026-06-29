/* Duck Race — 5 ducks, all pay 4×. The WINNER is server-decided; the visible
   race is built client-side: ducks only move forward but speed up / slow down,
   so the lead changes without anyone ever going backwards. */
(function () {
  const POOL = ['Lilian', 'François', 'Océane', 'Luccas', 'Pavel', 'LA', 'Lukian',
    'Yuki', 'Alejandro', 'Pierre', 'Gwenael', 'Jules', 'Mael', 'Florian', 'Seweryn',
    'Jonathan', 'Steven', 'Joyce', 'Nathan', 'Simon'];
  const COUNT = 5;

  function pickNames() {
    const p = POOL.slice();
    for (let i = p.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1)); const t = p[i]; p[i] = p[j]; p[j] = t; }
    return p.slice(0, COUNT);
  }

  Casino.games.duckrace = {
    render(root, ctx) {
      const meta = ctx.meta;
      const NAMES = pickNames();   // random names each time you open the game
      let pick = 0;
      let racing = false;

      const ducks = [];
      const lanes = NAMES.map((name, i) => {
        const duck = Casino.el('div', { class: 'duck' }, ['🦆']);
        ducks.push(duck);
        return Casino.el('div', { class: 'lane' + (i === pick ? ' picked' : '') }, [
          Casino.el('span', { class: 'lane-name' }, [name]),
          Casino.el('div', { class: 'finish' }),
          duck,
        ]);
      });
      function highlightPick() { lanes.forEach((l, i) => l.classList.toggle('picked', i === pick)); }

      const leaderTag = Casino.el('div', { class: 'leader-tag' }, ['']);
      const result = Casino.el('div', { class: 'result-line muted' }, ['Pick a duck — every duck pays 4×.']);
      const amount = Casino.ui.amountControl(meta);
      const goBtn = Casino.el('button', { class: 'btn btn-gold' }, ['Start race']);

      const pickEls = NAMES.map((name, i) =>
        Casino.el('div', { class: 'duck-opt' + (i === pick ? ' active' : '') }, ['🦆 ' + name]));
      pickEls.forEach((el, i) => el.addEventListener('click', () => {
        if (racing) return;
        pick = i;
        pickEls.forEach((x, j) => x.classList.toggle('active', j === i));
        highlightPick();
      }));

      function placeDucks(positions) {
        ducks.forEach((d, i) => {
          const pos = Math.max(0, Math.min(100, positions[i] || 0));
          d.style.left = 'calc(' + pos + '% - ' + (pos / 100) * 1.7 + 'rem)';
        });
      }
      function setLeader(idx) {
        ducks.forEach((d, i) => d.classList.toggle('leader', i === idx));
        lanes.forEach((l, i) => l.classList.toggle('leading', i === idx));
        leaderTag.innerHTML = '🚩 Leader: <b>' + NAMES[idx] + '</b>';
      }
      function clearRaceFx() {
        ducks.forEach((d) => d.classList.remove('bob', 'leader'));
        lanes.forEach((l) => l.classList.remove('leading'));
      }

      // Monotonic forward race: each duck has a random, always-positive set of
      // step sizes, normalised so it finishes at its server-ranked position.
      // Different step patterns => the lead keeps changing, but no one reverses.
      function buildFrames(order) {
        const TICKS = 80;
        const finalPos = [];
        order.forEach((idx, rank) => { finalPos[idx] = 100 - rank * 3; });
        const raw = [];
        for (let i = 0; i < COUNT; i++) {
          raw[i] = [0]; let acc = 0;
          for (let t = 1; t <= TICKS; t++) { acc += 0.2 + Math.random() * Math.random() * 3; raw[i].push(acc); }
        }
        const frames = [];
        for (let t = 1; t <= TICKS; t++) {
          const pos = [];
          for (let i = 0; i < COUNT; i++) pos[i] = raw[i][t] / raw[i][TICKS] * finalPos[i];
          let leader = 0;
          for (let i = 1; i < COUNT; i++) if (pos[i] > pos[leader]) leader = i;
          frames.push({ pos, leader });
        }
        return frames;
      }

      async function race() {
        if (racing) return;
        const amt = amount.value();
        const bad = Casino.ui.checkStake(amt, meta);
        if (bad) { Casino.toast(bad, 'err'); return; }
        racing = true; goBtn.disabled = true;
        result.className = 'result-line muted'; result.textContent = 'They’re off!';
        placeDucks([0, 0, 0, 0, 0]);
        ducks.forEach((d) => d.classList.add('bob'));

        try {
          const res = await Casino.api.play({ game: 'duckrace', bet: { amount: amt, duck: pick } });
          const o = res.outcome;
          const frames = buildFrames(o.order);
          for (let t = 0; t < frames.length; t++) {
            placeDucks(frames[t].pos);
            setLeader(frames[t].leader);
            await Casino.sleep(62); // a touch faster (~5s)
          }
          clearRaceFx();
          Casino.setBalance(res.balance);
          const winnerName = NAMES[o.winner];
          leaderTag.innerHTML = '🏁 <b>' + winnerName + '</b> wins!';
          if (o.win) {
            result.className = 'result-line win';
            result.textContent = '🏆 ' + winnerName + ' wins! 4× → +' + Casino.fmt(res.payout) + ' chips';
            Casino.confetti();
          } else {
            result.className = 'result-line lose';
            result.textContent = '🏁 ' + winnerName + ' wins. Your ' + NAMES[pick] + ' lost. −' + Casino.fmt(amt) + ' chips';
          }
        } catch (ex) {
          result.className = 'result-line lose'; result.textContent = ex.message; Casino.toast(ex.message, 'err');
        } finally {
          clearRaceFx();
          racing = false; goBtn.disabled = false;
        }
      }
      goBtn.addEventListener('click', race);

      root.appendChild(Casino.ui.gameHead('🦆', 'Duck Race', ctx));
      root.appendChild(Casino.el('div', { class: 'panel' }, [
        Casino.el('div', { class: 'stage' }, [
          Casino.el('div', { class: 'track' }, lanes),
          leaderTag,
          result,
        ]),
        Casino.el('div', { class: 'duck-pick' }, pickEls),
        Casino.el('div', { class: 'controls' }, [amount.node, goBtn]),
      ]));

      setTimeout(() => placeDucks([0, 0, 0, 0, 0]), 0);
    },
  };
})();
