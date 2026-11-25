<?php

/**
 * Customizer panel.
 *
 * @author  Renato Rodrigues Jr <juniorenato@msn.com>
 * @license GPL-3.0-or-later
 * @package WPB\Customizer
 */

namespace WPB\Customizer;

use WPB\Notices;

/**
 * Groups customizer sections under one item.
 *
 * A panel is optional. A section without a panel is listed at the root of
 * Appearance → Customize, on the same level as Site Identity.
 *
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 *
 * @see https://developer.wordpress.org/reference/classes/wp_customize_manager/add_panel/
 */
class Panel
{
    /**
     * Default position among customizer panels.
     */
    private const int DEFAULT_PRIORITY = 160;

    /**
     * Sanitized panel id.
     */
    private string $id;

    /**
     * Panel title shown in the customizer.
     */
    private string $title;

    /**
     * Text shown under the panel title.
     */
    private string $description = '';

    /**
     * Sort order. Lower numbers appear first.
     */
    private int $priority = self::DEFAULT_PRIORITY;

    /**
     * Whether the panel has an id and a title.
     */
    private bool $valid = true;

    /**
     * Starts a panel.
     *
     * @param string $id    Panel id. It passes through `sanitize_key()`.
     * @param string $title Panel title.
     */
    public function __construct(string $id, string $title)
    {
        $this->id    = self::key($id);
        $this->title = $title;

        if ($this->id === '' || $title === '') {
            $this->valid = false;
            Notices::error('A customizer panel needs an id and a title.');
        }
    }

    /**
     * Sets the text shown under the panel title.
     *
     * @param string $description Panel description.
     *
     * @return static
     */
    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Sets the panel sort order.
     *
     * @param int $priority Sort order. Lower numbers appear first.
     *
     * @return static
     */
    public function priority(int $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * Returns the sanitized panel id.
     *
     * @return string Panel id.
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Returns the panel title.
     *
     * @return string Panel title.
     */
    public function title(): string
    {
        return $this->title;
    }

    /**
     * Reports whether the panel can be registered.
     *
     * @return bool True when the id and the title are usable.
     */
    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * Returns the panel arguments stored for registration.
     *
     * @return array{
     *     id: string,
     *     title: string,
     *     description: string,
     *     priority: int
     * } Panel arguments.
     */
    public function export(): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'priority'    => $this->priority,
        ];
    }

    /**
     * Sanitizes an id the same way WordPress sanitizes a key.
     *
     * @param string $value Raw id.
     *
     * @return string Sanitized id.
     */
    private static function key(string $value): string
    {
        if (function_exists('sanitize_key')) {
            return sanitize_key($value);
        }

        return strtolower($value);
    }
}
