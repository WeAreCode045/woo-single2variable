<?php
namespace WS2V\Product;

class ProductMerger {
    /**
     * @var float Title similarity threshold
     */
    private $title_similarity_threshold;

    /**
     * @var \WS2V\AI\AIProviderInterface AI provider instance
     */
    private $ai_provider;

    /**
     * Constructor
     */
    public function __construct() {
        $this->title_similarity_threshold = get_option('ws2v_title_similarity', 80);
        
        // Initialize AI provider
        $settings = get_option('ws2v_settings', []);
        $provider = $settings['ws2v_default_ai_provider'] ?? 'openai';
        $credentials = [
            'api_key' => $settings['ws2v_' . $provider . '_api_key'] ?? '',
            'model' => $settings['ws2v_' . $provider . '_model'] ?? ''
        ];
        
        $this->ai_provider = \WS2V\AI\AIProviderFactory::get_provider($provider, $credentials);
    }

    /**
     * Check if products can be merged
     *
     * @param array $product_ids Array of product IDs to check
     * @return bool|WP_Error
     */
    public function can_merge_products($product_ids) {
        if (empty($product_ids)) {
            return new \WP_Error('invalid_products', __('No products selected', 'woo-single2variable'));
        }

        // Check if all products exist and are simple products
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (!$product || $product->get_type() !== 'simple') {
                return new \WP_Error('invalid_product_type', 
                    sprintf(__('Product %d is not a simple product', 'woo-single2variable'), $product_id)
                );
            }
        }

        // Check if products share the same category
        if (!$this->has_same_category($product_ids)) {
            return new \WP_Error('different_categories', __('Products must share the same category', 'woo-single2variable'));
        }

        // Check if products have the same brand
        if (!$this->has_same_brand($product_ids)) {
            return new \WP_Error('different_brands', __('Products must have the same brand', 'woo-single2variable'));
        }

        // Check title similarity
        if (!$this->check_title_similarity($product_ids)) {
            return new \WP_Error('title_mismatch', __('Product titles are not similar enough', 'woo-single2variable'));
        }

