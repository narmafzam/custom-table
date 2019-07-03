<?php

namespace CustomTable;

class DatabaseSchema
{
    public $fields = array();
    public $primaryKey = '';

    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    public function setPrimaryKey($primaryKey): void
    {
        $this->primaryKey = $primaryKey;
    }

    public function getFields()
    {
        return $this->fields;
    }

    public function setFields($fields): void
    {
        $this->fields = $fields;
    }

    public function addField($fieldId, $value): void
    {
        $this->fields[$fieldId] = $value;
    }

    public function __construct( $schema ) {

        if( gettype( $schema ) === 'string' ) {
            $schema = $this->stringSchemaToArray( $schema );
        }

        if( is_array( $schema ) ) {

            foreach( $schema as $field_id => $field_args ) {
                if(function_exists('wp_parse_args')) {
                    $field_args = wp_parse_args( $field_args, array(
                        'type'              => '',
                        'length'            => 0,
                        'decimals'          => 0,           // numeric fields
                        'format'            => '',          // time fields
                        'options'           => array(),     // ENUM and SET types
                        'nullable'          => false,
                        'unsigned'          => null,        // numeric field
                        'zerofill'          => null,        // numeric field
                        'binary'            => null,        // text fields
                        'charset'           => false,       // text fields
                        'collate'           => false,       // text fields
                        'default'           => false,
                        'auto_increment'    => false,
                        'unique'            => false,
                        'primary_key'       => false,
                        'key'               => false,
                    ) );

                    $this->addField($field_id, $field_args);
                }
            }

        }

    }

    public function fieldArrayToSchema( $field_id, $field_args ) {

        $schema = '';

        // Field name
        $schema .= $field_id . ' ';

        // Type definition
        $schema .= $field_args['type'];

        // Type definition args
        switch( strtoupper( $field_args['type'] ) ) {
            case 'ENUM':
            case 'SET':
                if( is_array( $field_args['options'] ) ) {
                    $schema .= '(' . implode( ',', $field_args['options'] ) . ')';
                } else {
                    $schema .= '(' . $field_args['options'] . ')';
                }
                break;
            case 'REAL':
            case 'DOUBLE':
            case 'FLOAT':
            case 'DECIMAL':
            case 'NUMERIC':
                if( $field_args['length'] !== 0 ) {
                    $schema .= '(' . $field_args['length'] . ',' . $field_args['decimals'] . ')';
                }
                break;
            case 'TIME':
            case 'TIMESTAMP':
            case 'DATETIME':
                if( $field_args['format'] !== '' ) {
                    $schema .= '(' . $field_args['format'] . ')';
                }
                break;
            default:
                if( $field_args['length'] !== 0 ) {
                    $schema .= '(' . $field_args['length'] . ')';
                }
                break;
        }

        $schema .= ' ';

        // Type specific definitions
        switch( strtoupper( $field_args['type'] ) ) {
            case 'TINYINT':
            case 'SMALLINT':
            case 'MEDIUMINT':
            case 'INT':
            case 'INTEGER':
            case 'BIGINT':
            case 'REAL':
            case 'DOUBLE':
            case 'FLOAT':
            case 'DECIMAL':
            case 'NUMERIC':
                // UNSIGNED definition
                if( $field_args['unsigned'] !== null ) {
                    if( $field_args['unsigned'] ) {
                        $schema .= 'UNSIGNED ';
                    } else {
                        $schema .= 'SIGNED ';
                    }
                }

                // ZEROFILL definition
                if( $field_args['zerofill'] !== null && $field_args['zerofill'] ) {
                    $schema .= 'ZEROFILL ';
                }
                break;
            case 'CHAR':
            case 'VARCHAR':
            case 'TINYTEXT':
            case 'TEXT':
            case 'MEDIUMTEXT':
            case 'LONGTEXT':
            case 'ENUM':
            case 'SET':
                // BINARY definition
                if( $field_args['binary'] !== null && $field_args['binary']) {
                    $schema .= 'BINARY ';
                }

                // CHARACTER SET definition
                if( $field_args['charset'] !== false ) {
                    $schema .= 'CHARACTER SET ' . $field_args['charset'] . ' ';
                }

                // COLLATE definition
                if( $field_args['collate'] !== false ) {
                    $schema .= 'COLLATE ' . $field_args['collate'] . ' ';
                }
                break;
        }


        // NULL definition
        if( $field_args['nullable'] ) {
            $schema .= 'NULL ';
        } else {
            $schema .= 'NOT NULL ';
        }

        // DEFAULT definition
        if( $field_args['default'] !== false ) {

            if( gettype( $field_args['default'] ) === 'string' ) {
                $field_args['default'] = "'" . $field_args['default'] . "'";
            }

            if( $field_args['default'] === null ) {
                $field_args['default'] = 'NULL';
            }

            $schema .= 'DEFAULT ' . $field_args['default'] . ' ';
        }

        // UNIQUE definition
        if( $field_args['unique'] ) {
            $schema .= 'UNIQUE ';
        }

        // AUTO_INCREMENT definition
        if( $field_args['auto_increment'] ) {
            $schema .= 'AUTO_INCREMENT ';
        }

        // PRIMARY KEY definition
        if( $field_args['primary_key'] ) {
            $this->setPrimaryKey($field_id);
        }

        // KEY definition
        if( $field_args['key'] ) {

            /*
             * Indexes have a maximum size of 767 bytes. WordPress 4.2 was moved to utf8mb4, which uses 4 bytes per character.
             * This means that an index which used to have room for floor(767/3) = 255 characters, now only has room for floor(767/4) = 191 characters.
             */
            $max_index_length = 191;

            if( $field_args['length'] > $max_index_length ) {
                $keys[] = 'KEY ' . $field_id . '(' . $field_id . '(' . $max_index_length . '))';
            } else {
                $keys[] = 'KEY ' . $field_id . '(' . $field_id . ')';
            }
        }

        return $schema;

    }

