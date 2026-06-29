<?php

declare(strict_types=1);

/**
 * Generates stable example responses (one file per game) so the frontend can
 * build and test animations without a running backend. Deterministic: uses
 * SeededRandom, so re-running produces identical fixtures.
 *
 *     php tools/generate-mocks.php
 */

require __DIR__ . '/../autoload.php';

use CasinoEngine\games\Blackjack;
use CasinoEngine\games\DuckRace;
use CasinoEngine\games\Game421;
use CasinoEngine\games\Roulette;
use CasinoEngine\games\Slots;
use CasinoEngine\SeededRandom;

$outDir = __DIR__ . '/../mock';

/**
 * The blackjack shoe (310+ cards) is server-only state; the frontend never
 * needs it. Replace it with a short placeholder so fixtures stay readable.
 */
function sanitize(array $node): array
{
    array_walk_recursive($node, static function (&$v, $k): void {});
    if (isset($node['response']['state']['shoe']) && is_array($node['response']['state']['shoe'])) {
        $node['response']['state']['shoe'] = '<' . count($node['response']['state']['shoe']) . ' cards kept server-side>';
    }
    if (isset($node['request']['state']['shoe']) && is_array($node['request']['state']['shoe'])) {
        $node['request']['state']['shoe'] = '<' . count($node['request']['state']['shoe']) . ' cards kept server-side>';
    }
    return $node;
}

function dump(string $dir, string $name, array $data): void
{
    if (isset($data['steps'])) {
        $data['steps'] = array_map('sanitize', $data['steps']);
    } else {
        $data = sanitize($data);
    }
    file_put_contents(
        "$dir/$name.json",
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
    );
    echo "wrote mock/$name.json\n";
}

// --- Roulette: a winning straight on 17 -------------------------------------
$r = new Roulette();
$rng = new SeededRandom(101);
do {
    $res = $r->play(['amount' => 10, 'type' => 'straight', 'value' => 17], $rng);
} while (!$res->outcome['win']); // pick a seed-run that lands a win, for a richer sample
dump($outDir, 'roulette', [
    'request' => ['game' => 'roulette', 'bet' => ['amount' => 10, 'type' => 'straight', 'value' => 17]],
    'response' => $res->toArray(),
]);

// --- Slots: a winning spin --------------------------------------------------
$s = new Slots();
$rng = new SeededRandom(7);
do {
    $res = $s->play(['amount' => 5], $rng);
} while (!$res->outcome['win']);
dump($outDir, 'slots', [
    'request' => ['game' => 'slots', 'bet' => ['amount' => 5]],
    'response' => $res->toArray(),
]);

// --- Duck race: a full race with timeline -----------------------------------
$d = new DuckRace();
$rng = new SeededRandom(55);
$res = $d->play(['amount' => 10, 'duck' => 0], $rng);
dump($outDir, 'duckrace', [
    'request' => ['game' => 'duckrace', 'bet' => ['amount' => 10, 'duck' => 0]],
    'response' => $res->toArray(),
]);

// --- 421: deal then stand (multi-step) --------------------------------------
$g = new Game421();
$rng = new SeededRandom(3);
$deal = $g->play(['amount' => 10], $rng);
$final = $g->play(['action' => 'stand'], $rng, $deal->state);
dump($outDir, '421', [
    'steps' => [
        ['request' => ['game' => '421', 'bet' => ['amount' => 10]], 'response' => $deal->toArray()],
        ['request' => ['game' => '421', 'bet' => ['action' => 'stand'], 'state' => $deal->state], 'response' => $final->toArray()],
    ],
]);

// --- Blackjack: deal then stand (multi-step, hole card hidden until resolve) -
$b = new Blackjack();
$rng = new SeededRandom(9);
$deal = $b->play(['amount' => 10], $rng);
if ($deal->isResolved()) {
    // Natural on the deal; one step is enough.
    dump($outDir, 'blackjack', [
        'steps' => [
            ['request' => ['game' => 'blackjack', 'bet' => ['amount' => 10]], 'response' => $deal->toArray()],
        ],
    ]);
} else {
    $final = $b->play(['action' => 'stand'], $rng, $deal->state);
    dump($outDir, 'blackjack', [
        'steps' => [
            ['request' => ['game' => 'blackjack', 'bet' => ['amount' => 10]], 'response' => $deal->toArray()],
            ['request' => ['game' => 'blackjack', 'bet' => ['action' => 'stand'], 'state' => $deal->state], 'response' => $final->toArray()],
        ],
    ]);
}

echo "done.\n";
