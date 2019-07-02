<?php

namespace CustomTable;

class Utility
{
    public static function getListLink( $name ) {
        global $registeredTables;

        // Check if table exists
        if( ! isset( $registeredTables[$name] ) ) {
            return '';
        }

        // Check if table has list view
        if( ! isset( $registeredTables[$name]->views->list ) ) {
            return '';
        }

        return $registeredTables[$name]->views->list->get_link();
    }

    public static function getEditLink( $name, $objectId = 0 ) {
        global $registeredTables;

        // Check if table exists
        if( ! isset( $registeredTables[$name] ) || $objectId === 0 ) {
            return '';
        }

        // Check if table has edit view
        if( ! isset( $registeredTables[$name]->views->edit ) ) {
            return '';
        }

        // Check if user has edit permissions
        if( ! current_user_can( $registeredTables[$name]->cap->edit_item, $objectId ) ) {
            return '';
        }

        $primary_key = $registeredTables[$name]->db->primary_key;

        // Edit link + object ID
        return add_query_arg( array( $primary_key => $objectId ), $registeredTables[$name]->views->edit->get_link() );
    }

    public static function getDeleteLink( $name, $objectId = 0 ) {
        global $registeredTables;

        // Check if table exists
        if( ! isset( $registeredTables[$name] ) || $objectId === 0 ) {
            return '';
        }

        // Check if table has list view
        if( ! isset( $registeredTables[$name]->views->list ) ) {
            return '';
        }

        // Check if user has delete permissions
        if( ! current_user_can( $registeredTables[$name]->cap->delete_item, $objectId ) ) {
            return '';
        }

        $primary_key = $registeredTables[$name]->db->primary_key;

        // List link + object ID + action delete
        $url = $registeredTables[$name]->views->list->get_link();
        $url = add_query_arg( array( $primary_key => $objectId ), $url );
        $url = add_query_arg( array( 'custom-table-action' => 'delete' ), $url );

        return $url;
    }
}