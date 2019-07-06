<?php

namespace CustomTable;

class Init
{
    public static function init()
    {
        self::addAction();
        self::addHook();
        Hook::init();
        Handler::init();
//        Asset::init();
    }

    public static function addAction() {
        // Hook in to the first hook we have available and fire our `loader_load' hook.
        add_action( 'muplugins_loaded', 'CustomTable\\Init::addHook', 9 );
        add_action( 'plugins_loaded', 'CustomTable\\Init::addHook', 9 );
        add_action( 'after_setup_theme', 'CustomTable\\Init::addHook', 9 );
    }

    public static function addHook() {
        if ( ! did_action( 'custom_table_loader_load' ) ) {
            // Then fire our hook.
            do_action( 'custom_table_loader_load' );
        }
    }
}