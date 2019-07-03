<?php

namespace CustomTable;

use stdClass;
use \WP_REST_Controller;
use \WP_Error;

class RestController extends WP_REST_Controller
{
    protected $name;

    /**
     * @var Table
     */
    public $table;

    /**
     * @var RestMetaField
     */
    protected $meta;

    public function __construct( $name ) {
        $this->name = $name;
        $this->namespace = 'wp/v2';
        $this->table = Handler::getTableObject( $name );
        $this->rest_base = ! empty( $this->getTable()->getRestBase() ) ? $this->getTable()->getRestBase() : $this->getName();

        if( in_array( 'meta', $this->getTable()->getSupports() ) ) {
            $this->meta = new RestMetaField( $name );
        }
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

    /**
     * @return RestMetaField|null
     */
    public function getMeta(): ? RestMetaField
    {
        return $this->meta;
    }

    public function register_routes() {

        register_rest_route( $this->namespace, '/' . $this->rest_base, array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_items' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
                'args'                => $this->get_collection_params(),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_item' ),
                'permission_callback' => array( $this, 'create_item_permissions_check' ),
                'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
            ),
            'schema' => array( $this, 'get_public_item_schema' ),
        ) );

        register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
            'args' => array(
                'id' => array(
                    'description' => __( 'Unique identifier for the object.' ),
                    'type'        => 'integer',
                ),
            ),
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_item' ),
                'permission_callback' => array( $this, 'get_item_permissions_check' ),
                'args'                => array(
                    'context'  => $this->get_context_param( array( 'default' => 'view' ) ),
                ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( $this, 'update_item' ),
                'permission_callback' => array( $this, 'update_item_permissions_check' ),
                'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::EDITABLE ),
            ),
            array(
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => array( $this, 'delete_item' ),
                'permission_callback' => array( $this, 'delete_item_permissions_check' ),
                'args'                => array(
                    // TODO: There is no support for trash functionality, so let's remove it temporally
                    /*'force' => array(
                        'type'        => 'boolean',
                        'default'     => false,
                        'description' => __( 'Whether to bypass trash and force deletion.' ),
                    ),*/
                ),
            ),
            'schema' => array( $this, 'get_public_item_schema' ),
        ) );
    }

    public function get_items_permissions_check( $request ) {

        if ( 'edit' === $request['context'] && ! current_user_can( $this->getTable()->getCap()->edit_items ) ) {
            return new WP_Error( 'rest_forbidden_context', __( 'Sorry, you are not allowed to edit items of this type.' ), array( 'status' => rest_authorization_required_code() ) );
        }

        return true;
    }

    public function get_items( $request ) {

        // Ensure a search string is set in case the orderby is set to 'relevance'.
        if ( ! empty( $request['orderby'] ) && 'relevance' === $request['orderby'] && empty( $request['search'] ) ) {
            return new WP_Error( 'rest_no_search_term_defined', __( 'You need to define a search term to order by relevance.' ), array( 'status' => 400 ) );
        }

        // Ensure an include parameter is set in case the orderby is set to 'include'.
        if ( ! empty( $request['orderby'] ) && 'include' === $request['orderby'] && empty( $request['include'] ) ) {
            return new WP_Error( 'rest_orderby_include_missing_include', __( 'You need to define an include parameter to order by include.' ), array( 'status' => 400 ) );
        }

        // Retrieve the list of registered collection query parameters.
        $registered = $this->get_collection_params();
        $args = array();

        $databaseTable = Handler::setupTable( $this->getName() );

        $parameter_mappings = array(
            // WP_REST_Controller fields
            'page'           => 'paged',
            'search'         => 's',
            // RestController fields
            'offset'         => 'offset',
            'order'          => 'order',
            'orderby'        => 'orderby',
        );

        $parameter_mappings = apply_filters( "custom_table_rest_{$this->getName()}_parameter_mappings", $parameter_mappings, $databaseTable, $request );

        foreach ( $parameter_mappings as $api_param => $wp_param ) {
            if ( isset( $registered[ $api_param ], $request[ $api_param ] ) ) {
                $args[ $wp_param ] = $request[ $api_param ];
            }
        }

        // Ensure our per_page parameter overrides any provided items_per_page filter.
        if ( isset( $registered['per_page'] ) ) {
            $args['items_per_page'] = $request['per_page'];
        }

        $args = apply_filters( "custom_table_rest_{$this->getName()}_query", $args, $request );
        $query_args = $this->prepare_items_query( $args, $request );

        $query  = new Query();
        $query_result = $query->query( $query_args );

        $items = array();

        foreach ( $query_result as $item ) {
            if ( ! $this->check_read_permission( $item ) ) {
                continue;
            }

            $data    = $this->prepare_item_for_response( $item, $request );
            $items[] = $this->prepare_response_for_collection( $data );
        }

        $page = (int) $query_args['paged'];
        $total_items = $query->getFoundResults();

        if ( $total_items < 1 ) {
            // Out-of-bounds, run the query again without LIMIT for total count.
            unset( $query_args['paged'] );

            $countQuery = new Query();
            $countQuery->query( $query_args );
            $total_items = $countQuery->getFoundResults();
        }

        $max_pages = ceil( $total_items / (int) $query->getQueryVars()['items_per_page'] );

        if ( $page > $max_pages && $total_items > 0 ) {
            return new WP_Error( 'rest_item_invalid_page_number', __( 'The page number requested is larger than the number of pages available.' ), array( 'status' => 400 ) );
        }

        $response = rest_ensure_response( $items );

        $response->header( 'X-WP-Total', (int) $total_items );
        $response->header( 'X-WP-TotalPages', (int) $max_pages );

        $request_params = $request->get_query_params();
        $base = add_query_arg( $request_params, rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ) );

        if ( $page > 1 ) {
            $prev_page = $page - 1;

            if ( $prev_page > $max_pages ) {
                $prev_page = $max_pages;
            }

            $prev_link = add_query_arg( 'page', $prev_page, $base );
            $response->link_header( 'prev', $prev_link );
        }
        if ( $max_pages > $page ) {
            $next_page = $page + 1;
            $next_link = add_query_arg( 'page', $next_page, $base );

            $response->link_header( 'next', $next_link );
        }

        Handler::resetSetupTable();

        return $response;
    }

    protected function get_object( $id ) {
        $error = new WP_Error( 'rest_item_invalid_id', __( 'Invalid item ID.' ), array( 'status' => 404 ) );
        if ( (int) $id <= 0 ) {
            return $error;
        }

        Handler::setupTable( $this->getName() );

        $object = Handler::getObject( (int) $id );
        $primaryKey = $this->getTable()->getDatabase()->getPrimaryKey();

        Handler::resetSetupTable();

        if ( empty( $object ) || empty( $object->$primaryKey ) ) {
            return $error;
        }

        return $object;
    }

    public function get_item_permissions_check( $request ) {
        $object = $this->get_object( $request['id'] );
        if ( is_wp_error( $object ) ) {
            return $object;
        }

        if ( 'edit' === $request['context'] && $object && ! $this->check_update_permission( $object ) ) {
            return new WP_Error( 'rest_forbidden_context', __( 'Sorry, you are not allowed to edit this item.' ), array( 'status' => rest_authorization_required_code() ) );
        }

        if ( $object ) {
            return $this->check_read_permission( $object );
        }

        return true;
    }

    public function get_item( $request ) {
        $object = $this->get_object( $request['id'] );
        if ( is_wp_error( $object ) ) {
            return $object;
        }

        $data     = $this->prepare_item_for_response( $object, $request );
        $response = rest_ensure_response( $data );

        return $response;
    }

    public function create_item_permissions_check( $request ) {
        if ( ! empty( $request['id'] ) ) {
            return new WP_Error( 'rest_item_exists', __( 'Cannot create existing item.' ), array( 'status' => 400 ) );
        }

        if ( ! current_user_can( $this->table->cap->create_items ) ) {
            return new WP_Error( 'rest_cannot_create', __( 'Sorry, you are not allowed to create items as this user.' ), array( 'status' => rest_authorization_required_code() ) );
        }

        return true;
    }

    public function create_item( $request ) {
        if ( ! empty( $request['id'] ) ) {
            return new WP_Error( 'rest_item_exists', __( 'Cannot create existing item.' ), array( 'status' => 400 ) );
        }

        Handler::setupTable( $this->getName() );

        $prepared_object = $this->prepare_item_for_database( $request );

        if ( is_wp_error( $prepared_object ) ) {
            return $prepared_object;
        }

        $objectId = Handler::insertObject( wp_slash( (array) $prepared_object ), true );

        if ( is_wp_error( $objectId ) ) {

            if ( 'db_insert_error' === $objectId->get_error_code() ) {
                $objectId->add_data( array( 'status' => 500 ) );
            } else {
                $objectId->add_data( array( 'status' => 400 ) );
            }

            return $objectId;
        }

        $object = Handler::getObject( $objectId );

        do_action( "custom_table_rest_insert_{$this->getName()}", $object, $request, true );

        $schema = $this->get_item_schema();

        if ( in_array( 'meta', $this->getTable()->getSupports() ) && ! empty( $schema['properties']['meta'] ) && isset( $request['meta'] ) ) {

            $meta_update = $this->getMeta()->update_value( $request['meta'], $objectId );

            if ( is_wp_error( $meta_update ) ) {
                return $meta_update;
            }

        }

        $object = Handler::getObject( $objectId );
        $fields_update = $this->update_additional_fields_for_object( $object, $request );

        if ( is_wp_error( $fields_update ) ) {
            return $fields_update;
        }

        $request->set_param( 'context', 'edit' );

        do_action( "custom_table_rest_after_insert_{$this->getName()}", $object, $request, true );

        $response = $this->prepare_item_for_response( $object, $request );
        $response = rest_ensure_response( $response );

        $response->set_status( 201 );
        $response->header( 'Location', rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $objectId ) ) );

        Handler::resetSetupTable();

        return $response;
    }

    public function update_item_permissions_check( $request ) {
        $object = $this->get_object( $request['id'] );
        if ( is_wp_error( $object ) ) {
            return $object;
        }

        if ( $object && ! $this->check_update_permission( $object ) ) {
            return new WP_Error( 'rest_cannot_edit', __( 'Sorry, you are not allowed to edit this item.' ), array( 'status' => rest_authorization_required_code() ) );
        }

        return true;
    }

    public function update_item( $request ) {
        $valid_check = $this->get_object( $request['id'] );
        if ( is_wp_error( $valid_check ) ) {
            return $valid_check;
        }

        Handler::setupTable( $this->getName() );

        $object = $this->prepare_item_for_database( $request );

        if ( is_wp_error( $object ) ) {
            return $object;
        }

        $objectId = Handler::updateObject( wp_slash( (array) $object ), true );

        if ( is_wp_error( $objectId ) ) {
            if ( 'db_update_error' === $objectId->get_error_code() ) {
                $objectId->add_data( array( 'status' => 500 ) );
            } else {
                $objectId->add_data( array( 'status' => 400 ) );
            }
            return $objectId;
        }

        $object = Handler::getObject( $objectId );

        do_action( "custom_table_rest_insert_{$this->getName()}", $object, $request, false );

        $schema = $this->get_item_schema();

        if ( in_array( 'meta', $this->getTable()->getSupports() ) && ! empty( $schema['properties']['meta'] ) && isset( $request['meta'] ) ) {

            $meta_update = $this->getMeta()->update_value( $request['meta'], $objectId );

            if ( is_wp_error( $meta_update ) ) {
                return $meta_update;
            }

        }

        $object = Handler::getObject( $objectId );
        $fields_update = $this->update_additional_fields_for_object( $object, $request );

        if ( is_wp_error( $fields_update ) ) {
            return $fields_update;
        }

        $request->set_param( 'context', 'edit' );

        do_action( "custom_table_rest_after_insert_{$this->getName()}", $object, $request, false );

        $response = $this->prepare_item_for_response( $object, $request );

        Handler::resetSetupTable();

        return rest_ensure_response( $response );
    }

    public function delete_item_permissions_check( $request ) {
        $object = $this->get_object( $request['id'] );
        if ( is_wp_error( $object ) ) {
            return $object;
        }

        if ( $object && ! $this->check_delete_permission( $object ) ) {
            return new WP_Error( 'rest_cannot_delete', __( 'Sorry, you are not allowed to delete this item.' ), array( 'status' => rest_authorization_required_code() ) );
        }

        return true;
    }

    public function delete_item( $request ) {
        $object = $this->get_object( $request['id'] );
        if ( is_wp_error( $object ) ) {
            return $object;
        }

        $id    = $request['id'];
        $force = (bool) $request['force'];

        $supports_trash = ( EMPTY_TRASH_DAYS > 0 );

        $supports_trash = apply_filters( "custom_table_rest_{$this->getName()}_trashable", $supports_trash, $object );

        if ( ! $this->check_delete_permission( $object ) ) {
            return new WP_Error( 'rest_user_cannot_delete_post', __( 'Sorry, you are not allowed to delete this post.' ), array( 'status' => rest_authorization_required_code() ) );
        }

        $request->set_param( 'context', 'edit' );

        // TODO: There is no support for trash functionality, so let's force deletion
        $force = true;

        // If we're forcing, then delete permanently.
        if ( $force ) {
            $previous = $this->prepare_item_for_response( $object, $request );
            $result = Handler::deleteObject( $id, true );
            $response = new WP_REST_Response();
            $response->set_data( array( 'deleted' => true, 'previous' => $previous->get_data() ) );
        } else {
            // If we don't support trashing for this type, error out.
            if ( ! $supports_trash ) {
                /* translators: %s: force=true */
                return new WP_Error( 'rest_trash_not_supported', sprintf( __( "The post does not support trashing. Set '%s' to delete." ), 'force=true' ), array( 'status' => 501 ) );
            }
            // (Note that internally this falls through to `wp_delete_post` if the trash is disabled.)
            //$result = wp_trash_post( $id );
            $object = Handler::getObject( $id );
            $response = $this->prepare_item_for_response( $object, $request );
        }

        if ( ! isset($result) ) {
            return new WP_Error( 'rest_cannot_delete', __( 'The item cannot be deleted.' ), array( 'status' => 500 ) );
        }

        do_action( "custom_table_rest_delete_{$this->getName()}", $object, $response, $request );

        return $response;
    }

    protected function prepare_items_query( $prepared_args = array(), $request = null ) {

        $databaseTable = $this->getTable();
        $query_args = array();

        foreach ( $prepared_args as $key => $value ) {
            $query_args[ $key ] = apply_filters( "custom_table_rest_query_var-{$key}", $value ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
        }

        // Map to proper Query orderby param.
        if ( isset( $query_args['orderby'] ) && isset( $request['orderby'] ) ) {
            $orderby_mappings = array();

            $orderby_mappings = apply_filters( "custom_table_rest_{$this->getName()}_orderby_mappings", $orderby_mappings, $databaseTable, $prepared_args, $request );

            if ( isset( $orderby_mappings[ $request['orderby'] ] ) ) {
                $query_args['orderby'] = $orderby_mappings[ $request['orderby'] ];
            }
        }

        return $query_args;
    }

    protected function prepare_date_response( $date_gmt, $date = null ) {
        // Use the date if passed.
        if ( isset( $date ) ) {
            return mysql_to_rfc3339( $date );
        }

        // Return null if $date_gmt is empty/zeros.
        if ( '0000-00-00 00:00:00' === $date_gmt ) {
            return null;
        }

        // Return the formatted datetime.
        return mysql_to_rfc3339( $date_gmt );
    }

    protected function prepare_item_for_database( $request ) {
        $prepared_object = new stdClass;
        $primaryKey = $this->getTable()->getDatabase()->getPrimaryKey();
        $tableFields = $this->getTable()->getDatabase()->getSchema()->getFields();

        // Parse object primary key as ID
        if( isset( $request[$primaryKey] ) ) {
            $request['id'] = $request[$primaryKey];
        }

        // Object ID.
        if ( isset( $request['id'] ) ) {
            $existing_object = $this->get_object( $request['id'] );
            if ( is_wp_error( $existing_object ) ) {
                return $existing_object;
            }

            $prepared_object->$primaryKey = $existing_object->$primaryKey;
        }

        $schema = $this->get_item_schema();

        if( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
            foreach( $schema['properties'] as $field => $field_args ) {

                // Check if field is on request and also if is a table field
                if( isset( $request[$field] ) && isset( $tableFields[$field] ) ) {

                    $value = $request[$field];

                    $value = apply_filters( "custom_table_rest_{$this->getName()}_sanitize_field_value", $value, $field, $request );

                    // Bail if value filtered returns an error
                    if( is_wp_error( $value ) ) {
                        return $value;
                    }

                    $prepared_object->$field = $request[$field];

                }

            }
        }

        return apply_filters( "custom_table_rest_pre_insert_{$this->getName()}", $prepared_object, $request );

    }

    public function check_read_permission( $item ) {
        $primaryKey = $this->getTable()->getDatabase()->getPrimaryKey();

        // Is the item readable?
        if ( current_user_can( $this->getTable()->getCap()->read_item, $item->$primaryKey ) ) {
            return true;
        }

        return false;
    }

    protected function check_update_permission( $item ) {
        $primaryKey = $this->getTable()->getDatabase()->getPrimaryKey();

        // Is the item editable?
        if ( current_user_can( $this->getTable()->getCap()->edit_item, $item->$primaryKey ) ) {
            return true;
        }

        return false;
    }

    protected function check_create_permission( $item ) {
        return current_user_can( $this->getTable()->getCap()->create_items );
    }

    protected function check_delete_permission( $item ) {
        $primaryKey = $this->getTable()->getDatabase()->getPrimaryKey();

        return current_user_can( $this->getTable()->getCap()->delete_item, $item->$primaryKey );
    }

    public function prepare_item_for_response( $object, $request ) {

        $fields = $this->get_fields_for_response( $request );
        $primaryKey = $this->getTable()->getDatabase()->getPrimaryKey();
        $data = array();

        foreach( $fields as $field ) {

            $value = isset( $object->$field ) ? $object->$field : '';

            $data[$field] = apply_filters( "custom_table_rest_prepare_{$this->getName()}_field_value", $value, $field, $object, $request, $fields );
        }

        if ( in_array( 'meta', $this->getTable()->getSupports() ) && in_array( 'meta', $fields, true ) ) {
            $data['meta'] = $this->getMeta()->get_value( $object->$primaryKey, $request );
        }

        $context = ! empty( $request['context'] ) ? $request['context'] : 'view';
        $data    = $this->add_additional_fields_to_object( $data, $request );
        $data    = $this->filter_response_by_context( $data, $context );

        // Wrap the data in a response object.
        $response = rest_ensure_response( $data );

        return apply_filters( "custom_table_rest_prepare_{$this->getTable()}", $response, $object, $request );
    }

    public function get_item_schema() {

        $schema = array(
            '$schema'    => 'http://json-schema.org/draft-04/schema#',
            'title'      => $this->getName(),
            'type'       => 'object',
            // Properties are the fields that will be returned through rest request.
            'properties' => array(
                // id is common to all registered tables
                'id' => array(
                    'description' => __( 'Unique identifier for the object.' ),
                    'type'        => 'integer',
                    'context'     => array( 'view', 'edit', 'embed' ),
                ),
            ),
        );

        // Add meta property if table has support for it
        if( in_array( 'meta', $this->getTable()->getSupports() ) ) {
            $schema['properties']['meta'] = $this->getMeta()->get_field_schema();
        }

        $schema = apply_filters( "custom_table_rest_{$this->getName()}_schema", $schema );

        return $this->add_additional_fields_schema( $schema );
    }

    public function get_collection_params() {

        $query_params = parent::get_collection_params();

        $query_params['context']['default'] = 'view';

        $databaseTable = $this->getTable();

        // Offset
        $query_params['offset'] = array(
            'description'        => __( 'Offset the result set by a specific number of items.' ),
            'type'               => 'integer',
        );

        // Order
        $query_params['order'] = array(
            'description'        => __( 'Order sort attribute ascending or descending.' ),
            'type'               => 'string',
            'default'            => 'desc',
            'enum'               => array( 'asc', 'desc' ),
        );

        // Order By
        $query_params['orderby'] = array(
            'description'        => __( 'Sort collection by object attribute.' ),
            'type'               => 'string',
            'default'            => $databaseTable->getDatabase()->getPrimaryKey(),
            'enum'               => array_merge(
            // Allow order by table fields
                array_keys( $databaseTable->getDatabase()->getSchema()->getFields() ),
                // Allow order by custom order by clauses
                array( 'include', 'relevance'  )
            ),
        );

        return apply_filters( "custom_table_rest_{$this->getName()}_collection_params", $query_params, $databaseTable );
    }
}