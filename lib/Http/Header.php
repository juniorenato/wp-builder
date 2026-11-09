<?php

/**
 * HTTP response header collector.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\Http
 */

namespace WPB\Http;

use InvalidArgumentException;

/**
 * Collects response headers and applies them on `wp_headers`.
 *
 * Call `set()`, `setIfAbsent()`, and `remove()` in any order, then `register()`.
 * The last operation for a name wins. Matching ignores case. This filter runs
 * on the public response. Admin, REST, and `admin-ajax` do not pass through it.
 *
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 *
 * @see https://developer.wordpress.org/reference/hooks/wp_headers/
 */
class Header
{
    /**
     * Last operation for each header, keyed by the lowercase name.
     *
     * A null value removes the header. `replace` false keeps a value already
     * present on the response.
     *
     * @var array<string, array{name: string, value: string|null, replace: bool}>
     */
    private array $operations = [];

    /**
     * Whether `wp_headers` is already hooked for this instance.
     */
    private bool $registered = false;

    /**
     * Stores a header value.
     *
     * The stored name is trimmed. An empty value is kept.
     *
     * @param string $name  Header name.
     * @param string $value Header value.
     *
     * @return static
     *
     * @throws InvalidArgumentException If the name or value cannot be sent.
     */
    public function set(string $name, string $value): static
    {
        $name = $this->assertName($name);
        $this->assertValue($value);
        $this->remember($name, $value, true);

        return $this;
    }

    /**
     * Stores a header value that is sent only when the response lacks that name.
     *
     * The stored name is trimmed. An empty value is kept. A header WordPress
     * already set, in any capitalization, stays as it is.
     *
     * @param string $name  Header name.
     * @param string $value Header value.
     *
     * @return static
     *
     * @throws InvalidArgumentException If the name or value cannot be sent.
     */
    public function setIfAbsent(string $name, string $value): static
    {
        $name = $this->assertName($name);
        $this->assertValue($value);
        $this->remember($name, $value, false);

        return $this;
    }

    /**
     * Stores a header removal.
     *
     * The stored name is trimmed.
     *
     * @param string $name Header name.
     *
     * @return static
     *
     * @throws InvalidArgumentException If the name cannot be sent.
     */
    public function remove(string $name): static
    {
        $name = $this->assertName($name);
        $this->remember($name, null, true);

        return $this;
    }

    /**
     * Hooks this instance into `wp_headers` once.
     *
     * Operations added after this call still apply. The list is read when
     * WordPress sends the headers.
     *
     * @see https://developer.wordpress.org/reference/hooks/wp_headers/
     */
    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;

        add_filter('wp_headers', [$this, 'apply']);
    }

    /**
     * Applies the stored operations to a header list.
     *
     * @param array<string, string> $headers Response headers.
     *
     * @return array<string, string>
     *
     * @see https://developer.wordpress.org/reference/hooks/wp_headers/
     */
    public function apply(array $headers): array
    {
        foreach ($this->operations as $key => $operation) {
            $matches = [];

            foreach (array_keys($headers) as $headerName) {
                if (strtolower((string) $headerName) === $key) {
                    $matches[] = $headerName;
                }
            }

            if ($operation['value'] === null) {
                foreach ($matches as $headerName) {
                    unset($headers[$headerName]);
                }

                continue;
            }

            if ($operation['replace'] === false && $matches !== []) {
                continue;
            }

            foreach ($matches as $headerName) {
                if ($headerName !== $operation['name']) {
                    unset($headers[$headerName]);
                }
            }

            $headers[$operation['name']] = $operation['value'];
        }

        return $headers;
    }

    /**
     * Accepts a header name that can be sent.
     *
     * @param string $name Header name.
     *
     * @return string Trimmed name.
     *
     * @throws InvalidArgumentException If the name is empty or contains a colon or a line break.
     */
    private function assertName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('Header name must not be empty.');
        }

        if (preg_match('/[\r\n:]/', $name) === 1) {
            throw new InvalidArgumentException(sprintf(
                'Header name "%s" must not contain a colon or a line break.',
                str_replace(["\r", "\n"], ['\r', '\n'], $name)
            ));
        }

        return $name;
    }

    /**
     * Stores the last operation for a header name.
     *
     * @param string      $name    Trimmed header name.
     * @param string|null $value   Header value, or null to remove it.
     * @param bool        $replace False keeps a value already present on the response.
     */
    private function remember(string $name, ?string $value, bool $replace): void
    {
        $this->operations[strtolower($name)] = [
            'name'    => $name,
            'value'   => $value,
            'replace' => $replace,
        ];
    }

    /**
     * Accepts a header value that can be sent.
     *
     * @param string $value Header value.
     *
     * @throws InvalidArgumentException If the value contains a line break.
     */
    private function assertValue(string $value): void
    {
        if (preg_match('/[\r\n]/', $value) === 1) {
            throw new InvalidArgumentException('Header value must not contain a line break.');
        }
    }
}
