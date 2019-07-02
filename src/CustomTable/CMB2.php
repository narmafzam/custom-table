<?php

namespace CustomTable;

use \CMB2_hookup;

class CMB2
{
    public static function init()
    {
        self::addAction();
        self::addFilter();
    }

    public static function addAction()
    {
        add_action( 'admin_init', 'CustomTable\\CMB2::adminInit', 1 );
        add_action( 'add_meta_boxes', 'CustomTable\\CMB2::addMetaBoxes', 10, 2 );
        add_action( 'custom_table_save_object', 'CustomTable\\CMB2::saveObject', 10, 2 );
    }

    public static function addFilter()
    {
        add_filter( 'cmb2_override_meta_value', 'CustomTable\\CMB2::overrideMetaValue', 10, 4 );
        add_filter( 'cmb2_override_meta_save', 'CustomTable\\CMB2::overrideMetaSave', 10, 4 );
        add_filter( 'cmb2_override_meta_remove', 'CustomTable\\CMB2::overrideMetaRemove', 10, 4 );
    }

    public static function adminInit() {

        global $registeredTables, $databaseTable, $cmb2Override, $pagenow;

        // Setup a custom global to meet that we need to override it
        $cmb2Override = false;

        // Check if is on admin.php
        if( $pagenow !== 'admin.php' ) {
            return;
        }

        // Check if isset page query parameter
        if( ! isset( $_GET['page'] ) ) {
            return;
        }

        /** @var Table $registeredTable */
        foreach( $registeredTables as $registeredTable ) {

            // Check if is edit page slug
            if( $_GET['page'] === $registeredTable->getViews()->edit->get_slug() ) {
                // Let know to this compatibility module it needs to operate
                $cmb2Override = true;
            }
        }

    }

    public static function addMetaBoxes( $tableName, $object ) {

        /**
         * @var Table $databaseTable
         */
        global $registeredTables, $databaseTable, $cmb2Override;

        // If not is a registered table, return
        if( ! isset( $registeredTables[$tableName] ) ) {
            return;
        }

        // If not object given, return
        if( ! $object ) {
            return;
        }

        $primaryKey = $databaseTable->getDatabase()->getPrimaryKey();

        // Setup a false post var to allow CMB2 trigger cmb2_override_meta_value hook
        $_REQUEST['post'] = $object->$primaryKey;

        // Let know to this compatibility module it needs to operate
        $cmb2Override = true;

        // Fix: CMB2 stop enqueuing their assets so need to add it again
        CMB2_hookup::enqueue_cmb_css();
        CMB2_hookup::enqueue_cmb_js();

    }

    public static function saveObject( $objectId, $object ) {

        /**
         * @var Table $databaseTable
         */
        global $registeredTables, $databaseTable, $cmb2Override;

        // Return if CMB2 not exists
        if( ! class_exists( 'CMB2' ) ) {
            return;
        }

        // Return if user is not allowed
        if ( ! current_user_can( 'edit_item', $objectId ) ) {
            return;
        }

        // Setup a custom global to meet that we need to override it
        $cmb2Override = true;

        // Loop all registered boxes
        foreach( CMB2_Boxes::get_all() as $cmb ) {

            // Skip meta boxes that do not support this Table
            if( ! in_array( $databaseTable->getName(), $cmb->meta_box['object_types'] ) ) {
                continue;
            }

            // Take a trip to reading railroad – if you pass go collect $200
            $cmb->save_fields( $objectId, 'post', $_POST );
        }

    }

    public static function overrideMetaValue( $value, $objectId, $args, $field ) {

        /**
         * @var Table $databaseTable
         */
        global $registeredTables, $databaseTable, $cmb2Override;

        if( ! is_a( $databaseTable, Table::class ) ) {
            return $value;
        }

        if( $cmb2Override !== true ) {
            return $value;
        }

        $object = (array) Handler::getObject( $objectId );

        // Check if is a main field
        if( isset( $object[$args['field_id']] ) ) {
            return $object[$args['field_id']];
        }

        // If not is a main field and Table supports meta data, then try to get its value from meta table
        if( in_array( 'meta', $databaseTable->getSupports() ) ) {
            return MetaHandler::getObjectMeta( $objectId, $args['field_id'], ( $args['single'] || $args['repeat'] ) );
        }

        return '';
    }

    public static function overrideMetaSave( $check, $args, $field_args, $field ) {

        /**
         * @var Table $databaseTable
         */
        global $registeredTables, $databaseTable, $cmb2Override;

        if( $cmb2Override !== true ) {
            return $check;
        }

        $object = (array) Handler::getObject( $args['id'] );

        // If not is a main field and Table supports meta data, then try to save the given value to the meta table
        // Note: Main fields are automatically stored by the save method on the EditView edit screen
        if( ! isset( $object[$args['field_id']] ) && in_array( 'meta', $databaseTable->getSupports() ) ) {

            // Add metadata if not single
            if ( ! $args['single'] ) {
                return MetaHandler::addObjectMeta( $args['id'], $args['field_id'], $args['value'], false );
            }

            // Delete meta if we have an empty array
            if ( is_array( $args['value'] ) && empty( $args['value'] ) ) {
                return MetaHandler::deleteObjectMeta( $args['id'], $args['field_id'], $field->value );
            }

            // Update metadata
            return MetaHandler::updateObjectMeta( $args['id'], $args['field_id'], $args['value'] );

        }

        return $check;

    }

    public static function overrideMetaRemove( $check, $args, $field_args, $field ) {

        /**
         * @var Table $databaseTable
         */
        global $registeredTables, $databaseTable, $cmb2Override;

        if( $cmb2Override !== true ) {
            return $check;
        }

        $object = (array) Handler::getObject( $args['id'] );

        // If not is a main field and Table supports meta data, then try to remove the given value to the meta table
        // Note: Main fields are automatically managed by the save method on the EditView edit screen
        if( ! isset( $object[$args['field_id']] ) && in_array( 'meta', $databaseTable->getSupports() ) ) {

            return MetaHandler::deleteObjectMeta( $args['id'], $args['field_id'], $field->value );

        }

        return $check;

    }

}