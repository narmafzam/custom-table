<?php

namespace CustomTable;

class TableMeta
{
    /**
     * Table key.
     *
     * @since 1.0.0
     * @access public
     * @var string $name
     */
    public $name;

    /**
     * Table Meta database.
     * @var DatabaseSchema $db
     */
    public $db;

    /**
     * @var Table $table
     */
    public $table;

    /**
     * CT_Table_Meta constructor.
     * @param Table $table
     */
    public function __construct( $table ) {

        $this->table = $table;
        $this->name  = $this->table->name . '_meta';

        $this->db = new DatabaseSchema( $this->name, array(
            'version' => 1,
            'global' => $this->table->db->global,
            'schema' => array(
                'meta_id' => array(
                    'type' => 'bigint',
                    'length' => 20,
                    'unsigned' => true,
                    'nullable' => false,
                    'auto_increment' => true,
                    'primary_key' => true
                ),
                $this->table->db->primary_key => array(
                    'type' => 'bigint',
                    'length' => 20,
                    'unsigned' => true,
                    'nullable' => false,
                    'default' => 0,
                    'key' => true
                ),
                'meta_key' => array(
                    'type' => 'varchar',
                    'length' => 255,
                    'nullable' => true,
                    'default' => null,
                    'key' => true
                ),
                'meta_value' => array(
                    'type' => 'longtext',
                    'nullable' => true,
                    'default' => null
                ),
            )
        ) );

    }
}