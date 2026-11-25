<?php

namespace App\Services;

/**
 * Typed section catalog for template-driven pages (FR-CMS-016/017/018).
 * Each section type carries a fixed field schema validated server-side.
 */
final class SectionCatalog
{
    /**
     * MVP catalog: exactly the 10 types from FR-CMS-017.
     *
     * @return array<string, array{label: string, fields: array<string, array<string, mixed>>}>
     */
    public static function types(): array
    {
        return [
            'hero' => [
                'label' => 'Hero',
                'fields' => [
                    'headline' => ['label' => 'Headline', 'type' => 'text', 'required' => true, 'max' => 160],
                    'subheadline' => ['label' => 'Subheadline', 'type' => 'text', 'required' => false, 'max' => 300],
                    'cta_label' => ['label' => 'CTA label', 'type' => 'text', 'required' => false, 'max' => 60],
                    'cta_url' => ['label' => 'CTA URL', 'type' => 'url', 'required' => false],
                    'background_image' => ['label' => 'Background image', 'type' => 'media', 'required' => false],
                ],
            ],
            'feature_grid' => [
                'label' => 'Feature grid',
                'fields' => [
                    'title' => ['label' => 'Title', 'type' => 'text', 'required' => false, 'max' => 160],
                    'items' => ['label' => 'Features', 'type' => 'list', 'required' => true, 'min' => 1, 'item' => [
                        'heading' => ['label' => 'Heading', 'type' => 'text', 'required' => true, 'max' => 120],
                        'body' => ['label' => 'Body', 'type' => 'text', 'required' => true, 'max' => 400],
                    ]],
                ],
            ],
            'pricing_table' => [
                'label' => 'Pricing table',
                'fields' => [
                    'title' => ['label' => 'Title', 'type' => 'text', 'required' => false, 'max' => 160],
                    // AC-FR-CMS-017-1: selectable products from the tenant's published catalog.
                    'product_ids' => ['label' => 'Products', 'type' => 'product_list', 'required' => true, 'min' => 1],
                ],
            ],
            'testimonials' => [
                'label' => 'Testimonials',
                'fields' => [
                    'items' => ['label' => 'Quotes', 'type' => 'list', 'required' => true, 'min' => 1, 'item' => [
                        'quote' => ['label' => 'Quote', 'type' => 'text', 'required' => true, 'max' => 500],
                        'author' => ['label' => 'Author', 'type' => 'text', 'required' => true, 'max' => 120],
                        'role' => ['label' => 'Role', 'type' => 'text', 'required' => false, 'max' => 120],
                    ]],
                ],
            ],
            'faq_accordion' => [
                'label' => 'FAQ accordion',
                'fields' => [
                    'items' => ['label' => 'Questions', 'type' => 'list', 'required' => true, 'min' => 1, 'item' => [
                        'question' => ['label' => 'Question', 'type' => 'text', 'required' => true, 'max' => 200],
                        'answer' => ['label' => 'Answer', 'type' => 'text', 'required' => true, 'max' => 1000],
                    ]],
                ],
            ],
            'cta' => [
                'label' => 'Call to action',
                'fields' => [
                    'headline' => ['label' => 'Headline', 'type' => 'text', 'required' => true, 'max' => 160],
                    'cta_label' => ['label' => 'Button label', 'type' => 'text', 'required' => true, 'max' => 60],
                    'cta_url' => ['label' => 'Button URL', 'type' => 'url', 'required' => true],
                ],
            ],
            'contact_form' => [
                'label' => 'Contact form',
                'fields' => [
                    'title' => ['label' => 'Form title', 'type' => 'text', 'required' => false, 'max' => 160],
                    'submit_label' => ['label' => 'Submit label', 'type' => 'text', 'required' => false, 'max' => 60],
                ],
            ],
            'blog_list' => [
                'label' => 'Blog list',
                'fields' => [
                    'title' => ['label' => 'Title', 'type' => 'text', 'required' => false, 'max' => 160],
                    'limit' => ['label' => 'Post limit', 'type' => 'int', 'required' => false, 'min' => 1, 'max' => 24],
                ],
            ],
            'media' => [
                'label' => 'Media',
                'fields' => [
                    'image' => ['label' => 'Image', 'type' => 'media', 'required' => true],
                    'alt' => ['label' => 'Alt text', 'type' => 'text', 'required' => true, 'max' => 200],
                    'caption' => ['label' => 'Caption', 'type' => 'text', 'required' => false, 'max' => 300],
                ],
            ],
            'custom_html' => [
                'label' => 'Custom HTML',
                'fields' => [
                    // Sanitized through the HTML allowlist on save (FR-CMS-020).
                    'html' => ['label' => 'HTML', 'type' => 'html', 'required' => true, 'max' => 50000],
                ],
            ],
        ];
    }

