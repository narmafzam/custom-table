<?php

namespace CustomTable;

use \WP_Error;

class Handler
{
    public static function init()
    {
        self::addAction();
    }

    public static function addAction()
    {
        add_action( 'wp_ajax_custom_table_ajax_list_table_request', 'CustomTable\\Handler::ajaxListTableHandleRequest' );
    }

    public static function registerTable( $name, $args ) {

        global $registeredTables;

        if ( ! is_array( $registeredTables ) ) {
            $registeredTables = array();
        }

        $name = sanitize_key( $name );

        if( isset( $registeredTables[$name] ) ) {
            return $registeredTables[$name];
        }

        $table = new Table( $name, $args );

        $registeredTables[$name] = $table;

        return $table;

    }

    public static function setupTable( $object ) {

        /**
         * @var Table $databaseTable
         * @var Table $previousTable
         */
        global $registeredTables, $previousTable, $databaseTable;

        if( is_object( $databaseTable ) ) {
            $previousTable = $databaseTable;
        }

        if( is_object( $object ) ) {
            $databaseTable = $object;
        } else if( gettype( $object ) === 'string' && isset( $registeredTables[$object] ) ) {
            $databaseTable = $registeredTables[$object];
        }

        return $databaseTable;

    }

    public static function resetSetupTable() {

        /**
         * @var Table $databaseTable
         * @var Table $previousTable
         */
        global $registeredTables, $previousTable, $databaseTable;

        if( is_object( $previousTable ) ) {
            $databaseTable = $previousTable;
        }

        return $databaseTable;

    }

    public static function getTableObject( $name ) {

        /**
         * @var Table $databaseTable
         */
        global $registeredTables, $databaseTable;

        if( is_object( $name ) ) {
            $databaseTable = $name;
        } else if( gettype( $name ) === 'string' && isset( $registeredTables[$name] ) ) {
            $databaseTable = $registeredTables[$name];
        }

        return $databaseTable;
    }

    /**
     * @param Table $table
     *
     * @return object
     */
    public static function getTableLabels( Table $table ) {

        $default_labels = array(
            'name' => __('%1$s', 'custom-table'),
            'singular_name' => __('%1$s', 'custom-table'),
            'plural_name' => __('%2$s', 'custom-table'),
            'add_new' => __('Add New', 'custom-table'),
            'add_new_item' => __('Add New %1$s', 'custom-table'),
            'edit_item' => __('Edit %1$s', 'custom-table'),
            'new_item' => __('New %1$s', 'custom-table'),
            'view_item' => __('View %1$s', 'custom-table'),
            'view_items' => __('View %2$s', 'custom-table'),
            'search_items' => __( 'Search %2$s', 'custom-table' ),
            'not_found' => __( 'No %2$s found.', 'custom-table' ),
            'not_found_in_trash' => __( 'No %2$s found in Trash.', 'custom-table' ),
            'parent_item_colon' => __( 'Parent %1$s:', 'custom-table' ),
            'all_items' => __( 'All %2$s', 'custom-table' ),
            'archives' => __( '%1$s Archives', 'custom-table' ),
            'attributes' => __( '%1$s Attributes', 'custom-table' ),
            'insert_into_item' => __( 'Insert into %1$s', 'custom-table' ),
            'uploaded_to_this_item' => __( 'Uploaded to this post', 'custom-table' ),
            'featured_image' => __( 'Featured Image', 'custom-table' ),
            'set_featured_image' => __( 'Set featured image', 'custom-table' ),
            'remove_featured_image' => __( 'Remove featured image', 'custom-table' ),
            'use_featured_image' => __( 'Use as featured image', 'custom-table' ),
            'filter_items_list' => __( 'Filter posts list', 'custom-table' ),
            'items_list_navigation' => __( '%2$s list navigation', 'custom-table' ),
            'items_list' => __( '%2$s list', 'custom-table' ),
        );

        foreach( $default_labels as $label => $pattern ) {
            $default_labels[$label] = sprintf( $pattern, $table->getSingular(), $table->getPlural() );
        }

        return (object) $default_labels;

    }

