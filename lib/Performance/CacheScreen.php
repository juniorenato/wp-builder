<?php

/**
 * Tools submenu for the page cache.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\Performance
 */

namespace WPB\Performance;

use WPB\Builder;
use WPB\Pages\AdminPage;

/**
 * Renders the cache settings with the project admin page and the file list.
 *
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 */
class CacheScreen extends AdminPage
{
    /**
     * Page cache controlled by this screen.
     */
    private PageCache $cache;

    /**
     * Attaches the cache service and resets the admin page defaults.
     *
     * @param PageCache $cache Page cache service.
     */
    public function __construct(PageCache $cache)
    {
        $this->cache = $cache;

        parent::__construct();
    }

    /**
     * Returns the cache service rendered with this screen.
     */
    public function cache(): PageCache
    {
        return $this->cache;
    }

    /**
     * Prints the settings form and the cached URL table.
     */
    public function buildPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        include Builder::PATH['PAGE'] . 'performance/cache.php';
    }
}
