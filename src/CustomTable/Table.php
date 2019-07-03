<?php

namespace CustomTable;

class Table
{
    public $name;

    /**
     * @var Database
     */
    public $database;

    public $views;

    /**
     * @var TableMeta
     */
    public $meta;

    public $singular;

    public $plural;

    public $label;

    public $labels;

    public $excludeFromSearch = null;

    public $publiclyQueryable = null;

    public $showUi = null;

    public $showInMenu = null;

    public $showInNavMenus = null;

    public $showInAdminBar = null;

    public $menuPosition = null;

    public $menuIcon = null;

    public $capabilityType = 'post';

    public $mapMetaCap = false;

    public $registerMetaBoxCallback = null;

    public $taxonomies = array();

    public $hasArchive = false;

    public $queryVar;

    public $canExport = true;

    public $deleteWithUser = null;

    public $builtin = false;

    public $editLink = 'post.php?post=%d';

    public $cap;

    public $rewrite;

    public $supports;

    public $showInRest;

    public $restBase;

    public $restControllerClass;

    public function __construct( $name, $args = array() ) {

        // Table name
        $this->name = $name;

        $this->setProps( $args );

    }

    public function getName()
    {
        return $this->name;
    }

    /**
     * @return Database|null
     */
    public function getDatabase(): ? Database
    {
        return $this->database;
    }

    public function getViews()
    {
        return $this->views;
    }

    /**
     * @return TableMeta|null
     */
    public function getMeta(): ? TableMeta
    {
        return $this->meta;
    }

    public function getSingular()
    {
        return $this->singular;
    }

    public function getPlural()
    {
        return $this->plural;
    }

    public function getLabel()
    {
        return $this->label;
    }

    public function getLabels()
    {
        return $this->labels;
    }

    public function getExcludeFromSearch()
    {
        return $this->excludeFromSearch;
    }

    public function getPubliclyQueryable()
    {
        return $this->publiclyQueryable;
    }

    public function getShowUi()
    {
        return $this->showUi;
    }

    public function getShowInMenu()
    {
        return $this->showInMenu;
    }

    public function getShowInNavMenus()
    {
        return $this->showInNavMenus;
    }

    public function getShowInAdminBar()
    {
        return $this->showInAdminBar;
    }

    public function getMenuPosition()
    {
        return $this->menuPosition;
    }

    public function getMenuIcon()
    {
        return $this->menuIcon;
    }

    public function getCapabilityType(): string
    {
        return $this->capabilityType;
    }

    public function isMapMetaCap(): bool
    {
        return $this->mapMetaCap;
    }

    public function getRegisterMetaBoxCallback()
    {
        return $this->registerMetaBoxCallback;
    }

    public function getTaxonomies(): array
    {
        return $this->taxonomies;
    }

    public function isHasArchive(): bool
    {
        return $this->hasArchive;
    }

    public function getQueryVar()
    {
        return $this->queryVar;
    }

    public function isCanExport(): bool
    {
        return $this->canExport;
    }

    public function getDeleteWithUser()
    {
        return $this->deleteWithUser;
    }

    public function isBuiltin(): bool
    {
        return $this->builtin;
    }

    public function getEditLink(): string
    {
        return $this->editLink;
    }

    public function getCap()
    {
        return $this->cap;
    }

    public function getRewrite()
    {
        return $this->rewrite;
    }

    public function getSupports()
    {
        return $this->supports;
    }

    public function getShowInRest()
    {
        return $this->showInRest;
    }

    public function getRestBase()
    {
        return $this->restBase;
    }

    public function getRestControllerClass()
    {
        return $this->restControllerClass;
    }

