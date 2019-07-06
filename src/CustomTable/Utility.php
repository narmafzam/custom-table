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

        return $registeredTables[$name]->views->list->getLink();
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

        $table = $registeredTables[$name];

        if ($table instanceof Table) {
            // Check if user has edit permissions
            if( ! current_user_can( $table->getCap()->edit_item, $objectId ) ) {
                return '';
            }

            $primaryKey = $table->getDatabase()->getPrimaryKey();

            if (isset($table->getViews()->edit) && !empty($table->getViews()->edit)) {

                // Edit link + object ID
                return add_query_arg( array( $primaryKey => $objectId ), $table->getViews()->edit->getLink() );
            }
        }
        return null;
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

        $table = $registeredTables[$name];
        if ($table instanceof Table) {
            // Check if user has delete permissions
            if( ! current_user_can( $table->getCap()->delete_item, $objectId ) ) {
                return '';
            }
            $primaryKey = $table->getDatabase()->getPrimaryKey();
            // List link + object ID + action delete
            if (isset($table->getViews()->list) && !empty($table->getViews()->list)) {
                $url = $table->getViews()->list->getLink();
                $url = add_query_arg( array( $primaryKey => $objectId ), $url );
                $url = add_query_arg( array( 'custom-table-action' => 'delete' ), $url );
                return $url;
            }
        }

        return null;
    }

    public static function generateCamelCase($str){

        $str = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $str))));
        return $str;
    }

    public static function getPathUrl($path, $protocol = 'http://')
    {
        if (defined('WP_SITEURL')) {
            return WP_SITEURL . str_replace($_SERVER['DOCUMENT_ROOT'], '', realpath($path));
        } else {
            return $protocol . $_SERVER['HTTP_HOST'] . str_replace($_SERVER['DOCUMENT_ROOT'], '', realpath($path));
        }
    }
}