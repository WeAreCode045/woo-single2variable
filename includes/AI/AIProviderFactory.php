<?php
namespace WS2V\AI;

class AIProviderFactory {
    /**
     * @var array Registered providers
     */
    private static $providers = [
        'openai' => OpenAIProvider::class
    ];

    /**
     * Get AI provider instance
     *
     * @param string $provider Provider name
     * @param array $credentials Provider credentials
     * @return AIProviderInterface|null
     */
    public static function get_provider(string $provider, array $credentials): ?AIProviderInterface {
        if (!isset(self::$providers[$provider])) {
            return null;
        }

        $provider_class = self::$providers[$provider];
        $instance = new $provider_class();
        
        if (!$instance->initialize($credentials)) {
            return null;
        }

        return $instance;
    }

    /**
     * Register new AI provider
     *
     * @param string $name Provider name
     * @param string $class Provider class name
     * @return void
     */
    public static function register_provider(string $name, string $class): void {
        if (!class_exists($class) || !in_array(AIProviderInterface::class, class_implements($class))) {
            return;
        }

        self::$providers[$name] = $class;
    }

    /**
     * Get available providers
     *
     * @return array
     */
    public static function get_available_providers(): array {
        return array_keys(self::$providers);
    }
}
