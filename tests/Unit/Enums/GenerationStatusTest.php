<?php

use App\Enums\GenerationStatus;

it('ranks a generation status by how far it has advanced', function (GenerationStatus $status, int $rank) {
    expect($status->rank())->toBe($rank);
})->with([
    [GenerationStatus::Pending, 0],
    [GenerationStatus::Queued, 1],
    [GenerationStatus::Processing, 2],
    [GenerationStatus::Succeeded, 3],
    [GenerationStatus::Failed, 3],
]);

it('treats only succeeded and failed as final', function (GenerationStatus $status, bool $final) {
    expect($status->isFinal())->toBe($final);
})->with([
    [GenerationStatus::Pending, false],
    [GenerationStatus::Queued, false],
    [GenerationStatus::Processing, false],
    [GenerationStatus::Succeeded, true],
    [GenerationStatus::Failed, true],
]);
