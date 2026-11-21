<?php

namespace App\Applications\App\Generation\Http\Handlers;

use App\Services\Pixelforge\AdminReportFilters;
use App\Services\Pixelforge\AdminReportLines;
use Illuminate\Http\Request;

class DownloadAdminReportHandler
{
    /** @var array{from: string|null, to: string|null, user_id: int|null, status: string|null} */
    private array $filters;

    public function __construct(
        private AdminReportLines $lines,
        private AdminReportFilters $filtersReader,
    ) {}

    public function handle(Request $request): void
    {
        $this->readFilters($request);
        $this->publish($request);
    }

    private function readFilters(Request $request): void
    {
        $this->filters = $this->filtersReader->fromRequest($request);
    }

    private function publish(Request $request): void
    {
        $request->attributes->set('lines', $this->lines->present($this->lines->completed($this->filters)));
    }
}
