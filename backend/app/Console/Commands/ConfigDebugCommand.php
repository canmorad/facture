<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ConfigDebugCommand extends Command
{
    protected $signature = 'config:debug {--all : Show all config}';
    protected $description = 'Debug current configuration values';

    public function handle(): int
    {
        $this->info('=== Gemini Configuration Debug ===');
        $this->newLine();

        $this->table(
            ['Config Key', 'Value', 'Source'],
            [
                ['GEMINI_API_KEY', substr(config('services.gemini.api_key', 'NOT SET'), 0, 20) . '...', 'env'],
                ['GEMINI_MODEL', config('services.gemini.model', 'NOT SET'), 'env'],
                ['GEMINI_VERBOSE_LOGGING', config('services.gemini.verbose_logging', false) ? 'true' : 'false', 'env'],
                ['APP_ENV', config('app.env'), 'env'],
                ['APP_DEBUG', config('app.debug') ? 'true' : 'false', 'env'],
            ]
        );

        $this->newLine();
        $this->info('MODEL_FALLBACKS array:');

        // Read the service file to get the actual fallbacks
        $serviceFile = file_get_contents(app_path('Services/GeminiAIService.php'));
        if (preg_match('/MODEL_FALLBACKS\s*=\s*\[(.*?)\];/s', $serviceFile, $matches)) {
            $models = explode(',', $matches[1]);
            foreach ($models as $model) {
                $model = trim(str_replace(["'", '//'], ['', ''], $model));
                if (!empty($model) && !str_contains($model, '//')) {
                    $this->line("  • {$model}");
                }
            }
        }

        return self::SUCCESS;
    }
}
