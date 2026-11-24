<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Thelia\Test\Volume\Measure;

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Calls a page of a running shop and counts the statements its database received meanwhile, from
 * the server's own counter (SHOW GLOBAL STATUS LIKE 'Questions'). The count does not depend on the
 * machine or the load, which is why it is the main measure; it is exact only while nothing else
 * talks to the database server.
 *
 * Keeps one cookie jar per session (anonymous, customer, administrator).
 */
final class QueryCountingClient
{
    private HttpClientInterface $http;

    /** @var array<string, array<string, string>> cookies per session */
    private array $cookies = [];

    /** Statements the counter reading itself adds, measured once. */
    private ?int $readingCost = null;

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $baseUrl,
        ?HttpClientInterface $http = null,
    ) {
        // A measure targets a disposable shop, often behind a local certificate.
        $this->http = $http ?? HttpClient::create(['verify_peer' => false, 'verify_host' => false, 'max_redirects' => 0, 'timeout' => 600]);
    }

    public function questions(): int
    {
        $statement = $this->connection->prepare("SHOW GLOBAL STATUS LIKE 'Questions'");
        $statement->execute();
        $row = $statement->fetch(\PDO::FETCH_NUM);

        return \is_array($row) ? (int) $row[1] : throw new \RuntimeException('The database server does not report its Questions counter.');
    }

    public function readingCost(): int
    {
        if (null === $this->readingCost) {
            $costs = [];
            for ($attempt = 0; $attempt < 3; ++$attempt) {
                $before = $this->questions();
                $costs[] = $this->questions() - $before;
            }
            $this->readingCost = min($costs);
        }

        return $this->readingCost;
    }

    public function measure(string $path, string $session = 'anonymous'): PageMeasure
    {
        $cost = $this->readingCost();
        $before = $this->questions();
        $response = $this->http->request('GET', $this->url($path), ['headers' => $this->headers($session)]);
        $status = $response->getStatusCode();
        $size = \strlen($response->getContent(false));
        $after = $this->questions();
        $this->keepCookies($session, $response->getHeaders(false));

        $info = $response->getInfo();

        return new PageMeasure(
            status: $status,
            queries: $after - $before - $cost,
            firstByteSeconds: (float) ($info['starttransfer_time'] ?? $info['total_time'] ?? 0.0),
            totalSeconds: (float) ($info['total_time'] ?? 0.0),
            bytes: $size,
        );
    }

    /**
     * Saves a form of the shop as it stands, as a person who opens it and clicks save would: the
     * form page is read first and not counted, then only the post is counted. The post must
     * redirect to the path given in $expectedLocation, which is how a back-office save tells a
     * success from a refusal (both redirect).
     *
     * @param array<string, string> $fields values sent in place of the ones the form holds
     */
    public function submit(string $formPath, string $action, array $fields, string $expectedLocation, string $session = 'anonymous'): PageMeasure
    {
        [$actionUrl, $body] = $this->readForm($session, $formPath, $action, $fields);

        $cost = $this->readingCost();
        $before = $this->questions();
        $response = $this->http->request('POST', $actionUrl, [
            'headers' => $this->headers($session) + ['Origin' => $this->origin(), 'Referer' => $this->url($formPath), 'Content-Type' => 'application/x-www-form-urlencoded'],
            'body' => $body,
        ]);
        $status = $response->getStatusCode();
        $size = \strlen($response->getContent(false));
        $after = $this->questions();
        $location = $response->getHeaders(false)['location'][0] ?? '';
        $this->keepCookies($session, $response->getHeaders(false));

        if (302 !== $status || parse_url($location, \PHP_URL_PATH) !== $expectedLocation) {
            throw new \RuntimeException(\sprintf('Posting %s did not save: %d, redirected to "%s" instead of %s.', $action, $status, $location, $expectedLocation));
        }

        $info = $response->getInfo();

        return new PageMeasure(
            status: $status,
            queries: $after - $before - $cost,
            firstByteSeconds: (float) ($info['starttransfer_time'] ?? $info['total_time'] ?? 0.0),
            totalSeconds: (float) ($info['total_time'] ?? 0.0),
            bytes: $size,
        );
    }

    /**
     * Signs a session in through the form of the shop, as a visitor would: the form page gives the
     * hidden fields and the CSRF token, the post carries the Origin of the shop.
     *
     * @param array<string, string> $fields
     */
    public function signIn(string $session, string $formPath, string $action, array $fields): void
    {
        [$actionUrl, $body] = $this->readForm($session, $formPath, $action, $fields);

        $response = $this->http->request('POST', $actionUrl, [
            'headers' => $this->headers($session) + ['Origin' => $this->origin(), 'Referer' => $this->url($formPath), 'Content-Type' => 'application/x-www-form-urlencoded'],
            'body' => $body,
        ]);
        $status = $response->getStatusCode();
        $location = $response->getHeaders(false)['location'][0] ?? '';
        $this->keepCookies($session, $response->getHeaders(false));

        if (302 !== $status || str_contains($location, $formPath)) {
            throw new \RuntimeException(\sprintf('Signing the %s session in failed: %d %s.', $session, $status, $location));
        }
    }

    /**
     * Reads the form of a page whose action starts with $action: the URL it posts to, and the
     * encoded body of what it holds (hidden fields and CSRF token, text, selected options, checked
     * boxes), $fields replacing the values of the same name.
     *
     * @param array<string, string> $fields
     *
     * @return array{string, string}
     */
    private function readForm(string $session, string $formPath, string $action, array $fields): array
    {
        $page = $this->http->request('GET', $this->url($formPath), ['headers' => $this->headers($session)]);
        $html = $page->getContent(false);
        $this->keepCookies($session, $page->getHeaders(false));

        $nodes = (new Crawler($html, $this->url($formPath)))->filterXPath(\sprintf('//form[starts-with(@action, "%s")]', $action));
        if (0 === $nodes->count()) {
            throw new \RuntimeException(\sprintf('No form posting to %s on %s.', $action, $formPath));
        }
        $form = $nodes->first()->form();

        $pairs = [];
        foreach (array_merge($form->getValues(), $fields) as $name => $value) {
            foreach ((array) $value as $item) {
                $pairs[] = rawurlencode((string) $name).'='.rawurlencode((string) $item);
            }
        }

        return [$form->getUri(), implode('&', $pairs)];
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');
    }

    private function origin(): string
    {
        $parts = parse_url($this->baseUrl);

        return \sprintf('%s://%s%s', $parts['scheme'] ?? 'http', $parts['host'] ?? 'localhost', isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $session): array
    {
        $cookies = $this->cookies[$session] ?? [];
        if ([] === $cookies) {
            return [];
        }

        return ['Cookie' => implode('; ', array_map(
            static fn (string $name, string $value): string => $name.'='.$value,
            array_keys($cookies),
            array_values($cookies),
        ))];
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private function keepCookies(string $session, array $headers): void
    {
        foreach ($headers['set-cookie'] ?? [] as $header) {
            [$pair] = explode(';', $header, 2);
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $name = trim($name);
            if ('' === $value || 'deleted' === $value) {
                unset($this->cookies[$session][$name]);

                continue;
            }
            $this->cookies[$session][$name] = $value;
        }
    }
}
