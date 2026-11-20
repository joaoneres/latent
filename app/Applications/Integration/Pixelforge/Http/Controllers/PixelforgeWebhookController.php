<?php

namespace App\Applications\Integration\Pixelforge\Http\Controllers;

use App\Applications\Integration\Pixelforge\Http\Handlers\HandlePixelforgeWebhookHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PixelforgeWebhookController
{
    public function store(Request $request, HandlePixelforgeWebhookHandler $handler): Response
    {
        $handler->handle($request);

        return response()->noContent(200);
    }
}