    public function stringSchemaToArray( $schema ) {

        $schema_array = explode( ',', trim( $schema ) );
        $new_schema = array();

        foreach( $schema_array as $field_def ) {

            $field_id = '';
            $field_args = array();

            $field_def_parts = explode( ' ', trim( $field_def ) );

            foreach( $field_def_parts as $index => $field_def_part ) {


                if( $index === 0 ) {

                    // Field id at index 0
                    if( $field_def_part !== 'PRIMARY' && $field_def_part !== 'KEY' ) {
                        $field_id = $field_def_part;
                        continue;
                    }

                    // PRIMARY KEY at index 0
                    if( $field_def_part === 'PRIMARY' ) {
                        if( isset( $field_def_parts[$index+1] ) && strtoupper( $field_def_parts[$index+1] ) === 'KEY' && isset( $field_def_parts[$index+2] ) ) {
                            $primary_key_field = str_replace( array( '(', ')' ), '', $field_def_parts[$index+2] );

                            if( isset( $this->getFields()[$primary_key_field] ) ) {
                                $this->addField($primary_key_field, ['primary_key' => true]);
                                continue;
                            }
                        }
                    }
                }

                // NOT NULL definition
                if( strtoupper( $field_def_part ) === 'NOT' ) {
                    if( isset( $field_def_parts[$index+1] ) && strtoupper( $field_def_parts[$index+1] ) === 'NULL' ) {
                        $field_args['nullable'] = false;
                        continue;
                    }
                }

                // NULL definition
                if( strtoupper( $field_def_part ) === 'NULL' ) {
                    if( isset( $field_def_parts[$index-1] ) && strtoupper( $field_def_parts[$index-1] ) !== 'NOT' ) {
                        $field_args['nullable'] = true;
                        continue;
                    }
                }

                // UNSIGNED definition
                if( strtoupper( $field_def_part ) === 'UNSIGNED' ) {
                    $field_args['unsigned'] = true;
                    continue;
                }

                // SIGNED definition
                if( strtoupper( $field_def_part ) === 'SIGNED' ) {
                    $field_args['unsigned'] = false;
                    continue;
                }

                // ZEROFILL definition
                if( strtoupper( $field_def_part ) === 'ZEROFILL' ) {
                    $field_args['zerofill'] = true;
                    continue;
                }

                // BINARY definition
                if( strtoupper( $field_def_part ) === 'BINARY' ) {
                    $field_args['binary'] = true;
                    continue;
                }

                // CHARACTER SET definition
                if( strtoupper( $field_def_part ) === 'CHARACTER' ) {
                    if( isset( $field_def_parts[$index+1] ) && strtoupper( $field_def_parts[$index+1] ) === 'SET' ) {
                        $field_args['charset'] = $field_def_parts[$index+2];
                        continue;
                    }
                }

                // COLLATE definition
                if( strtoupper( $field_def_part ) === 'COLLATE' ) {
                    if( isset( $field_def_parts[$index+1] ) ) {
                        $field_args['collate'] = $field_def_parts[$index+1];
                        continue;
                    }
                }

                // DEFAULT definition
                if( strtoupper( $field_def_part ) === 'DEFAULT' ) {
                    if( isset( $field_def_parts[$index+1] ) ) {
                        $field_args['default'] = $field_def_parts[$index+1];
                        continue;
                    }
                }

                // UNIQUE definition
                if( strtoupper( $field_def_part ) === 'UNIQUE' ) {
                    $field_args['unique'] = true;
                    continue;
                }

                // AUTO_INCREMENT definition
                if( strtoupper( $field_def_part ) === 'AUTO_INCREMENT' ) {
                    $field_args['auto_increment'] = true;
                    continue;
                }

                // PRIMARY KEY definition
                if( strtoupper( $field_def_part ) === 'PRIMARY' ) {
                    if( isset( $field_def_parts[$index+1] ) && strtoupper( $field_def_parts[$index+1] ) === 'KEY' ) {
                        $field_args['primary_key'] = true;
                        continue;
                    }
                }

                $type_parts = explode( '(', $field_def_part );

                // Possible field type
                if( in_array( strtoupper( $field_def_part ), $this->allowedFieldTypes() ) ) {
                    $field_args['type'] = strtoupper( $field_def_part );
                    continue;
                } else if( isset( $type_parts[0] ) && in_array( strtoupper( $type_parts[0] ), $this->allowedFieldTypes() ) ) {
                    $field_args['type'] = strtoupper( $type_parts[0] );

                    if( isset( $type_parts[1] ) ) {
                        $type_def = explode( ',',  str_replace(')', '', $type_parts[1] ) );

                        switch( $field_args['type'] ) {
                            case 'ENUM':
                            case 'SET':
                                $field_args['options'] = $type_def;
                                break;
                            case 'REAL':
                            case 'DOUBLE':
                            case 'FLOAT':
                            case 'DECIMAL':
                            case 'NUMERIC':
                                $field_args['length'] = $type_def[0];

                                if( isset( $type_def[1] ) ) {
                                    $field_args['decimals'] = $type_def[1];
                                }
                                break;
                            case 'TIME':
                            case 'TIMESTAMP':
                            case 'DATETIME':
                                $field_args['format'] = $type_def[0];
                                break;
                            default:
                                $field_args['length'] = $type_def[0];
                                break;
                        }
                    }
                }
            }

            if( ! empty( $field_id ) && ! empty( $field_args ) ) {
                $new_schema[$field_id] = $field_args;
            }
        }

        return $new_schema;

    }

