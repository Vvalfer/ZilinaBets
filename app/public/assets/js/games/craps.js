/* Craps — Pass Line. 7/11 win, 2/3/12 lose on the come-out; otherwise chase
   your point before a 7. Even-money payout. Stateful (point lives on server). */
(function () {
  Casino.games.craps = {
    render(root, ctx) {
      const meta = ctx.meta;
      let roundId = null, busy = false;

      function pips(v) { const a = []; for (let i = 0; i < v; i++) a.push(Casino.el('div', { class: 'pip' })); return a; }
      function makeDie() { return Casino.el('div', { class: 'die', 'data-v': 1 }, pips(1)); }
      function setFace(die, v) { die.setAttribute('data-v', v); die.innerHTML = ''; pips(v).forEach((p) => die.appendChild(p)); }

      const dieA = makeDie(), dieB = makeDie();
      const diceRow = Casino.el('div', { class: 'dice-row' }, [dieA, dieB]);
      const phase = Casino.el('div', { class: 'craps-phase' }, ['Come-out roll']);
      const info = Casino.el('div', { class: 'result-line muted' }, ['7 or 11 wins · 2, 3, 12 loses · else it’s your point.']);
      const amount = Casino.ui.amountControl(meta);
      const rollBtn = Casino.el('button', { class: 'btn btn-pink' }, ['Roll']);

      async function tumble(finalA, finalB) {
        dieA.classList.add('rolling'); dieB.classList.add('rolling');
        for (let f = 0; f < 10; f++) {
          setFace(dieA, 1 + Math.floor(Math.random() * 6));
          setFace(dieB, 1 + Math.floor(Math.random() * 6));
          await Casino.sleep(55);
        }
        dieA.classList.remove('rolling'); dieB.classList.remove('rolling');
        setFace(dieA, finalA); setFace(dieB, finalB);
        dieA.classList.add('landing'); dieB.classList.add('landing');
        setTimeout(() => { dieA.classList.remove('landing'); dieB.classList.remove('landing'); }, 460);
      }

      function apply(res) {
        const o = res.outcome;
        Casino.setBalance(res.balance);
        if (res.status === 'in_progress') {
          roundId = res.roundId;
          phase.innerHTML = 'Point: <b>' + o.point + '</b> — roll ' + o.point + ' again before a 7';
          info.className = 'result-line muted';
          info.textContent = 'You rolled ' + o.sum + '. Keep rolling!';
          amount.input.disabled = true;
          rollBtn.textContent = 'Roll for point';
        } else {
          roundId = null;
          if (o.result === 'win') {
            info.className = 'result-line win';
            info.textContent = (o.point ? 'Hit your point ' + o.point + '!' : 'Rolled ' + o.sum + '!') + ' WIN +' + Casino.fmt(res.net) + ' chips';
            Casino.confetti();
          } else {
            info.className = 'result-line lose';
            const why = o.point ? 'seven out' : 'craps (' + o.sum + ')';
            info.textContent = 'Rolled ' + o.sum + ' — ' + why + '. −' + Casino.fmt(-res.net) + ' chips';
          }
          phase.textContent = 'Come-out roll';
          amount.input.disabled = false;
          rollBtn.textContent = 'Roll again';
        }
      }

      async function roll() {
        if (busy) return;
        let payload;
        if (roundId === null) {
          const amt = amount.value();
          const bad = Casino.ui.checkStake(amt, meta);
          if (bad) { Casino.toast(bad, 'err'); return; }
          payload = { bet: { amount: amt } };
        } else {
          payload = { roundId };
        }
        busy = true; rollBtn.disabled = true;
        info.className = 'result-line muted'; info.textContent = 'Rolling…';
        try {
          const res = await Casino.api.craps(payload);
          await tumble(res.outcome.dice[0], res.outcome.dice[1]);
          apply(res);
        } catch (ex) {
          info.className = 'result-line lose'; info.textContent = ex.message; Casino.toast(ex.message, 'err');
        } finally {
          busy = false; rollBtn.disabled = false;
        }
      }
      rollBtn.addEventListener('click', roll);

      root.appendChild(Casino.ui.gameHead('🎲', '421', ctx));
      root.appendChild(Casino.el('div', { class: 'panel' }, [
        Casino.el('div', { class: 'stage' }, [
          Casino.el('div', { class: 'craps-felt' }, [diceRow, phase]),
          info,
        ]),
        Casino.el('div', { class: 'controls' }, [amount.node, rollBtn]),
        Casino.el('details', { class: 'payouts' }, [
          Casino.el('summary', {}, ['How to play']),
          Casino.el('div', { style: 'padding:.2rem .8rem .6rem; font-size:.82rem; color:var(--ink-soft); line-height:1.5' }, [
            'Come-out roll: 7 or 11 wins, 2 / 3 / 12 loses. Any other number becomes your "point". ' +
            'Then keep rolling — hit the point again to win, but a 7 loses. Pass line pays even money (2×).',
          ]),
        ]),
      ]));
    },
  };
})();
