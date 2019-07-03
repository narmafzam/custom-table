<?php

namespace CustomTable;

class TableMeta
{
    /**
     * Table key.
     *
     * @var string $name
     */
    public $name;

    /**
     * Table Meta database.
     *
     * @var Database $database
     */
    public $database;

    /**
     * @var Table $table
     */
    public $table;

    /**
     * TableMeta constructor.
     * @param Table $table
     */
    public function __construct( $table ) {

        $this->table = $table;
        $this->name  = $this->getTable()->getName() . '_meta';

        $this->database = new Database( $this->getName(), array(
            'version' => 1,
            'global' => $this->getTable()->getDatabase()->isGlobal(),
            'schema' => array(
                'meta_id' => array(
                    'type' => 'bigint',
                    'length' => 20,
                    'unsigned' => true,
                    'nullable' => false,
                    'auto_increment' => true,
                    'primary_key' => true
                ),
                $this->getTable()->getDatabase()->getPrimaryKey() => array(
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

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return Table
     */
    public function getTable(): Table
    {
        return $this->table;
    }

    /**
     * @return Database
     */
    public function getDatabase(): Database
    {
        return $this->database;
    }
}