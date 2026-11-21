<?php

namespace App\Enums;

enum PixelforgeEventType: string
{
    case Ping = 'ping';
    case GenerationProcessing = 'generation.processing';
    case GenerationSucceeded = 'generation.succeeded';
    case GenerationFailed = 'generation.failed';

    public function appliesToGeneration(): bool
    {
        return $this !== self::Ping;
    }
}
