<?php

/**
 * Content-Security-Policy collector.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\Http
 */

namespace WPB\Http;

use InvalidArgumentException;

/**
 * Builds a Content-Security-Policy and sends it through `Header`.
 *
 * Call `add()` for each directive, then `register()`. Sources for the same
 * directive are merged in first-seen order. A repeated source is kept once.
 * Directive names match without case.
 *
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Content-Security-Policy
 */
class Csp
{
    /**
     * Source keywords that browsers expect in single quotes.
     *
     * @var list<string>
     */
    private const array KEYWORDS = [
        'self',
        'none',
        'unsafe-inline',
        'unsafe-eval',
        'strict-dynamic',
        'wasm-unsafe-eval',
        'unsafe-hashes',
        'report-sample',
    ];

    /**
     * Sources for each directive, keyed by the lowercase name.
     *
     * @var array<string, list<string>>
     */
    private array $directives = [];

    /**
     * Header that sends the policy. Empty until the first real `register()`.
     */
    private ?Header $header = null;

    /**
     * Merges sources into a directive.
     *
     * An empty list does not create the directive. An invalid call leaves the
     * stored policy unchanged.
     *
     * @param string       $directive Directive name, such as `script-src`.
     * @param array<mixed> $sources   Source list.
     *
     * @return static
     *
     * @throws InvalidArgumentException If the directive or a source cannot be sent.
     */
    public function add(string $directive, array $sources): static
    {
        $directive = $this->assertDirective($directive);
        $normalized = [];

        foreach ($sources as $source) {
            if (!is_string($source)) {
                throw new InvalidArgumentException('CSP source must be a string.');
            }

            $normalized[] = $this->normalizeSource($source);
        }

        if ($normalized === []) {
            return $this;
        }

        if (!isset($this->directives[$directive])) {
            $this->directives[$directive] = [];
        }

        foreach ($normalized as $source) {
            if (!in_array($source, $this->directives[$directive], true)) {
                $this->directives[$directive][] = $source;
            }
        }

        return $this;
    }

    /**
     * Returns the policy value.
     *
     * @return string Directives separated by `; `, or an empty string.
     */
    public function policy(): string
    {
        $parts = [];

        foreach ($this->directives as $directive => $sources) {
            $parts[] = $directive . ' ' . implode(' ', $sources);
        }

        return implode('; ', $parts);
    }

    /**
     * Sends the current policy through one `Header`.
     *
     * A later `add()` is sent only after `register()` runs again. The second
     * call updates the same header and does not add another filter. An empty
     * policy does not register a header.
     */
    public function register(): void
    {
        $policy = $this->policy();

        if ($policy === '') {
            return;
        }

        if ($this->header === null) {
            $this->header = new Header();
            $this->header->register();
        }

        $this->header->set('Content-Security-Policy', $policy);
    }

    /**
     * Accepts a directive name that can be sent.
     *
     * @param string $directive Directive name.
     *
     * @return string Lowercase name.
     *
     * @throws InvalidArgumentException If the name is empty or contains other characters.
     */
    private function assertDirective(string $directive): string
    {
        $directive = strtolower(trim($directive));

        if ($directive === '') {
            throw new InvalidArgumentException('CSP directive must not be empty.');
        }

        if (preg_match('/^[a-z0-9-]+$/', $directive) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'CSP directive "%s" must contain only letters, numbers, and hyphens.',
                str_replace(["\r", "\n"], ['\r', '\n'], $directive)
            ));
        }

        return $directive;
    }

    /**
     * Returns a source as it is written in the policy.
     *
     * @param string $source Source expression.
     *
     * @return string Quoted keyword, nonce, or hash, or the original host or scheme.
     *
     * @throws InvalidArgumentException If the source is empty or contains whitespace, a semicolon, or a comma.
     */
    private function normalizeSource(string $source): string
    {
        $source = trim($source);

        if ($source === '' || preg_match('/[\s;,]/', $source) === 1) {
            throw new InvalidArgumentException(sprintf(
                'CSP source "%s" must not be empty or contain whitespace, a semicolon, or a comma.',
                str_replace(["\r", "\n"], ['\r', '\n'], $source)
            ));
        }

        if (str_starts_with($source, "'") && str_ends_with($source, "'") && strlen($source) >= 2) {
            $source = substr($source, 1, -1);
        }

        if ($source === '') {
            throw new InvalidArgumentException('CSP source must not be empty.');
        }

        if ($this->quotesSource($source)) {
            return "'" . $source . "'";
        }

        return $source;
    }

    /**
     * Reports whether a source is written in single quotes.
     *
     * @param string $source Unquoted source.
     *
     * @return bool True for a keyword, nonce, or hash.
     */
    private function quotesSource(string $source): bool
    {
        if (in_array($source, self::KEYWORDS, true)) {
            return true;
        }

        $source = strtolower($source);

        return str_starts_with($source, 'nonce-')
            || str_starts_with($source, 'sha256-')
            || str_starts_with($source, 'sha384-')
            || str_starts_with($source, 'sha512-');
    }
}
