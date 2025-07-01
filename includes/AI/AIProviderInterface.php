<?php
namespace WS2V\AI;

interface AIProviderInterface {
    /**
     * Initialize the AI provider with API credentials
     *
     * @param array $credentials Provider-specific credentials
     * @return bool True if initialization successful
     */
    public function initialize(array $credentials): bool;

    /**
     * Check if products are similar enough to be merged
     *
     * @param array $products Array of WC_Product objects
     * @param float $threshold Similarity threshold percentage
     * @return array [
     *      'similar' => bool,
     *      'similarity_score' => float,
     *      'explanation' => string
     * ]
     */
    public function analyze_product_similarity(array $products, float $threshold): array;

    /**
     * Generate a suitable name for the variable product
     *
     * @param array $products Array of WC_Product objects
     * @return string Generated name
     */
    public function generate_variable_product_name(array $products): string;

    /**
     * Analyze and extract common attributes from products
     *
     * @param array $products Array of WC_Product objects
     * @return array [
     *      'attributes' => array,
     *      'variations' => array
     * ]
     */
    public function analyze_product_attributes(array $products): array;
}
