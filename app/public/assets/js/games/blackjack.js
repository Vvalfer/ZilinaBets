/* Blackjack — 6-deck shoe, dealer stands on 17, blackjack pays 3:2. Stateful.
   Cards are dealt one at a time from the shoe; the hole card flips on reveal. */
(function () {
  const SUIT = { S: '♠', H: '♥', D: '♦', C: '♣' };

  Casino.games.blackjack = {
    render(root, ctx) {
      const meta = ctx.meta;
      let roundId = null, stake = 0, busy = false;
      let playerCount = 0, dealerInit = false;

      const dealerCards = Casino.el('div', { class: 'cards' }, []);
      const playerCards = Casino.el('div', { class: 'cards' }, []);
      const dealerTotal = Casino.el('span', {}, ['']);
      const playerTotal = Casino.el('span', {}, ['']);
      const message = Casino.el('div', { class: 'result-line muted' }, ['Place your bet and deal.']);

      const amount = Casino.ui.amountControl(meta);
      const dealBtn = Casino.el('button', { class: 'btn btn-pink' }, ['Deal']);
      const hitBtn = Casino.el('button', { class: 'btn', style: 'display:none' }, ['Hit']);
      const standBtn = Casino.el('button', { class: 'btn btn-gold', style: 'display:none' }, ['Stand']);
      const dblBtn = Casino.el('button', { class: 'btn btn-blue', style: 'display:none' }, ['Double']);

      function cardEl(card) {
        const red = card.suit === 'H' || card.suit === 'D';
        const s = SUIT[card.suit] || '?';
        return Casino.el('div', { class: 'card dealt' + (red ? ' red-suit' : '') }, [
          Casino.el('div', { class: 'r-top' }, [card.rank + s]),
          Casino.el('div', { class: 'r-suit' }, [s]),
          Casino.el('div', { class: 'r-bot' }, [card.rank + s]),
        ]);
      }
      const backEl = () => Casino.el('div', { class: 'card back dealt' }, []);

      function resetTable() {
        playerCards.innerHTML = ''; dealerCards.innerHTML = '';
        playerCount = 0; dealerInit = false;
        playerTotal.textContent = ''; dealerTotal.textContent = '';
      }

      // Append only the player cards we haven't drawn yet (so a Hit animates just the new card).
      function syncPlayer(cards) {
        for (let i = playerCount; i < cards.length; i++) {
          const el = cardEl(cards[i]);
          el.style.animationDelay = ((i - playerCount) * 0.12) + 's';
          playerCards.appendChild(el);
        }
        playerCount = cards.length;
      }

      function dealerUp(upCard) {
        if (dealerInit) return;
        const up = cardEl(upCard);
        const back = backEl(); back.style.animationDelay = '.12s';
        dealerCards.appendChild(up);
        dealerCards.appendChild(back);
        dealerInit = true;
      }

      // Reveal: card 0 stays, the hole card (1) flips in, any extra draws deal in.
      function dealerRevealAll(dealer) {
        dealerCards.innerHTML = '';
        dealer.forEach((c, i) => {
          const el = cardEl(c);
          el.classList.remove('dealt');
          if (i === 1) {
            el.classList.add('flip');
          } else if (i >= 2) {
            el.classList.add('dealt');
            el.style.animationDelay = (0.4 + (i - 2) * 0.18) + 's';
          }
          dealerCards.appendChild(el);
        });
      }

      function showActions(next) {
        const has = (a) => next && next.indexOf(a) !== -1;
        hitBtn.style.display = has('hit') ? '' : 'none';
        standBtn.style.display = has('stand') ? '' : 'none';
        const canAffordDouble = Casino.getBalance() >= stake;
        dblBtn.style.display = has('double') && canAffordDouble ? '' : 'none';
        dealBtn.style.display = 'none';
        amount.input.disabled = true;
      }
      function hideActions() {
        hitBtn.style.display = standBtn.style.display = dblBtn.style.display = 'none';
        dealBtn.style.display = ''; dealBtn.textContent = 'Play again';
        amount.input.disabled = false;
      }

      const RESULT_TXT = { win: 'You win!', lose: 'Dealer wins.', push: 'Push — stake returned.', blackjack: 'BLACKJACK! Pays 3:2.' };
      const RESULT_CLS = { win: 'win', blackjack: 'win', push: 'push', lose: 'lose' };

      function resolved(res) {
        const o = res.outcome;
        syncPlayer(o.player);
        playerTotal.textContent = ' (' + o.playerTotal + ')';
        dealerRevealAll(o.dealer);
        dealerTotal.textContent = ' (' + o.dealerTotal + ')';
        Casino.setBalance(res.balance);
        message.className = 'result-line ' + (RESULT_CLS[o.result] || 'muted');
        let txt = RESULT_TXT[o.result] || o.result;
        if (o.result === 'win' || o.result === 'blackjack') { txt += ' +' + Casino.fmt(res.net) + ' chips'; Casino.confetti(); }
        else if (o.result === 'lose') txt += ' −' + Casino.fmt(-res.net) + ' chips';
        message.textContent = txt;
        roundId = null;
        hideActions();
      }

      function inProgress(res) {
        const o = res.outcome;
        stake = res.stake || stake;
        roundId = res.roundId;
        syncPlayer(o.player);
        playerTotal.textContent = ' (' + o.playerTotal + ')';
        dealerUp(o.dealerUpCard);
        dealerTotal.textContent = ' (?)';
        message.className = 'result-line muted';
        message.textContent = 'Your move.';
        showActions(res.nextActions);
      }

      async function deal() {
        if (busy) return;
        const amt = amount.value();
        const bad = Casino.ui.checkStake(amt, meta);
        if (bad) { Casino.toast(bad, 'err'); return; }
        busy = true; dealBtn.disabled = true;
        resetTable();
        message.className = 'result-line muted'; message.textContent = 'Dealing…';
        try {
          const res = await Casino.api.play({ game: 'blackjack', bet: { amount: amt } });
          stake = amt;
          if (res.status === 'resolved') resolved(res); else inProgress(res);
        } catch (ex) {
          message.className = 'result-line lose'; message.textContent = ex.message; Casino.toast(ex.message, 'err');
        } finally {
          busy = false; dealBtn.disabled = false;
        }
      }

      async function act(action) {
        if (busy || !roundId) return;
        busy = true; hitBtn.disabled = standBtn.disabled = dblBtn.disabled = true;
        try {
          const res = await Casino.api.play({ roundId, bet: { action } });
          if (res.status === 'resolved') resolved(res); else inProgress(res);
        } catch (ex) {
          message.className = 'result-line lose'; message.textContent = ex.message; Casino.toast(ex.message, 'err');
        } finally {
          busy = false; hitBtn.disabled = standBtn.disabled = dblBtn.disabled = false;
        }
      }

      dealBtn.addEventListener('click', deal);
      hitBtn.addEventListener('click', () => act('hit'));
      standBtn.addEventListener('click', () => act('stand'));
      dblBtn.addEventListener('click', () => act('double'));

      root.appendChild(Casino.ui.gameHead('🃏', 'Blackjack', ctx));
      root.appendChild(Casino.el('div', { class: 'panel' }, [
        Casino.el('div', { class: 'stage' }, [
          Casino.el('div', { class: 'felt' }, [
            Casino.el('div', { class: 'hand-row' }, [
              Casino.el('div', { class: 'hand-label' }, [Casino.el('span', {}, ['Dealer']), dealerTotal]),
              dealerCards,
            ]),
            Casino.el('div', { class: 'hand-row' }, [
              Casino.el('div', { class: 'hand-label' }, [Casino.el('span', {}, ['You']), playerTotal]),
              playerCards,
            ]),
          ]),
          message,
        ]),
        Casino.el('div', { class: 'controls' }, [amount.node, dealBtn, hitBtn, standBtn, dblBtn]),
      ]));
    },
  };
})();
