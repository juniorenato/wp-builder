<?php

namespace WPB\Taxonomy;

/**
 * -----------------------------------------------------------------------------
 * Internationalization of a Taxonomy
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
     * Default english
     * -------------------------------------------------------------------------
     *
     * @return void
     */
    protected function en_US()
    {
        $new    = (!$this->male) ? __('nova', $this->td) : __('novo', $this->td);
        $found  = (!$this->male) ? __('encontradas', $this->td) : __('encontrados', $this->td);
        $parent = (!$this->male) ? __('mãe', $this->td) : __('pai', $this->td);
        $all    = (!$this->male) ? __('todas as', $this->td) : __('todos os', $this->td);
        $used   = (!$this->male) ? __('usadas', $this->td) : __('usados', $this->td);

        $this->labels = [
            'name'                       => ucfirst($this->plural),
            'singular_name'              => ucfirst($this->singular),
            'search_items'               => ucfirst(sprintf(__('search %s',$this->td), $this->plural)),
            'popular_items'              => ucfirst(sprintf(__('popular %s',$this->td), $this->plural)),
            'all_items'                  => ucfirst(sprintf(__('all %s', $this->td), $this->plural)),
            'parent_item'                => ucfirst(sprintf(__('parent %s', $this->td), $this->singular)),
            'parent_item_colon'          => ucfirst(sprintf(__('parent %s:', $this->td), $this->singular)),
            //'name_field_description'     => ucfirst('The name is how it appears on your site'),
            //'slug_field_description'     => ucfirst('The “slug” is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens'),
            //'parent_field_description'   => ucfirst('Assign a parent term to create a hierarchy. The term Jazz, for example, would be the parent of Bebop and Big Band'),
            //'desc_field_description'     => ucfirst('The description is not prominent by default; however, some themes may show it'),
            'edit_item'                  => ucfirst(sprintf(__('edit %s',$this->td), $this->singular)),
            'view_item'                  => ucfirst(sprintf(__('view %s',$this->td), $this->singular)),
            'update_item'                => ucfirst(sprintf(__('update %s',$this->td), $this->singular)),
            'add_new_item'               => ucfirst(sprintf(__('add new %s',$this->td), $this->singular)),
            'new_item_name'              => ucfirst(sprintf(__('new %s name',$this->td), $this->singular)),
            'separate_items_with_commas' => ucfirst(sprintf(__('separate %s with commas',$this->td), $this->plural)),
            'add_or_remove_items'        => ucfirst(sprintf(__('add or remove %s',$this->td), $this->plural)),
            'choose_from_most_used'      => ucfirst(sprintf(__('choose %s from most used',$this->td), $this->singular)),
            'not_found'                  => ucfirst(sprintf(__('%s not found',$this->td), $this->singular)),
            'no_terms'                   => ucfirst(sprintf(__('no %s',$this->td), $this->plural)),
            'filter_by_item'             => ucfirst(sprintf(__('filter by %s',$this->td), $this->singular)),
            'items_list_navigation'      => ucfirst(sprintf(__('%s list navigation',$this->td), $this->plural)),
            'items_list'                 => ucfirst(sprintf(__('%s list',$this->td), $this->plural)),
            'most_used'                  => ucfirst(sprintf(__('%s most used', $this->td), $this->singular)),
            'back_to_items'              => ucfirst(sprintf(__('back to %s', $this->td), $this->plural)),
            'item_link'                  => ucfirst(sprintf(__('%s link'), $this->singular)),
            'item_link_description'      => ucfirst(sprintf(__('%s link description'), $this->singular)),
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
        $new    = (!$this->male) ? __('nova', $this->td) : __('novo', $this->td);
        $found  = (!$this->male) ? __('encontradas', $this->td) : __('encontrados', $this->td);
        $parent = (!$this->male) ? __('mãe', $this->td) : __('pai', $this->td);
        $all    = (!$this->male) ? __('todas as', $this->td) : __('todos os', $this->td);
        $used   = (!$this->male) ? __('usadas', $this->td) : __('usados', $this->td);

        $this->labels = [
            'name'                       => ucfirst(_x($this->plural, 'taxonomy general name', $this->td)),
            'singular_name'              => ucfirst(_x($this->singular, 'taxonomy singular name', $this->td)),
            'search_items'               => ucfirst(sprintf(__('procurar %s',$this->td), $this->plural)),
            'popular_items'              => ucfirst(sprintf(__('%s populares',$this->td), $this->plural)),
            'all_items'                  => ucfirst($all . $this->plural),
            'parent_item'                => ucfirst($this->singular .' '. $parent),
            'parent_item_colon'          => ucfirst($this->singular .' '. $parent .':'),
            //'name_field_description'     => ucfirst('The name is how it appears on your site'),
            //'slug_field_description'     => ucfirst('The “slug” is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens'),
            //'parent_field_description'   => ucfirst('Assign a parent term to create a hierarchy. The term Jazz, for example, would be the parent of Bebop and Big Band'),
            //'desc_field_description'     => ucfirst('The description is not prominent by default; however, some themes may show it'),
            'edit_item'                  => ucfirst(sprintf(__('editar %s',$this->td), $this->singular)),
            'view_item'                  => ucfirst(sprintf(__('ver %s',$this->td), $this->singular)),
            'update_item'                => ucfirst(sprintf(__('atualizar %s',$this->td), $this->singular)),
            'add_new_item'               => ucfirst(sprintf(__('adicionar %s %s',$this->td), $new, $this->singular)),
            'new_item_name'              => ucfirst(sprintf(__('%s nome',$this->td), $new)),
            'separate_items_with_commas' => ucfirst(sprintf(__('separe %s por virgulas',$this->td), $this->plural)),
            'add_or_remove_items'        => ucfirst(sprintf(__('adicionar ou remover %s',$this->td), $this->plural)),
            'choose_from_most_used'      => ucfirst(sprintf(__('escolher %s mais comuns',$this->td), $this->singular)),
            'not_found'                  => ucfirst(sprintf(__('%s não %s',$this->td), $this->singular, $found)),
            'no_terms'                   => ucfirst(sprintf(__('sem %s',$this->td), $this->plural)),
            'filter_by_item'             => ucfirst(sprintf(__('filtrar por %s',$this->td), $this->singular)),
            'items_list_navigation'      => ucfirst(sprintf(__('navegação de %s',$this->td), $this->plural)),
            'items_list'                 => ucfirst(sprintf(__('lista de %s',$this->td), $this->plural)),
            'most_used'                  => ucfirst(sprintf(__('%s mais %s', $this->td), $this->singular, $used)),
            'back_to_items'              => ucfirst(sprintf(__('voltar para %s', $this->td), $this->plural)),
            'item_link'                  => ucfirst(sprintf(__('link para %s.'), $this->plural)),
            'item_link_description'      => ucfirst(sprintf(__('Um link para %s.'), $this->plural)),
        ];
    }
}
