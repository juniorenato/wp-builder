<?php

/**
 * Request rate limits.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\Security
 */

namespace WPB\Security;

use InvalidArgumentException;

/**
 * Counts requests in a fixed window and ships policies for the common cases.
 *
 * An empty instance changes nothing. `login()`, `comments()`, and `rest()`
 * hook WordPress. `allow()` and `form()` count a key when you call them.
 * The first registration of each policy wins for the rest of the process.
 *
 * The client is `REMOTE_ADDR` until a proxy list is configured. Set
 * `WPB_TRUSTED_PROXIES` in `wp-config.php`, or call `trustedProxies()` to
 * replace that constant for one instance. Only `X-Forwarded-For` is read,
 * and only from a peer that belongs to the list. The client is the rightmost
 * address in that chain that is not itself trusted.
 *
 * `private_ranges` expands to the loopback, link-local, and private networks
 * used by Symfony. A visitor on one of those networks is then treated as a
 * proxy too, so name the proxy address when visitors share that network.
 *
 * Each bucket is one WordPress transient. The window starts at the first hit
 * and later hits do not extend it. Two requests can pass the limit together,
 * because the transient write is not atomic.
 *
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 */
class RateLimit
{
    /**
     * Seconds for the password-reset bucket.
     */
    private const int LOST_PASSWORD_WINDOW = 900;

    /**
     * Loopback, link-local, and private networks.
     *
     * This is Symfony's `IpUtils::PRIVATE_RANGES` list.
     *
     * @var list<string>
     */
    private const array PRIVATE_RANGES = [
        '127.0.0.0/8',
        '10.0.0.0/8',
        '192.168.0.0/16',
        '172.16.0.0/12',
        '169.254.0.0/16',
        '0.0.0.0/8',
        '240.0.0.0/4',
        '::1/128',
        'fc00::/7',
        'fe80::/10',
        '::ffff:0:0/96',
    ];

    /**
     * Policies already hooked in this process.
     *
     * @var array<string, true>
     */
    private static array $hooked = [];

    /**
     * Resolved proxy rules for this instance.
     *
     * Null until the constant or `trustedProxies()` has been read.
     *
     * @var list<string>|null
     */
    private ?array $proxies = null;

    /**
     * Failed logins allowed for one username and IP.
     */
    private int $loginMax = 5;

    /**
     * Login window in seconds.
     */
    private int $loginInterval = 60;

    /**
     * Comment submits allowed for one IP.
     */
    private int $commentLimit = 5;

    /**
     * Comment window in seconds.
     */
    private int $commentWindow = 600;

    /**
     * Anonymous API requests allowed for one IP.
     */
    private int $restLimit = 60;

    /**
     * API window in seconds.
     */
    private int $restWindow = 60;

    /**
     * Replaces `WPB_TRUSTED_PROXIES` for this instance.
     *
     * Pass addresses, CIDR ranges, `private_ranges`, `PRIVATE_SUBNETS`, or
     * `REMOTE_ADDR`. A string may list several entries separated by commas.
     * `REMOTE_ADDR` is the peer of the current request, resolved when the
     * client address is read.
     *
     * @param list<string>|string $proxies Proxy rules.
     *
     * @return static
     *
     * @throws InvalidArgumentException If an entry is not a rule this class accepts.
     */
    public function trustedProxies(array|string $proxies): static
    {
        $this->proxies = $this->parseProxies($proxies);

        return $this;
    }

