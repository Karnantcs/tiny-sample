<?php

namespace NinjaHello\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Checks the standalone static page in static-page/. It reads the files only,
 * so it needs no server and no WordPress install.
 */
final class StaticPageTest extends TestCase
{
    private string $pageDir;
    private string $html;

    protected function setUp(): void
    {
        $this->pageDir = dirname(__DIR__) . '/static-page';
        $html = file_get_contents($this->pageDir . '/index.html');
        self::assertIsString($html, 'static-page/index.html is missing.');
        $this->html = $html;
    }

    public function testPageIsHtml5WithEnglishLanguage(): void
    {
        $this->assertStringStartsWith('<!doctype html>', strtolower($this->html));
        $this->assertMatchesRegularExpression('/<html[^>]*\blang="en"/i', $this->html);
    }

    public function testPageDeclaresUtf8AndViewport(): void
    {
        $this->assertMatchesRegularExpression('/<meta\s+charset="utf-8"\s*>/i', $this->html);
        $this->assertMatchesRegularExpression('/<meta\s+name="viewport"[^>]*width=device-width/i', $this->html);
    }

    public function testPageHasTitle(): void
    {
        $this->assertMatchesRegularExpression('#<title>\s*Hello from Ninja\s*</title>#', $this->html);
    }

    public function testPageShowsHelloHeading(): void
    {
        $this->assertMatchesRegularExpression('#<h2>\s*Hello, world!\s*</h2>#', $this->html);
    }

    public function testPageHasHeaderMainAndFooter(): void
    {
        $this->assertStringContainsString('<header class="site-header">', $this->html);
        $this->assertStringContainsString('<main class="content">', $this->html);
        $this->assertStringContainsString('<footer class="site-footer">', $this->html);
    }

    public function testHtmlTagsAreBalanced(): void
    {
        foreach (['html', 'head', 'body', 'header', 'main', 'footer', 'h1', 'h2', 'p', 'a'] as $tag) {
            $opening = preg_match_all('#<' . $tag . '[\s>]#i', $this->html);
            $closing = preg_match_all('#</' . $tag . '>#i', $this->html);
            $this->assertSame($opening, $closing, "The <$tag> tags are not balanced.");
        }
    }

    public function testLocalStylesheetExists(): void
    {
        $this->assertMatchesRegularExpression('#<link\s+rel="stylesheet"\s+href="style.css">#', $this->html);
        $this->assertFileExists($this->pageDir . '/style.css');
    }

    public function testLocalLinksResolveToFiles(): void
    {
        preg_match_all('#(?:href|src)="([^"]+)"#i', $this->html, $matches);
        foreach ($matches[1] as $link) {
            if (preg_match('#^(https?:|mailto:|\#)#i', $link)) {
                continue; // External or in-page links are not checked here.
            }
            $this->assertFileExists($this->pageDir . '/' . $link, "The local link '$link' points to a missing file.");
        }
    }

    public function testExternalLinksUseHttps(): void
    {
        preg_match_all('#href="(https?:[^"]+)"#i', $this->html, $matches);
        foreach ($matches[1] as $link) {
            $this->assertStringStartsWith('https://', $link, "The external link '$link' is not https.");
        }
    }

    public function testStylesheetIsNotEmpty(): void
    {
        $css = file_get_contents($this->pageDir . '/style.css');
        $this->assertIsString($css);
        $this->assertStringContainsString('.content', $css);
    }
}
