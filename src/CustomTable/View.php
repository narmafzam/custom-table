<?php


namespace CustomTable;


class View
{
    /**
     * @var string View name
     */
    protected $name = '';

    /**
     * @var array View args
     */
    protected $args = array();

    public function __construct( $name, $args ) {

        $this->name = $name;

        $this->args = wp_parse_args( $args, array(
            'menu_title' => ucfirst( $this->getName()),
            'page_title' => ucfirst( $this->getName() ),
            'menu_slug' => $this->getName(),
            'parent_slug' => '',
            'show_in_menu' => true,
            'menu_icon' => '',
            'menu_position' => null,
            'capability' => 'manage_options',
        ) );

        $this->init();
    }

    public function getName()
    {
        return $this->name;
    }

    public function getArgs()
    {
        return $this->args;
    }

    public function init()
    {
        $this->addAction();
        $this->addFilter();
    }

    public function addAction() {

        add_action( 'admin_init', array( $this, 'adminInit' ) );
        add_action( 'admin_menu', array( $this, 'adminMenu' ), empty( $this->getArgs()['parent_slug'] ) ? 10 : 11 );
    }

    public function addFilter()
    {
        add_filter( 'screen_options_show_screen', array( $this, 'showScreenOptions' ), 10, 2 );
        add_filter( 'screen_settings', array( $this, 'maybeScreenSettings' ), 10, 2 );
        add_filter( 'admin_init', array( $this, 'maybeSetScreenSettings' ), 11 );
        add_filter( 'custom-table-set-screen-option', array( $this, 'setScreenSettings' ), 10, 3 );
    }

    public function showScreenOptions( $showScreen, $screen ) {

        $screenSlug = explode( '_page_', $screen->id );

        if( isset( $screenSlug[1] ) &&  $screenSlug[1] === $this->args['menu_slug'] ) {
            return true;
        }

        return $showScreen;

    }

    public function maybeScreenSettings( $screenSettings, $screen ) {

        $screenSlug = explode( '_page_', $screen->id );

        // Check if current screen matches this menu slug
        if( isset( $screenSlug[1] ) &&  $screenSlug[1] === $this->args['menu_slug'] ) {

            /**
             * @var Table $databaseTable
             */
            global $registeredTables, $databaseTable;

            if( ! isset( $registeredTables[$this->getName()] ) ) {
                return $screenSettings;
            }

            // Set up global vars
            $databaseTable = $registeredTables[$this->getName()];

            ob_start();
            $this->screenSettings( $screenSettings, $screen );
            $screenSettings .= ob_get_clean();

        }

        return $screenSettings;

    }

    public function screenSettings( $screenSettings, $screen ) {
        // Override
    }

    function maybeSetScreenSettings() {

        if ( isset( $_POST['wp_screen_options'] ) && is_array( $_POST['wp_screen_options'] ) ) {
            check_admin_referer( 'screen-options-nonce', 'screenoptionnonce' );

            if ( ! $user = wp_get_current_user() )
                return;

            $option = $_POST['wp_screen_options']['option'];
            $value = $_POST['wp_screen_options']['value'];

            if ( $option != sanitize_key( $option ) )
                return;

            $option = str_replace('-', '_', $option);

            $value = apply_filters( 'custom-table-set-screen-option', false, $option, $value );

            if ( false === $value )
                return;

            update_user_meta( $user->ID, $option, $value );

            $url = remove_query_arg( array( 'pagenum', 'apage', 'paged' ), wp_get_referer() );
            if ( isset( $_POST['mode'] ) ) {
                $url = add_query_arg( array( 'mode' => $_POST['mode'] ), $url );
            }

            wp_safe_redirect( $url );
            exit;
        }
    }

    public function setScreenSettings( $value_to_set, $option, $value ) {

        // Override

        return $value_to_set;

    }

    public function adminMenu() {

        if( ! $this->args['show_in_menu'] ) {

            add_submenu_page( null, $this->args['page_title'], $this->args['menu_title'], $this->args['capability'], $this->args['menu_slug'], array( $this, 'render' ) );

        } else {

            if( empty( $this->args['parent_slug'] ) ) {
                // View menu
                add_menu_page( $this->args['page_title'], $this->args['menu_title'], $this->args['capability'], $this->args['menu_slug'], array( $this, 'render' ), $this->args['menu_icon'], $this->args['menu_position'] );
            } else {
                // View sub menu
                add_submenu_page( $this->args['parent_slug'], $this->args['page_title'], $this->args['menu_title'], $this->args['capability'], $this->args['menu_slug'], array( $this, 'render' ) );
            }

        }

    }

    public function getSlug() {
        return $this->getArgs()['menu_slug'];
    }

    public function getLink() {
        return admin_url( "admin.php?page=" . $this->args['menu_slug'] );
    }

    public function adminInit() {

        /**
         * @var TableMeta $databaseTable
         */
        global $registeredTables, $databaseTable, $pagenow;

        if( $pagenow !== 'admin.php' ) {
            return;
        }

        if( ! isset( $_GET['page'] ) ) {
            return;
        }

        if( empty( $_GET['page'] ) || $_GET['page'] !== $this->getArgs()['menu_slug'] ) {
            return;
        }

        if( ! isset( $registeredTables[$this->getName()] ) ) {
            return;
        }

        // Setup the global Table object for this screen
        $databaseTable = $registeredTables[$this->getName()];
        do_action( "custom_table_init_{$this->getName()}_view", $this );
    }

    public function render() {

        do_action( "custom_table_render_{$this->getName()}_view", $this );

    }
}