    public static function isType(string $type): bool
    {
        return isset(self::types()[$type]);
    }

    /**
     * Validate an ordered section list against the catalog.
     *
     * @param  array<int, array<string, mixed>>  $sections
     * @return list<string> exact failing section index + field, per AC-FR-CMS-025-1
     */
    public static function validate(?array $sections): array
    {
        if ($sections === null) {
            return [];
        }

        if (! is_array($sections)) {
            return ['sections must be an array'];
        }

        $errors = [];

        foreach (array_values($sections) as $index => $section) {
            $type = is_array($section) ? (string) ($section['type'] ?? '') : '';
            if (! self::isType($type)) {
                $errors[] = sprintf('section %d: unknown type "%s"', $index, $type);

                continue;
            }

            $settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
            foreach (self::types()[$type]['fields'] as $field => $schema) {
                foreach (self::validateField($type, $field, $schema, $settings[$field] ?? null) as $error) {
                    $errors[] = $error;
                }
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return list<string>
     */
    private static function validateField(string $type, string $field, array $schema, mixed $value): array
    {
        $label = sprintf('section type "%s" field "%s"', $type, $schema['label'] ?? $field);

        if ($value === null || $value === '' || $value === []) {
            return ! empty($schema['required']) ? [$label.' is required'] : [];
        }

        $errors = [];

        switch ($schema['type'] ?? 'text') {
            case 'text':
            case 'media':
                if (! is_string($value)) {
                    return [$label.' must be a string'];
                }
                if (isset($schema['max']) && mb_strlen($value) > $schema['max']) {
                    $errors[] = sprintf('%s exceeds %d characters', $label, $schema['max']);
                }
                break;

            case 'url':
                if (! is_string($value) || ! preg_match('#^https?://#i', $value)) {
                    $errors[] = $label.' must be an http(s) URL';
                }
                break;

            case 'int':
                if (! is_numeric($value)) {
                    return [$label.' must be a number'];
                }
                if (isset($schema['min']) && $value < $schema['min']) {
                    $errors[] = sprintf('%s must be at least %s', $label, $schema['min']);
                }
                if (isset($schema['max']) && $value > $schema['max']) {
                    $errors[] = sprintf('%s must be at most %s', $label, $schema['max']);
                }
                break;

            case 'html':
                if (! is_string($value)) {
                    return [$label.' must be a string'];
                }
                if (isset($schema['max']) && strlen($value) > $schema['max']) {
                    $errors[] = sprintf('%s exceeds %d bytes', $label, $schema['max']);
                }
                break;

            case 'product_list':
                if (! is_array($value)) {
                    return [$label.' must be a list of product ids'];
                }
                if (isset($schema['min']) && count($value) < $schema['min']) {
                    $errors[] = sprintf('%s requires at least %d product', $label, $schema['min']);
                }
                break;

            case 'list':
                if (! is_array($value)) {
                    return [$label.' must be a list'];
                }
                if (isset($schema['min']) && count($value) < $schema['min']) {
                    $errors[] = sprintf('%s requires at least %d item(s)', $label, $schema['min']);
                    break;
                }
                foreach (array_values($value) as $i => $item) {
                    if (! is_array($item)) {
                        $errors[] = sprintf('%s item %d must be an object', $label, $i);

                        continue;
                    }
                    foreach ($schema['item'] as $itemField => $itemSchema) {
                        foreach (self::validateField($type, $itemField, $itemSchema, $item[$itemField] ?? null) as $error) {
                            $errors[] = sprintf('%s item %d: %s', $label, $i, $error);
                        }
                    }
                }
                break;
        }

        return $errors;
    }
}
