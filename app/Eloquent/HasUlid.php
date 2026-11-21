<?php

namespace App\Eloquent;

use Illuminate\Support\Str;

trait HasUlid
{
    protected static function bootHasUlid(): void
    {
        static::creating(function ($model): void {
            if (! $model->public_id) {
                $model->public_id = (string) Str::ulid();
            }
        });
    }

    public function initializeHasUlid(): void
    {
        if (! isset($this->attributes['public_id'])) {
            $this->attributes['public_id'] = (string) Str::ulid();
        }
    }
}
