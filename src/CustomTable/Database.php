<?php

namespace CustomTable;

class Database
{
    protected $name                 = '';
    protected $global               = false;
    protected $schema               = '';
    protected $version              = 0;
    protected $database             = false;
    protected $tableName            = '';
    protected $primaryKey           = '';
    protected $databaseVersion      = 0;
    protected $charsetCollation     = '';
    protected $databaseVersionKey   = '';

    public function __construct( $name, $args ) {

        $this->name         = $name;
        $this->primaryKey   = ( isset( $args['primary_key'] ) ) ? $args['primary_key'] : '';
        $this->version      = ( isset( $args['version'] ) ) ? $args['version'] : 1;
        $this->global       = ( isset( $args['global'] ) && $args['global'] === true ) ? true : false;
        $this->schema       = ( isset( $args['schema'] ) ) ? new DataBaseSchema( $args['schema'] ) : '';

        // If not primary key given, then look at out schema
        if( $this->getSchema() && ! $this->getPrimaryKey() ) {

            foreach( $this->schema->fields as $field_id => $field_args ) {

                if( $field_args['primary_key'] === true ) {

                    $this->setPrimaryKey($field_id);
                    break;

                }

            }

        }

        // Bail if no database object or table name
        if ( empty( $GLOBALS['wpdb'] ) || empty( $this->getName() ) ) {
            return;
        }

        // Setup the database
        $this->setupDatabase();

        // Get the version of he table currently in the database
        $this->setupDatabaseVersion();

        // Add the table to the object
        $this->setWpDatabaseTables();

        // Setup the database schema
        $this->setupSchema();

        // Add hooks to WordPress actions
        $this->addHooks();
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

    public function setPrimaryKey($primaryKey): void
    {
        $this->primaryKey = $primaryKey;
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

    public function setDatabaseVersion($databaseVersion): void
    {
        $this->databaseVersion = $databaseVersion;
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

    public function maybeUpgrade() {

        // Bail if no upgrade needed
        if ( version_compare( (int) $this->getDatabaseVersion(), (int) $this->getVersion(), '>=' ) && $this->exists() ) {
            return;
        }

        // Include file with dbDelta() for create/upgrade usages
        if ( ! function_exists( 'dbDelta' ) ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        // Bail if global and upgrading global tables is not allowed
        if ( ( true === $this->isGlobal() ) && ! wp_should_upgrade_global_tables() ) {
            return;
        }

        // Create or upgrade?
        $this->exists()
            ? $this->upgrade()
            : $this->create();

        // Set the database version
        if ( $this->exists() ) {
            $this->setupDatabaseVersion();
        }
    }

    public function get( $id ) {

        return $this->getDatabase()->get_row( $this->getDatabase()->prepare( "SELECT * FROM {$this->getTableName()} WHERE {$this->getPrimaryKey()} = %s", $id ) );

    }

    public function query( $args = array(), $output = OBJECT ) {

        return $this->getDatabase()->get_results( "SELECT * FROM {$this->getTableName()}" );

    }

    public function insert( $data ) {

        if( $this->getDatabase()->insert( $this->getTableName(), $data ) ) {
            return $this->getDatabase()->insert_id;
        }

        return false;

    }

    public function update( $data, $where ) {

        $table_data = array();
        $schema_fields = array_keys( $this->getSchema()->getFields());

        // Filter extra data to prevent insert data outside table schema
        foreach( $data as $field => $value ) {
            if( ! in_array( $field, $schema_fields ) ) {
                continue;
            }

            $table_data[$field] = $value;
        }

        return $this->getDatabase()->update( $this->getTableName(), $table_data, $where );

    }

    public function delete( $value ) {

        return $this->getDatabase()->query( $this->getDatabase()->prepare( "DELETE FROM {$this->getTableName()} WHERE {$this->getPrimaryKey()} = %s", $value ) );

    }

    protected function setupSchema()
    {

    }

    private function setWpDatabaseTables() {

        // Global
        if ( true === $this->isGlobal() ) {
            $prefix = $this->getDatabase()->get_blog_prefix( 0 );
            $this->getDatabase()->{$this->getName()} = "{$prefix}{$this->getName()}";
            $this->getDatabase()->ms_global_tables[] = $this->getName();

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

    private function setupDatabase()
    {
        // Setup database
        $this->database = $GLOBALS['wpdb'];
        $this->setName(sanitize_key( $this->getName() ));

        // Maybe create database key
        if ( empty( $this->getDatabaseVersionKey() ) ) {
            $this->setDatabaseVersionKey("wpdb_{$this->getName()}_version");
        }
    }

    private function setupDatabaseVersion()
    {
        $this->setDatabaseVersion(( true === $this->isGlobal() )
            ? get_network_option( null, $this->getDatabaseVersionKey(), false )
            :         get_option(       $this->getDatabaseVersionKey(), false ));
    }

    private function addHooks() {

        // Activation hook
        register_activation_hook( __FILE__, array( $this, 'maybeUpgrade' ) );

        // Add table to the global database object
        add_action( 'switch_blog', array( $this, 'switchBlog'   ) );
        add_action( 'admin_init',  array( $this, 'maybeUpgrade' ) );
    }

    private function create() {

        // Run CREATE TABLE query
        $created = dbDelta( array( "CREATE TABLE {$this->getTableName()} ( {$this->getSchema()} ) {$this->getCharsetCollation()};" ) );

        // Was anything created?
        return ! empty( $created );
    }

    private function exists() {

        $table_exist = $this->getDatabase()->get_var( $this->getDatabase()->prepare(
            "SHOW TABLES LIKE %s",
            $this->getDatabase()->esc_like( $this->getTableName() )
        ) );

        return ! empty( $table_exist );

    }
}