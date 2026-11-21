<?php

namespace App\Applications\App\Generation\Http\Handlers;

use App\Applications\Http\HandlesHttpRequest;
use App\Services\Pixelforge\AdminReportFilters;
use App\Services\Pixelforge\AdminReportLines;
use Illuminate\Http\Request;

class DownloadAdminReportHandler implements HandlesHttpRequest
{
    /** @var array{from: string|null, to: string|null, user_id: string|null, status: string|null} */
    private array $filters;

    public function __construct(
        private Request $request,
        private AdminReportLines $lines,
        private AdminReportFilters $filtersReader,
    ) {}

    public function handle(): void
    {
        $this->readFilters($this->request);
        $this->publish($this->request);
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