    public function setProps( $args ) {
        $args = wp_parse_args( $args );

        $args = apply_filters( 'custom_table_register_table_args', $args, $this->getName() );

        $has_edit_link = isset( $args['_edit_link'] ) && !empty( $args['_edit_link'] );

        // Args prefixed with an underscore are reserved for internal use.
        $defaults = array(
            'singular'              => $this->getName(),
            'plural'                => $this->getName() . 's',
            'labels'                => array(),
            'description'           => '',
            'public'                => false,
            'hierarchical'          => false,
            'exclude_from_search'   => null,
            'publicly_queryable'    => null,
            'show_ui'               => null,
            'show_in_menu'          => null,
            'show_in_nav_menus'     => null,
            'show_in_admin_bar'     => null,
            'menu_position'         => null,
            'menu_icon'             => null,
            'capability_type'       => 'item',
            'capabilities'          => array(),
            'map_meta_cap'          => null,
            'supports'              => array(),
            'register_meta_box_cb'  => null,
            //'taxonomies'            => array(),
            'has_archive'           => false,
            'rewrite'               => true,
            'query_var'             => true,
            'can_export'            => true,
            'delete_with_user'      => null,
            'show_in_rest'          => false,
            'rest_base'             => false,
            'rest_controller_class' => false,
            //'_builtin'              => false,
            //'_edit_link'            => 'post.php?post=%d',
        );

        $args = array_merge( $defaults, $args );

        $args['name'] = $this->getName();

        // If not set, default to the setting for public.
        if ( null === $args['publicly_queryable'] ) {
            $args['publicly_queryable'] = $args['public'];
        }

        // If not set, default to the setting for public.
        if ( null === $args['show_ui'] ) {
            $args['show_ui'] = $args['public'];
        }

        // If not set, default to the setting for show_ui.
        if ( null === $args['show_in_menu'] || ! $args['show_ui'] ) {
            $args['show_in_menu'] = $args['show_ui'];
        }

        // If not set, default to the whether the full UI is shown.
        if ( null === $args['show_in_admin_bar'] ) {
            $args['show_in_admin_bar'] = (bool) $args['show_in_menu'];
        }

        // If not set, default to the setting for public.
        if ( null === $args['show_in_nav_menus'] ) {
            $args['show_in_nav_menus'] = $args['public'];
        }

        // If not set, default to true if not public, false if public.
        if ( null === $args['exclude_from_search'] ) {
            $args['exclude_from_search'] = ! $args['public'];
        }

        // Back compat with quirky handling in version 3.0. #14122.
        if ( empty( $args['capabilities'] ) && null === $args['map_meta_cap'] && in_array( $args['capability_type'], array( 'post', 'page' ) ) ) {
            $args['map_meta_cap'] = true;
        }

        // If not set, default to false.
        if ( null === $args['map_meta_cap'] ) {
            $args['map_meta_cap'] = false;
        }

        // If there's no specified edit link and no UI, remove the edit link.
        if ( ! $args['show_ui'] && ! $has_edit_link ) {
            $args['_edit_link'] = '';
        }

        $this->cap = Handler::getTableCapabilities( (object) $args );

        unset( $args['capabilities'] );

        if ( is_array( $args['capability_type'] ) ) {
            $args['capability_type'] = $args['capability_type'][0];
        }

        if ( false !== $args['query_var'] ) {
            if ( true === $args['query_var'] ) {
                $args['query_var'] = $this->getName();
            } else {
                $args['query_var'] = sanitize_title_with_dashes( $args['query_var'] );
            }
        }

        if ( false !== $args['rewrite'] && ( is_admin() || '' != get_option( 'permalink_structure' ) ) ) {
            if ( ! is_array( $args['rewrite'] ) ) {
                $args['rewrite'] = array();
            }
            if ( empty( $args['rewrite']['slug'] ) ) {
                $args['rewrite']['slug'] = $this->getName();
            }
            if ( ! isset( $args['rewrite']['with_front'] ) ) {
                $args['rewrite']['with_front'] = true;
            }
            if ( ! isset( $args['rewrite']['pages'] ) ) {
                $args['rewrite']['pages'] = true;
            }
            if ( ! isset( $args['rewrite']['feeds'] ) || ! $args['has_archive'] ) {
                $args['rewrite']['feeds'] = (bool) $args['has_archive'];
            }
            if ( ! isset( $args['rewrite']['ep_mask'] ) ) {
                if ( isset( $args['permalink_epmask'] ) ) {
                    $args['rewrite']['ep_mask'] = $args['permalink_epmask'];
                } else {
                    $args['rewrite']['ep_mask'] = EP_PERMALINK;
                }
            }
        }

        foreach ( $args as $property_name => $property_value ) {
            $property_name = Utility::generateCamelCase($property_name);
            $this->$property_name = $property_value;
        }

        $this->singular  = $args['singular'];
        $this->plural  = $args['plural'];

        $labels = (array) Handler::getTableLabels( $this );

        // Custom defined labels overrides default
        if( isset( $args['labels'] ) && is_array( $args['labels'] ) ) {
            $labels = wp_parse_args( $args['labels'], $labels );
        }

        $this->labels = (object) $labels;

        $this->label  = $this->getLabels()->name;

        // Table database

        if( isset( $args['db'] ) ) {
            if( is_array( $args['db'] ) ) {
                // Table as array of args to pass to Database
                $this->database = new Database( $this->getName(), $args['db'] );
            } else if( $args['db'] instanceof Database || is_subclass_of( $args['db'],  Database::class ) ) {
                // Table as custom object
                $this->database = $args['db'];
            }
        } else {
            // Default database initialization
            $this->database = new Database( $this->getName(), $args );
        }

        // Views (list, add, edit)

        $views_defaults = array(
            'list' => array(
                'page_title'    => $this->getLabels()->plural_name,
                'menu_title'    => $this->getLabels()->all_items,
                'menu_slug'     => $this->getName(),
                'show_in_menu'  => $this->getShowUi(),

                // Specific view args
                'per_page'      => 20,
                'columns'       => array(),
            ),
            'add' => array(
                'page_title'    => $this->getLabels()->add_new,
                'menu_title'    => $this->getLabels()->add_new,
                'menu_slug'     => 'add_' . $this->getName(),
                'parent_slug'   => $this->getName(),
                'show_in_menu'  => $this->getShowUi(),

                // Specific view args
                'columns'       => 2,
            ),
            'edit' => array(
                'page_title'    => $this->getLabels()->edit_item,
                'menu_title'    => $this->getLabels()->edit_item,
                'menu_slug'     => 'edit_' . $this->getName(),
                'show_in_menu'  => false,

                // Specific view args
                'columns'       => 2,
            ),
        );

        if( isset( $args['views'] ) && is_array( $args['views'] ) ) {

            $views = array();

            // Ensure default views (list, add, edit) are in
            foreach( $views_defaults as $view => $view_args ) {
                if( ! isset( $args['views'][$view] ) ) {
                    $args['views'][$view] = $view_args;
                }
            }

            foreach( $args['views'] as $view => $view_args ) {

                if( is_array( $view_args ) ) {

                    // Parse default view args
                    if( isset( $views_defaults[$view] ) ) {
                        $view_args = wp_parse_args( $view_args, $views_defaults[$view] );
                    }

                    // View as array of args to pass to View
                    switch( $view ) {
                        case 'list':
                            $views[$view] = new ListView( $this->getName(), $view_args );
                            break;
                        case 'add':
                            $views[$view] = new EditView( $this->getName(), $view_args );
                            break;
                        case 'edit':
                            $views[$view] = new EditView( $this->getName(), $view_args );
                            break;
                        default:
                            $views[$view] = new View( $this->getName(), $view_args );
                            break;
                    }
                } else if( $view_args instanceof View || is_subclass_of( $view_args, View::class ) ) {
                    // View as custom object
                    $views[$view] = $view_args;
                }
            }

            $this->views = (object) $views;

        } else {
            // Default views initialization
            $this->views = (object) array(
                'list' => new ListView( $this->getName(), $views_defaults['list'] ),
                'add' => new EditView( $this->getName(), $views_defaults['add'] ),
                'edit' => new EditView( $this->getName(), $views_defaults['edit'] ),
            );
        }

        // Meta data
        if( in_array( 'meta', $this->supports ) ) {
            $this->meta = new TableMeta( $this );
        }
    }
}