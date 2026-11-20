<?php

use App\Services\Pixelforge\MoneyFormatter;

it('formats nanodollars for display', function (int $nano, string $formatted) {
    expect((new MoneyFormatter)->format($nano))->toBe($formatted);
})->with([
    [57_400_000, '$0,0574'],
    [373_875_000, '$0,373875'],
    [169_080_000, '$0,16908'],
    [467_343_750, '$0,467344'],
    [0, '$0,0000'],
    [1_000_000_000, '$1,0000'],
    [1_234_500_000_000, '$1.234,5000'],
]);
