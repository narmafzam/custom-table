<?php

namespace CustomTable;

use \WP_Error;

class Handler
{
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

        if ( isset( $objectData[$primaryKey] ) ) {
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

        $objectData = apply_filters( 'custom_table_insert_object_data', $databaseTable, $originalObjectData );

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
}