    /**
     * @param Table $table
     *
     * @return mixed
     */
    public static function getTableFields( Table $table ) {

        return $table->getDatabase()->getSchema()->getFields();

    }

    public static function populateRoles() {

        // Add caps for Administrator role
        $role = get_role( 'administrator' );

        // Bail if administrator role is not setup
        if( ! $role ) {
            return;
        }

        $args = (object) array(
            'capabilities' => array(),
            'capability_type' => 'item',
            'map_meta_cap' => null
        );

        $capabilities = self::getTableCapabilities( $args );

        foreach( $capabilities as $cap ) {
            $role->add_cap( $cap );
        }

    }

    public static function getTableCapabilities( $args ) {
        if ( ! is_array( $args->capability_type ) )
            $args->capability_type = array( $args->capability_type, $args->capability_type . 's' );

        // Singular base for meta capabilities, plural base for primitive capabilities.
        list( $singular_base, $plural_base ) = $args->capability_type;

        $default_capabilities = array(
            // Meta capabilities
            'edit_item'          => 'edit_'         . $singular_base,
            'read_item'          => 'read_'         . $singular_base,
            'delete_item'        => 'delete_'       . $singular_base,
            'delete_items'       => 'delete_'       . $plural_base,
            // Primitive capabilities used outside of map_meta_cap():
            'edit_items'         => 'edit_'         . $plural_base,
            'edit_others_items'  => 'edit_others_'  . $plural_base,
            'publish_items'      => 'publish_'      . $plural_base,
            'read_private_items' => 'read_private_' . $plural_base,
        );

        // Primitive capabilities used within map_meta_cap():
        if ( $args->map_meta_cap ) {
            $default_capabilities_for_mapping = array(
                'read'                   => 'read',
                'delete_items'           => 'delete_'           . $plural_base,
                'delete_private_items'   => 'delete_private_'   . $plural_base,
                'delete_published_items' => 'delete_published_' . $plural_base,
                'delete_others_items'    => 'delete_others_'    . $plural_base,
                'edit_private_items'     => 'edit_private_'     . $plural_base,
                'edit_published_items'   => 'edit_published_'   . $plural_base,
            );
            $default_capabilities = array_merge( $default_capabilities, $default_capabilities_for_mapping );
        }

        $capabilities = array_merge( $default_capabilities, $args->capabilities );

        // Post creation capability simply maps to edit_items by default:
        if ( ! isset( $capabilities['create_items'] ) )
            $capabilities['create_items'] = $capabilities['edit_items'];

        // Remember meta capabilities for future reference.
        if ( $args->map_meta_cap )
            self::metaCapabilities( $capabilities );

        return (object) $capabilities;
    }

    public static function metaCapabilities( $capabilities = null ) {
        global $metaCaps;

        foreach ( $capabilities as $core => $custom ) {
            if ( in_array( $core, array( 'read_item', 'delete_item', 'edit_item' ) ) ) {
                $metaCaps[ $custom ] = $core;
            }
        }
    }

    public static function getObject( $object = null, $output = OBJECT, $filter = 'raw' ) {

        /**
         * @var Table $databaseTable
         */
        global $wpdb, $databaseTable;

        if ( is_object( $object ) ) {
            $primaryKey = $databaseTable->getDatabase()->getPrimaryKey();

            $object = Handler::getObjectInstance( $object->$primaryKey );
        } else {
            $object = Handler::getObjectInstance( $object );
        }

        if ( ! $object )
            return null;

        if ( $output == ARRAY_A )
            return (array) $object;
        elseif ( $output == ARRAY_N )
            return array_values( (array) $object );

        return $object;

    }

    public static function getObjectInstance( $objectId = null ) {
        /**
         * @var Table $databaseTable
         */
        global $wpdb, $databaseTable;

        $objectId = (int) $objectId;
        if ( ! $objectId ) {
            return false;
        }

        $object = wp_cache_get( $objectId, $databaseTable->getName() );

        if ( ! $object ) {
            $object = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$databaseTable->getDatabase()->getTableName()} WHERE {$databaseTable->getDatabase()->getPrimaryKey()} = %d LIMIT 1", $objectId ) );

            if ( ! $object )
                return false;

            wp_cache_add( $objectId, $object, $databaseTable->getName() );
        }

