<?php

namespace CustomTable;

class CustomTable
{
    /**
     * @var CustomTable
     */
    private static $instance;

    /**
     * @return object self::$instance
     */
    public static function getInstance()
    {
        if (empty(self::$instance)) {
            self::$instance = new CustomTable();
            self::addHook();
        }

        return self::$instance;
    }

    public static function init()
    {
        self::addAction();
    }

    public static function addAction()
    {
        Handler::populateRoles();
        // Trigger CT init hook
        do_action( 'custom_table_init' );
        if( is_admin() ) {
            // Trigger CT admin init hook
            do_action( 'custom_table_admin_init' );
        }
    }

    public static function addHook()
    {
        add_action( 'init', 'CustomTable\\CustomTable::init', 1 );
    }


    public function loadTextDomain() {
        // Set filter for language directory
        $lang_dir = CUSTOM_TABLE_DIR . '/languages/';
        $lang_dir = apply_filters( 'custom_table_languages_directory', $lang_dir );
        // Traditional WordPress plugin locale filter
        $locale = apply_filters( 'plugin_locale', get_locale(), 'ct' );
        $mofile = sprintf( '%1$s-%2$s.mo', 'ct', $locale );
        // Setup paths to current locale file
        $mofile_local   = $lang_dir . $mofile;
        $mofile_global  = WP_LANG_DIR . '/custom_table/' . $mofile;
        if( file_exists( $mofile_global ) ) {
            // Look in global /wp-content/languages/ct/ folder
            load_textdomain( 'custom_table', $mofile_global );
        } elseif( file_exists( $mofile_local ) ) {
            // Look in local /wp-content/plugins/ct/languages/ folder
            load_textdomain( 'custom_table', $mofile_local );
        } else {
            // Load the default language files
            load_plugin_textdomain( 'custom_table', false, $lang_dir );
        }
    }
}