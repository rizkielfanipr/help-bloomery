<?php

namespace App\Services\Rnd;

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Security boundary for rich-text content stored by R&D features. It only transforms strings:
 * callers authorize the actor and own the storage operations.
 */
class RndRichContentService
{
    public const MAX_HTML_LENGTH = 5000000;

    /**
     * Allowlist parse for new content. The parser normalizes entity and whitespace tricks first, so
     * the later steps only read attributes it has already re-encoded.
     * Semantic-empty content becomes null.
     */
    public function sanitize(?string $html, string $attachmentPrefix, string $imageAlt): ?string
    {
        $sanitized = $this->allowlistSanitizer()->sanitize((string) $html);
        $sanitized = preg_replace_callback(
            '/<a\b[^>]*>/iu',
            fn (array $match): string => $this->rebuildLink($match[0]),
            $sanitized,
        ) ?? '';
        $sanitized = trim(preg_replace_callback(
            '/<img\b[^>]*>/iu',
            fn (array $match): string => $this->rebuildAttachmentImage($match[0], $attachmentPrefix, $imageAlt),
            $sanitized,
        ) ?? '');

        return $this->isSemanticallyEmpty($sanitized) ? null : $sanitized;
    }

    /**
     * BOM instruction rules, which also accept base64 and legacy stored-URL images. Kept apart from
     * sanitize() because stored BOM content and PDF exports depend on this exact output.
     */
    public function sanitizeLegacy(string $html, string $attachmentPrefix, string $legacyImageUrlPattern, string $imageAlt): string
    {
        $html = strip_tags($html, '<p><br><strong><b><em><i><u><s><ol><ul><li><h1><h2><h3><blockquote><a><img>');
        $html = preg_replace('/\son\w+\s*=\s*(["\']).*?\1/iu', '', $html) ?? '';
        $html = preg_replace('/javascript\s*:/iu', '', $html) ?? '';
        $html = preg_replace_callback('/<img\b[^>]*>/iu', function (array $match) use ($attachmentPrefix, $legacyImageUrlPattern, $imageAlt): string {
            $dimensions = $this->legacyImageDimensions($match[0]);

            if (preg_match('/\bdata-id\s*=\s*(["\'])(.*?)\1/iu', $match[0], $dataId)) {
                $path = html_entity_decode($dataId[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                return str_starts_with($path, $attachmentPrefix)
                    ? '<img data-id="'.e($path).'" alt="'.e($imageAlt).'"'.$dimensions.'>'
                    : '';
            }

            if (! preg_match('/\bsrc\s*=\s*(["\'])(.*?)\1/iu', $match[0], $src)) {
                return '';
            }

            $url = $src[2];
            $isBase64 = preg_match('/^data:image\/(?:jpeg|png|webp);base64,[A-Za-z0-9+\/=]+$/iu', $url) === 1;
            $isStoredImage = preg_match($legacyImageUrlPattern, $url) === 1;

            if (! $isBase64 && ! $isStoredImage) {
                return '';
            }

            return '<img src="'.e($url).'" alt="'.e($imageAlt).'"'.$dimensions.'>';
        }, $html) ?? '';
        $html = preg_replace_callback('/<a\b[^>]*>/iu', static function (array $match): string {
            if (! preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/iu', $match[0], $hrefMatch)) {
                return '<a>';
            }

            $href = html_entity_decode($hrefMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $href = preg_replace('/[\x00-\x20]+/u', '', $href) ?? $href;

            $scheme = null;
            if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $href, $schemeMatch)) {
                $scheme = strtolower($schemeMatch[1]);
            }

            $isSafeRelative = $scheme === null && ! str_starts_with($href, '//');
            $isAllowedScheme = $scheme !== null && in_array($scheme, ['http', 'https', 'mailto'], true);

            if (! $isSafeRelative && ! $isAllowedScheme) {
                return '<a>';
            }

            return '<a href="'.e($href).'" target="_blank" rel="noopener noreferrer">';
        }, $html) ?? '';

        return trim($html);
    }

    /** Resolve private attachments to temporary URLs. The HTML must already be sanitized. */
    public function render(string $html): string
    {
        if (! str_contains($html, 'data-id=')) {
            return $html;
        }

        return RichContentRenderer::make($html)
            ->fileAttachmentsDisk('b2')
            ->fileAttachmentsVisibility('private')
            ->toHtml();
    }

    /**
     * A direct file under the prefix; a plain prefix check would let "../" escape to another record.
     */
    public function isAllowedAttachmentPath(string $path, string $attachmentPrefix): bool
    {
        return str_starts_with($path, $attachmentPrefix)
            && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/D', substr($path, strlen($attachmentPrefix))) === 1;
    }

    /**
     * @return list<string>
     */
    public function attachmentPaths(string $html, string $attachmentPrefix, ?string $legacyPathPattern = null): array
    {
        $legacyMatches = [];
        if ($legacyPathPattern !== null) {
            preg_match_all($legacyPathPattern, $html, $legacyMatches);
        }

        preg_match_all(
            '#data-id=(["\'])('.preg_quote($attachmentPrefix, '#').'[^"\']+)\1#iu',
            $html,
            $attachmentMatches,
        );

        return array_values(array_unique(array_merge($legacyMatches[1] ?? [], $attachmentMatches[2] ?? [])));
    }

    /**
     * Files referenced before but not after a save. Only delete these once the save has succeeded.
     *
     * @return list<string>
     */
    public function orphanedAttachmentPaths(string $previousHtml, string $currentHtml, string $attachmentPrefix, ?string $legacyPathPattern = null): array
    {
        return array_values(array_diff(
            $this->attachmentPaths($previousHtml, $attachmentPrefix, $legacyPathPattern),
            $this->attachmentPaths($currentHtml, $attachmentPrefix, $legacyPathPattern),
        ));
    }

    private function allowlistSanitizer(): HtmlSanitizer
    {
        $config = (new HtmlSanitizerConfig)
            ->withMaxInputLength(self::MAX_HTML_LENGTH)
            ->allowRelativeLinks()
            ->allowLinkSchemes(['http', 'https', 'mailto', 'Http', 'Https', 'Mailto', 'HTTP', 'HTTPS', 'MAILTO'])
            ->allowElement('a', ['href'])
            ->allowElement('img', ['data-id', 'width', 'height']);

        foreach (['p', 'br', 'h1', 'h2', 'h3', 'blockquote', 'strong', 'b', 'em', 'i', 'u', 's', 'ol', 'ul', 'li'] as $element) {
            $config = $config->allowElement($element);
        }

        // Harmless wrappers keep their text; every other unknown element is dropped with its content.
        foreach (['div', 'span', 'section', 'article', 'code', 'pre', 'h4', 'h5', 'h6', 'small', 'mark', 'sub', 'sup', 'table', 'tbody', 'thead', 'tr', 'td', 'th'] as $element) {
            $config = $config->blockElement($element);
        }

        return new HtmlSanitizer($config);
    }

    private function rebuildLink(string $tag): string
    {
        if (! preg_match('/\shref="([^"]*)"/iu', $tag, $match)) {
            return '<a>';
        }

        $href = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // The allowlist accepts these as relative links, but browsers resolve them to another host.
        if (str_starts_with($href, '//') || str_contains($href, '\\')) {
            return '<a>';
        }

        return '<a href="'.e($href).'" target="_blank" rel="noopener noreferrer">';
    }

    private function rebuildAttachmentImage(string $tag, string $attachmentPrefix, string $imageAlt): string
    {
        if (! preg_match('/\sdata-id="([^"]*)"/iu', $tag, $match)) {
            return '';
        }

        $path = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (! $this->isAllowedAttachmentPath($path, $attachmentPrefix)) {
            return '';
        }

        $dimensions = '';

        foreach (['width', 'height'] as $attribute) {
            if (! preg_match('/\s'.$attribute.'="([^"]*)"/iu', $tag, $dimension)) {
                continue;
            }

            $value = $this->safeImageDimension($dimension[1]);

            if ($value !== null && ! (str_ends_with($value, '%') && (int) $value > 100)) {
                $dimensions .= ' '.$attribute.'="'.e($value).'"';
            }
        }

        return '<img data-id="'.e($path).'" alt="'.e($imageAlt).'"'.$dimensions.'>';
    }

    private function legacyImageDimensions(string $imageTag): string
    {
        $attributes = '';

        foreach (['width', 'height'] as $attribute) {
            if (! preg_match('/\b'.$attribute.'\s*=\s*(["\'])(.*?)\1/iu', $imageTag, $match)) {
                continue;
            }

            $value = $this->safeImageDimension($match[2]);

            if ($value !== null) {
                $attributes .= ' '.$attribute.'="'.e($value).'"';
            }
        }

        return $attributes;
    }

    private function safeImageDimension(string $value): ?string
    {
        $value = strtolower(trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return preg_match('/^(?:[1-9]\d{0,3})(?:px|%)?$/', $value) === 1 ? $value : null;
    }

    private function isSemanticallyEmpty(string $html): bool
    {
        if (str_contains($html, '<img')) {
            return false;
        }

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(str_replace("\u{00A0}", ' ', $text)) === '';
    }
}
