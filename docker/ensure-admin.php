<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$user = User::query()->firstOrNew(['email' => 'test@example.com']);
$user->name = 'Test User';

if (! $user->exists) {
    $user->password = 'password';
}

$user->email_verified_at ??= now();
$user->is_admin = true;
$user->save();
