<?php

namespace CustomTable;

use CT_DataBase_Schema;
use CT_DataBase_Schema_Updater;

class CustomTable
{
    protected $name = '';

    protected $primaryKey = '';

    protected $version = 0;

    protected $global = false;

    protected $dbVersionKey = '';

    protected $dbVersion = 0;

    protected $tableName = '';

    protected $schema = '';

    protected $charsetCollation = '';

    protected $db = false;

    public function __construct( $name, $args ) {

        $this->name = $name;

        $this->primary_key = ( isset( $args['primary_key'] ) ) ? $args['primary_key'] : '';

        $this->version = ( isset( $args['version'] ) ) ? $args['version'] : 1;

        $this->global = ( isset( $args['global'] ) && $args['global'] === true ) ? true : false;

        $this->schema = ( isset( $args['schema'] ) ) ? new CT_DataBase_Schema( $args['schema'] ) : '';

        // If not primary key given, then look at out schema
        if( $this->schema && ! $this->primary_key ) {

            foreach( $this->schema->fields as $field_id => $field_args ) {

                if( $field_args['primary_key'] === true ) {

                    $this->primary_key = $field_id;
                    break;

                }

            }

        }

        // Bail if no database object or table name
        if ( empty( $GLOBALS['wpdb'] ) || empty( $this->name ) ) {
            return;
        }

        // Setup the database
        $this->set_db();

        // Get the version of he table currently in the database
        $this->get_db_version();

        // Add the table to the object
        $this->set_wpdb_tables();

        // Setup the database schema
        $this->set_schema();

        // Add hooks to WordPress actions
        $this->add_hooks();
    }

    public function getName()
    {
        return $this->name;
    }

    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    public function getVersion()
    {
        return $this->version;
    }

    public function isGlobal()
    {
        return $this->global;
    }

    public function getDbVersionKey()
    {
        return $this->dbVersionKey;
    }

    public function getDbVersion()
    {
        return $this->dbVersion;
    }

    public function getTableName()
    {
        return $this->tableName;
    }

    public function getSchema()
    {
        return $this->schema;
    }

    public function getCharsetCollation()
    {
        return $this->charsetCollation;
    }

    public function getDb()
    {
        return $this->db;
    }

    protected function upgrade() {

        $schema_updater = new CT_DataBase_Schema_Updater( $this );

        $schema_updater->run();

    }

    public function switch_blog( $site_id = 0 ) {

        // Update DB version based on the current site
        if ( false === $this->global ) {
            $this->db_version = get_blog_option( $site_id, $this->db_version_key, false );
        }

        // Update table references based on th current site
        $this->set_wpdb_tables();
    }

    public function maybe_upgrade() {

        // Bail if no upgrade needed
        if ( version_compare( (int) $this->db_version, (int) $this->version, '>=' ) && $this->exists() ) {
            return;
        }

        // Include file with dbDelta() for create/upgrade usages
        if ( ! function_exists( 'dbDelta' ) ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        // Bail if global and upgrading global tables is not allowed
        if ( ( true === $this->global ) && ! wp_should_upgrade_global_tables() ) {
            return;
        }

        // Create or upgrade?
        $this->exists()
            ? $this->upgrade()
            : $this->create();

        // Set the database version
        if ( $this->exists() ) {
            $this->set_db_version();
        }
    }

    public function get( $id ) {

        return $this->db->get_row( $this->db->prepare( "SELECT * FROM {$this->table_name} WHERE {$this->primary_key} = %s", $id ) );

    }

    public function query( $args = array(), $output = OBJECT ) {

        return $this->db->get_results( "SELECT * FROM {$this->table_name}" );

    }

    public function insert( $data ) {

        if( $this->db->insert( $this->table_name, $data ) ) {
            return $this->db->insert_id;
        }

        return false;

    }

    public function update( $data, $where ) {

        $table_data = array();
        $schema_fields = array_keys( $this->schema->fields );

        // Filter extra data to prevent insert data outside table schema
        foreach( $data as $field => $value ) {
            if( ! in_array( $field, $schema_fields ) ) {
                continue;
            }

            $table_data[$field] = $value;
        }

        return $this->db->update( $this->table_name, $table_data, $where );

    }

    public function delete( $value ) {

        return $this->db->query( $this->db->prepare( "DELETE FROM {$this->table_name} WHERE {$this->primary_key} = %s", $value ) );

    }

    private function set_db() {

        // Setup database
        $this->db   = $GLOBALS['wpdb'];
        $this->name = sanitize_key( $this->name );

        // Maybe create database key
        if ( empty( $this->db_version_key ) ) {
            $this->db_version_key = "wpdb_{$this->name}_version";
        }
    }

    private function set_wpdb_tables() {

        // Global
        if ( true === $this->global ) {
            $prefix                       = $this->db->get_blog_prefix( 0 );
            $this->db->{$this->name}      = "{$prefix}{$this->name}";
            $this->db->ms_global_tables[] = $this->name;

            // Site
        } else {
            $prefix                  = $this->db->get_blog_prefix( null );
            $this->db->{$this->name} = "{$prefix}{$this->name}";
            $this->db->tables[]      = $this->name;
        }

        // Set the table name locally
        $this->table_name = $this->db->{$this->name};

        // Charset
        if ( ! empty( $this->db->charset ) ) {
            $this->charset_collation = "DEFAULT CHARACTER SET {$this->db->charset}";
        }

        // Collation
        if ( ! empty( $this->db->collate ) ) {
            $this->charset_collation .= " COLLATE {$this->db->collate}";
        }
    }

    private function set_db_version() {

        // Set the class version
        $this->db_version = $this->version;

        // Update the DB version
        ( true === $this->global )
            ? update_network_option( null, $this->db_version_key, $this->version )
            :         update_option(       $this->db_version_key, $this->version );
    }

    private function get_db_version() {
        $this->db_version = ( true === $this->global )
            ? get_network_option( null, $this->db_version_key, false )
            :         get_option(       $this->db_version_key, false );
    }

    private function add_hooks() {

        // Activation hook
        register_activation_hook( __FILE__, array( $this, 'maybe_upgrade' ) );

        // Add table to the global database object
        add_action( 'switch_blog', array( $this, 'switch_blog'   ) );
        add_action( 'admin_init',  array( $this, 'maybe_upgrade' ) );
    }

    private function create() {

        // Run CREATE TABLE query
        $created = dbDelta( array( "CREATE TABLE {$this->table_name} ( {$this->schema} ) {$this->charset_collation};" ) );

        // Was anything created?
        return ! empty( $created );
    }

    private function exists() {

        $table_exist = $this->db->get_var( $this->db->prepare(
            "SHOW TABLES LIKE %s",
            $this->db->esc_like( $this->table_name )
        ) );

        return ! empty( $table_exist );

    }
}