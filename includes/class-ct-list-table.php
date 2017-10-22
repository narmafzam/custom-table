<?php
/**
 * List Table class
 *
 * Based on WP_Posts_List_Table class
 *
 * @since 1.0.0
 */
// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'CT_List_Table' ) ) :

    class CT_List_Table extends WP_List_Table {

        /**
         * Get things started
         *
         * @access public
         * @since  1.0
         *
         * @param array $args Optional. Arbitrary display and query arguments to pass through
         *                    the list table. Default empty array.
         */
        public function __construct( $args = array() ) {
            global $ct_table, $ct_query;

            parent::__construct( array(
                'singular' => $ct_table->labels->singular_name,
                'plural' => $ct_table->labels->plural_name,
                'screen' => convert_to_screen( $ct_table->labels->plural_name )
            ) );
        }

        /**
         * Retrieve view counts
         *
         * @since 1.0.0
         *
         * @return void
         */
        public function get_views_counts() {

            $search = isset( $_GET['s'] ) ? $_GET['s'] : '';


        }

        /**
         * Show the search field
         *
         * @since 1.0.0
         *
         * @param string $text Label for the search box
         * @param string $input_id ID of the search box
         *
         * @return void
         */
        public function search_box( $text, $input_id ) {
            if ( empty( $_REQUEST['s'] ) && !$this->has_items() )
                return;

            $input_id = $input_id . '-search-input';

            if ( ! empty( $_REQUEST['orderby'] ) )
                echo '<input type="hidden" name="orderby" value="' . esc_attr( $_REQUEST['orderby'] ) . '" />';
            if ( ! empty( $_REQUEST['order'] ) )
                echo '<input type="hidden" name="order" value="' . esc_attr( $_REQUEST['order'] ) . '" />';
            ?>
            <p class="search-box">
                <label class="screen-reader-text" for="<?php echo $input_id ?>"><?php echo $text; ?>:</label>
                <input type="search" id="<?php echo $input_id ?>" name="s" value="<?php _admin_search_query(); ?>" />
                <?php submit_button( $text, 'button', false, false, array( 'ID' => 'search-submit' ) ); ?>
            </p>
            <?php
        }

        /**
         * Retrieve the view types
         *
         * @access public
         * @since 1.0.0
         *
         * @return array $views All the views available
         */
        public function get_views() {
            $views = array();

            return $views;
        }

        /**
         *
         * @return array
         */
        protected function get_bulk_actions() {

            $actions = array();

            return $actions;
        }

        /**
         *
         * @return array
         */
        protected function get_table_classes() {
            global $ct_table;

            return array( 'widefat', 'fixed', 'striped', $ct_table->name );
        }

        /**
         * Retrieve the table columns
         *
         * @access public
         * @since 1.0
         * @return array $columns Array of all the list table columns
         */
        public function get_columns() {
            global $ct_table;

            $columns = array();
            $bulk_actions = $this->get_bulk_actions();

            if( ! empty( $bulk_actions ) ) {
                $columns['cb'] = '<input type="checkbox" />';
            }

            /**
             * Filters the columns displayed in the Posts list table.
             *
             * @since 1.0.0
             *
             * @param array  $posts_columns An array of column names.
             * @param string $post_type     The post type slug.
             */
            return apply_filters( "manage_{$ct_table->name}_columns", $columns );
        }

        /**
         * Retrieve the table's sortable columns
         *
         * @access public
         * @since 1.0
         * @return array Array of all the sortable columns
         */
        public function get_sortable_columns() {
            global $ct_table;

            $sortable_columns = array();

            /**
             * Filters the columns displayed in the Posts list table.
             *
             * @since 1.5.0
             *
             * @param array  $posts_columns An array of column names.
             * @param string $post_type     The post type slug.
             */
            return apply_filters( "manage_{$ct_table->name}_sortable_columns", $sortable_columns );
        }

        /**
         * This function renders most of the columns in the list table.
         *
         * @access public
         * @since 1.0
         *
         * @param stdClass  $item           The current object.
         * @param string    $column_name    The name of the column
         * @return string                   The column value.
         */
        public function column_default( $item, $column_name ) {
            global $ct_table;

            $value = isset( $item->$column_name ) ? $item->$column_name : '';

            $primary_key = $ct_table->db->primary_key;

            /**
             * Fires for each custom column of a specific post type in the Posts list table.
             *
             * The dynamic portion of the hook name, `$post->post_type`, refers to the post type.
             *
             * @since 3.1.0
             *
             * @param string $column_name The name of the column to display.
             * @param int    $post_id     The current post ID.
             */
            ob_start();
            do_action( "manage_{$ct_table->name}_custom_column", $column_name, $item->$primary_key );
            $custom_output = ob_get_clean();

            if( ! empty( $custom_output ) ) {
                return $custom_output;
            }

            $bulk_actions = $this->get_bulk_actions();

            $first_column_index = ( ! empty( $bulk_actions ) ) ? 1 : 0;

            $can_edit_item = current_user_can( $ct_table->cap->edit_item, $item->$primary_key );
            $columns = $this->get_columns();
            $columns_keys = array_keys( $columns );

            if( $column_name === $columns_keys[$first_column_index] && $can_edit_item ) {

                // Turns first column into a text link with url to edit the item
                $value = sprintf( '<a href="%s" aria-label="%s">%s</a>',
                    ct_get_edit_link( $ct_table->name, $item->$primary_key ),
                    esc_attr( sprintf( __( 'Edit &#8220;%s&#8221;' ), $value ) ),
                    $value
                );

            }

            return $value;
        }

        /**
         * Generates and displays row action links.
         *
         * @since 4.3.0
         * @access protected
         *
         * @param object $item        The item being acted upon.
         * @param string $column_name Current column name.
         * @param string $primary     Primary column name.
         *
         * @return string Row actions output for posts.
         */
        protected function handle_row_actions( $item, $column_name, $primary ) {
            if ( $primary !== $column_name ) {
                return '';
            }

            global $ct_table;

            $primary_key = $ct_table->db->primary_key;
            $can_edit_item = current_user_can( $ct_table->cap->edit_item, $item->$primary_key );
            $actions = array();

            // TODO: add custom map_meta_cap()
            //$current_user = wp_get_current_user();

            //$current_user->has_cap( $ct_table->cap->edit_item, $item->$primary_key );

            //map_meta_cap();

            if ( $ct_table->views->edit && $can_edit_item ) {
                $actions['edit'] = sprintf(
                    '<a href="%s" aria-label="%s">%s</a>',
                    ct_get_edit_link( $ct_table->name, $item->$primary_key ),
                    esc_attr( __( 'Edit' ) ),
                    __( 'Edit' )
                );
            }

            /**
             * Filters the array of row action links on the Posts list table.
             *
             * The filter is evaluated only for non-hierarchical post types.
             *
             * @since 2.8.0
             *
             * @param array $actions An array of row action links. Defaults are
             *                         'Edit', 'Quick Edit', 'Restore, 'Trash',
             *                         'Delete Permanently', 'Preview', and 'View'.
             * @param WP_Post $post The post object.
             */
            $actions = apply_filters( "{$ct_table->name}_row_actions", $actions, $item );

            return $this->row_actions( $actions );
        }

        /**
         * Handles the checkbox column output.
         *
         * @since 1.0.0
         *
         * @param WP_Post $item The current WP_Post object.
         */
        public function column_cb( $item ) {
            global $ct_table;

            $primary_key = $ct_table->db->primary_key;

            if ( current_user_can( $ct_table->cap->edit_items ) ): ?>
                <label class="screen-reader-text" for="cb-select-<?php echo $item->$primary_key; ?>"><?php
                    printf( __( 'Select %s' ), _draft_or_post_title() );
                    ?></label>
                <input id="cb-select-<?php echo $item->$primary_key; ?>" type="checkbox" name="post[]" value="<?php echo $item->$primary_key; ?>" />
                <div class="locked-indicator">
                    <span class="locked-indicator-icon" aria-hidden="true"></span>
                    <span class="screen-reader-text"><?php
                        printf(
                        /* translators: %s: post title */
                            __( '&#8220;%s&#8221; is locked' ),
                            _draft_or_post_title()
                        );
                        ?></span>
                </div>
            <?php endif;
        }

        /**
         * Renders the message to be displayed when there are no results.
         *
         * @since  1.0.0
         */
        function no_items() {
            global $ct_table;

            echo $ct_table->labels->not_found;
        }

        public function prepare_items() {

            global $ct_table, $ct_query;

            $this->items = $ct_query->get_results();

            $total_items = $ct_query->found_results;
            $per_page = $this->get_items_per_page( 'edit_' . $ct_table->name . '_per_page' );

            $this->set_pagination_args( array(
                'total_items' => $total_items,
                'per_page'    => $per_page,
                'total_pages' => ceil( $total_items / $per_page )
            ) );
        }

    }

endif;