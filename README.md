# EC Product Variation Radio Widget

A Drupal Commerce module that provides a radio button widget for product variation selection with enhanced customization options.

## Description

This module provides an alternative widget for selecting product variations in Drupal Commerce. Instead of the default dropdown selector, it displays product variations as radio buttons with customizable labels and content.

## Features

- **Radio Button Selection**: Display product variations as radio buttons instead of a dropdown
- **Customizable Labels**: Configure custom labels for the variation selector
- **Display Mode Support**: Choose different view modes for rendering variation content
- **Conditional Visibility**: Option to hide the widget when only one variation exists
- **Visual Customization**: Ability to visually hide radio buttons while maintaining accessibility
- **AJAX Support**: Seamless form updates when selecting variations
- **Twig Template**: Provides a twig template for custom theming in your theme

## Requirements

- Drupal: ^10 || ^11
- Commerce Core (commerce:commerce)
- Commerce Product (commerce:commerce_product)

## Installation

1. Download and place the module in your `modules/custom` or `modules/contrib` directory
2. Enable the module via Drush: `drush en ec_radio_widget`
   Or via the UI: Admin → Extend

## Configuration

1. Navigate to your product display settings
2. Find the "Variations" field widget settings
3. Select "EC Product variation radio" as the widget type
4. Configure the widget settings:
  - **Display label**: Show/hide the main label
  - **Label text**: Custom text for the label
  - **Hide widget for single variation**: Automatically hide when only one variation exists
  - **Hide radio buttons visually**: Hide radio buttons while keeping them accessible
  - **Display mode**: Choose how variation content is rendered

## Theming

The module provides a Twig template for customization:

```
form-element--radio--ec-product-variation-radio_html.twig
```

Copy this template to your custom theme to override the default rendering.

### CSS Classes

The widget uses the following CSS classes:
- `.variation-radios-wrapper` - Main container
- `.variation-title` - Title/label container
- `.variation-option-wrapper` - Individual option wrapper
- `.variation-label` - Variation label
- `.variation-content` - Variation content container
- `.variation-radio-hidden` - Applied when radio buttons are visually hidden

## Usage

Once configured, the widget will automatically appear on product add-to-cart forms, allowing customers to select product variations using radio buttons.

## License

This project is licensed under the GPL-2.0+.

## Author

Pavel Kasianov.

Linkedin: https://www.linkedin.com/in/pkasianov/</br>
Drupal org: https://www.drupal.org/u/pkasianov

## Support

For issues or feature requests, please contact the module maintainer.
