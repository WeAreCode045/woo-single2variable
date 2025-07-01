# WooCommerce Single to Variable Products

A WordPress plugin that uses AI to generate WooCommerce variable products by merging single products.

## Features

- Merge single products into variable products based on shared attributes
- Smart product matching using AI-powered title similarity analysis
- Support for multiple AI providers (OpenAI, Claude, Gemini)
- Automated attribute, price, and stock management
- Live processing dashboard with stats and logs
- Configurable settings for fine-tuning the merging process

## Requirements

- WordPress 5.8 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher

## Installation

1. Upload the plugin files to the `/wp-content/plugins/woo-single2variable` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Configure the plugin settings under 'Single to Variable' in the WordPress admin menu

## Configuration

### General Settings

- **Title Similarity Threshold**: Set the minimum percentage of similarity required between product titles (default: 80%)
- **Brand Attribute**: Configure which attribute or meta key to use for brand matching

### AI Provider Settings

- Configure API keys for supported AI providers
- Select default AI provider
- Choose specific AI models for processing

## Usage

1. Navigate to the Single to Variable dashboard
2. Click "Start Processing" to begin analyzing and merging products
3. Monitor progress through the live dashboard
4. Review created variable products in WooCommerce

## Support

For support, please visit [our support page](https://wearecode.com/support).

## License

GPL v2 or later
