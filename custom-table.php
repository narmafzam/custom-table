<?php

use CustomTable\CustomTable;
use CustomTable\Init;

define( 'CUSTOM_TABLE_VER', '1.0.0');
define( 'CUSTOM_TABLE_FILE', __FILE__ );
define( 'CUSTOM_TABLE_DIR', plugin_dir_path( __FILE__ ) );
define( 'CUSTOM_TABLE_URL', plugin_dir_url( __FILE__ ) );
define( 'CUSTOM_TABLE_DEBUG', false );
define( 'CUSTOM_TABLE_LOADER_PRIORITY', 99999 - absint( str_replace( '.', '', CUSTOM_TABLE_VER) ) );
define( 'CUSTOM_TABLE_LOADED', CUSTOM_TABLE_LOADER_PRIORITY );

function getCustomTableInstance()
{
    return CustomTable::getInstance();
}

Init::init();
getCustomTableInstance();