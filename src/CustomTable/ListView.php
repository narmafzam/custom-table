<?php

namespace CustomTable;

class ListView extends View
{
    protected $perPage = 20;

    protected $columns = array();

    public function __construct( $name, $args ) {

        parent::__construct( $name, $args );

        $this->per_page = isset( $args['per_page'] ) ? $args['per_page'] : 20;
        $this->columns  = isset( $args['columns'] ) ? $args['columns'] : array();

    }

    public function getPerPage()
    {
        return $this->perPage;
    }

    public function getColumns( $columns ) {

        foreach( $this->columns as $column => $column_args ) {

            if( is_array( $column_args ) && isset( $column_args['label'] ) ) {
                // 'column_name' => array( 'label' => 'Column Label' )
                $columns[$column] = $column_args['label'];
            } else if( gettype( $column_args ) === 'string' ) {
                // 'column_name' => 'Column Label'
                $columns[$column] = $column_args;
            }

        }

        return $columns;
    }

    public function addFilter() {

        parent::addFilter();

        add_filter( "manage_{$this->getName()}_columns", array( $this, 'getColumns' ) );
        add_filter( "manage_{$this->getName()}_sortable_columns", array( $this, 'getSortableColumns' ) );

    }

    public function getSortableColumns( $sortable_columns ) {

        foreach( $this->columns as $column => $column_args ) {

            if( is_array( $column_args ) && isset( $column_args['sortable'] ) ) {
                // 'column_name' => array( 'sortable' => 'sortable_setup' )
                $sortable_columns[$column] = $column_args['sortable'];
            }

        }

        return $sortable_columns;

    }

    public function init() {

        global $registeredTables, $databaseTable, $query, $listTable;

        if( ! isset( $registeredTables[$this->getName()] ) ) {
            return;
        }

        // Setup Table
        $databaseTable = $registeredTables[$this->getName()];

        // Check for bulk delete
        if( isset( $_GET['action'] ) ) {

            if( $_GET['action'] === 'delete' ) {
                // Deleting
                $this->bulkDelete();
            }

        }

        // Check for delete action
        if( isset( $_GET['custom-table-action'] ) ) {

            if( $_GET['custom-table-action'] === 'delete' ) {
                // Deleting
                $this->delete();
            }

        }

        // Setup the query and the list table objects
        $query = new Query( $_GET );
        $listTable = new ListTable();
    }

    public function screenSettings( $screen_settings, $screen ) {

        $this->renderListTableColumnsPreferences();
        $this->renderPerPageOptions();

    }

    public function renderListTableColumnsPreferences() {
        /**
         * @var ListTable $listTable
         * @var Table $databaseTable
         */
        global $databaseTable, $listTable;

        // Set up vars
        $columns = $listTable->get_columns();
        $hidden  = get_hidden_columns( $databaseTable->getName() );

        if ( ! $columns )
            return;

        $legend = ! empty( $columns['_title'] ) ? $columns['_title'] : __( 'Columns' );
        ?>
        <fieldset class="metabox-prefs">
            <legend><?php echo $legend; ?></legend>
            <?php
            $special = array( '_title', 'cb' );

            foreach ( $columns as $column => $title ) {
                // Can't hide these for they are special
                if ( in_array( $column, $special ) || empty( $title ) ) {
                    continue;
                }

                $id = "$column-hide";
                echo '<label>';
                echo '<input class="hide-column-tog" name="' . $id . '" type="checkbox" id="' . $id . '" value="' . $column . '"' . checked( ! in_array( $column, $hidden ), true, false ) . ' />';
                echo "$title</label>\n";
            }
            ?>
        </fieldset>
        <?php
    }

