<?php

namespace CustomTable;

class Database
{
    protected $name = '';

    protected $primaryKey = '';
    protected $version = 0;
    protected $global = false;
    protected $databaseVersionKey = '';
    protected $databaseVersion = 0;
    protected $tableName = '';
    protected $schema = '';
    protected $charsetCollation = '';
    protected $database = false;

    public function __construct( $name, $args ) {

        $this->name = $name;

        $this->primary_key = ( isset( $args['primary_key'] ) ) ? $args['primary_key'] : '';

        $this->version = ( isset( $args['version'] ) ) ? $args['version'] : 1;

        $this->global = ( isset( $args['global'] ) && $args['global'] === true ) ? true : false;

        $this->schema = ( isset( $args['schema'] ) ) ? new DataBaseSchema( $args['schema'] ) : '';

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
        $this->setDatabase();

        // Get the version of he table currently in the database
        $this->getDatabaseVersion();

        // Add the table to the object
        $this->setWpDatabaseTables();

        // Setup the database schema
        $this->set_schema();

        // Add hooks to WordPress actions
        $this->add_hooks();
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
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

    public function getDatabaseVersionKey()
    {
        return $this->databaseVersionKey;
    }

    public function setDatabaseVersionKey($databaseVersionKey): void
    {
        $this->databaseVersionKey = $databaseVersionKey;
    }

    public function getDatabaseVersion()
    {
        return $this->databaseVersion;
    }

    public function setDatabaseVersion(): void
    {
        $this->databaseVersion = ( true === $this->isGlobal() )
            ? get_network_option( null, $this->getDatabaseVersionKey(), false )
            :         get_option(       $this->getDatabaseVersionKey(), false );
    }

    public function getTableName()
    {
        return $this->tableName;
    }

    public function setTableName($tableName): void
    {
        $this->tableName = $tableName;
    }

    public function getSchema(): ? DataBaseSchema
    {
        return $this->schema;
    }

    public function getCharsetCollation()
    {
        return $this->charsetCollation;
    }

    public function setCharsetCollation($charsetCollation): void
    {
        $this->charsetCollation = $charsetCollation;
    }

    public function getDatabase()
    {
        return $this->database;
    }

    private function setDatabase(): void
    {
        // Setup database
        $this->database = $GLOBALS['wpdb'];
        $this->setName(sanitize_key( $this->getName() ));

        // Maybe create database key
        if ( empty( $this->getDatabaseVersionKey() ) ) {
            $this->setDatabaseVersionKey("wpdb_{$this->getName()}_version");
        }
    }

    protected function upgrade() {
        $schemaUpdater = new DataBaseSchemaUpdater( $this );
        $schemaUpdater->run();
    }

    public function switchBlog( $site_id = 0 ) {

        // Update DB version based on the current site
        if ( false === $this->global ) {
            $this->databaseVersion = get_blog_option( $site_id, $this->getDatabaseVersionKey(), false );
        }

        // Update table references based on th current site
        $this->setWpDatabaseTables();
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

    private function setWpDatabaseTables() {

        // Global
        if ( true === $this->isGlobal() ) {
            $prefix = $this->getDatabase()->get_blog_prefix( 0 );
            $this->getDatabase()->{$this->getName()} = "{$prefix}{$this->name}";
            $this->getDatabase()->ms_global_tables[] = $this->name;

            // Site
        } else {
            $prefix = $this->getDatabase()->get_blog_prefix( null );
            $this->getDatabase()->{$this->getName()} = "{$prefix}{$this->getName()}";
            $this->getDatabase()->tables[] = $this->getName();
        }

        // Set the table name locally
        $this->setTableName($this->getDatabase()->{$this->getName()});

        // Charset
        if ( ! empty( $this->getDatabase()->charset ) ) {
            $this->setCharsetCollation("DEFAULT CHARACTER SET {$this->getDatabase()->charset}");
        }

        // Collation
        if ( ! empty( $this->getDatabase()->collate ) ) {
            $this->setCharsetCollation($this->getCharsetCollation() . " COLLATE {$this->getDatabase()->collate}") ;
        }
    }

    private function

    private function addHooks() {

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