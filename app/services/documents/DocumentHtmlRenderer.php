<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Services\SignatoryService;

/**
 * Composes a Document into its independent, standalone HTML.
 *
 * The result has no application chrome of any kind: no sidebar, no topbar, no
 * filter panel, no buttons, no URLs, no debug output. It is the same markup
 * for the on-screen preview, the PDF pipeline and the PNG pipeline, which is
 * what makes "what the administrator approved" and "what the school prints"
 * the same object rather than two similar-looking things.
 */
final class DocumentHtmlRenderer
{
    public function __construct(private readonly PageComposer $composer = new PageComposer())
    {
    }

    /**
     * @param array{signatures?:bool, watermark?:bool, compact?:bool, embed?:bool} $options
     */
    public function render(
        Document $document,
        DocumentIdentity $identity,
        array $options = [],
        bool $forExport = true,
    ): string {
        $composed = $this->composer->compose($document);
        $pages = $composed['pages'];
        $totalPages = $composed['total'];
        $embed = (bool) ($options['embed'] ?? false);

        // Resolved once per render, never per page: the footer repeats on
        // every page, but the signature configuration is a single read.
        $signatories = $document->signatures !== []
            ? SignatoryService::resolve($document->signatures)
            : [];
        $signatureFonts = array_values(array_unique(array_map(
            static fn (array $signatory): string => (string) $signatory['font_key'],
            $signatories
        )));

        ob_start();
        // Variables consumed by the layout and its partials.
        $layoutState = compact(
            'document',
            'identity',
            'pages',
            'totalPages',
            'forExport',
            'embed',
            'options',
            'signatories',
            'signatureFonts'
        );
        extract($layoutState, EXTR_SKIP);
        require dirname(__DIR__, 3) . '/views/documents/document.php';

        return (string) ob_get_clean();
    }

    /** Page count for a document without producing output -- used by preview and bulk planning. */
    public function pageCount(Document $document): int
    {
        return $this->composer->compose($document)['total'];
    }
}