    public function renderPerPageOptions() {

        /**
         * @var Table $databaseTable
         */
        global $databaseTable;

        if ( ! $this->getPerPage() )
            return;

        // Set up vars
        $perPageLabel = __( 'Number of items per page:' );

        $option = str_replace( '-', '_', "edit_{$databaseTable->getName()}_per_page" );

        $perPage = (int) get_user_option( $option );

        if ( empty( $perPage ) || $perPage < 1 )
            $perPage = $this->getPerPage();

        $perPage = apply_filters( "{$option}", $perPage );

        // This needs a submit button
        add_filter( 'screen_options_show_submit', '__return_true' );

        ?>
        <fieldset class="screen-options">
            <legend><?php _e( 'Pagination' ); ?></legend>
            <?php if ( $perPageLabel ) : ?>
                <label for="<?php echo esc_attr( $option ); ?>"><?php echo $perPageLabel; ?></label>
                <input type="number" step="1" min="1" max="999" class="screen-per-page" name="wp_screen_options[value]"
                       id="<?php echo esc_attr( $option ); ?>" maxlength="3"
                       value="<?php echo esc_attr( $perPage ); ?>" />
            <?php endif; ?>
            <input type="hidden" name="wp_screen_options[option]" value="<?php echo esc_attr( $option ); ?>" />
        </fieldset>
        <?php
    }

    public function setScreenSettings( $valueToSet, $option, $value ) {
        /**
         * @var ListTable $listTable
         * @var Table $databaseTable
         */
        global $databaseTable, $listTable;

        $viewSettings = array(
            str_replace( '-', '_', "edit_{$databaseTable->getName()}_per_page" ) // Per page
        );

        // Columns hidden setting
        $columns = $listTable->get_columns();
        $special = array( '_title', 'cb' );

        foreach ( $columns as $column => $title ) {
            // Can't hide these for they are special
            if ( in_array( $column, $special ) || empty( $title ) )
                continue;

            $viewSettings[] = "$column-hide";
        }

        // If option is on this view settings list, then save it
        if( in_array( $option, $viewSettings ) ) {
            $valueToSet = $value;
        }

        return $valueToSet;

    }

    public function bulkDelete() {
        /**
         * @var Table $databaseTable
         */
        global $databaseTable;

        // If not CT object, die
        if ( ! $databaseTable )
            wp_die( __( 'Invalid item type.' ) );

        // If not CT object allow ui, die
        if ( ! $databaseTable->getShowUI() ) {
            wp_die( __( 'Sorry, you are not allowed to delete items of this type.' ) );
        }

        $objectIds = array();

        // Check received items
        if ( ! empty( $_REQUEST['item'] ) ) {
            $objectIds = array_map('intval', $_REQUEST['item']);
        }

        $deleted = 0;

        foreach ( (array) $objectIds as $objectId ) {

            // If not current user can delete, die
            if ( ! current_user_can( 'delete_item', $objectId ) ) {
                wp_die( __( 'Sorry, you are not allowed to delete this item.' ) );
            }

            if ( ! Handler::deleteObject( $objectId ) )
                wp_die( __( 'Error in deleting.' ) );

            $deleted++;
        }

        $location = add_query_arg( array( 'deleted' => $deleted ), $this->getLink() );

        wp_redirect( $location );
        exit;
    }

    public function delete() {
        /**
         * @var Table $databaseTable
         */
        global $databaseTable;

        // If not CT object, die
        if ( ! $databaseTable )
            wp_die( __( 'Invalid item type.' ) );

        // If not CT object allow ui, die
        if ( ! $databaseTable->getShowUI() ) {
            wp_die( __( 'Sorry, you are not allowed to delete items of this type.' ) );
        }

        $primaryKey = $databaseTable->getDatabase()->getPrimaryKey();

        // Object ID is required
        if( ! isset( $_GET[$primaryKey] ) ) {
            wp_die( __( 'Sorry, you are not allowed to delete items of this type.' ) );
        }

        $objectId = (int) $_GET[$primaryKey];

        // If not current user can delete, die
        if ( ! current_user_can( 'delete_item', $objectId ) ) {
            wp_die( __( 'Sorry, you are not allowed to delete this item.' ) );
        }

        if ( ! Handler::deleteObject( $objectId ) )
            wp_die( __( 'Error in deleting.' ) );

        $location = add_query_arg( array( 'deleted' => 1 ), $this->getLink() );

        wp_redirect( $location );
        exit;

    }