    /**
     * Returns the client address for this request.
     *
     * @return string An IP address, or `0.0.0.0` when the peer is missing.
     */
    public function clientIp(): string
    {
        $remote = $this->validIp((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

        if ($remote === null) {
            return '0.0.0.0';
        }

        if (!$this->isTrusted($remote)) {
            return $remote;
        }

        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';

        if (!is_string($forwarded) || trim($forwarded) === '') {
            return $remote;
        }

        $chain = [];

        foreach (explode(',', $forwarded) as $part) {
            $ip = $this->validIp($part);

            if ($ip !== null) {
                $chain[] = $ip;
            }
        }

        $chain[] = $remote;
        $untrusted = [];
        $firstTrusted = null;

        foreach ($chain as $ip) {
            if ($this->isTrusted($ip)) {
                $firstTrusted ??= $ip;
                continue;
            }

            $untrusted[] = $ip;
        }

        if ($untrusted === []) {
            return $firstTrusted ?? $remote;
        }

        return $untrusted[array_key_last($untrusted)];
    }

    /**
     * Counts one hit and reports whether it is still inside the limit.
     *
     * The key is the identity. Include the client, user, or form in it when
     * the bucket should not be global. The stored name is an HMAC of the key.
     *
     * @param string $key    Bucket identity.
     * @param int    $limit  Hits allowed in the window.
     * @param int    $window Window length in seconds.
     *
     * @return bool True when this hit is allowed.
     *
     * @throws InvalidArgumentException If the limit or the window is below 1.
     */
    public function allow(string $key, int $limit, int $window): bool
    {
        $this->assertBudget($limit, $window);

        return $this->consume($key, $limit, $window)['allowed'];
    }

    /**
     * Deletes one bucket.
     *
     * @param string $key Bucket identity passed to `allow()`.
     */
    public function clear(string $key): void
    {
        delete_transient($this->storageKey($key));
    }

    /**
     * Counts one submit of a named public form for the current client.
     *
     * There is no WordPress hook for an arbitrary form. Call this before
     * accepting the POST.
     *
     * @param string $name   Form identity. It is trimmed.
     * @param int    $limit  Submits allowed in the window.
     * @param int    $window Window length in seconds.
     *
     * @return bool True when this submit is allowed.
     *
     * @throws InvalidArgumentException If the name is blank or the budget is below 1.
     */
    public function form(string $name, int $limit = 5, int $window = 600): bool
    {
        $this->assertBudget($limit, $window);
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('Form name cannot be empty.');
        }

        return $this->allow($this->clientIp() . '|form|' . $name, $limit, $window);
    }

    /**
     * Limits failed logins and password resets.
     *
     * One username and IP can fail `$maxAttempts` times during `$interval`.
     * The IP can fail five times that many attempts across usernames. A
     * successful login clears both buckets. Password reset allows the same
     * number of requests in a separate 15-minute bucket for that IP.
     *
     * A blocked login stays on the login form as a `WP_Error`. The check runs
     * before WordPress verifies the password.
     *
     * @param int $maxAttempts Failures allowed for one username and IP.
     * @param int $interval    Window length in seconds.
     *
     * @return static
     *
     * @throws InvalidArgumentException If the budget is below 1.
     */
    public function login(int $maxAttempts = 5, int $interval = 60): static
    {
        $this->assertBudget($maxAttempts, $interval);

        if (!$this->once('login')) {
            return $this;
        }

        $this->loginMax = $maxAttempts;
        $this->loginInterval = $interval;
        add_filter('authenticate', [$this, 'blockLogin'], 1, 3);
        add_action('wp_login_failed', [$this, 'recordLoginFailure']);
        add_action('wp_login', [$this, 'clearLogin']);
        add_action('lostpassword_post', [$this, 'limitLostPassword']);

        return $this;
    }

    /**
     * Rejects a login attempt that is already over the limit.
     *
     * @param mixed  $user     User, error, or null from an earlier authenticator.
     * @param string $username Submitted username.
     *
     * @return mixed The original result, or a `WP_Error` when the attempt is blocked.
     */
    public function blockLogin(mixed $user, string $username): mixed
    {
        if ($user instanceof \WP_User || $user instanceof \WP_Error) {
            return $user;
        }

        $ip = $this->clientIp();

        if ($this->limited($this->loginIpKey($ip), $this->loginMax * 5)) {
            return $this->loginError();
        }

        $name = $this->normalizeUsername($username);

        if ($name !== '' && $this->limited($this->loginUserKey($name, $ip), $this->loginMax)) {
            return $this->loginError();
        }

        return $user;
    }

    /**
     * Counts one failed login against the IP and, when present, the username.
     *
     * @param string $username Submitted username.
     */
    public function recordLoginFailure(string $username): void
    {
        $ip = $this->clientIp();
        $this->hit($this->loginIpKey($ip), $this->loginInterval);
        $name = $this->normalizeUsername($username);

        if ($name === '') {
            return;
        }

        $this->hit($this->loginUserKey($name, $ip), $this->loginInterval);
    }

    /**
     * Clears the login buckets after a successful login.
     *
     * @param string $username Authenticated username.
     */
    public function clearLogin(string $username): void
    {
        $ip = $this->clientIp();
        $name = $this->normalizeUsername($username);

        if ($name !== '') {
            $this->clear($this->loginUserKey($name, $ip));
        }

        $this->clear($this->loginIpKey($ip));
    }

    /**
     * Counts a password-reset request and records an error when it is over the limit.
     *
     * @param mixed $errors Error bag from `lostpassword_post`.
     */
    public function limitLostPassword(mixed $errors): void
    {
        if (!$errors instanceof \WP_Error) {
            return;
        }

        $decision = $this->consume(
            'lostpassword|' . $this->clientIp(),
            $this->loginMax,
            self::LOST_PASSWORD_WINDOW
        );

        if ($decision['allowed']) {
            return;
        }

        $errors->add(
            'wpb_rate_limit',
            'Too many password reset attempts. Try again later.'
        );
    }

    /**
     * Limits comment submits per client IP.
     *
     * Logged-in authors count too. The submit past the limit ends with status
     * 429 and a `Retry-After` header.
     *
     * @param int $limit  Submits allowed in the window.
     * @param int $window Window length in seconds.
     *
     * @return static
     *
     * @throws InvalidArgumentException If the budget is below 1.
     */
    public function comments(int $limit = 5, int $window = 600): static
    {
        $this->assertBudget($limit, $window);

        if (!$this->once('comments')) {
            return $this;
        }

        $this->commentLimit = $limit;
        $this->commentWindow = $window;
        add_filter('preprocess_comment', [$this, 'guardComment']);

        return $this;
    }

    /**
     * Stops a comment submit that is over the limit.
     *
     * @param mixed $comment Comment data from `preprocess_comment`.
     *
     * @return mixed The original comment data when the submit is allowed.
     */
    public function guardComment(mixed $comment): mixed
    {
        $decision = $this->consume(
            'comment|' . $this->clientIp(),
            $this->commentLimit,
            $this->commentWindow
        );

        if ($decision['allowed']) {
            return $comment;
        }

        $this->reject($decision['retryAfter'], 'Too many comments. Try again later.');
        exit;
    }

    /**
     * Limits anonymous REST and `admin-ajax` requests on one shared bucket.
     *
     * Logged-in requests are not counted. REST over the limit becomes a
     * `WP_Error` with status 429. `admin-ajax` ends with status 429. Both
     * send `Retry-After`.
     *
     * @param int $limit  Requests allowed in the window.
     * @param int $window Window length in seconds.
     *
     * @return static
     *
     * @throws InvalidArgumentException If the budget is below 1.
     */
    public function rest(int $limit = 60, int $window = 60): static
    {
        $this->assertBudget($limit, $window);

        if (!$this->once('rest')) {
            return $this;
        }

        $this->restLimit = $limit;
        $this->restWindow = $window;
        add_filter('rest_pre_dispatch', [$this, 'guardRest'], 10, 3);
        add_action('admin_init', [$this, 'guardAjax']);

        return $this;
    }

    /**
     * Stops an anonymous REST request that is over the shared API limit.
     *
     * @param mixed $result  Short-circuit result.
     * @param mixed $server  REST server. Unused. The bucket is the client IP.
     * @param mixed $request REST request. Unused. The bucket is the client IP.
     *
     * @return mixed The original result, or a `WP_Error` with status 429.
     */
    public function guardRest(mixed $result, mixed $server, mixed $request): mixed
    {
        unset($server, $request);

        if ($result !== null || is_user_logged_in()) {
            return $result;
        }

        $decision = $this->consume(
            'api|' . $this->clientIp(),
            $this->restLimit,
            $this->restWindow
        );

        if ($decision['allowed']) {
            return $result;
        }

        header('Retry-After: ' . $decision['retryAfter']);

        return new \WP_Error(
            'wpb_rate_limit',
            'Too many requests. Try again later.',
            [
                'status'      => 429,
                'retry_after' => $decision['retryAfter'],
            ]
        );
    }

    /**
     * Stops an anonymous `admin-ajax` request that is over the shared API limit.
     */
    public function guardAjax(): void
    {
        if (!defined('DOING_AJAX') || !DOING_AJAX || is_user_logged_in()) {
            return;
        }

        $decision = $this->consume(
            'api|' . $this->clientIp(),
            $this->restLimit,
            $this->restWindow
        );

        if ($decision['allowed']) {
            return;
        }

        $this->reject($decision['retryAfter'], 'Too many requests. Try again later.');
        exit;
    }

    /**
     * Records a policy the first time it is enabled.
     *
     * @param string $feature Policy name.
     *
     * @return bool True when this call should register hooks.
     */
    private function once(string $feature): bool
    {
        if (isset(self::$hooked[$feature])) {
            return false;
        }

        self::$hooked[$feature] = true;

        return true;
    }

    /**
     * Rejects a limit below 1.
     *
     * @param int $limit  Hits allowed in the window.
     * @param int $window Window length in seconds.
     *
     * @throws InvalidArgumentException If either value is below 1.
     */
    private function assertBudget(int $limit, int $window): void
    {
        if ($limit < 1 || $window < 1) {
            throw new InvalidArgumentException('The rate limit and the window must be at least 1.');
        }
    }

    /**
     * Builds the login failure shown on the form.
     */
    private function loginError(): \WP_Error
    {
        return new \WP_Error(
            'wpb_rate_limit',
            'Too many failed login attempts. Try again later.'
        );
    }

    /**
     * Normalizes a submitted username for the bucket key.
     *
     * @param string $username Raw username.
     *
     * @return string Lowercased username, or an empty string when it is blank.
     */
    private function normalizeUsername(string $username): string
    {
        $username = trim((string) wp_unslash($username));

        if ($username === '') {
            return '';
        }

        if (preg_match('//u', $username) === 1) {
            return mb_strtolower($username, 'UTF-8');
        }

        return strtolower($username);
    }

    /**
     * Bucket for every failed login from one client.
     *
     * @param string $ip Client address.
     */
    private function loginIpKey(string $ip): string
    {
        return 'login|ip|' . $ip;
    }

    /**
     * Bucket for one username from one client.
     *
     * @param string $username Normalized username.
     * @param string $ip       Client address.
     */
    private function loginUserKey(string $username, string $ip): string
    {
        return 'login|user|' . $username . '|' . $ip;
    }

    /**
     * Reports whether a bucket is already full.
     *
     * @param string $key   Bucket identity.
     * @param int    $limit Hits allowed in the window.
     */
    private function limited(string $key, int $limit): bool
    {
        $bucket = get_transient($this->storageKey($key));

        if (!is_array($bucket) || (int) ($bucket['expires'] ?? 0) <= time()) {
            return false;
        }

        return (int) ($bucket['count'] ?? 0) >= $limit;
    }

    /**
     * Adds one hit without caring about the limit.
     *
     * @param string $key    Bucket identity.
     * @param int    $window Window length in seconds.
     */
    private function hit(string $key, int $window): void
    {
        $this->consume($key, PHP_INT_MAX, $window);
    }

    /**
     * Adds one hit and returns the decision for this window.
     *
     * @param string $key    Bucket identity.
     * @param int    $limit  Hits allowed in the window.
     * @param int    $window Window length in seconds.
     *
     * @return array{allowed: bool, retryAfter: int}
     */
    private function consume(string $key, int $limit, int $window): array
    {
        $now = time();
        $bucket = get_transient($this->storageKey($key));

        if (!is_array($bucket) || (int) ($bucket['expires'] ?? 0) <= $now) {
            $bucket = [
                'count'   => 0,
                'expires' => $now + $window,
            ];
        }

        $bucket['count'] = (int) $bucket['count'] + 1;
        $retryAfter = max(1, (int) $bucket['expires'] - $now);
        set_transient($this->storageKey($key), $bucket, $retryAfter);

        return [
            'allowed'    => $bucket['count'] <= $limit,
            'retryAfter' => $retryAfter,
        ];
    }

    /**
     * Ends the request with status 429.
     *
     * @param int    $retryAfter Seconds until the window ends.
     * @param string $message    Response body.
     */
    private function reject(int $retryAfter, string $message): void
    {
        $retryAfter = max(1, $retryAfter);
        status_header(429);
        header('Retry-After: ' . $retryAfter);
        wp_die($message, 'Too many requests', [
            'response'    => 429,
            'retry_after' => $retryAfter,
        ]);
    }

    /**
     * Hashes a bucket identity into a transient name.
     *
     * @param string $key Bucket identity.
     */
    private function storageKey(string $key): string
    {
        return 'wpb_rl_' . substr(hash_hmac('sha256', $key, wp_salt('auth')), 0, 32);
    }

    /**
     * Returns the proxy rules for this instance.
     *
     * @return list<string>
     */
    private function proxies(): array
    {
        if ($this->proxies !== null) {
            return $this->proxies;
        }

        $configured = defined('WPB_TRUSTED_PROXIES') ? constant('WPB_TRUSTED_PROXIES') : null;

        if ((is_string($configured) && $configured !== '') || is_array($configured)) {
            $this->proxies = $this->parseProxies($configured);
        } else {
            $this->proxies = [];
        }

        return $this->proxies;
    }

    /**
     * Expands proxy configuration into addresses, ranges, and the `REMOTE_ADDR` token.
     *
     * @param list<string>|string $proxies Proxy rules.
     *
     * @return list<string>
     *
     * @throws InvalidArgumentException If an entry is not a rule this class accepts.
     */
    private function parseProxies(array|string $proxies): array
    {
        if (is_string($proxies)) {
            $proxies = array_map(trim(...), explode(',', $proxies));
        }

        $resolved = [];

        foreach ($proxies as $proxy) {
            if (!is_string($proxy)) {
                throw new InvalidArgumentException('A trusted proxy must be a string.');
            }

            $proxy = trim($proxy);

            if ($proxy === '') {
                continue;
            }

            if ($proxy === 'private_ranges' || $proxy === 'PRIVATE_SUBNETS') {
                array_push($resolved, ...self::PRIVATE_RANGES);
                continue;
            }

            if ($proxy === 'REMOTE_ADDR') {
                $resolved[] = 'REMOTE_ADDR';
                continue;
            }

            if (!$this->isProxyRule($proxy)) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid trusted proxy "%s".',
                    $proxy
                ));
            }

