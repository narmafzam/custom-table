<?php

namespace CustomTable;

class Asset
{
    public static function init()
    {
        self::addAction();
    }

    public static function addAction()
    {
        do_action('admin_enqueue_scripts', 'CustomTable\\Asset::enqueue');
    }

    public static function enqueue()
    {
        wp_enqueue_script( 'custom-table-ajax-list-table-js', Utility::getPathUrl(__DIR__ . '/../../asset/js/list-table.js'), array( 'jquery' ), CUSTOM_TABLE_VER, true );
        wp_enqueue_style( 'custom-table-ajax-list-table-css', Utility::getPathUrl(__DIR__ . '/../../asset/css/list-table.css'), array(), CUSTOM_TABLE_VER );
    }
}