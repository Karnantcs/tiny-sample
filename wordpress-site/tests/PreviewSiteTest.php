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

    public function testShopListsSampleShoesAndSoldOutStock(): void
    {
        $port = $this->freePort();
        $process = $this->startPreview($port);

        try {
            $this->waitForPage($process, $port);
            [$status, , $body] = $this->request($port, '/?pagename=shoes');
        } finally {
            proc_terminate($process);
            proc_close($process);
        }

        $this->assertStringStartsWith('HTTP/1.1 200', $status, "The Shoes page did not load.\nLog:\n" . $this->readLog());
        $this->assertStringContainsString('Trail Runner', $body);
        $this->assertStringContainsString('Size US 9', $body);
        $this->assertStringContainsString('Canvas Low Top', $body);
        $this->assertStringContainsString('Sold out', $body);
        $this->assertStringContainsString('$89.00', $body);
    }

    public function testMockPaymentApprovesAndDeclinesShoePurchases(): void
    {
        $port = $this->freePort();
        $process = $this->startPreview($port);

        try {
            $this->waitForPage($process, $port);

            [, , $list] = $this->request($port, '/?pagename=shoes');
            $this->assertSame(1, preg_match('/ninja_shoe=(\d+)/', $list, $shoeMatch), "No shoe is on sale.\n" . $list);
            $shoeId = $shoeMatch[1];

            [, , $checkout] = $this->request($port, '/?pagename=shoes&ninja_shoe=' . $shoeId);
            $this->assertSame(1, preg_match('/name="_wpnonce" value="([^"]+)"/', $checkout, $nonce), "No checkout form.\n" . $checkout);
            $this->assertSame(1, preg_match('/name="shop_page" value="(\d+)"/', $checkout, $page));

            $form = [
                'ninja_shoes_action' => 'checkout',
                '_wpnonce' => $nonce[1],
                'shop_page' => $page[1],
                'ninja_shoe_id' => $shoeId,
                'name' => 'Ada Lovelace',
                'email' => 'ada@example.com',
                'quantity' => '2',
                'card_number' => '4242 4242 4242 4242',
                'expiry_month' => '12',
                'expiry_year' => (string) ((int) gmdate('Y') + 3),
                'cvc' => '123',
            ];

            [$status, $headers] = $this->request($port, '/?pagename=shows', $form);
            $this->assertStringStartsWith('HTTP/1.1 303', $status, "The approved purchase did not redirect.\n" . $headers . "\nLog:\n" . $this->readLog());
            $this->assertSame(1, preg_match('/^Location:\s*(\S+)/mi', $headers, $location), $headers);
            $confirmation = (string) parse_url(trim($location[1]), PHP_URL_QUERY);
            [, , $confirmationPage] = $this->request($port, '/?' . $confirmation);
            $this->assertStringContainsString('Thank you, Ada Lovelace!', $confirmationPage);
            $this->assertStringContainsString('$178.00', $confirmationPage);
            $this->assertStringContainsString('US 9', $confirmationPage);

            $declined = $form;
            $declined['card_number'] = '4000 0000 0000 0002';
            [, $headers] = $this->request($port, '/?pagename=shows', $declined);
            $this->assertSame(1, preg_match('/^Location:\s*(\S+)/mi', $headers, $notice), $headers);
            $noticeQuery = (string) parse_url(trim($notice[1]), PHP_URL_QUERY);
            [, , $retry] = $this->request($port, '/?' . $noticeQuery);
            preg_match_all('/ninja-field-error">([^<]+)/', $retry, $fieldErrors);
            $this->assertSame([], $fieldErrors[1], 'Checkout rejected the declined purchase before the gateway ran.');
            $this->assertStringContainsString('Payment was declined. The card was declined.', $retry);
            $this->assertStringContainsString('value="Ada Lovelace"', $retry);
        } finally {
            proc_terminate($process);
            proc_close($process);
        }
    }

    /**
     * Sends one request to the preview without following redirects.
     *
     * @param array<string, string>|null $form Form fields to POST, or null for a GET.
     * @return array{0: string, 1: string, 2: string} The status line, the response headers and the body.
     */
    private function request(int $port, string $path, ?array $form = null): array
    {
        $http = ['timeout' => 10, 'ignore_errors' => true, 'follow_location' => 0];
        if ($form !== null) {
            $http['method'] = 'POST';
            $http['header'] = "Content-Type: application/x-www-form-urlencoded\r\n";
            $http['content'] = http_build_query($form);
        }

        $body = @file_get_contents('http://127.0.0.1:' . $port . $path, false, stream_context_create(['http' => $http]));
        if ($body === false) {
            self::fail("The preview did not answer $path.\n" . $this->readLog());
        }

        $responseHeaders = $http_response_header ?? [];

        return [$responseHeaders[0] ?? '', implode("\n", $responseHeaders), $body];
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