        return true;
    }

    /**
     * Check if products share the same category
     *
     * @param array $product_ids
     * @return bool
     */
    private function has_same_category($product_ids) {
        $categories = [];
        foreach ($product_ids as $product_id) {
            $terms = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
            $categories[$product_id] = $terms;
        }

        // Get intersection of all category arrays
        $common_categories = array_intersect(...array_values($categories));
        return !empty($common_categories);
    }

    /**
     * Check if products have the same brand
     *
     * @param array $product_ids
     * @return bool
     */
    private function has_same_brand($product_ids) {
        $brands = [];
        foreach ($product_ids as $product_id) {
            // Try to get brand from attribute first
            $product = wc_get_product($product_id);
            $brand = $product->get_attribute('brand');
            
            // If no attribute, try meta
            if (!$brand) {
                $brand = get_post_meta($product_id, 'brand', true);
            }
            
            $brands[$product_id] = $brand;
        }

        // Remove empty values and check if all remaining brands are the same
        $brands = array_filter($brands);
        return count(array_unique($brands)) === 1;
    }

    /**
     * Check title similarity between products
     *
     * @param array $product_ids
     * @return bool
     */
    private function check_title_similarity($product_ids) {
        $titles = [];
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            $titles[] = $product->get_name();
        }

        // Compare each title pair
        for ($i = 0; $i < count($titles) - 1; $i++) {
            for ($j = $i + 1; $j < count($titles); $j++) {
                $similarity = $this->calculate_title_similarity($titles[$i], $titles[$j]);
                if ($similarity < $this->title_similarity_threshold) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Calculate similarity between two titles
     *
     * @param string $title1
     * @param string $title2
     * @return float Percentage of similarity
     */
    private function calculate_title_similarity($title1, $title2) {
        // Simple Levenshtein distance implementation
        // Can be enhanced with more sophisticated algorithms
        $maxLength = max(strlen($title1), strlen($title2));
        if ($maxLength === 0) return 100;
        
        $distance = levenshtein(strtolower($title1), strtolower($title2));
        return (1 - $distance / $maxLength) * 100;
    }

    /**
     * Create variable product from simple products
     *
     * @param array $product_ids
     * @return int|WP_Error New product ID or error
     */
    public function create_variable_product($product_ids) {
        // Validate products can be merged
        $can_merge = $this->can_merge_products($product_ids);
        if (is_wp_error($can_merge)) {
            return $can_merge;
        }

        // Create new variable product
        $variable_product = new \WC_Product_Variable();
        
        // Get main product (first in array)
        $main_product = wc_get_product($product_ids[0]);
        
        // Set basic product data
        $variable_product->set_name($this->generate_variable_product_name($product_ids));
        $variable_product->set_description($main_product->get_description());
        $variable_product->set_short_description($main_product->get_short_description());
        $variable_product->set_status('publish');
        $variable_product->set_catalog_visibility('visible');
        
        // Copy categories and other taxonomies
        $this->copy_taxonomies($main_product, $variable_product);
        
        // Set featured image
        $this->copy_featured_image($main_product, $variable_product);
        
        // Save product to get ID
        $variable_product->save();
        
        // Create variations
        $this->create_variations($variable_product, $product_ids);
        
        // Update original products status
        $this->update_original_products_status($product_ids, $variable_product->get_id());
        
        return $variable_product->get_id();
    }

    /**
     * Generate variable product name
     *
     * @param array $product_ids Array of product IDs
     * @return string Generated name
     */
    private function generate_variable_product_name($product_ids) {
        $products = array_map('wc_get_product', $product_ids);
        
        if ($this->ai_provider) {
            $name = $this->ai_provider->generate_variable_product_name($products);
            if ($name) {
                return $name;
            }
        }
        
        // Fallback: Use the first product's name
        return $products[0]->get_name();
    }

    /**
     * Copy taxonomies from source to target product
     *
     * @param WC_Product $source_product
     * @param WC_Product_Variable $target_product
     */
    private function copy_taxonomies($source_product, $target_product) {
        $taxonomies = get_object_taxonomies('product');
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_object_terms($source_product->get_id(), $taxonomy, ['fields' => 'ids']);
            if (!is_wp_error($terms)) {
                wp_set_object_terms($target_product->get_id(), $terms, $taxonomy);
            }
        }
    }

    /**
     * Copy featured image from source to target product
     *
     * @param WC_Product $source_product
     * @param WC_Product_Variable $target_product
     */
    private function copy_featured_image($source_product, $target_product) {
        $image_id = $source_product->get_image_id();
        if ($image_id) {
            $target_product->set_image_id($image_id);
            $target_product->save();
        }
    }

    /**
     * Create variations for the variable product
     *
     * @param WC_Product_Variable $variable_product
     * @param array $product_ids Array of source product IDs
     */
    private function create_variations($variable_product, $product_ids) {
        $products = array_map('wc_get_product', $product_ids);
        
        // Get attribute analysis from AI
        $attribute_data = [];
        if ($this->ai_provider) {
            $attribute_data = $this->ai_provider->analyze_product_attributes($products);
        }
        
        // Create product attributes
        $attributes = [];
        foreach ($products as $product) {
            foreach ($product->get_attributes() as $key => $attribute) {
                if (!isset($attributes[$key])) {
                    $attributes[$key] = [];
                }
                if ($attribute->is_taxonomy()) {
                    $terms = wc_get_product_terms($product->get_id(), $attribute->get_name(), ['fields' => 'names']);
                    $attributes[$key] = array_merge($attributes[$key], $terms);
                } else {
                    $attributes[$key] = array_merge($attributes[$key], $attribute->get_options());
                }
            }
        }
        
        // Set product attributes
        $product_attributes = [];
        foreach ($attributes as $key => $values) {
            $values = array_unique($values);
            $product_attributes[$key] = [
                'name' => $key,
                'value' => implode('|', $values),
                'position' => 0,
                'is_visible' => 1,
                'is_variation' => 1,
                'is_taxonomy' => strpos($key, 'pa_') === 0
            ];
        }
        $variable_product->set_attributes($product_attributes);
        
        // Create variations
        foreach ($products as $product) {
            $variation = new \WC_Product_Variation();
            $variation->set_parent_id($variable_product->get_id());
            
            // Copy attributes
            foreach ($product->get_attributes() as $key => $attribute) {
                if ($attribute->is_taxonomy()) {
                    $terms = wc_get_product_terms($product->get_id(), $attribute->get_name(), ['fields' => 'names']);
                    $variation->set_attribute($key, reset($terms));
                } else {
                    $variation->set_attribute($key, $attribute->get_options()[0]);
                }
            }
            
            // Copy other data
            $variation->set_status('publish');
            $variation->set_price($product->get_price());
            $variation->set_regular_price($product->get_regular_price());
            $variation->set_sale_price($product->get_sale_price());
            $variation->set_manage_stock($product->get_manage_stock());
            $variation->set_stock_quantity($product->get_stock_quantity());
            $variation->set_stock_status($product->get_stock_status());
            $variation->set_weight($product->get_weight());
            $variation->set_dimensions([
                'length' => $product->get_length(),
                'width' => $product->get_width(),
                'height' => $product->get_height()
            ]);
            
            // Set variation image
            $image_id = $product->get_image_id();
            if ($image_id) {
                $variation->set_image_id($image_id);
            }
            
            $variation->save();
        }
        
        $variable_product->save();
    }

    /**
     * Update status of original products after merging
     *
     * @param array $product_ids Array of product IDs
     * @param int $variable_product_id New variable product ID
     */
    private function update_original_products_status($product_ids, $variable_product_id) {
        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $product->set_status('draft');
                $product->save();
                
                // Add link to variable product
                update_post_meta($product_id, '_merged_into_variable', $variable_product_id);
            }
        }
    }
}
