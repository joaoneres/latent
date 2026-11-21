<?php

namespace App\Applications\Integration\Pixelforge\Http\Controllers;

use App\Applications\Integration\Pixelforge\Http\Handlers\HandlePixelforgeWebhookHandler;
use Illuminate\Http\Response;

class PixelforgeWebhookController
{
    public function store(HandlePixelforgeWebhookHandler $handler): Response
    {
        $handler->handle();

        return response()->noContent(200);
    }
}