    public function allowedFieldTypes() {
        return array(
            'BIT',
            'TINYINT',
            'SMALLINT',
            'MEDIUMINT',
            'INT',
            'INTEGER',
            'BIGINT',
            'REAL',
            'DOUBLE',
            'FLOAT',
            'DECIMAL',
            'NUMERIC',
            'DATE',
            'TIME',
            'TIMESTAMP',
            'DATETIME',
            'YEAR',
            'CHAR',
            'VARCHAR',
            'BINARY',
            'VARBINARY',
            'TINYBLOB',
            'BLOB',
            'MEDIUMBLOB',
            'LONGBLOB',
            'TINYTEXT',
            'TEXT',
            'JSON'
        );
    }

    public function __toString() {

        $fields_def = array();
        $keys = array();

        foreach( $this->getFields() as $field_id => $field_args ) {


            $fields_def[] = $this->fieldArrayToSchema( $field_id, $field_args );

        }

        // Setup PRIMARY KEY definition
        $sql = implode( ', ', $fields_def ) . ', '
            . 'PRIMARY KEY  (' . $this->getPrimaryKey() . ')'; // Add two spaces to avoid issues

        // Setup KEY definition
        if( ! empty( $keys ) ) {
            $sql .= ', ' . implode( ', ', $keys );
        }

        return $sql;
    }
}