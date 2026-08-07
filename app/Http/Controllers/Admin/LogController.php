<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LogController extends Controller
{
    private const MAX_LINES_PER_FILE = 5000;
    private const MAX_ENTRIES = 250;

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'source' => ['nullable', 'in:all,sunat,laravel'],
            'level' => ['nullable', 'string', 'max:20'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $filters = [
            'source' => $filters['source'] ?? 'all',
            'level' => strtoupper((string) ($filters['level'] ?? '')),
            'q' => trim((string) ($filters['q'] ?? '')),
        ];

        return Inertia::render('admin/logs/index', [
            'entries' => $this->readEntries($filters),
            'filters' => $filters,
            'stats' => $this->stats(),
            'sources' => [
                ['value' => 'all', 'label' => 'Todos'],
                ['value' => 'sunat', 'label' => 'SUNAT'],
                ['value' => 'laravel', 'label' => 'Sistema'],
            ],
            'levels' => ['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'],
        ]);
    }

    /**
     * @param array{source:string,level:string,q:string} $filters
     * @return array<int,array<string,mixed>>
     */
    private function readEntries(array $filters): array
    {
        $entries = [];

        foreach ($this->logFiles($filters['source']) as $source => $files) {
            foreach ($files as $file) {
                foreach ($this->tail($file) as $line) {
                    $entry = $this->parseLine($line, $source, basename($file));

                    if ($filters['level'] !== '' && $entry['level'] !== $filters['level']) {
                        continue;
                    }

                    if ($filters['q'] !== '' && ! str_contains(strtolower($entry['raw']), strtolower($filters['q']))) {
                        continue;
                    }

                    $entries[] = $entry;
                }
            }
        }

        usort($entries, fn (array $a, array $b) => strcmp((string) $b['datetime'], (string) $a['datetime']));

        return array_slice($entries, 0, self::MAX_ENTRIES);
    }

    /**
     * @return array<string,array<int,string>>
     */
    private function logFiles(string $source): array
    {
        $all = [
            'sunat' => $this->matchingFiles(storage_path('logs/sunat*.log')),
            'laravel' => $this->matchingFiles(storage_path('logs/laravel*.log')),
        ];

        if ($source === 'sunat') {
            return ['sunat' => $all['sunat']];
        }

        if ($source === 'laravel') {
            return ['laravel' => $all['laravel']];
        }

        return $all;
    }

    /**
     * @return array<int,string>
     */
    private function matchingFiles(string $pattern): array
    {
        $files = array_filter(glob($pattern) ?: [], 'is_readable');
        usort($files, fn (string $a, string $b) => filemtime($b) <=> filemtime($a));

        return array_slice($files, 0, 5);
    }

    /**
     * @return array<int,string>
     */
    private function tail(string $file): array
    {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        return array_slice($lines, -self::MAX_LINES_PER_FILE);
    }

    /**
     * @return array<string,mixed>
     */
    private function parseLine(string $line, string $source, string $file): array
    {
        $entry = [
            'datetime' => null,
            'source' => $source,
            'level' => 'INFO',
            'message' => $line,
            'context' => null,
            'file' => $file,
            'raw' => $line,
        ];

        if (preg_match('/^\[(?<datetime>[^\]]+)]\s+(?<env>[^.]+)\.(?<level>[A-Z]+):\s+(?<message>.*)$/', $line, $matches)) {
            $entry['datetime'] = $matches['datetime'];
            $entry['level'] = $matches['level'];
            $entry['message'] = $matches['message'];

            if (preg_match('/^(?<message>.*?)\s+(?<json>\{.*})$/', $matches['message'], $jsonMatches)) {
                $decoded = json_decode($jsonMatches['json'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $entry['message'] = $jsonMatches['message'];
                    $entry['context'] = $decoded;
                }
            }
        }

        return $entry;
    }

    /**
     * @return array<string,int>
     */
    private function stats(): array
    {
        return [
            'sunat_files' => count($this->matchingFiles(storage_path('logs/sunat*.log'))),
            'laravel_files' => count($this->matchingFiles(storage_path('logs/laravel*.log'))),
            'max_entries' => self::MAX_ENTRIES,
        ];
    }
}