        return $object;

    }

    public static function cleanObjectCache( $object ) {
        /**
         * @var Table $databaseTable
         */
        global $_wp_suspend_cache_invalidation, $databaseTable;

        if ( ! empty( $_wp_suspend_cache_invalidation ) )
            return;

        $object = Handler::getObject( $object );
        if ( empty( $object ) )
            return;

        $primaryKey = $databaseTable->getDatabase()->getPrimaryKey();

        wp_cache_delete( $object->$primaryKey, $databaseTable->getName() );

        //wp_cache_delete( 'wp_get_archives', 'general' );

        do_action( 'custom_table_clean_object_cache', $object->$primaryKey, $object );

        wp_cache_set( 'last_changed', microtime(), $databaseTable->getName() );
    }

    public static function insertObject( $objectData, $wp_error = false ) {
        /**
         * @var Table $databaseTable
         */
        global $wpdb, $databaseTable;

        if( ! $databaseTable instanceof Table ) {
            return new WP_Error( 'invalid_custom_table_table', __( 'Invalid CustomTable Table object.' ) );
        }

        $defaults = apply_filters( "custom_table_{$databaseTable->getName()}_default_data", array() );

        $objectData = wp_parse_args( $objectData, $defaults );

        // Are we updating or creating?
        $objectId = 0;
        $originalObjectData = array();
        $update = false;
        $primaryKey = $databaseTable->getDatabase()->getPrimaryKey();

        if ( isset( $objectData[$primaryKey] ) && !empty($objectData[$primaryKey]) ) {
            $update = true;

            // Get the object ID.
            $objectId = $databaseTable[$primaryKey];
            $objectBefore = Handler::getObject( $objectId );
            $originalObjectData = Handler::getObject( $objectId, ARRAY_A );

            if ( is_null( $originalObjectData ) ) {

                if ( $wp_error ) {
                    return new WP_Error( 'invalid_object', __( 'Invalid object ID.' ) );
                }

                return 0;
            }
        }

        $objectData = apply_filters( 'custom_table_insert_object_data', $objectData, $originalObjectData );

        $objectData = wp_unslash( $objectData );

        $where = array( $primaryKey => $objectId );

        if ( $update ) {

            do_action( 'pre_object_update', $objectId, $objectData );

            if ( false === $databaseTable->getDatabase()->update( $objectData, $where ) ) {
                if ( $wp_error ) {
                    return new WP_Error('db_update_error', __('Could not update object in the database'), $wpdb->last_error);
                } else {
                    return 0;
                }
            }
        } else {

            $importId = isset( $objectData['import_id'] ) ? $objectData['import_id'] : 0;

            // If there is a suggested ID, use it if not already present.
            if ( ! empty( $importId ) ) {

                $importId = (int) $importId;
                if ( ! $wpdb->get_var( $wpdb->prepare("SELECT {$primaryKey} FROM {$databaseTable->getDatabase()->getTableName()} WHERE {$primaryKey} = %d", $importId) ) ) {
                    $objectData[$primaryKey] = $importId;
                }
            }

            if ( false === $wpdb->insert( $databaseTable->getDatabase()->getTableName(), $objectData ) ) {

                if ( $wp_error ) {
                    return new WP_Error('db_insert_error', __('Could not insert object into the database'), $wpdb->last_error);
                } else {
                    return 0;
                }

            }

            $objectId = (int) $wpdb->insert_id;
        }

        // If isset meta_input and object supports meta, then add meta data
        if ( isset( $objectData['meta_input'] ) && ! empty( $objectData['meta_input'] ) && $databaseTable->getMeta() ) {
            foreach ( $objectData['meta_input'] as $field => $value ) {
                MetaHandler::updateObjectMeta( $objectId, $field, $value );
            }
        }

        Handler::cleanObjectCache( $objectId );

        $object = Handler::getObject( $objectId );

        if ( $update ) {

            do_action( 'custom_table_edit_object', $objectId, $object );

            $objectAfter = self::getObject( $objectId );

            do_action( 'custom_table_object_updated', $objectId, $objectAfter, $objectBefore);
        }

        do_action( "ct_save_object_{$databaseTable->getName()}", $objectId, $object, $update );

        do_action( 'custom_table_save_object', $objectId, $object, $update );

        do_action( 'custom_table_insert_object', $objectId, $object, $update );

        return $objectId;
    }

    public static function updateObject( $objectData = array(), $wp_error = false ) {
        /**
         * @var Table $databaseTable
         */
        global $databaseTable;

        if ( is_object( $objectData ) ) {
            // Non-escaped post was passed.
            $objectData = self::getObject( $objectData );
            $objectData = wp_slash( $objectData );
        }

        $primaryKey = $databaseTable->getDatabase()->getPrimaryKey();

        // First, get all of the original fields.
        $object = self::getObject( $objectData[$primaryKey], ARRAY_A );

        if ( is_null( $object ) ) {
            if ( $wp_error )
                return new WP_Error( 'invalid_object', __( 'Invalid object ID.' ) );
            return 0;
        }

        // Escape data pulled from DB.
        $object = wp_slash( $object );

        // Merge old and new fields with new fields overwriting old ones.
        $objectData = array_merge( $object, $objectData );

        return Handler::insertObject( $objectData, $wp_error );

    }

    public static function deleteObject( $objectId = 0, $force_delete = false ) {
        /**
         * @var Table $databaseTable
         */
        global $wpdb, $databaseTable;

        if( ! $databaseTable instanceof Table ) {
            return new WP_Error( 'invalid_custom_table_table', __( 'Invalid CustomTable Table object.' ) );
        }

        $object = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$databaseTable->getDatabase()->getTableName()} WHERE {$databaseTable->getDatabase()->getPrimaryKey()} = %d", $objectId ) );

        if ( ! $object ) {
            return $object;
        }

        $object = Handler::getObject( $object );

        // TODO: Add support for trash functionality
        //if ( ! $force_delete && ( 'post' === $post->post_type || 'page' === $post->post_type ) && 'trash' !== get_post_status( $postid ) && EMPTY_TRASH_DAYS ) {
        //return trashObject( $objectId );
        //}

        $check = apply_filters( 'pre_delete_object', null, $object, $force_delete );
        if ( null !== $check ) {
            return $check;
        }

        do_action( 'before_delete_object', $objectId );

        if( $databaseTable->getMeta() ) {
            MetaHandler::deleteObjectMeta( $objectId, '_wp_trash_meta_status' );
            MetaHandler::deleteObjectMeta( $objectId, '_wp_trash_meta_time' );

            $objectMetaIds = $wpdb->get_col( $wpdb->prepare( "SELECT {$databaseTable->getMeta()->getDatabase()->getPrimaryKey()} FROM {$databaseTable->getMeta()->getDatabase()->getPrimaryKey()} WHERE {$databaseTable->getDatabase()->getPrimaryKey()} = %d ", $objectId ));

            foreach ( $objectMetaIds as $mid ) {
                MetaHandler::deleteMetadataByMid( 'post', $mid );
            }
        }

        do_action( 'delete_object', $objectId );

        $result = $databaseTable->getDatabase()->delete( $objectId );

        if ( ! $result ) {
            return false;
        }

        do_action( 'deleted_object', $objectId );

        Handler::cleanObjectCache( $object );

        do_action( 'after_delete_object', $objectId );

        return $object;
    }

    /**
     * @param ListTable $listTable
     * @param $which
     */
    public static function renderAjaxListTableNav( $listTable, $which ) {
        ?>
        <div class="tablenav <?php echo esc_attr( $which ); ?>">

            <?php if ( $listTable->has_items() ): ?>
                <div class="alignleft actions bulkactions">
                    <?php $listTable->bulk_actions( $which ); ?>
                </div>
            <?php endif;
            $listTable->extra_tablenav( $which );
            $listTable->pagination( $which );
            ?>

            <br class="clear" />
        </div>
        <?php
    }

    /**
     * @param string $table
     * @param array $query_args
     * @param array $view_args
     */
    public static function renderAjaxListTable( $table, $query_args = array(), $view_args = array() ) {
        /**
         * @var Table $databaseTable
         * @var Query $query
         * @var ListTable $listTable
         */
        global $databaseTable, $query, $listTable;

        $databaseTable = self::setupTable( $table );
        if( is_object( $databaseTable ) ) {
            // Setup this constant to allow from ListTable meet that this render comes from this plugin
            @define( 'CUSTOM_TABLE_AJAX_LIST_TABLE', true );
            // Enqueue assets
            Asset::enqueue();
            // Setup query args
            $query_args = wp_parse_args( $query_args, array(
                'paged' => 1,
                'items_per_page' => 20,
            ) );
            // setup view args
            $view_args = wp_parse_args( $view_args, array(
                'views' => true,
                'search_box' => true,
            ) );
            // Set up vars
            $query = new Query( $query_args );
            $listTable = new ListTable();
            $listTable->prepare_items();
            ?>

            <div class="wrap ct-ajax-list-table" data-object="<?php echo $databaseTable->getName(); ?>" data-query-args="<?php echo str_replace( '"', "'", json_encode( $query_args ) ); ?>">

                <?php
                if( $view_args['views'] ) {
                    $listTable->views();
                }
                ?>

                <?php // <form id="ct-list-filter" method="get"> ?>

                <?php
                if( $view_args['search_box'] ) {
                    $listTable->search_box( $databaseTable->getLabels()->search_items, $databaseTable->getName() );
                }
                ?>

                <?php self::renderAjaxListTableNav( $listTable, 'top' ); ?>

                <table class="wp-list-table <?php echo implode( ' ', $listTable->get_table_classes() ); ?>">
                    <thead>
                    <tr>
                        <?php $listTable->print_column_headers(); ?>
                    </tr>
                    </thead>

                    <?php $singular = $listTable->_args['singular']; ?>
                    <tbody id="the-list"<?php
                    if ( $singular ) {
                        echo " data-wp-lists='list:$singular'";
                    } ?>>
                    <?php $listTable->display_rows_or_placeholder(); ?>
                    </tbody>

                    <tfoot>
                    <tr>
                        <?php $listTable->print_column_headers( false ); ?>
                    </tr>
                    </tfoot>

                </table>

                <?php self::renderAjaxListTableNav( $listTable, 'bottom' ); ?>

                <?php // </form> ?>

                <div id="ajax-response"></div>
                <br class="clear" />

            </div>

            <?php
        }
    }

    public static function ajaxListTableHandleRequest() {
        global $databaseTable, $query, $listTable;
        if( ! isset( $_GET['object'] ) ) {
            wp_send_json_error();
        }
        $databaseTable = self::setupTable( $_GET['object'] );
        if( ! is_object( $databaseTable ) ) {
            wp_send_json_error();
        }
        // Setup this constant to allow from ListTable meet that this render comes from this plugin
        @define( 'CUSTOM_TABLE_AJAX_LIST_TABLE', true );
        if( is_array( $_GET['query_args'] ) ) {
            $query_args = $_GET['query_args'];
        } else {
            $query_args = json_decode( str_replace( "\\'", "\"", $_GET['query_args'] ), true );
        }
        $query_args = wp_parse_args( $query_args, array(
            'paged' => 1,
            'items_per_page' => 20,
        ) );
        if( isset( $_GET['paged'] ) ) {
            $query_args['paged'] = $_GET['paged'];
        }
        // Set up vars
        $query = new Query( $query_args );
        $listTable = new ListTable();
        $listTable->prepare_items();
        ob_start();
        $listTable->display();
        $output = ob_get_clean();
        wp_send_json_success( $output );
    }
}