            $resolved[] = $proxy;
        }

        return $resolved;
    }

    /**
     * Reports whether a rule is an IP address or a CIDR range.
     *
     * @param string $proxy One proxy entry.
     */
    private function isProxyRule(string $proxy): bool
    {
        if (!str_contains($proxy, '/')) {
            return $this->validIp($proxy) !== null;
        }

        [$address, $bits] = explode('/', $proxy, 2);

        if (!ctype_digit($bits) || $this->validIp($address) === null) {
            return false;
        }

        $max = str_contains($address, ':') ? 128 : 32;
        $size = (int) $bits;

        return $size >= 0 && $size <= $max;
    }

    /**
     * Reports whether an address belongs to a trusted rule.
     *
     * @param string $ip Address to test.
     */
    private function isTrusted(string $ip): bool
    {
        foreach ($this->proxies() as $rule) {
            if ($rule === 'REMOTE_ADDR') {
                $remote = $this->validIp((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

                if ($remote !== null && $ip === $remote) {
                    return true;
                }

                continue;
            }

            if ($this->ipInRange($ip, $rule)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reports whether an address belongs to an exact address or a CIDR range.
     *
     * @param string $ip   Address to test.
     * @param string $rule Exact address or CIDR range.
     */
    private function ipInRange(string $ip, string $rule): bool
    {
        $bits = null;
        $subnet = $rule;

        if (str_contains($rule, '/')) {
            [$subnet, $bits] = explode('/', $rule, 2);
        }

        $ipBytes = inet_pton($ip);
        $subnetBytes = inet_pton($subnet);

        if (
            $ipBytes === false
            || $subnetBytes === false
            || strlen($ipBytes) !== strlen($subnetBytes)
        ) {
            return false;
        }

        $size = $bits === null ? strlen($ipBytes) * 8 : (int) $bits;
        $bytes = intdiv($size, 8);
        $remainder = $size % 8;

        if ($bytes > 0 && substr($ipBytes, 0, $bytes) !== substr($subnetBytes, 0, $bytes)) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainder)) & 0xFF;

        return (ord($ipBytes[$bytes]) & $mask) === (ord($subnetBytes[$bytes]) & $mask);
    }

    /**
     * Returns a normalized IP address, or null when the value is not one.
     *
     * Strips a bracketed IPv6 port and an IPv4 port. An IPv4-mapped IPv6
     * address is returned as IPv4 so it matches IPv4 ranges.
     *
     * @param string $value Raw address.
     */
    private function validIp(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '[')) {
            $end = strpos($value, ']');

            if ($end === false) {
                return null;
            }

            $value = substr($value, 1, $end - 1);
        } elseif (str_contains($value, '.') && substr_count($value, ':') === 1) {
            $value = strstr($value, ':', true);
        }

        $value = trim($value, " \t\"'");
        $valid = filter_var($value, FILTER_VALIDATE_IP);

        if ($valid === false) {
            return null;
        }

        if (str_starts_with(strtolower($valid), '::ffff:')) {
            $mapped = filter_var(substr($valid, 7), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);

            if ($mapped !== false) {
                return $mapped;
            }
        }

        return $valid;
    }
}
