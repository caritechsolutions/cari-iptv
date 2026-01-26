<?php
/**
 * CARI-IPTV AI Service
 * Provides AI capabilities via local Ollama or cloud providers
 */

namespace CariIPTV\Services;

class AIService
{
    private SettingsService $settings;
    private string $provider;
    private array $config;

    // Provider constants
    public const PROVIDER_OLLAMA = 'ollama';
    public const PROVIDER_OPENAI = 'openai';
    public const PROVIDER_ANTHROPIC = 'anthropic';

    public function __construct()
    {
        $this->settings = new SettingsService();
        $this->loadConfig();
    }

    /**
     * Load AI configuration from settings
     */
    private function loadConfig(): void
    {
        $this->provider = $this->settings->get('provider', self::PROVIDER_OLLAMA, 'ai');

        $this->config = [
            'ollama' => [
                'url' => $this->settings->get('ollama_url', 'http://localhost:11434', 'ai'),
                'model' => $this->settings->get('ollama_model', 'llama3.2:1b', 'ai'),
            ],
            'openai' => [
                'api_key' => $this->settings->get('openai_api_key', '', 'ai'),
                'model' => $this->settings->get('openai_model', 'gpt-4o-mini', 'ai'),
            ],
            'anthropic' => [
                'api_key' => $this->settings->get('anthropic_api_key', '', 'ai'),
                'model' => $this->settings->get('anthropic_model', 'claude-3-haiku-20240307', 'ai'),
            ],
        ];
    }

    /**
     * Check if AI is configured and available
     */
    public function isAvailable(): bool
    {
        switch ($this->provider) {
            case self::PROVIDER_OLLAMA:
                return $this->testOllamaConnection();

            case self::PROVIDER_OPENAI:
                return !empty($this->config['openai']['api_key']);

            case self::PROVIDER_ANTHROPIC:
                return !empty($this->config['anthropic']['api_key']);

            default:
                return false;
        }
    }

    /**
     * Test connection to Ollama
     */
    public function testOllamaConnection(): bool
    {
        try {
            $url = rtrim($this->config['ollama']['url'], '/') . '/api/tags';

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $httpCode === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get available Ollama models
     */
    public function getOllamaModels(): array
    {
        try {
            $url = rtrim($this->config['ollama']['url'], '/') . '/api/tags';

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                return $data['models'] ?? [];
            }

            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Generate text completion
     */
    public function complete(string $prompt, array $options = []): ?string
    {
        switch ($this->provider) {
            case self::PROVIDER_OLLAMA:
                return $this->completeOllama($prompt, $options);

            case self::PROVIDER_OPENAI:
                return $this->completeOpenAI($prompt, $options);

            case self::PROVIDER_ANTHROPIC:
                return $this->completeAnthropic($prompt, $options);

            default:
                return null;
        }
    }

    /**
     * Generate completion via Ollama
     */
    private function completeOllama(string $prompt, array $options = []): ?string
    {
        try {
            $url = rtrim($this->config['ollama']['url'], '/') . '/api/generate';
            $model = $options['model'] ?? $this->config['ollama']['model'];

            $payload = [
                'model' => $model,
                'prompt' => $prompt,
                'stream' => false,
                'options' => [
                    'temperature' => $options['temperature'] ?? 0.7,
                    'num_predict' => $options['max_tokens'] ?? 500,
                ],
            ];

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT => 60,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                return $data['response'] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Generate completion via OpenAI
     */
    private function completeOpenAI(string $prompt, array $options = []): ?string
    {
        try {
            $apiKey = $this->config['openai']['api_key'];
            if (empty($apiKey)) {
                return null;
            }

            $model = $options['model'] ?? $this->config['openai']['model'];

            $payload = [
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => $options['temperature'] ?? 0.7,
                'max_tokens' => $options['max_tokens'] ?? 500,
            ];

            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey,
                ],
                CURLOPT_TIMEOUT => 60,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                return $data['choices'][0]['message']['content'] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Generate completion via Anthropic
     */
    private function completeAnthropic(string $prompt, array $options = []): ?string
    {
        try {
            $apiKey = $this->config['anthropic']['api_key'];
            if (empty($apiKey)) {
                return null;
            }

            $model = $options['model'] ?? $this->config['anthropic']['model'];

            $payload = [
                'model' => $model,
                'max_tokens' => $options['max_tokens'] ?? 500,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ];

            $ch = curl_init('https://api.anthropic.com/v1/messages');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'x-api-key: ' . $apiKey,
                    'anthropic-version: 2023-06-01',
                ],
                CURLOPT_TIMEOUT => 60,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                return $data['content'][0]['text'] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Generate channel description
     */
    public function generateChannelDescription(string $channelName, array $context = []): ?string
    {
        $prompt = $this->buildChannelDescriptionPrompt($channelName, $context);
        return $this->complete($prompt, ['max_tokens' => 200]);
    }

    /**
     * Build prompt for channel description
     */
    private function buildChannelDescriptionPrompt(string $channelName, array $context = []): string
    {
        $prompt = "Write a brief, professional description (2-3 sentences) for a TV channel named \"{$channelName}\".";

        if (!empty($context['category'])) {
            $prompt .= " The channel is in the {$context['category']} category.";
        }

        if (!empty($context['country'])) {
            $prompt .= " It is based in {$context['country']}.";
        }

        $prompt .= " The description should be suitable for an IPTV/streaming platform listing. Be concise and informative. Only output the description, no additional text.";

        return $prompt;
    }

    /**
     * Generate VOD description
     */
    public function generateVODDescription(string $title, array $context = []): ?string
    {
        $prompt = $this->buildVODDescriptionPrompt($title, $context);
        return $this->complete($prompt, ['max_tokens' => 300]);
    }

    /**
     * Build prompt for VOD description
     */
    private function buildVODDescriptionPrompt(string $title, array $context = []): string
    {
        $type = $context['type'] ?? 'movie';
        $prompt = "Write a compelling description (3-4 sentences) for a {$type} titled \"{$title}\".";

        if (!empty($context['genre'])) {
            $prompt .= " Genre: {$context['genre']}.";
        }

        if (!empty($context['year'])) {
            $prompt .= " Released in {$context['year']}.";
        }

        if (!empty($context['cast'])) {
            $prompt .= " Starring: {$context['cast']}.";
        }

        $prompt .= " The description should be engaging and suitable for a streaming platform. Only output the description, no additional text.";

        return $prompt;
    }

    /**
     * Get current provider
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * Get provider display name
     */
    public function getProviderName(): string
    {
        return match ($this->provider) {
            self::PROVIDER_OLLAMA => 'Ollama (Local)',
            self::PROVIDER_OPENAI => 'OpenAI',
            self::PROVIDER_ANTHROPIC => 'Anthropic',
            default => 'Unknown',
        };
    }

    /**
     * Get provider status
     */
    public function getStatus(): array
    {
        $available = $this->isAvailable();

        $status = [
            'provider' => $this->provider,
            'provider_name' => $this->getProviderName(),
            'available' => $available,
            'message' => $available ? 'Connected' : 'Not configured or unavailable',
        ];

        if ($this->provider === self::PROVIDER_OLLAMA && $available) {
            $models = $this->getOllamaModels();
            $status['models'] = array_column($models, 'name');
            $status['current_model'] = $this->config['ollama']['model'];
        }

        return $status;
    }
}
