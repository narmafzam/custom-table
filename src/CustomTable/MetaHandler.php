<?php

namespace CustomTable;

use stdClass;

class MetaHandler
{
    public static function addObjectMeta( $objectId, $metaKey, $metaValue, $unique = false ) {

        /**
         * @var Table $databaseTable
         */
        global $wpdb, $databaseTable;

        // Bail if Table not supports meta data
        if( ! $databaseTable->getMeta() ) {
            return false;
        }

        $objectId = absint( $objectId );
        if ( ! $objectId ) {
            return false;
        }

        $primaryKey = $databaseTable->getDatabase()->getPrimaryKey();
        $metaPrimaryKey = $databaseTable->getMeta()->getDatabase()->getPrimaryKey();
        $metaTableName = $databaseTable->getMeta()->getDatabase()->getTableName();

        // expected_slashed ($metaKey)
        $metaKey = wp_unslash($metaKey);
        $metaValue = wp_unslash($metaValue);
        $metaValue = sanitize_meta( $metaKey, $metaValue, $databaseTable->getName() );

        $check = apply_filters( "add_{$databaseTable->getName()}_metadata", null, $objectId, $metaKey, $metaValue, $unique );
        if ( null !== $check )
            return $check;

        if ( $unique && $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$metaTableName} WHERE meta_key = %s AND {$primaryKey} = %d",
                $metaKey, $objectId ) ) )
            return false;

        $_metaValue = $metaValue;
        $metaValue = maybe_serialize( $metaValue );

        do_action( "add_{$databaseTable->getName()}_meta", $objectId, $metaKey, $_metaValue );

        $result = $wpdb->insert( $metaTableName, array(
            $primaryKey => $objectId,
            'meta_key' => $metaKey,
            'meta_value' => $metaValue
        ) );

        if ( ! $result )
            return false;

        $mid = (int) $wpdb->insert_id;

        wp_cache_delete( $objectId, $databaseTable->getMeta()->getName() );

        do_action( "added_{$databaseTable->getName()}_meta", $mid, $objectId, $metaKey, $_metaValue );

        return $mid;
    }

    public static function deleteObjectMeta( $objectId, $metaKey, $metaValue = '' ) {
        /**
         * @var Table $databaseTable
         */
        global $wpdb, $databaseTable;

        // Bail if Table not supports meta data
        if( ! $databaseTable->meta ) {
            return false;
        }

        $objectId = absint( $objectId );
        if ( ! $objectId ) {
            return false;
        }

        $primaryKey = $databaseTable->getDatabase()->getPrimaryKey();
        $metaPrimaryKey = $databaseTable->getMeta()->getDatabase()->getPrimaryKey();
        $metaTableName = $databaseTable->getMeta()->getDatabase()->getTableName();

        // expected_slashed ($metaKey)
        $metaKey = wp_unslash($metaKey);
        $metaValue = wp_unslash($metaValue);
        $delete_all = false;

        $check = apply_filters( "delete_{$databaseTable->getName()}_metadata", null, $objectId, $metaKey, $metaValue, $delete_all );
        if ( null !== $check )
            return (bool) $check;

        $_metaValue = $metaValue;
        $metaValue = maybe_serialize( $metaValue );

        $query = $wpdb->prepare( "SELECT {$metaPrimaryKey} FROM {$metaTableName} WHERE meta_key = %s", $metaKey );

        if ( !$delete_all )
            $query .= $wpdb->prepare(" AND $primaryKey = %d", $objectId );

        if ( '' !== $metaValue && null !== $metaValue && false !== $metaValue )
            $query .= $wpdb->prepare(" AND meta_value = %s", $metaValue );

        $meta_ids = $wpdb->get_col( $query );
        if ( !count( $meta_ids ) )
            return false;

        if ( $delete_all ) {
            $value_clause = '';
            if ( '' !== $metaValue && null !== $metaValue && false !== $metaValue ) {
                $value_clause = $wpdb->prepare( " AND meta_value = %s", $metaValue );
            }

            $objectIds = $wpdb->get_col( $wpdb->prepare( "SELECT {$metaPrimaryKey} FROM {$metaTableName} WHERE meta_key = %s {$value_clause}", $metaKey ) );
        }

        do_action( "delete_{$databaseTable->getName()}_meta", $meta_ids, $objectId, $metaKey, $_metaValue );

        $query = "DELETE FROM $metaTableName WHERE {$metaPrimaryKey} IN( " . implode( ',', $meta_ids ) . " )";

        $count = $wpdb->query($query);

        if ( !$count )
            return false;

        if ( $delete_all && isset($objectIds) ) {
            foreach ( (array) $objectIds as $o_id ) {
                wp_cache_delete( $o_id, $databaseTable->getMeta()->getName() );
            }
        } else {
            wp_cache_delete( $objectId, $databaseTable->getMeta()->getName() );
        }

        do_action( "deleted_{$databaseTable->getName()}_meta", $meta_ids, $objectId, $metaKey, $_metaValue );

        return true;
    }

    public static function getObjectMeta( $objectId, $metaKey = '', $single = false ) {
        /**
         * @var Table $databaseTable
         */
        global $wpdb, $databaseTable;

        // Bail if Table not supports meta data
        if( ! $databaseTable->meta ) {
            return false;
        }

        $objectId = absint( $objectId );
        if ( ! $objectId ) {
            return false;
        }

        $check = apply_filters( "get_{$databaseTable->getName()}_metadata", null, $objectId, $metaKey, $single );
        if ( null !== $check ) {
            if ( $single && is_array( $check ) )
                return $check[0];
            else
                return $check;
        }

        $meta_cache = wp_cache_get( $objectId, $databaseTable->getMeta()->getName() );

        if ( !$meta_cache ) {
            $meta_cache = self::updateMetaCache( $databaseTable->getMeta()->getName(), array( $objectId ) );
            $meta_cache = $meta_cache[$objectId];
        }

        if ( ! $metaKey ) {
            return $meta_cache;
        }

        if ( isset($meta_cache[$metaKey]) ) {
            if ( $single )
                return maybe_unserialize( $meta_cache[$metaKey][0] );
            else
                return array_map('maybe_unserialize', $meta_cache[$metaKey]);
        }

        if ( $single )
            return '';
        else
            return array();
    }

    public static function updateObjectMeta( $objectId, $metaKey, $metaValue, $prev_value = '' ) {
        /**
         * @var Table $databaseTable
         */
        global $wpdb, $databaseTable;

        if( ! is_a( $databaseTable, Table::class ) ) {
            return false;
        }

        // Bail if Table not supports meta data
        if( ! $databaseTable->meta ) {
            return false;
        }

        $objectId = absint( $objectId );
        if ( ! $objectId ) {
            return false;
        }

        $primaryKey = $databaseTable->getDatabase()->getPrimaryKey();
        $metaPrimaryKey = $databaseTable->getMeta()->getDatabase()->getPrimaryKey();
        $metaTableName = $databaseTable->getMeta()->getDatabase()->getTableName();

        // Keep original values
        $raw_meta_key = $metaKey;
        $passed_value = $metaValue;

        // Sanitize vars
        $metaKey = wp_unslash( $metaKey );
        $metaValue = wp_unslash( $metaValue );
        $metaValue = sanitize_meta( $metaKey, $metaValue, $databaseTable->getName() );

        $check = apply_filters( "update_{$databaseTable->getName()}_metadata", null, $objectId, $metaKey, $metaValue, $prev_value );
        if ( null !== $check )
            return (bool) $check;

        // Compare existing value to new value if no prev value given and the key exists only once.
        if ( empty($prev_value) ) {
            $old_value = self::getObjectMeta( $objectId, $metaKey );
            if ( count( $old_value ) == 1 ) {
                if ( $old_value[0] === $metaValue )
                    return false;
            }
        }

        $meta_ids = $wpdb->get_col( $wpdb->prepare( "SELECT {$metaPrimaryKey} FROM {$metaTableName} WHERE meta_key = %s AND {$primaryKey} = %d", $metaKey, $objectId ) );
        if ( empty( $meta_ids ) ) {
            return self::addObjectMeta( $objectId, $raw_meta_key, $passed_value );
        }

        $_metaValue = $metaValue;
        $metaValue = maybe_serialize( $metaValue );

        $data  = compact( 'meta_value' );
        $where = array(
            $primaryKey => $objectId,
            'meta_key' => $metaKey
        );

        if ( ! empty( $prev_value ) ) {
            $prev_value = maybe_serialize( $prev_value );
            $where['meta_value'] = $prev_value;
        }

        foreach ( $meta_ids as $meta_id ) {

            do_action( "update_{$databaseTable->getName()}_meta", $meta_id, $objectId, $metaKey, $_metaValue );
        }

        $result = $databaseTable->getMeta()->getDatabase()->update( $data, $where );

        if ( ! $result )
            return false;

        wp_cache_delete( $objectId, $databaseTable->getMeta()->getName() );

        foreach ( $meta_ids as $meta_id ) {

            do_action( "updated_{$databaseTable->getName()}_meta", $meta_id, $objectId, $metaKey, $_metaValue );
        }

        return true;
    }

    public static function updateMetaCache( $metaType, $objectIds) {
        /**
         * @var Table $databaseTable
         */
        global $wpdb, $databaseTable;

        if ( ! $metaType || ! $objectIds ) {
            return false;
        }

        // Bail if Table not supports meta data
        if( ! $databaseTable->meta ) {
            return false;
        }

        // Setup vars
        $primaryKey = $databaseTable->getDatabase()->getPrimaryKey();
        $metaPrimaryKey = $databaseTable->getMeta()->getDatabase()->getPrimaryKey();
        $metaTableName = $databaseTable->getMeta()->getDatabase()->getTableName();

        if ( !is_array($objectIds) ) {
            $objectIds = preg_replace('|[^0-9,]|', '', $objectIds);
            $objectIds = explode(',', $objectIds);
        }

        $objectIds = array_map('intval', $objectIds);

        $cache_key = $metaTableName;
        $ids = array();
        $cache = array();
        foreach ( $objectIds as $id ) {
            $cached_object = wp_cache_get( $id, $cache_key );
            if ( false === $cached_object )
                $ids[] = $id;
            else
                $cache[$id] = $cached_object;
        }

        if ( empty( $ids ) )
            return $cache;

        // Get meta info
        $idList = join( ',', $ids );

        $metaList = $wpdb->get_results( "SELECT {$primaryKey}, meta_key, meta_value FROM {$metaTableName} WHERE {$primaryKey} IN ($idList) ORDER BY {$metaPrimaryKey} ASC", ARRAY_A );

        if ( !empty($metaList) ) {
            foreach ( $metaList as $metarow) {
                $mpid = intval($metarow[$primaryKey]);
                $mkey = $metarow['meta_key'];
                $mval = $metarow['meta_value'];

                // Force subkeys to be array type:
                if ( !isset($cache[$mpid]) || !is_array($cache[$mpid]) )
                    $cache[$mpid] = array();
                if ( !isset($cache[$mpid][$mkey]) || !is_array($cache[$mpid][$mkey]) )
                    $cache[$mpid][$mkey] = array();

                // Add a value to the current pid/key:
                $cache[$mpid][$mkey][] = $mval;
            }
        }

        foreach ( $ids as $id ) {
            if ( ! isset($cache[$id]) )
                $cache[$id] = array();
            wp_cache_add( $id, $cache[$id], $cache_key );
        }

        return $cache;
    }

    public static function getMetadataByMid( $metaType, $meta_id ) {
        /**
         * @var Table $databaseTable
         */
        global $wpdb, $databaseTable;

        if ( ! $metaType || ! is_numeric( $meta_id ) || floor( $meta_id ) != $meta_id ) {
            return false;
        }

        $meta_id = intval( $meta_id );

        if ( $meta_id <= 0 ) {
            return false;
        }

        // Bail if Table not supports meta data
        if( ! $databaseTable->meta ) {
            return false;
        }

        // Setup vars
        $metaPrimaryKey = $databaseTable->getMeta()->getDatabase()->getPrimaryKey();
        $metaTableName = $databaseTable->getMeta()->getDatabase()->getTableName();

        $meta = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$metaTableName} WHERE {$metaPrimaryKey} = %d", $meta_id ) );

        if ( empty( $meta ) )
            return false;

        if ( isset( $meta->meta_value ) )
            $meta->meta_value = maybe_unserialize( $meta->meta_value );

        return $meta;

    }

    public static function deleteMetadataByMid( $metaType, $meta_id ) {
        /**
         * @var Table $databaseTable
         */
        global $wpdb, $databaseTable;

        // Make sure everything is valid.
        if ( ! $metaType || ! is_numeric( $meta_id ) || floor( $meta_id ) != $meta_id ) {
            return false;
        }

        $meta_id = intval( $meta_id );

        if ( $meta_id <= 0 ) {
            return false;
        }

        // Bail if Table not supports meta data
        if( ! $databaseTable->meta ) {
            return false;
        }

        // Setup vars
        $metaPrimaryKey = $databaseTable->getMeta()->getDatabase()->getPrimaryKey();
        $metaTableName = $databaseTable->getMeta()->getDatabase()->getTableName();

        // Fetch the meta and go on if it's found.
        if ( $meta = self::getMetadataByMid( $metaType, $meta_id ) ) {
            $objectId = $meta->{$metaPrimaryKey};

            do_action( "delete_{$metaType}_meta", (array) $meta_id, $objectId, $meta->meta_key, $meta->meta_value );

            // Run the query, will return true if deleted, false otherwise
            $result = (bool) $wpdb->delete( $metaTableName, array( $metaPrimaryKey => $meta_id ) );

            // Clear the caches.
            wp_cache_delete($objectId, $metaType . '_meta');

            do_action( "deleted_{$metaType}_meta", (array) $meta_id, $objectId, $meta->meta_key, $meta->meta_value );

            return $result;

        }

        // Meta id was not found.
        return false;
    }

    public static function hasMeta( $objectId ) {
        /**
         * @var Table $databaseTable
         */
        global $wpdb, $databaseTable;

        $primaryKey = $databaseTable->getDatabase()->getPrimaryKey();
        $metaPrimaryKey = $databaseTable->getMeta()->getDatabase()->getPrimaryKey();
        $metaTableName = $databaseTable->getMeta()->getDatabase()->getTableName();

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT meta_key, meta_value, meta_id, {$primaryKey}
         FROM {$metaTableName} WHERE {$primaryKey} = %d
		 ORDER BY meta_key,meta_id",
            $objectId ), ARRAY_A );
    }

    public static function metaForm( $object = null ) {

        /**
         * @var Table $databaseTable
         */
        global $wpdb, $databaseTable;

        $primaryKey = $databaseTable->getDatabase()->getPrimaryKey();
        $object = Handler::getObject( $object );

        $keys = apply_filters( "{$databaseTable->getName()}_meta_form_keys", null, $object );

        if ( null === $keys ) {
            $limit = apply_filters( "{$databaseTable->getName()}_meta_form_limit", 30 );
            $sql = "SELECT DISTINCT meta_key
			FROM {$databaseTable->getMeta()->getDatabase()->getTableName()}
			WHERE meta_key NOT BETWEEN '_' AND '_z'
			HAVING meta_key NOT LIKE %s
			ORDER BY meta_key
			LIMIT %d";
            $keys = $wpdb->get_col( $wpdb->prepare( $sql, $wpdb->esc_like( '_' ) . '%', $limit ) );
        }

        if ( $keys ) {
            natcasesort( $keys );
            $metaKey_input_id = 'metakeyselect';
        } else {
            $metaKey_input_id = 'metakeyinput';
        }
        ?>
        <p><strong><?php _e( 'Add New Custom Field:' ) ?></strong></p>
        <table id="newmeta">
            <thead>
            <tr>
                <th class="left"><label for="<?php echo $metaKey_input_id; ?>"><?php _ex( 'Name', 'meta name' ) ?></label></th>
                <th><label for="metavalue"><?php _e( 'Value' ) ?></label></th>
            </tr>
            </thead>

            <tbody>
            <tr>
                <td id="newmetaleft" class="left">
                    <?php if ( $keys ) { ?>
                        <select id="metakeyselect" name="metakeyselect">
                            <option value="#NONE#"><?php _e( '&mdash; Select &mdash;' ); ?></option>
                            <?php

                            foreach ( $keys as $key ) {
                                if ( is_protected_meta( $key, 'post' ) || ! current_user_can( 'add_post_meta', $object->$primaryKey, $key ) )
                                    continue;
                                echo "\n<option value='" . esc_attr($key) . "'>" . esc_html($key) . "</option>";
                            }
                            ?>
                        </select>
                        <input class="hide-if-js" type="text" id="metakeyinput" name="metakeyinput" value="" />
                        <a href="#postcustomstuff" class="hide-if-no-js" onclick="jQuery('#metakeyinput, #metakeyselect, #enternew, #cancelnew').toggle();return false;">
                            <span id="enternew"><?php _e('Enter new'); ?></span>
                            <span id="cancelnew" class="hidden"><?php _e('Cancel'); ?></span></a>
                    <?php } else { ?>
                        <input type="text" id="metakeyinput" name="metakeyinput" value="" />
                    <?php } ?>
                </td>
                <td><textarea id="metavalue" name="metavalue" rows="2" cols="25"></textarea></td>
            </tr>

            <tr><td colspan="2">
                    <div class="submit">
                        <?php submit_button( __( 'Add Custom Field' ), '', 'addmeta', false, array( 'id' => 'newmeta-submit', 'data-wp-lists' => 'add:the-list:newmeta' ) ); ?>
                    </div>
                    <?php wp_nonce_field( 'add-meta', '_ajax_nonce-add-meta', false ); ?>
                </td></tr>
            </tbody>
        </table>
        <?php

    }

}