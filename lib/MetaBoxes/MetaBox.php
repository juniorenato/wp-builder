<?php

namespace WPB\MetaBoxes;

use WPB\Forms\AdminForm;

/**
 * -----------------------------------------------------------------------------
 * Meta Box
 * -----------------------------------------------------------------------------
 *
 * @since v0.3.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 * @package juniorenato/wp-builder
 */
class MetaBox
{
    use AdminForm;

    public string $metaBox;
    public string $title;
    private array  $screen;
    private string $context;
    private string $priority;
    private array  $args;

    public function __construct()
    {
        $this->init();
    }

    private function init()
    {
        $this->metaBox  = '';
        $this->title    = '';
        $this->screen   = [];
        $this->context  = 'normal';
        $this->priority = 'default';
        $this->args     = [
            '__back_compat_meta_box' => false,
        ];
        $this->fields = [];
    }

    public function metaBoxId(string $metaBoxId)
    {
        $this->metaBox = sanitize_title($metaBoxId);
    }

    public function title(string $title): MetaBox
    {
        if($this->title) $this->init();

        $this->title = ucfirst($title);

        return $this;
    }

    public function screen($screen): MetaBox
    {
        $this->screen[] = $screen;

        return $this;
    }

    public function context(string $context): MetaBox
    {
        $this->context = $context;

        return $this;
    }

    public function priority(string $priority): MetaBox
    {
        $this->priority = $priority;

        return $this;
    }

    public function args($callbackArgs): MetaBox
    {
        if(is_array($callbackArgs)) {
            $this->args = array_merge($this->args, $callbackArgs);
        }

        else {
            $this->args[] = $callbackArgs;
        }

        return $this;
    }

    public function register(?string $title = null, $screen = null): bool
    {
        if(1 == 1
            && $title
            && $screen
        ) {
            $this->title($title);
            $this->screen($screen);
        }

        if(1 == 0
            || !$this->metaBox
            || !$this->title
            || !$this->screen
        ) { return false; }

        add_action('init', [$this, 'setMetaboxes']);

        return true;
    }

    public function setMetaboxes(): void
    {
        if(!$this->metaBox) $this->metaBox = sanitize_title($this->title);

        // WordPress - Add Meta Box
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
    }

    public function addMetaBoxes(): void
    {
        // Create the meta box
        add_meta_box(
            $this->metaBox,
            $this->title,
            [$this, 'registerMetabox'],
            $this->screen,
            $this->context,
            $this->priority,
            $this->args
        );
    }

    public function registerMetabox()
    {
        global $post;

        $this->id = $post->ID ?? 0;
        $this->fieldsType = 'box';

        $this->field();
    }

}
