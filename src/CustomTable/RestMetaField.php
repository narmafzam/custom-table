<?php


namespace CustomTable;

use \WP_REST_Meta_Fields;
use \WP_Error;

class RestMetaField extends WP_REST_Meta_Fields
{
    /**
     * Table name.
     *
     * @var string
     */
    protected $name;

    /**
     * Table Meta table object.
     *
     * @var Table $table
     */
    public $table;

    public function __construct( $name ) {
        $this->name  = $name;
        $this->table = Handler::getTableObject( $name );

    }

    /**
     * @return Table|null
     */
    public function getTable(): ? Table
    {
        return $this->table;
    }

    public function getName()
    {
        return $this->name;
    }

    protected function get_meta_type() {
        return $this->getTable()->getMeta()->name;
    }

    protected function get_meta_subtype() {
        return $this->getName();
    }

    public function get_rest_field_type() {
        return $this->getName();
    }

    public function get_value( $objectId, $request ) {
        $fields   = $this->get_registered_fields();
        $response = array();

        foreach ( $fields as $meta_key => $args ) {
            $name = $args['name'];
            $all_values = MetaHandler::getObjectMeta( $objectId, $meta_key, false );
            if ( $args['single'] ) {
                if ( empty( $all_values ) ) {
                    $value = $args['schema']['default'];
                } else {
                    $value = $all_values[0];
                }
                $value = $this->prepare_value_for_response( $value, $request, $args );
            } else {
                $value = array();
                foreach ( $all_values as $row ) {
                    $value[] = $this->prepare_value_for_response( $row, $request, $args );
                }
            }

            $response[ $name ] = $value;
        }

        return $response;
    }

    protected function update_meta_value( $objectId, $meta_key, $name, $value ) {
        $metaType = $this->get_meta_type();
        if ( ! current_user_can(  "edit_{$metaType}_meta", $objectId, $meta_key ) ) {
            return new WP_Error(
                'rest_cannot_update',
                /* translators: %s: custom field key */
                sprintf( __( 'Sorry, you are not allowed to edit the %s custom field.' ), $name ),
                array( 'key' => $name, 'status' => rest_authorization_required_code() )
            );
        }

        // Do the exact same check for a duplicate value as in update_metadata() to avoid update_metadata() returning false.
        $old_value = MetaHandler::getObjectMeta( $objectId, $meta_key );
        $subtype   = get_object_subtype( $metaType, $objectId );

        if ( 1 === count( $old_value ) ) {
            if ( (string) sanitize_meta( $meta_key, $value, $metaType, $subtype ) === $old_value[0] ) {
                return true;
            }
        }

        if ( ! MetaHandler::updateObjectMeta( $objectId, wp_slash( $meta_key ), wp_slash( $value ) ) ) {
            return new WP_Error(
                'rest_meta_database_error',
                __( 'Could not update meta value in database.' ),
                array( 'key' => $name, 'status' => WP_Http::INTERNAL_SERVER_ERROR )
            );
        }

        return true;
    }
}