-- Migration: Add default settings for AI, metadata, and image integrations
-- Date: 2026-01-26

-- AI Settings
INSERT IGNORE INTO `settings` (`group`, `key`, `value`, `type`, `description`) VALUES
('ai', 'provider', 'ollama', 'string', 'AI provider: ollama, openai, or anthropic'),
('ai', 'ollama_url', 'http://localhost:11434', 'string', 'Ollama server URL'),
('ai', 'ollama_model', 'llama3.2:1b', 'string', 'Ollama model to use'),
('ai', 'openai_api_key', '', 'string', 'OpenAI API key'),
('ai', 'openai_model', 'gpt-4o-mini', 'string', 'OpenAI model to use'),
('ai', 'anthropic_api_key', '', 'string', 'Anthropic API key'),
('ai', 'anthropic_model', 'claude-3-haiku-20240307', 'string', 'Anthropic model to use');

-- Metadata API Settings
INSERT IGNORE INTO `settings` (`group`, `key`, `value`, `type`, `description`) VALUES
('metadata', 'fanart_api_key', '', 'string', 'Fanart.tv API key for channel logos'),
('metadata', 'tmdb_api_key', '', 'string', 'TMDB API key for movie/TV metadata'),
('metadata', 'auto_fetch_logos', '1', 'boolean', 'Automatically fetch logos when adding channels'),
('metadata', 'auto_fetch_metadata', '1', 'boolean', 'Automatically fetch metadata for VOD content');

-- Image Processing Settings
INSERT IGNORE INTO `settings` (`group`, `key`, `value`, `type`, `description`) VALUES
('image', 'webp_quality', '85', 'integer', 'WebP compression quality (0-100)'),
('image', 'keep_originals', '1', 'boolean', 'Keep original images after processing'),
('image', 'generate_sizes', '1', 'boolean', 'Generate multiple size variants');
