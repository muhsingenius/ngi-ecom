<?php

namespace App\Services;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use InvalidArgumentException;

/**
 * Allow-list SVG sanitiser. Anything not explicitly known to be a harmless drawing
 * primitive is removed: scripts, event handlers, external references, embedded
 * images, foreignObject and style sheets.
 */
class SvgSanitizer
{
    private const ELEMENTS = [
        'svg', 'g', 'defs', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'lineargradient', 'radialgradient', 'stop', 'clippath', 'mask', 'use', 'title', 'desc', 'symbol',
    ];

    private const ATTRIBUTES = [
        'id', 'class', 'd', 'x', 'y', 'x1', 'x2', 'y1', 'y2', 'cx', 'cy', 'r', 'rx', 'ry', 'width', 'height',
        'points', 'transform', 'viewbox', 'preserveaspectratio', 'xmlns', 'version', 'fill', 'fill-opacity', 'fill-rule',
        'stroke', 'stroke-width', 'stroke-opacity', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-dasharray',
        'stroke-dashoffset', 'opacity', 'offset', 'stop-color', 'stop-opacity', 'gradientunits', 'gradienttransform', 'fx', 'fy',
        'clip-path', 'clip-rule', 'mask', 'font-family', 'font-size', 'font-weight', 'font-style', 'text-anchor', 'dx', 'dy',
        'href', 'xlink:href', 'xmlns:xlink', 'style', 'display', 'visibility', 'clippathunits', 'maskunits',
    ];

    /**
     * @return array{svg: string, width: float, height: float}
     */
    public function sanitize(string $svg): array
    {
        if (strlen($svg) > 5 * 1024 * 1024) {
            throw new InvalidArgumentException('SVG is too large.');
        }

        // Reject DOCTYPE/ENTITY declarations outright (XXE and billion-laughs).
        if (preg_match('/<!\s*(DOCTYPE|ENTITY)/i', $svg)) {
            throw new InvalidArgumentException('SVG contains a disallowed declaration.');
        }

        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $loaded = $dom->loadXML($svg, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $dom->documentElement;

        if (! $loaded || ! $root || strtolower($root->localName) !== 'svg') {
            throw new InvalidArgumentException('That file is not a valid SVG.');
        }

        $this->cleanAttributes($root);
        $this->clean($root);

        [$width, $height] = $this->dimensions($root);

        // Make sure Fabric can size the graphic even when the author left width/height off.
        $root->setAttribute('width', (string) $width);
        $root->setAttribute('height', (string) $height);

        if (! $root->hasAttribute('viewBox')) {
            $root->setAttribute('viewBox', "0 0 {$width} {$height}");
        }

        return ['svg' => (string) $dom->saveXML($root), 'width' => $width, 'height' => $height];
    }

    private function clean(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                // Links are dropped but the artwork inside them is kept.
                if (strtolower($child->localName) === 'a') {
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }

                    $node->removeChild($child);
                    $this->clean($node);

                    return;
                }

                if (! in_array(strtolower($child->localName), self::ELEMENTS, true)) {
                    $node->removeChild($child);

                    continue;
                }

                $this->cleanAttributes($child);
                $this->clean($child);
            } elseif ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                $node->removeChild($child);
            }
        }
    }

    private function cleanAttributes(DOMElement $el): void
    {
        /** @var DOMAttr $attr */
        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->nodeName);
            $value = trim($attr->value);

            $allowed = in_array($name, self::ATTRIBUTES, true)
                && ! str_starts_with($name, 'on')
                && ! preg_match('/javascript:|data:|expression\s*\(|@import|&#/i', $value);

            // References may only point inside the document.
            if ($allowed && in_array($name, ['href', 'xlink:href'], true) && ! str_starts_with($value, '#')) {
                $allowed = false;
            }

            if ($allowed && preg_match('/url\(\s*[\'"]?(?!#)/i', $value)) {
                $allowed = false;
            }

            if (! $allowed) {
                $el->removeAttributeNode($attr);
            }
        }
    }

    /** @return array{0: float, 1: float} */
    private function dimensions(DOMElement $root): array
    {
        $number = fn (string $v) => (float) preg_replace('/[^0-9.]/', '', $v);
        $width = $number($root->getAttribute('width'));
        $height = $number($root->getAttribute('height'));

        if (($width <= 0 || $height <= 0) && preg_match('/^\s*[-\d.]+[ ,]+[-\d.]+[ ,]+([\d.]+)[ ,]+([\d.]+)/', $root->getAttribute('viewBox'), $m)) {
            [$width, $height] = [(float) $m[1], (float) $m[2]];
        }

        if ($width <= 0 || $height <= 0) {
            throw new InvalidArgumentException('The SVG has no size. Add a width, height or viewBox.');
        }

        return [$width, $height];
    }
}
