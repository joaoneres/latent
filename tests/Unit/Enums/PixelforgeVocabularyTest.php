<?php

use App\Enums\ImageQuality;
use App\Enums\PixelforgeErrorCode;
use App\Enums\PixelforgeEventType;

it('retries only the temporary provider limits', function (PixelforgeErrorCode $code, int $status, bool $retries) {
    expect($code->retriesOn($status))->toBe($retries);
})->with([
    [PixelforgeErrorCode::RateLimitExceeded, 429, true],
    [PixelforgeErrorCode::ConcurrencyLimitExceeded, 429, true],
    [PixelforgeErrorCode::Overloaded, 503, true],
    [PixelforgeErrorCode::DailyLimitExceeded, 429, false],
    [PixelforgeErrorCode::Overloaded, 429, false],
    [PixelforgeErrorCode::ContentPolicyViolation, 400, false],
]);

it('applies a webhook to a generation except for a ping', function (PixelforgeEventType $type, bool $applies) {
    expect($type->appliesToGeneration())->toBe($applies);
})->with([
    [PixelforgeEventType::Ping, false],
    [PixelforgeEventType::GenerationProcessing, true],
    [PixelforgeEventType::GenerationSucceeded, true],
    [PixelforgeEventType::GenerationFailed, true],
]);

it('labels an image quality', function (ImageQuality $quality, string $label) {
    expect($quality->label())->toBe($label);
})->with([
    [ImageQuality::Low, 'Low'],
    [ImageQuality::Medium, 'Medium'],
    [ImageQuality::High, 'High'],
]);
