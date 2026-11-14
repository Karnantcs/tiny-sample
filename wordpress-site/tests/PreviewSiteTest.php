<?php

namespace NinjaHello\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Starts the WordPress preview with preview/start.sh, as NinjaBots does, and
 * checks that the home page shows the [ninja_hello] shortcode. Then runs the
 * installer again to check that a later start changes nothing.
 *
 * Needs pdo_sqlite, proc_open and sh. The test is skipped when they are missing.
 */
final class PreviewSiteTest extends TestCase
{
    private const START_TIMEOUT_SECONDS = 180;

    private string $siteDir;
    private string $webRoot;
    private string $logFile;

    protected function setUp(): void
    {
        $this->siteDir = dirname(__DIR__);

        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('The preview needs the pdo_sqlite extension.');
        }
        if (!function_exists('proc_open')) {
            self::markTestSkipped('The preview needs proc_open.');
        }
        if (!is_file($this->siteDir . '/vendor/johnpbloch/wordpress-core/wp-includes/version.php')) {
            self::markTestSkipped('WordPress core is not installed. Run composer install first.');
        }

        $name = 'ninja-preview-' . bin2hex(random_bytes(6));
        $this->webRoot = sys_get_temp_dir() . '/' . $name;
        $this->logFile = sys_get_temp_dir() . '/' . $name . '.log';
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->webRoot);
        if (is_file($this->logFile)) {
            unlink($this->logFile);
        }
    }

    public function testHomePageShowsTheNinjaHelloShortcode(): void
    {
        $port = $this->freePort();
        $process = $this->startPreview($port);

        try {
            $body = $this->waitForPage($process, $port);
        } finally {
            proc_terminate($process);
            proc_close($process);
        }

        $this->assertStringContainsString(
            '<span class="ninja-hello">Hello, Ada!</span>',
            $body,
            "The home page does not show the shortcode. Page start:\n" . substr($body, 0, 1500) . "\nLog:\n" . $this->readLog()
        );
    }

    public function testLinksAndStylesUseTheForwardedAddress(): void
    {
        $port = $this->freePort();
        $process = $this->startPreview($port);

        try {
            $this->waitForPage($process, $port);
            $headers = "X-Forwarded-Proto: https\r\n"
                . "X-Forwarded-Host: ninja.example.test\r\n"
                . "X-Forwarded-Prefix: /preview/\r\n";
            [$status, $body] = $this->fetchPage($port, $headers);
        } finally {
            proc_terminate($process);
            proc_close($process);
        }

        $this->assertStringStartsWith('HTTP/1.1 200', $status, "The page did not load. Status: $status\nBody start:\n" . substr($body, 0, 800) . "\nLog:\n" . $this->readLog());
        preg_match_all('#https?://[^"\'\s<>]+#', $body, $found);
        $urls = implode(' ', array_unique($found[0]));

        if (!str_contains($body, 'href="https://ninja.example.test/preview/"')) {
            self::fail('The home link does not use the forwarded address. URLs on the page: ' . $urls);
        }
        if (!str_contains($body, 'https://ninja.example.test/preview/wp-content/themes/ninja-theme/style.css')) {
            self::fail('The theme stylesheet does not use the forwarded address. URLs on the page: ' . $urls);
        }
        self::assertFalse(
            (bool) preg_match('#https?://localhost#', $body),
            "The page still links to localhost. URLs on the page:\n" . $urls
        );
    }

    public function testStartingAgainDoesNotReinstall(): void
    {
        $port = $this->freePort();
        $process = $this->startPreview($port);
        try {
            $this->waitForPage($process, $port);
        } finally {
            proc_terminate($process);
            proc_close($process);
        }

        $output = $this->runInstaller($port);

        $this->assertStringContainsString('already installed', $output, "Installer output: $output\nLog:\n" . $this->readLog());
        $this->assertStringNotContainsString('installing WordPress', $output);
    }

    /**
     * Starts preview/start.sh with the settings NinjaBots would give it.
     *
     * @return resource
     */
    private function startPreview(int $port)
    {
        $env = array_merge(getenv(), [
            'PORT' => (string) $port,
            'PHP_BIN' => PHP_BINARY,
            'NINJA_PREVIEW_DIR' => $this->webRoot,
            'WP_ADMIN_PASSWORD' => 'preview-test-password',
            'PHP_CLI_SERVER_WORKERS' => '4',
        ]);
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $this->logFile, 'a'],
            2 => ['file', $this->logFile, 'a'],
        ];

        $process = proc_open(['sh', $this->siteDir . '/preview/start.sh'], $descriptors, $pipes, $this->siteDir, $env);
        if (!is_resource($process)) {
            self::fail('Could not start preview/start.sh.');
        }
        fclose($pipes[0]);

        return $process;
    }

    /**
     * Waits until the preview answers on its port, then returns the home page HTML.
     *
     * @param resource $process
     */
    private function waitForPage($process, int $port): string
    {
        $context = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
        $url = 'http://127.0.0.1:' . $port . '/';
        $deadline = time() + self::START_TIMEOUT_SECONDS;

        while (time() < $deadline) {
            $status = proc_get_status($process);
            if (!$status['running']) {
                self::fail("The preview stopped before it answered.\n" . $this->readLog());
            }

            $body = @file_get_contents($url, false, $context);
            if ($body !== false) {
                return $body;
            }

            usleep(500000);
        }

        self::fail("The preview did not answer within " . self::START_TIMEOUT_SECONDS . " seconds.\n" . $this->readLog());
    }

    /**
     * Requests the home page once, with the given extra request headers, without following redirects.
     *
     * @return array{0: string, 1: string} The status line and the body.
     */
    private function fetchPage(int $port, string $headers): array
    {
        $context = stream_context_create([
            'http' => ['timeout' => 10, 'ignore_errors' => true, 'follow_location' => 0, 'header' => $headers],
        ]);
        $body = @file_get_contents('http://127.0.0.1:' . $port . '/', false, $context);
        if ($body === false) {
            self::fail("The preview did not answer the request.\n" . $this->readLog());
        }

        $status = $http_response_header[0] ?? '';
        foreach ($http_response_header ?? [] as $line) {
            if (stripos($line, 'Location:') === 0) {
                $status .= ' ' . $line;
            }
        }

        return [$status, $body];
    }

    /**
     * Runs the installer on the existing web root, as a later start does.
     */
    private function runInstaller(int $port): string
    {
        $env = array_merge(getenv(), ['PORT' => (string) $port]);
        $process = proc_open(
            [PHP_BINARY, $this->siteDir . '/preview/bootstrap.php', $this->webRoot],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->siteDir,
            $env
        );
        if (!is_resource($process)) {
            self::fail('Could not run preview/bootstrap.php.');
        }

        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        self::assertSame(0, $exitCode, "Installer failed:\n" . $errors);

        return $output;
    }

    private function freePort(): int
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($server === false) {
            self::fail("No free port: $errstr");
        }
        $address = stream_socket_get_name($server, false);
        fclose($server);

        return (int) substr($address, strrpos($address, ':') + 1);
    }

    private function readLog(): string
    {
        return is_file($this->logFile) ? (string) file_get_contents($this->logFile) : '';
    }

    private function removeTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->removeTree($path . '/' . $entry);
            }
        }
        rmdir($path);
    }
}
