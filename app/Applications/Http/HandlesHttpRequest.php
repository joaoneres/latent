<?php

namespace App\Applications\Http;

interface HandlesHttpRequest
{
    public function handle(): void;
}
