/* Lucky Reels — three reels, win on three of a kind. Real spinning strips. */
(function () {
  const EMOJI = { cherry: '🍒', lemon: '🍋', bell: '🔔', star: '⭐', diamond: '💎', seven: '7️⃣' };
  const SYMS = Object.keys(EMOJI);
  const TILE = 104;       // must match .reel-tile height in CSS
  const LEAD = 26;        // random tiles scrolled through before the result
  const DUR = [1.7, 2.1, 2.5]; // per-reel spin seconds (staggered stop)
  const rand = () => SYMS[Math.floor(Math.random() * SYMS.length)];

  // son du 67 : préchargé tout de suite pour démarrer instantanément (joué une fois)
  const eggAudio = new Audio('/assets/audio/sixseven.wav');
  eggAudio.volume = 0.85;
  eggAudio.preload = 'auto';

  // image du Kangoo sauvage (préchargée). Si le fichier n'est pas là → 🚐 en secours.
  const kangooImg = new Image();
  kangooImg.src = '/assets/img/kangoo.png';

  // ---- Le 67 : gros montage MLG / CS:GO 2014 ----
  function sixSeven(payout) {
    const ov = document.createElement('div');
    ov.id = 'six-seven';
    ov.style.pointerEvents = 'auto';                  // cliquable pour passer
    const big = document.createElement('div'); big.className = 'big67'; big.textContent = '6️⃣7️⃣';
    const tag = document.createElement('div'); tag.className = 'mlg-tag'; tag.innerHTML = 'SIX&nbsp;SEVEN';
    const hint = document.createElement('div'); hint.className = 'mlg-hint'; hint.textContent = '(clique pour passer)';
    ov.appendChild(big); ov.appendChild(tag); ov.appendChild(hint);
    if (payout) { const pay = document.createElement('div'); pay.className = 'mlg-payout'; pay.textContent = '+' + payout; ov.appendChild(pay); }
    document.body.appendChild(ov);

    // le son démarre tout de suite, dès le début de l'animation
    eggAudio.currentTime = 0;
    eggAudio.play().catch(() => {});

    const stuff = ['67', '6-7', 'SIX SEVEN', '67!!!', 'MLG', 'WOW', 'GET REKT', '+670',
      '🔥', '💥', '🤑', '⚡', '🎉', '👑', '🚀', '😎', '🔺'];
    const centers = ['6️⃣7️⃣', '67', '🔥', '🤑', '💥'];
    const tags = ['SIX&nbsp;SEVEN', '6️⃣7️⃣', '67 !!!', 'M&nbsp;L&nbsp;G', 'WOW', 'GET&nbsp;REKT'];

    const burst = setInterval(() => {
      const count = 1 + Math.floor(Math.random() * 2);     // 1 ou 2 à la fois
      for (let n = 0; n < count; n++) {
        const s = document.createElement('div');
        s.className = 's67';
        s.textContent = stuff[Math.floor(Math.random() * stuff.length)];
        s.style.left = Math.random() * 90 + 'vw';
        s.style.top = Math.random() * 85 + 'vh';
        s.style.fontSize = (1.6 + Math.random() * 4) + 'rem';
        ov.appendChild(s);
        setTimeout(() => s.remove(), 1200);
      }
    }, 140);

    // petites variations pour ne pas tourner en rond sur toute la durée
    let k = 0;
    const variate = setInterval(() => {
      k++;
      big.textContent = centers[k % centers.length];
      tag.innerHTML = tags[k % tags.length];
      if (k % 4 === 0) {
        const g = document.createElement('div'); g.className = 'mlg-glasses'; g.textContent = '🕶️';
        ov.appendChild(g); setTimeout(() => g.remove(), 1500);
      }
    }, 1000);

    // le Kangoo sauvage qui traverse l'écran dans les deux sens, plein de fois
    const kangooTimer = setInterval(() => {
      const ltr = Math.random() < 0.5;
      const car = document.createElement('div');
      car.className = 'kangoo ' + (ltr ? 'ltr' : 'rtl');
      car.style.top = (8 + Math.random() * 72) + 'vh';
      car.style.width = (130 + Math.random() * 150) + 'px';
      const d = 0.8 + Math.random() * 0.9;
      car.style.animationDuration = d + 's';
      const im = document.createElement('img');
      im.src = '/assets/img/kangoo.png'; im.alt = '';
      car.appendChild(im);
      ov.appendChild(car);
      setTimeout(() => car.remove(), d * 1000 + 200);
    }, 600);

    // première paire de lunettes
    setTimeout(() => {
      const g = document.createElement('div'); g.className = 'mlg-glasses'; g.textContent = '🕶️';
      ov.appendChild(g); setTimeout(() => g.remove(), 1500);
    }, 450);

    let done = false;
    function finish() {
      if (done) return; done = true;
      clearInterval(burst); clearInterval(variate); clearInterval(kangooTimer);
      eggAudio.pause();
      ov.style.transition = 'opacity .5s';
      ov.style.opacity = '0';
      setTimeout(() => ov.remove(), 500);
    }
    ov.addEventListener('click', finish);                        // clic = passer
    eggAudio.addEventListener('ended', finish, { once: true });  // dure toute la durée du son
    const dur = (isFinite(eggAudio.duration) && eggAudio.duration > 0) ? eggAudio.duration * 1000 : 33000;
    setTimeout(finish, dur + 800);                               // filet de sécurité
  }

  Casino.games.slots = {
    render(root, ctx) {
      const meta = ctx.meta;
      const strips = [];
      const reels = [0, 1, 2].map((i) => {
        const strip = Casino.el('div', { class: 'reel-strip' }, [
          Casino.el('div', { class: 'reel-tile' }, [EMOJI[rand()]]),
        ]);
        strips.push(strip);
        return Casino.el('div', { class: 'reel' }, [strip]);
      });

      const result = Casino.el('div', { class: 'result-line muted' }, ['Pull the lever!']);
      const amount = Casino.ui.amountControl(meta);
      const spinBtn = Casino.el('button', { class: 'btn btn-pink' }, ['Spin']);

      const rows = Object.keys(meta.slotsPayouts)
        .sort((a, b) => meta.slotsPayouts[a] - meta.slotsPayouts[b])
        .map((s) => Casino.el('tr', {}, [
          Casino.el('td', {}, [EMOJI[s] + ' ' + EMOJI[s] + ' ' + EMOJI[s]]),
          Casino.el('td', {}, [meta.slotsPayouts[s] + '×']),
        ]));
      // ligne mystère : le 67 est dans le tableau, mais censuré (ni emoji ni multiplicateur)
      rows.push(Casino.el('tr', { class: 'mystery67' }, [
        Casino.el('td', {}, ['❔ ❔ ❔']),
        Casino.el('td', {}, ['???']),
      ]));

      let spinning = false;

      // Build a reel strip ending on the real result symbol, then animate it in.
      function startReel(i, finalSym) {
        const strip = strips[i];
        const tiles = [];
        for (let k = 0; k < LEAD; k++) tiles.push(Casino.el('div', { class: 'reel-tile' }, [EMOJI[rand()]]));
        tiles.push(Casino.el('div', { class: 'reel-tile' }, [EMOJI[finalSym] || '❔']));
        strip.innerHTML = '';
        tiles.forEach((t) => strip.appendChild(t));
        // Measure the actual (responsive) tile height so the landing is exact.
        const tile = Math.round(reels[i].getBoundingClientRect().height) || TILE;
        strip.style.transition = 'none';
        strip.style.transform = 'translateY(0)';
        void strip.offsetHeight; // force reflow
        strip.style.transition = 'transform ' + DUR[i] + 's cubic-bezier(.12,.73,.2,1)';
        strip.style.transform = 'translateY(' + (-(LEAD) * tile) + 'px)';
        reels[i].classList.remove('win-flash');
      }

      async function spin() {
        if (spinning) return;
        const amt = amount.value();
        const bad = Casino.ui.checkStake(amt, meta);
        if (bad) { Casino.toast(bad, 'err'); return; }

        spinning = true; spinBtn.disabled = true;
        result.className = 'result-line muted'; result.textContent = 'Spinning…';

        try {
          // Resolve on the server FIRST, then spin the reels to that exact result —
          // correct regardless of network latency.
          const res = await Casino.api.play({ game: 'slots', bet: { amount: amt } });
          const o = res.outcome;
          Casino.setBalance(res.balance);
          const egg = o.jackpot === true;            // le 67 (décidé serveur, ~7%, ×670)
          const SS = ['0', '6', '7'];
          [0, 1, 2].forEach((i) => {
            startReel(i, o.reels[i]);
            if (egg) strips[i].lastChild.textContent = SS[i];   // affiche 0, 6, 7
          });
          await Casino.sleep(DUR[2] * 1000 + 120);

          if (egg) {
            reels.forEach((r) => r.classList.add('win-flash'));
            result.className = 'result-line win';
            result.textContent = '0️⃣6️⃣7️⃣ SIX-SEVEN !!! ×670 → +' + Casino.fmt(res.payout) + ' chips';
            sixSeven(Casino.fmt(res.payout));
            Casino.confetti();
          } else if (o.win) {
            reels.forEach((r) => r.classList.add('win-flash'));
            result.className = 'result-line win';
            result.textContent = '★ Three ' + EMOJI[o.symbol] + ' — ' + o.multiplier + '× → +' + Casino.fmt(res.payout) + ' chips';
            Casino.confetti();
          } else {
            result.className = 'result-line lose';
            result.textContent = 'No match. −' + Casino.fmt(amt) + ' chips';
          }
        } catch (ex) {
          result.className = 'result-line lose';
          result.textContent = ex.message;
          Casino.toast(ex.message, 'err');
        } finally {
          spinning = false; spinBtn.disabled = false;
        }
      }
      spinBtn.addEventListener('click', spin);

      root.appendChild(Casino.ui.gameHead('🎰', 'Lucky Reels', ctx));
      root.appendChild(Casino.el('div', { class: 'panel' }, [
        Casino.el('div', { class: 'stage' }, [
          Casino.el('div', { class: 'slot-machine' }, [Casino.el('div', { class: 'reels' }, reels)]),
          result,
        ]),
        Casino.el('div', { class: 'controls' }, [amount.node, spinBtn]),
        Casino.el('details', { class: 'payouts' }, [
          Casino.el('summary', {}, ['Paytable — 3 of a kind']),
          Casino.el('table', { class: 'paytable' }, rows),
        ]),
      ]));
    },
  };
})();
