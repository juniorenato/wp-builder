<?php

/**
 * Request hardening that can live in the theme.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\Security
 */

namespace WPB\Security;

use WPB\Notices;

/**
 * Disables XML-RPC and anonymous user enumeration.
 *
 * Pass the features to enable. An empty constructor changes nothing.
 * `false` skips that entry. A bare method name enables that method.
 * `enableAll()` turns every feature on.
 *
 * `disableFileEdit()` removes the theme and plugin file editors. Installing
 * and updating plugins and themes stays available.
 *
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 */
class Security
{
    /**
     * Features already hooked on this instance.
     *
     * @var array<string, true>
     */
    private array $active = [];

    /**
     * Enables the given security features.
     *
     * @param array<int|string, bool|string> $features Method names, or names mapped to `true` or `false`.
     */
    public function __construct(array $features = [])
    {
        foreach ($features as $feature => $argument) {
            if (is_int($feature)) {
                $feature = $argument;
                $argument = true;
            }

            if ($argument === false || !is_string($feature)) {
                continue;
            }

            $this->activate($feature);
        }
    }

    /**
     * Stops XML-RPC, including pingbacks, and removes its discovery links.
     *
     * On an `xmlrpc.php` request this ends the request with status 403 before
     * the XML-RPC server runs. The filters cover a server booted some other way.
     *
     * @see https://developer.wordpress.org/reference/hooks/xmlrpc_enabled/
     * @see https://developer.wordpress.org/reference/hooks/xmlrpc_methods/
     */
    public function disableXmlRpc(): void
    {
        if (!$this->once('disableXmlRpc')) {
            return;
        }

        add_filter('xmlrpc_enabled', '__return_false');
        add_filter('xmlrpc_methods', [$this, 'removeXmlRpcMethods']);
        add_filter('wp_headers', [$this, 'removePingbackHeader']);
        remove_action('wp_head', 'rsd_link');

        if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
            status_header(403);
            exit;
        }
    }

    /**
     * Removes every XML-RPC method.
     *
     * @return array<string, mixed>
     */
    public function removeXmlRpcMethods(): array
    {
        return [];
    }

    /**
     * Removes the `X-Pingback` header.
     *
     * @param array<string, string> $headers Response headers.
     *
     * @return array<string, string>
     */
    public function removePingbackHeader(array $headers): array
    {
        unset($headers['X-Pingback']);

        return $headers;
    }

    /**
     * Stops anonymous requests from turning a user ID into a public slug.
     *
     * `/?author=1` redirects home instead of `/author/slug/`. Author archives
     * that already use the slug stay available. Guests also lose `GET /wp/v2/users`.
     * Logged-in users keep that route, because the block editor needs it.
     *
     * @see https://developer.wordpress.org/reference/functions/redirect_canonical/
     * @see https://developer.wordpress.org/reference/hooks/rest_pre_dispatch/
     */
    public function disableUserEnumeration(): void
    {
        if (!$this->once('disableUserEnumeration')) {
            return;
        }

        add_action('template_redirect', [$this, 'blockAuthorQuery'], 0);
        add_filter('rest_pre_dispatch', [$this, 'blockUsersRoute'], 10, 3);
    }

    /**
     * Redirects numeric `author` queries from anonymous visitors.
     *
     * @see https://developer.wordpress.org/reference/functions/wp_safe_redirect/
     */
    public function blockAuthorQuery(): void
    {
        if (is_admin() || is_user_logged_in() || !isset($_GET['author'])) {
            return;
        }

        $author = wp_unslash($_GET['author']);
        $isId = is_string($author) && preg_match('/^[0-9]+$/', $author) === 1;

        if (!$isId && !is_array($author)) {
            return;
        }

        wp_safe_redirect(home_url('/'), 302);
        exit;
    }

    /**
     * Rejects the public users route for anonymous REST requests.
     *
     * @param mixed            $result  Short-circuit result.
     * @param \WP_REST_Server  $server  REST server.
     * @param \WP_REST_Request $request Current request.
     *
     * @return mixed
     */
    public function blockUsersRoute(mixed $result, \WP_REST_Server $server, \WP_REST_Request $request): mixed
    {
        if ($result !== null || is_user_logged_in()) {
            return $result;
        }

        $route = $request->get_route();

        if (!preg_match('#^/wp/v2/users(?:/|$)#', $route) || str_ends_with($route, '/me')) {
            return $result;
        }

        return new \WP_Error(
            'rest_forbidden',
            'User listing is disabled.',
            ['status' => 401]
        );
    }

    /**
     * Removes the theme and plugin file editors.
     *
     * WordPress checks `edit_themes`, `edit_plugins`, and `edit_files` when
     * the admin menu and the editor screens load, which is after the theme.
     * The constant covers that same check. The capability filter still denies
     * editing when `wp-config.php` defined the constant as false.
     *
     * @see https://developer.wordpress.org/reference/hooks/map_meta_cap/
     */
    public function disableFileEdit(): void
    {
        if (!$this->once('disableFileEdit')) {
            return;
        }

        if (!defined('DISALLOW_FILE_EDIT')) {
            define('DISALLOW_FILE_EDIT', true);
        }

        add_filter('map_meta_cap', [$this, 'denyFileEdit'], 10, 2);
    }

    /**
     * Denies the capabilities that open the theme and plugin file editors.
     *
     * @param list<string> $caps Primitive capabilities required for the check.
     * @param string       $cap  Capability being checked.
     *
     * @return list<string> `do_not_allow` for the file editors, otherwise the original list.
     */
    public function denyFileEdit(array $caps, string $cap): array
    {
        if (in_array($cap, ['edit_files', 'edit_plugins', 'edit_themes'], true)) {
            return ['do_not_allow'];
        }

        return $caps;
    }

    /**
     * Enables every security feature.
     */
    public function enableAll(): void
    {
        $this->disableXmlRpc();
        $this->disableUserEnumeration();
        $this->disableFileEdit();
    }

    /**
     * Hooks one named feature.
     *
     * @param string $feature Method name.
     */
    private function activate(string $feature): void
    {
        match ($feature) {
            'disableXmlRpc' => $this->disableXmlRpc(),
            'disableUserEnumeration' => $this->disableUserEnumeration(),
            'disableFileEdit' => $this->disableFileEdit(),
            'enableAll' => $this->enableAll(),
            default => Notices::error(sprintf(
                'Unknown security feature "%s".',
                $feature
            )),
        };
    }

    /**
     * Records a feature the first time it is enabled.
     *
     * @param string $feature Feature name.
     *
     * @return bool True when this call should register hooks.
     */
    private function once(string $feature): bool
    {
        if (isset($this->active[$feature])) {
            return false;
        }

        $this->active[$feature] = true;

        return true;
    }
}