    public function render() {
        /**
         * @var ListTable $listTable
         * @var Table $databaseTable
         */
        global $databaseTable, $listTable;

        $listTable->prepare_items();

        $bulkCounts = array(
            'updated'   => isset( $_REQUEST['updated'] )   ? absint( $_REQUEST['updated'] )   : 0,
            'locked'    => isset( $_REQUEST['locked'] )    ? absint( $_REQUEST['locked'] )    : 0,
            'deleted'   => isset( $_REQUEST['deleted'] )   ? absint( $_REQUEST['deleted'] )   : 0,
            'trashed'   => isset( $_REQUEST['trashed'] )   ? absint( $_REQUEST['trashed'] )   : 0,
            'untrashed' => isset( $_REQUEST['untrashed'] ) ? absint( $_REQUEST['untrashed'] ) : 0,
        );

        $bulkMessages = array(
            'updated'   => _n( '%s item updated.', '%s items updated.', $bulkCounts['updated'] ),
            'locked'    => ( 1 == $bulkCounts['locked'] ) ? __( '1 item not updated, somebody is editing it.' ) :
                _n( '%s item not updated, somebody is editing it.', '%s items not updated, somebody is editing them.', $bulkCounts['locked'] ),
            'deleted'   => _n( '%s item permanently deleted.', '%s items permanently deleted.', $bulkCounts['deleted'] ),
            'trashed'   => _n( '%s item moved to the Trash.', '%s items moved to the Trash.', $bulkCounts['trashed'] ),
            'untrashed' => _n( '%s item restored from the Trash.', '%s items restored from the Trash.', $bulkCounts['untrashed'] ),
        );

        $bulk_messages = apply_filters( 'bulk_object_updated_messages', $bulkMessages, $bulkCounts );
        $bulk_counts = array_filter( $bulkCounts );

        ?>

        <div class="wrap">

            <h1 class="wp-heading-inline"><?php echo $databaseTable->getLabels()->plural_name; ?></h1>

            <?php if ( property_exists( $databaseTable->getViews(), 'add' ) && $databaseTable->getViews()->add && current_user_can( $databaseTable->getCap()->create_items ) ) :
                echo ' <a href="' . esc_url( $databaseTable->getViews()->add->get_link() ) . '" class="page-title-action">' . esc_html( $databaseTable->getLabels()->add_new_item ) . '</a>';
            endif; ?>

            <hr class="wp-header-end">

            <?php
            // If we have a bulk message to issue:
            $messages = array();
            foreach ( $bulk_counts as $message => $count ) {
                if ( isset( $bulk_messages[ $message ] ) )
                    $messages[] = sprintf( $bulk_messages[ $message ], number_format_i18n( $count ) );

                //if ( $message == 'trashed' && isset( $_REQUEST['ids'] ) ) {
                //$ids = preg_replace( '/[^0-9,]/', '', $_REQUEST['ids'] );
                //$messages[] = '<a href="' . esc_url( wp_nonce_url( "edit.php?post_type=$post_type&doaction=undo&action=untrash&ids=$ids", "bulk-posts" ) ) . '">' . __('Undo') . '</a>';
                //}
            }

            if ( $messages )
                echo '<div id="message" class="updated notice is-dismissible"><p>' . join( ' ', $messages ) . '</p></div>';
            unset( $messages );

            $_SERVER['REQUEST_URI'] = remove_query_arg( array( 'locked', 'skipped', 'updated', 'deleted', 'trashed', 'untrashed' ), $_SERVER['REQUEST_URI'] );
            ?>

            <?php $databaseTable->getViews(); ?>

            <form id="ct-list-filter" method="get">

                <input type="hidden" name="page" value="<?php echo esc_attr( $this->args['menu_slug'] ); ?>" />

                <?php $listTable->search_box( $databaseTable->getLabels()->search_items, $databaseTable->getName() ); ?>

                <?php $listTable->display(); ?>

            </form>

            <div id="ajax-response"></div>
            <br class="clear" />

        </div>

        <?php
    }
}