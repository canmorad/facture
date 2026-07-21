<?php

namespace App\Console\Commands;

use App\Services\GeminiAIService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestGeminiCommand extends Command
{
    protected $signature = 'gemini:test {--list : List all available models} {--test : Test each model individually} {--find : Auto-find a working model}';
    protected $description = 'Test Gemini API connection and list available models';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('=== Gemini API Test ===');
        $this->newLine();

        try {
            $service = app(GeminiAIService::class);

            // Check API key
            $apiKey = config('services.gemini.api_key');
            if (!$apiKey || $apiKey === 'your_gemini_api_key_here') {
                $this->error('❌ Gemini API key not configured!');
                $this->warn('Please set GEMINI_API_KEY in your .env file');
                $this->warn('Get your API key at: https://aistudio.google.com/app/apikey');
                return Command::FAILURE;
            }

            $this->info('✅ API key is configured');
            $this->line('API Key: ' . substr($apiKey, 0, 10) . '...' . substr($apiKey, -4));
            $this->newLine();

            // List models if requested
            if ($this->option('list')) {
                return $this->listModels($service);
            }

            // Find working model if requested
            if ($this->option('find')) {
                return $this->findWorkingModel($service);
            }

            // Test each model if requested
            if ($this->option('test')) {
                return $this->testAllModels($service);
            }

            // Default: quick connection test
            $this->info('Testing connection...');
            if ($service->testConnection()) {
                $this->info('✅ Connection successful!');

                // Try to find a working model
                $this->newLine();
                $this->info('Searching for a working model...');
                $workingModel = $service->findWorkingModel();

                if ($workingModel) {
                    $this->info("✅ Found working model: {$workingModel}");
                    $this->warn("You may want to set GEMINI_MODEL={$workingModel} in your .env");
                    return Command::SUCCESS;
                } else {
                    $this->error('❌ No working model found!');
                    $this->warn('Run: php artisan gemini:test --list');
                    $this->warn('Then run: php artisan gemini:test --test');
                    return Command::FAILURE;
                }
            } else {
                $this->error('❌ Connection failed!');
                $this->warn('Please check your API key and internet connection');
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            $this->newLine();
            $this->warn('Troubleshooting tips:');
            $this->warn('1. Verify your API key at: https://aistudio.google.com/app/apikey');
            $this->warn('2. Check if Gemini API is enabled in Google Cloud Console');
            $this->warn('3. Check https://aistudio.google.com/status for service outages');
            $this->warn('4. Try running: php artisan gemini:test --list');
            return Command::FAILURE;
        }
    }

    private function listModels(GeminiAIService $service): int
    {
        $this->info('Fetching available models...');
        $this->newLine();

        $result = $service->listAvailableModels();

        if (!$result['success']) {
            $this->error('❌ Failed to list models: ' . $result['error']);
            return Command::FAILURE;
        }

        $models = $result['models'];
        $this->info("✅ Found {$result['total']} models that support generateContent:");
        $this->newLine();

        $this->table(
            ['Model ID', 'Display Name', 'Input Tokens', 'Output Tokens'],
            array_map(function ($model) {
                return [
                    $model['model_id'] ?? $model['base_model'],
                    $model['display_name'],
                    number_format($model['input_tokens']),
                    number_format($model['output_tokens']),
                ];
            }, $models)
        );

        $this->newLine();
        $this->info('Recommended models for invoice analysis:');
        $flashModels = array_filter($models, fn($m) => str_contains($m['name'], 'flash'));
        foreach (array_slice($flashModels, 0, 5) as $model) {
            $modelId = $model['model_id'] ?? $model['base_model'];
            $this->line("  • {$modelId} - {$model['display_name']}");
        }

        return Command::SUCCESS;
    }

    private function testAllModels(GeminiAIService $service): int
    {
        $models = [
            'gemini-1.5-flash',
            'gemini-1.5-flash-001',
            'gemini-1.5-flash-002',
            'gemini-2.0-flash',
            'gemini-2.5-flash',
            'gemini-2.5-flash-lite',
            'gemini-pro',
            'gemini-pro-001',
            'gemini-3.5-flash',
        ];

        $this->info('Testing common models...');
        $this->newLine();

        $workingModels = [];
        $failedModels = [];

        foreach ($models as $model) {
            $this->line("Testing {$model}...", verbosity: 'v');
            $result = $service->testModel($model);

            if ($result['success']) {
                $workingModels[] = $model;
                $this->info("  ✅ {$model}");
            } else {
                $failedModels[$model] = $result['error'] ?? 'Unknown error';
                $this->error("  ❌ {$model}");
            }
        }

        $this->newLine();

        if ($workingModels) {
            $this->info('✅ Working models:');
            foreach ($workingModels as $model) {
                $this->line("  • {$model}");
            }
            $this->newLine();
            $this->warn("Recommendation: Set GEMINI_MODEL={$workingModels[0]} in your .env");
        } else {
            $this->error('❌ No working models found!');
            $this->newLine();
            $this->warn('Failed models:');
            foreach ($failedModels as $model => $error) {
                $this->line("  • {$model}: {$error}");
            }
        }

        return $workingModels ? Command::SUCCESS : Command::FAILURE;
    }

    private function findWorkingModel(GeminiAIService $service): int
    {
        $this->info('Searching for a working model...');
        $this->newLine();

        $model = $service->findWorkingModel();

        if ($model) {
            $this->info("✅ Found working model: {$model}");
            $this->newLine();
            $this->warn("Add this to your .env file:");
            $this->line("GEMINI_MODEL={$model}");
            return Command::SUCCESS;
        } else {
            $this->error('❌ No working model found!');
            $this->newLine();
            $this->warn('Possible reasons:');
            $this->warn('1. Your API key is very new (wait a few hours)');
            $this->warn('2. Your API key has restrictions');
            $this->warn('3. Gemini API is having issues (check https://aistudio.google.com/status)');
            return Command::FAILURE;
        }
    }
}
