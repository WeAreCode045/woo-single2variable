<?php
namespace WS2V\AI;

class OpenAIProvider implements AIProviderInterface {
    /**
     * @var string OpenAI API key
     */
    private $api_key;

    /**
     * @var string Selected model
     */
    private $model;

    /**
     * @var string API endpoint
     */
    private const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    /**
     * Initialize the provider
     */
    public function initialize(array $credentials): bool {
        if (empty($credentials['api_key'])) {
            return false;
        }

        $this->api_key = $credentials['api_key'];
        $this->model = $credentials['model'] ?? 'gpt-4';
        return true;
    }

    /**
     * Analyze product similarity
     */
    public function analyze_product_similarity(array $products, float $threshold): array {
        $product_data = $this->prepare_product_data($products);
        
        $prompt = sprintf(
            "Analyze these products and determine if they are similar enough to be merged into a variable product. " .
            "Similarity threshold: %.2f%%\n\nProducts:\n%s\n\n" .
            "Respond in JSON format with keys: similar (boolean), similarity_score (float), explanation (string)",
            $threshold,
            $product_data
        );

        $response = $this->make_api_request([
            'messages' => [
                ['role' => 'system', 'content' => 'You are a product analysis AI assistant.'],
                ['role' => 'user', 'content' => $prompt]
            ]
        ]);

        if (empty($response)) {
            return [
                'similar' => false,
                'similarity_score' => 0,
                'explanation' => 'API request failed'
            ];
        }

        try {
            $result = json_decode($response, true);
            return [
                'similar' => $result['similar'] ?? false,
                'similarity_score' => $result['similarity_score'] ?? 0,
                'explanation' => $result['explanation'] ?? 'Unknown error'
            ];
        } catch (\Exception $e) {
            return [
                'similar' => false,
                'similarity_score' => 0,
                'explanation' => 'Failed to parse API response'
            ];
        }
    }

    /**
     * Generate variable product name
     */
    public function generate_variable_product_name(array $products): string {
        $product_data = $this->prepare_product_data($products);
        
        $prompt = sprintf(
            "Generate a suitable name for a variable product that will combine these products. " .
            "The name should be concise and descriptive.\n\nProducts:\n%s\n\n" .
            "Respond with only the generated name, no additional text.",
            $product_data
        );

        $response = $this->make_api_request([
            'messages' => [
                ['role' => 'system', 'content' => 'You are a product naming AI assistant.'],
                ['role' => 'user', 'content' => $prompt]
            ]
        ]);

        return trim($response) ?: $products[0]->get_name();
    }

    /**
     * Analyze product attributes
     */
    public function analyze_product_attributes(array $products): array {
        $product_data = $this->prepare_product_data($products);
        
        $prompt = sprintf(
            "Analyze these products and identify common attributes that could be used as variations. " .
            "Focus on size, color, and other significant attributes.\n\nProducts:\n%s\n\n" .
            "Respond in JSON format with keys: attributes (array of attribute names), " .
            "variations (array of variation combinations)",
            $product_data
        );

        $response = $this->make_api_request([
            'messages' => [
                ['role' => 'system', 'content' => 'You are a product attribute analysis AI assistant.'],
                ['role' => 'user', 'content' => $prompt]
            ]
        ]);

        try {
            $result = json_decode($response, true);
            return [
                'attributes' => $result['attributes'] ?? [],
                'variations' => $result['variations'] ?? []
            ];
        } catch (\Exception $e) {
            return [
                'attributes' => [],
                'variations' => []
            ];
        }
    }

    /**
     * Prepare product data for API requests
     */
    private function prepare_product_data(array $products): string {
        $data = '';
        foreach ($products as $index => $product) {
            $data .= sprintf(
                "%d. Name: %s\nSKU: %s\nDescription: %s\nAttributes: %s\n\n",
                $index + 1,
                $product->get_name(),
                $product->get_sku(),
                wp_strip_all_tags($product->get_description()),
                $this->get_product_attributes_string($product)
            );
        }
        return $data;
    }

    /**
     * Get product attributes as string
     */
    private function get_product_attributes_string($product): string {
        $attributes = $product->get_attributes();
        $attr_strings = [];
        
        foreach ($attributes as $attribute) {
            if ($attribute->is_taxonomy()) {
                $terms = wc_get_product_terms(
                    $product->get_id(),
                    $attribute->get_name(),
                    ['fields' => 'names']
                );
                $attr_strings[] = $attribute->get_name() . ': ' . implode(', ', $terms);
            } else {
                $attr_strings[] = $attribute->get_name() . ': ' . $attribute->get_options()[0];
            }
        }
        
        return implode('; ', $attr_strings);
    }

    /**
     * Make API request to OpenAI
     */
    private function make_api_request(array $data): string {
        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode(array_merge($data, [
                'model' => $this->model,
                'temperature' => 0.3,
                'max_tokens' => 500
            ])),
            'timeout' => 30,
        ];

        $response = wp_remote_post(self::API_ENDPOINT, $args);

        if (is_wp_error($response)) {
            error_log('OpenAI API Error: ' . $response->get_error_message());
            return '';
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['choices'][0]['message']['content'] ?? '';
    }
}
