<?php

namespace WPB\PostType;

/**
 * -----------------------------------------------------------------------------
 * Internationalization of a Post Type
 * -----------------------------------------------------------------------------
 *
 * @since 0.1.0
 * @author Renato Rodrigues Jr <juniorenato@msn.com>
 * @package hoststyle/hswp-theme-builder
 */
trait I18n
{
    /**
     * -------------------------------------------------------------------------
     * Default en-US language
     * -------------------------------------------------------------------------
     *
     * @return void
     */
    protected function en_US()
    {
        $this->labels = [
            'name'                     => ucfirst($this->plural),
            'singular_name'            => ucfirst($this->singular),
            'add_new'                  => ucfirst(sprintf(__('add %s', $this->td), $this->singular)),
            'add_new_item'             => ucfirst(sprintf(__('add new %s', $this->td), $this->singular)),
            'edit_item'                => ucfirst(sprintf(__('edit %s', $this->td), $this->singular)),
            'new_item'                 => ucfirst(sprintf(__('new %s', $this->td), $this->singular)),
            'view_item'                => ucfirst(sprintf(__('view %s', $this->td), $this->singular)),
            'view_items'               => ucfirst(sprintf(__('view %s', $this->td), $this->plural)),
            'search_items'             => ucfirst(sprintf(__('search %s', $this->td), $this->plural)),
            'not_found'                => ucfirst(sprintf(__('%s not found', $this->td), $this->plural)),
            'not_found_in_trash'       => ucfirst(sprintf(__('%s not found in trash', $this->td), $this->plural)),
            'parent_item_colon'        => ucfirst(sprintf(__('parent %s', $this->td), $this->singular)),
            'all_items'                => ucfirst(sprintf(__('all %s', $this->td), $this->plural)),
            'archives'                 => ucfirst(sprintf(__('archives of %s', $this->td), $this->plural)),
            'attributes'               => ucfirst(sprintf(__('%s attributes', $this->td), $this->singular)),
            'insert_into_item'         => ucfirst(sprintf(__('insert into %s', $this->td), $this->plural)),
            'uploaded_to_this_item'    => ucfirst(sprintf(__('updated to this %s', $this->td), $this->singular)),
            'menu_name'                => ucfirst($this->plural),
            'filter_items_list'        => ucfirst(sprintf(__('filter %s list', $this->td), $this->plural)),
            'items_list_navigation'    => ucfirst(sprintf(__('%s list navigation', $this->td), $this->plural)),
            'items_list'               => ucfirst(sprintf(__('%s list', $this->td), $this->plural)),
            'item_published'           => ucfirst(sprintf(__('%s published', $this->td), $this->singular)),
            'item_published_privately' => ucfirst(sprintf(__('%s published privately', $this->td), $this->singular)),
            'item_reverted_to_draft'   => ucfirst(sprintf(__('%s reverted to draft', $this->td), $this->singular)),
            'item_trashed'             => ucfirst(sprintf(__('%s trashed', $this->td), $this->singular)),
            'item_scheduled'           => ucfirst(sprintf(__('%s scheduled', $this->td), $this->singular)),
            'item_updated'             => ucfirst(sprintf(__('%s updated', $this->td), $this->singular)),
            'item_link'                => ucfirst(sprintf(__('%s link', $this->td), $this->singular)),
            'item_link_description'    => ucfirst(sprintf(__('%s link description', $this->td), $this->singular)),
        ];
    }

    /**
     * -------------------------------------------------------------------------
     * Brazilian portuguese
     * -------------------------------------------------------------------------
     *
     * @return void
     */
    protected function pt_BR()
    {
        $new       = (!$this->male) ? __('nova', $this->td) : __('novo', $this->td);
        $found     = (!$this->male) ? __('encontradas', $this->td) : __('encontrados', $this->td);
        $parent    = (!$this->male) ? __('mãe', $this->td) : __('pai', $this->td);
        $all       = (!$this->male) ? __('todas as', $this->td) : __('todos os', $this->td);
        $item      = (!$this->male) ? __('esta', $this->td) : __('este', $this->td);
        $published = (!$this->male) ? __('publicada', $this->td) : __('publicado', $this->td);
        $scheduled = (!$this->male) ? __('agendada', $this->td) : __('agendado', $this->td);
        $updated   = (!$this->male) ? __('autalizada', $this->td) : __('autalizado', $this->td);

        $this->labels = [
            'name'                     => ucfirst($this->plural),
            'singular_name'            => ucfirst($this->singular),
            'add_new'                  => ucfirst(sprintf(__('adicionar %s', $this->td), $new)),
            'add_new_item'             => ucfirst(sprintf(__('adicionar %s %s', $this->td), $new, $this->singular)),
            'edit_item'                => ucfirst(sprintf(__('editar %s', $this->td), $this->singular)),
            'new_item'                 => ucfirst($new .' '. $this->singular),
            'view_item'                => ucfirst(sprintf(__('ver %s', $this->td), $this->singular)),
            'view_items'               => ucfirst(sprintf(__('ver %s', $this->td), $this->plural)),
            'search_items'             => ucfirst(sprintf(__('pesquisar %s', $this->td), $this->plural)),
            'not_found'                => ucfirst(sprintf(__('%s não %s', $this->td), $this->plural, $found)),
            'not_found_in_trash'       => ucfirst(sprintf(__('%s não %s na lixeira', $this->td), $this->plural, $found)),
            'parent_item_colon'        => ucfirst($this->singular .' '. $parent),
            'all_items'                => ucfirst($all .' '. $this->plural),
            'archives'                 => ucfirst(sprintf(__('arquivos de %s', $this->td), $this->plural)),
            'attributes'               => ucfirst(__('atributos', $this->td)),
            'insert_into_item'         => ucfirst(sprintf(__('arquivos de %s', $this->td), $this->plural)),
            'uploaded_to_this_item'    => ucfirst(sprintf(__('enviado para %s %s', $this->td), $item ,$this->singular)),
            'menu_name'                => ucfirst($this->plural),
            'filter_items_list'        => ucfirst($this->plural),
            'items_list_navigation'    => ucfirst($this->plural),
            'items_list'               => ucfirst($this->plural),
            'item_published'           => ucfirst($this->singular . ' '. $published),
            'item_published_privately' => ucfirst(sprintf(__('%s %s no modo privado', $this->td), $this->singular, $published)),
            'item_reverted_to_draft'   => ucfirst(__('%s voltou para rascunho', $this->td, $this->singular)),
            'item_trashed'             => ucfirst(__('%s', $this->td)),
            'item_scheduled'           => ucfirst($this->singular .' '. $scheduled),
            'item_updated'             => ucfirst($this->singular .' '. $updated),
            'item_link'                => ucfirst(sprintf(__('link de %s', $this->td), $this->plural)),
            'item_link_description'    => ucfirst(sprintf(__('um link para %s', $this->td), $this->singular)),
        ];
    }
}
