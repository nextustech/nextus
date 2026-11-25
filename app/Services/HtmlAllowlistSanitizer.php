<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Facades\Log;

/**
 * HTML allowlist sanitizer shared by custom_html sections and product
 * descriptions (FR-CMS-020, FR-CMS-053). Policy is versioned; violations
 * are logged with the policy version.
 */
class HtmlAllowlistSanitizer
{
    public const POLICY_VERSION = '1.0';

    private const ALLOWED_TAGS = [
        'p', 'br', 'hr', 'strong', 'b', 'em', 'i', 'u', 's', 'small', 'sub', 'sup',
        'span', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'blockquote', 'pre', 'code',
        'a', 'img', 'figure', 'figcaption',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption',
    ];

    /** Tags whose entire content is dropped (not unwrapped). */
    private const DROP_CONTENT_TAGS = ['script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template'];

    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'title', 'rel', 'target'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'td' => ['colspan', 'rowspan'],
        'th' => ['colspan', 'rowspan', 'scope'],
        '*' => ['class', 'id'],
    ];

    private const URL_ATTRIBUTES = ['href', 'src'];

    /**
     * @return array{html: string, removed: list<string>}
     */
    public function sanitize(string $html): array
    {
        if (trim($html) === '') {
            return ['html' => '', 'removed' => []];
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8"><body>'.$html.'</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $removed = [];
        $body = $document->getElementsByTagName('body')->item(0);
        if ($body === null) {
            return ['html' => '', 'removed' => []];
        }

        $this->filter($body, $removed);

        $clean = '';
        foreach (iterator_to_array($body->childNodes) as $child) {
            $clean .= $document->saveHTML($child);
        }

        $clean = trim($clean);
        if ($removed !== []) {
            Log::warning('HTML allowlist sanitizer removed disallowed markup', [
                'policy_version' => self::POLICY_VERSION,
                'removed' => array_values(array_unique($removed)),
            ]);
        }

        return ['html' => $clean, 'removed' => array_values(array_unique($removed))];
    }

    /** @param  list<string>  $removed */
    private function filter(DOMNode $node, array &$removed): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP_CONTENT_TAGS, true)) {
                $removed[] = $tag;
                $node->removeChild($child);

                continue;
            }

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                // Disallowed wrapper: unwrap and keep its (filtered) children.
                $removed[] = $tag;
                while ($child->firstChild !== null) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                // Re-filter the nodes we just moved into place.
                $this->filter($node, $removed);

                return;
            }

            $this->filterAttributes($child, $removed);
            $this->filter($child, $removed);
        }
    }

    /** @param  list<string>  $removed */
    private function filterAttributes(DOMElement $element, array &$removed): void
    {
        $allowed = array_merge(
            self::ALLOWED_ATTRIBUTES['*'],
            self::ALLOWED_ATTRIBUTES[$element->tagName] ?? [],
        );

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);

            if (str_starts_with($name, 'on')) {
                $element->removeAttribute($attribute->name);
                $removed[] = 'attribute on*';

                continue;
            }

            if (! in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->name);
                $removed[] = 'attribute '.$name;

                continue;
            }

            if (in_array($name, self::URL_ATTRIBUTES, true) && ! $this->isSafeUrl($attribute->value)) {
                $element->removeAttribute($attribute->name);
                $removed[] = 'unsafe url in '.$name;
            }
        }

        if ($element->tagName === 'a' && $element->hasAttribute('target')) {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private function isSafeUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, '/')) {
            return true;
        }

        return (bool) preg_match('#^(https?|mailto):#i', $url);
    }
}
