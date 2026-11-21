<?php

namespace App\Http\Controllers;

use App\Applications\App\Generation\Http\Handlers\DownloadAdminReportHandler;
use App\Applications\App\Generation\Http\Handlers\ListAdminReportHandler;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportController extends Controller
{
    public function index(Request $request, ListAdminReportHandler $handler): Response
    {
        $handler->handle();

        return Inertia::render('Reports/Index', [
            'summary' => $request->attributes->get('summary'),
            'days' => $request->attributes->get('days'),
            'generations' => $request->attributes->get('generations'),
            'filters' => $request->attributes->get('filters'),
            'users' => $request->attributes->get('users'),
        ]);
    }

    public function download(Request $request, DownloadAdminReportHandler $handler): StreamedResponse
    {
        $handler->handle();
        /** @var list<array<string, int|string>> $lines */
        $lines = $request->attributes->get('lines');

        return response()->streamDownload(function () use ($lines): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fputcsv($output, [
                'ID',
                'Completed at (UTC)',
                'User',
                'Email',
                'Status',
                'Prompt',
                'Size',
                'Quality',
                'Charged',
                'Provider cost',
                'Profit',
                'Error code',
                'Error message',
                'Pixelforge ID',
            ]);

            foreach ($lines as $line) {
                fputcsv($output, [
                    $line['id'],
                    $line['completed_at'],
                    $line['user'],
                    $line['email'],
                    $line['status'],
                    $line['prompt'],
                    $line['size'],
                    $line['quality'],
                    $line['charged'],
                    $line['provider_cost'],
                    $line['profit'],
                    $line['error_code'],
                    $line['error_message'],
                    $line['pixelforge_id'],
                ]);
            }

            fclose($output);
        }, 'latent-report.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
