<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class LogRotateCommand extends Command
{
    protected $signature = 'logs:rotate {--lines=5000 : Keep only last N lines} {--all : Rotate all log files}';
    protected $description = 'Rotate and clean Laravel log files';

    public function handle(): int
    {
        $logPath = storage_path('logs');
        $linesToKeep = (int) $this->option('lines');
        $rotateAll = $this->option('all');

        $this->info("🔄 Rotating logs in: {$logPath}");
        $this->newLine();

        if (!File::exists($logPath)) {
            $this->warn("Log directory does not exist.");
            return self::SUCCESS;
        }

        $files = $rotateAll
            ? File::glob($logPath . '/*.log')
            : [$logPath . '/laravel.log'];

        $totalSaved = 0;

        foreach ($files as $file) {
            if (!File::exists($file)) {
                continue;
            }

            $filename = basename($file);
            $originalSize = File::size($file);
            $lineCount = $this->countLines($file);

            $this->line("📄 {$filename}:");
            $this->line("   Size: " . $this->formatBytes($originalSize));
            $this->line("   Lines: " . number_format($lineCount));

            if ($lineCount <= $linesToKeep) {
                $this->info("   ✅ Already under limit ({$linesToKeep} lines)");
                continue;
            }

            // Rotate the file
            $this->rotateFile($file, $linesToKeep);

            $newSize = File::exists($file) ? File::size($file) : 0;
            $saved = $originalSize - $newSize;
            $totalSaved += $saved;

            $this->info("   ✅ Rotated - Saved: " . $this->formatBytes($saved));
            $this->newLine();
        }

        $this->info("✅ Total space saved: " . $this->formatBytes($totalSaved));

        return self::SUCCESS;
    }

    private function rotateFile(string $filePath, int $linesToKeep): void
    {
        // Read last N lines
        $lines = [];
        $handle = fopen($filePath, 'r');

        if (!$handle) {
            return;
        }

        // Read file line by line (memory efficient for large files)
        while (!feof($handle)) {
            $line = fgets($handle);
            if ($line === false) {
                continue;
            }
            $lines[] = $line;

            // Keep only last N lines
            if (count($lines) > $linesToKeep) {
                array_shift($lines);
            }
        }

        fclose($handle);

        // Write back
        file_put_contents($filePath, implode('', $lines));
    }

    private function countLines(string $filePath): int
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return 0;
        }

        $count = 0;
        while (!feof($handle)) {
            if (fgets($handle) !== false) {
                $count++;
            }
        }

        fclose($handle);
        return $count;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
