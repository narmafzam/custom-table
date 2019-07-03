<?php

namespace CustomTable;

use \WP_List_Table;

class ListTable extends WP_List_Table
{
    public function __construct( $args = array() ) {
        /** @var Table $databaseTable */
        global $databaseTable;

        parent::__construct( array(
            'singular' => $databaseTable->getLabels()->singular_name,
            'plural' => $databaseTable->getLabels()->plural_name,
            'screen' => convert_to_screen( $databaseTable->getLabels()->plural_name )
        ) );
    }

    public function getViewsCounts() {
        $search = isset( $_GET['s'] ) ? $_GET['s'] : '';
    }

    public function search_box( $text, $input_id ) {
        if ( empty( $_REQUEST['s'] ) && !$this->has_items() )
            return;

        $input_id = $input_id . '-search-input';

        if ( isset($_REQUEST['orderby']) && ! empty( $_REQUEST['orderby'] ) )
            echo '<input type="hidden" name="orderby" value="' . esc_attr( $_REQUEST['orderby'] ) . '" />';
        if ( isset($_REQUEST['orderby']) && ! empty( $_REQUEST['order'] ) )
            echo '<input type="hidden" name="order" value="' . esc_attr( $_REQUEST['order'] ) . '" />';
        ?>
        <p class="search-box">
            <label class="screen-reader-text" for="<?php echo $input_id ?>"><?php echo $text; ?>:</label>
            <input type="search" id="<?php echo $input_id ?>" name="s" value="<?php _admin_search_query(); ?>" />
            <?php submit_button( $text, 'button', false, false, array( 'ID' => 'search-submit' ) ); ?>
        </p>
        <?php
    }

    public function get_views() {
        $views = array();

        return $views;
    }

    protected function get_bulk_actions() {

        /** @var Table $databaseTable */
        global $databaseTable;

        $actions = array();

        if ( current_user_can( $databaseTable->getCap()->delete_items ) ) {
            $actions['delete'] = __( 'Delete Permanently' );
        }

        $actions = apply_filters( "{$databaseTable->getName()}_bulk_actions", $actions );

        return $actions;
    }

    protected function get_table_classes() {
        /** @var Table $databaseTable */
        global $databaseTable;

        return array( 'widefat', 'fixed', 'striped', $databaseTable->getName() );
    }

    public function get_columns() {
        /** @var Table $databaseTable */
        global $databaseTable;

        $columns = array();
        $bulk_actions = $this->get_bulk_actions();

        if( ! empty( $bulk_actions ) ) {
            $columns['cb'] = '<input type="checkbox" />';
        }

        return apply_filters( "manage_{$databaseTable->getName()}_columns", $columns, $databaseTable );
    }

    public function get_sortable_columns() {
        /** @var Table $databaseTable */
        global $databaseTable;

        $sortable_columns = array();

        return apply_filters( "manage_{$databaseTable->getName()}_sortable_columns", $sortable_columns, $databaseTable );
    }

    public function column_default( $item, $column_name ) {
        /** @var Table $databaseTable */
        global $databaseTable;

        $value = isset( $item->$column_name ) ? $item->$column_name : '';

        $primary_key = $databaseTable->getDatabase()->getPrimaryKey();

        ob_start();

        do_action( "manage_{$databaseTable->getName()}_custom_column", $column_name, $item->$primary_key, $item, $databaseTable );
        $custom_output = ob_get_clean();

        if( ! empty( $custom_output ) ) {
            return $custom_output;
        }

        $bulk_actions = $this->get_bulk_actions();

        $first_column_index = ( ! empty( $bulk_actions ) ) ? 1 : 0;

        $can_edit_item = current_user_can( $databaseTable->getCap()->edit_item, $item->$primary_key );
        $columns = $this->get_columns();
        $columns_keys = array_keys( $columns );

        if( $column_name === $columns_keys[$first_column_index] && $can_edit_item ) {

            // Turns first column into a text link with url to edit the item
            $value = sprintf( '<a href="%s" aria-label="%s">%s</a>',
                Utility::getEditLink( $databaseTable->getName(), $item->$primary_key ),
                esc_attr( sprintf( __( 'Edit &#8220;%s&#8221;' ), $value ) ),
                $value
            );

            // Small screens toggle
            $value .= '<button type="button" class="toggle-row"><span class="screen-reader-text">' . __( 'Show more details' ) . '</span></button>';

        }

        return $value;
    }

    protected function handle_row_actions( $item, $column_name, $primary ) {
        if ( $primary !== $column_name ) {
            return '';
        }

        /** @var Table $databaseTable */
        global $databaseTable;

        $primary_key = $databaseTable->getDatabase()->getPrimaryKey();
        $actions = array();

        if ( $databaseTable->getViews()->edit && current_user_can( $databaseTable->getCap()->edit_item, $item->$primary_key ) ) {
            $actions['edit'] = sprintf(
                '<a href="%s" aria-label="%s">%s</a>',
                Utility::getEditLink( $databaseTable->getName(), $item->$primary_key ),
                esc_attr( __( 'Edit' ) ),
                __( 'Edit' )
            );
        }

        if ( current_user_can( $databaseTable->getCap()->delete_item, $item->$primary_key ) ) {
            $actions['delete'] = sprintf(
                '<a href="%s" class="submitdelete" onclick="%s" aria-label="%s">%s</a>',
                Utility::getDeleteLink( $databaseTable->getName(), $item->$primary_key ),
                "return confirm('" .
                esc_attr( __( "Are you sure you want to delete this item?\\n\\nClick \\'Cancel\\' to go back, \\'OK\\' to confirm the delete." ) ) .
                "');",
                esc_attr( __( 'Delete permanently' ) ),
                __( 'Delete Permanently' )
            );
        }

        $actions = apply_filters( "{$databaseTable->getName()}_row_actions", $actions, $item );

        return $this->row_actions( $actions );
    }

    public function column_cb( $item ) {
        /** @var Table $databaseTable */
        global $databaseTable;

        $primary_key = $databaseTable->getDatabase()->getPrimaryKey();

        if ( current_user_can( $databaseTable->getCap()->edit_items ) ): ?>
            <label class="screen-reader-text" for="cb-select-<?php echo $item->$primary_key; ?>"><?php
                printf( __( 'Select Item #%d' ), $item->$primary_key );
                ?></label>
            <input id="cb-select-<?php echo $item->$primary_key; ?>" type="checkbox" name="item[]" value="<?php echo $item->$primary_key; ?>" />
            <div class="locked-indicator">
                <span class="locked-indicator-icon" aria-hidden="true"></span>
                <span class="screen-reader-text"><?php
                    printf(
                    /* translators: %d: item ID */
                        __( '&#8220;Item #%d&#8221; is locked' ),
                        $item->$primary_key
                    );
                    ?></span>
            </div>
        <?php endif;
    }

    function no_items() {
        /** @var Table $databaseTable */
        global $databaseTable;

        echo $databaseTable->getLabels()->not_found;
    }

    public function prepare_items() {
        /**
         * @var Table $databaseTable
         * @var Query $query
         */
        global $databaseTable, $query;

        // Get per page setting
        $per_page = $this->get_items_per_page( 'edit_' . $databaseTable->getName() . '_per_page' );

        // Update query vars based on settings
        $query->queryVars['items_per_page'] = $per_page;

        // Get query results
        $this->items = $query->getQueryResults();

        $total_items = $query->getFoundResults();

        // Setup pagination args based on items found and per page settings
        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page )
        ) );
    }
}