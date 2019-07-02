<?php

namespace CustomTable;

class EditView extends View
{
    protected $objectId = 0;
    protected $object = null;
    protected $editing = false;
    protected $message = false;
    protected $columns = 2;

    public function __construct($name, $args)
    {

        parent::__construct($name, $args);

        $this->columns = isset($args['columns']) ? $args['columns'] : 2;

    }

    public function getObject()
    {
        return $this->object;
    }

    public function setObject($object): void
    {
        $this->object = $object;
    }

    public function getObjectId()
    {
        return $this->objectId;
    }

    public function setObjectId($objectId): void
    {
        $this->objectId = $objectId;
    }

    public function isEditing()
    {
        return $this->editing;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function init()
    {
        /**
         * @var Table $databaseTable
         */
        global $registeredTables, $databaseTable;

        if (!isset($registeredTables[$this->getName()])) {
            wp_die(__('Invalid item type.'));
        }

        // Setup global $databaseTable
        $databaseTable = $registeredTables[$this->getName()];

        // If not CT object, die
        if (!$databaseTable)
            wp_die(__('Invalid item type.'));

        // If not CT object allow ui, die
        if (!$databaseTable->getShowUi()) {
            wp_die(__('Sorry, you are not allowed to edit items of this type.'));
        }

        if (isset($_POST['ct-save'])) {
            // Saving
            $this->save();
        }

        $primaryKey = $databaseTable->getDatabase()->getPrimaryKey();

        if (isset($_GET[$primaryKey])) {
            // Editing object
            $this->setObjectId((int) $_GET[$primaryKey]);
            $this->setObject($databaseTable->getDatabase()->get($this->getObjectId()));
            $this->editing = true;

            // If not id, return to list
            if (empty($this->getObjectId())) {
                wp_redirect(Utility::getListLink($databaseTable->getName()));
                exit();
            }

            // If not object, die
            if (!$this->object) {
                wp_die(__('You attempted to edit an item that doesn&#8217;t exist. Perhaps it was deleted?'));
            }

            // If not current user can edit, die
            if (!current_user_can('edit_item', $this->getObjectId())) {
                wp_die(__('Sorry, you are not allowed to edit this item.'));
            }

        } else {
            // See filter "custom_table_{$databaseTable->getName()}_default_data"
            $this->setObjectId(Handler::insertObject(array()));

            // If not id, return to list
            if (empty($this->getObjectId())) {
                wp_redirect(Utility::getListLink($databaseTable->getName()));
                exit();
            }

            $this->setObject(Handler::getObject($this->getObjectId()));

            // If not object, die
            if (!$this->getObject())
                wp_die(__('Unable to create the draft item.'));

            // If not current user can create, die
            if (!current_user_can('create_item', $this->getObjectId()) && !current_user_can('edit_item', $this->getObjectId())) {
                wp_die(__('Sorry, you are not allowed to create items of this type.'));
            }

            // Redirect to edit screen to prevent add a draft item multiples times
            wp_redirect(Utility::getEditLink($databaseTable->getName(), $this->getObjectId()));
        }

    }

    public function screenSettings($screen_settings, $screen)
    {

        $this->renderMetaBoxesPreferences();
        $this->renderScreenLayout();

    }

    public function renderMetaBoxesPreferences()
    {
        /**
         * @var Table $databaseTable
         */
        global $wp_meta_boxes, $databaseTable;
        do_action('add_meta_boxes', $databaseTable->getName(), $this->getObject());

        /** This action is documented in wp-admin/edit-form-advanced.php */
        do_action("add_meta_boxes_{$databaseTable->getName()}", $this->getObject());

        /** This action is documented in wp-admin/edit-form-advanced.php */
        do_action('do_meta_boxes', $databaseTable->getName(), 'normal', $this->getObject());
        /** This action is documented in wp-admin/edit-form-advanced.php */
        do_action('do_meta_boxes', $databaseTable->getName(), 'advanced', $this->getObject());
        /** This action is documented in wp-admin/edit-form-advanced.php */
        do_action('do_meta_boxes', $databaseTable->getName(), 'side', $this->getObject());

        if (!isset($wp_meta_boxes[$databaseTable->getName()])) {
            return;
        }
        ?>

        <fieldset class="metabox-prefs">
            <legend><?php _e('Boxes'); ?></legend>
            <?php meta_box_prefs($databaseTable->getName()); ?>
        </fieldset>

        <?php
    }

    public function renderScreenLayout()
    {

        if ($this->getColumns() <= 1) {
            return;
        }

        $screen_layout_columns = get_current_screen()->get_columns();

        if (!$screen_layout_columns) {
            $screen_layout_columns = $this->getColumns();
        }

        $num = $this->getColumns();

        ?>
        <fieldset class='columns-prefs'>
            <legend class="screen-layout"><?php _e('Layout'); ?></legend><?php
            for ($i = 1; $i <= $num; ++$i):
                ?>
                <label class="columns-prefs-<?php echo $i; ?>">
                    <input type='radio' name='screen_columns' value='<?php echo esc_attr($i); ?>'
                        <?php checked($screen_layout_columns, $i); ?> />
                    <?php printf(_n('%s column', '%s columns', $i), number_format_i18n($i)); ?>
                </label>
            <?php
            endfor; ?>
        </fieldset>
        <?php
    }

    public function submitMetaBox($object)
    {
        /**
         * @var Table $databaseTable
         */
        global $databaseTable;

        do_action("custom_table_{$databaseTable->getName()}_edit_screen_submit_meta_box_top", $object, $databaseTable, $this->isEditing(), $this);

        $submit_label = __('Add');

        if ($this->isEditing()) {
            $submit_label = __('Update');
        }

        $submit_label = apply_filters("custom_table_{$databaseTable->getName()}_edit_screen_submit_label", $submit_label, $object, $databaseTable, $this->isEditing(), $this);

        $primaryKey = $databaseTable->getDatabase()->getPrimaryKey();
        $objectId = $object->$primaryKey;

        ?>

        <div class="submitbox" id="submitpost">

            <?php

            do_action("custom_table_{$databaseTable->getName()}_edit_screen_submit_meta_box_submit_post_top", $object, $databaseTable, $this->isEditing(), $this); ?>

            <div id="minor-publishing">

                <?php ob_start();

                do_action("custom_table_{$databaseTable->getName()}_edit_screen_submit_meta_box_minor_publishing_actions", $object, $databaseTable, $this->isEditing(), $this);
                $minor_publishing_actions = ob_get_clean(); ?>

                <?php // Since minor-publishing-actions has a margin, check if minor publishing actions has any content to render it or not
                if (!empty($minor_publishing_actions)) : ?>
                    <div id="minor-publishing-actions"><?php echo $minor_publishing_actions; ?></div>
                <?php endif; ?>

                <?php ob_start();

                do_action("custom_table_{$databaseTable->getName()}_edit_screen_submit_meta_box_misc_publishing_actions", $object, $databaseTable, $this->isEditing(), $this);
                $misc_publishing_actions = ob_get_clean(); ?>

                <?php // Since misc-publishing-actions has a margin, check if misc publishing actions has any content to render it or not
                if (!empty($misc_publishing_actions)) : ?>
                    <div id="misc-publishing-actions"><?php echo $misc_publishing_actions; ?></div>
                <?php endif; ?>

                <div class="clear"></div>

            </div>

            <div id="major-publishing-actions">

                <?php
                if (current_user_can($databaseTable->getCap()->delete_item, $objectId)) {

                    printf(
                        '<a href="%s" class="submitdelete deletion" onclick="%s" aria-label="%s">%s</a>',
                        Utility::getDeleteLink($databaseTable->getName(), $objectId),
                        "return confirm('" .
                        esc_attr(__("Are you sure you want to delete this item?\\n\\nClick \\'Cancel\\' to go back, \\'OK\\' to confirm the delete.")) .
                        "');",
                        esc_attr(__('Delete permanently')),
                        __('Delete Permanently')
                    );

                } ?>

                <div id="publishing-action">
                    <span class="spinner"></span>
                    <?php submit_button($submit_label, 'primary large', 'ct-save', false); ?>
                </div>

                <div class="clear"></div>

            </div>

            <?php

            do_action("custom_table_{$databaseTable->getName()}_edit_screen_submit_meta_box_submit_post_bottom", $object, $databaseTable, $this->isEditing(), $this); ?>

        </div>

        <?php

        do_action("custom_table_{$databaseTable->getName()}_edit_screen_submit_meta_box_bottom", $object, $databaseTable, $this->isEditing(), $this);
    }

    public function save()
    {
        /**
         * @var Table $databaseTable
         */
        global $registeredTables, $databaseTable;

        // If not CT object, die
        if (!$databaseTable)
            wp_die(__('Invalid item type.'));

        // If not CT object allow ui, die
        if (!$databaseTable->getShowUI()) {
            wp_die(__('Sorry, you are not allowed to edit items of this type.'));
        }

        $objectData = &$_POST;

        unset($objectData['custom-table-save']);

        $success = Handler::updateObject($objectData);

        $primaryKey = $databaseTable->getDatabase()->getPrimaryKey();
        $objectId = $_POST[$primaryKey];

        $location = add_query_arg(array($primaryKey => $objectId), $this->getLink());

        if ($success) {
            $location = add_query_arg(array('message' => 1), $location);
        } else {
            $location = add_query_arg(array('message' => 0), $location);
        }

        wp_redirect($location);
        exit;

    }

    public function preRender()
    {
        /**
         * @var Table $databaseTable
         */
        global $registeredTables, $databaseTable;

        $messages = array(
            0 => __('%s could not be updated.'),
            1 => __('%s updated successfully.'),
        );

        $messages = apply_filters('custom_table_table_updated_messages', $messages);

        // Setup screen message
        if (isset($_GET['message'])) {

            if (isset($messages[$_GET['message']]))
                $this->message = sprintf($messages[$_GET['message']], $databaseTable->getLabels()->singular_name);

        }

        wp_enqueue_script('post');

        if (wp_is_mobile()) {
            wp_enqueue_script('jquery-touch-punch');
        }

        // Register submitdiv metabox
        add_meta_box('submitdiv', __('Save Changes'), array($this, 'submit_meta_box'), $databaseTable->getName(), 'side', 'core');

        do_action('add_meta_boxes', $databaseTable->getName(), $this->getObject());

        do_action("add_meta_boxes_{$databaseTable->getName()}", $this->getObject());

        do_action('do_meta_boxes', $databaseTable->getName(), 'normal', $this->getObject());

        do_action('do_meta_boxes', $databaseTable->getName(), 'advanced', $this->getObject());

        do_action('do_meta_boxes', $databaseTable->getName(), 'side', $this->getObject());

        // TODO: Need to add it manually through screen_settings() function
        //add_screen_option( 'layout_columns', array( 'max' => $this->columns, 'default' => $this->columns ) );

    }

    public function render()
    {
        /**
         * @var Table $databaseTable
         */
        global $registeredTables, $databaseTable;

        $this->preRender();

        if ($this->isEditing()) {
            $title = $databaseTable->getLabels()->edit_item;
            $new_url = ($databaseTable->getViews()->add ? $databaseTable->getViews()->add->get_link() : false);
        } else {
            $title = $databaseTable->getLabels()->add_new_item;
        }

        ?>

        <div class="wrap">

            <h1 class="wp-heading-inline"><?php echo $title; ?></h1>

            <?php if (isset($new_url) && $new_url && current_user_can($databaseTable->getCap()->create_items)) :
                echo ' <a href="' . esc_url($new_url) . '" class="page-title-action">' . esc_html($databaseTable->getLabels()->add_new_item) . '</a>';
            endif; ?>

            <hr class="wp-header-end">

            <?php if ($this->getMessage()) : ?>
                <div id="message" class="updated notice notice-success is-dismissible">
                    <p><?php echo $this->getMessage(); ?></p></div>
            <?php endif; ?>

            <form name="custom_table_edit_form" action="" method="post" id="custom_table_edit_form">

                <input type="hidden" id="object_id" name="<?php echo $databaseTable->getDatabase()->getPrimaryKey(); ?>"
                       value="<?php echo $this->getObjectId(); ?>">

                <?php

                do_action('custom_table_edit_form_top', $this->getObject()); ?>

                <div id="poststuff">

                    <div id="post-body"
                         class="metabox-holder columns-<?php echo get_current_screen()->get_columns() === 1 || $this->getColumns() === 1 ? '1' : '2'; ?>">

                        <div id="postbox-container-1" class="postbox-container">

                            <?php do_meta_boxes($databaseTable->getName(), 'side', $this->getObject()); ?>

                        </div>

                        <div id="postbox-container-2" class="postbox-container">

                            <?php do_meta_boxes($databaseTable->getName(), 'normal', $this->getObject()); ?>

                            <?php do_meta_boxes($databaseTable->getName(), 'advanced', $this->getObject()); ?>

                        </div>

                    </div><!-- /post-body -->

                    <br class="clear"/>

                </div><!-- /poststuff -->

                <?php

                do_action('custom_table_edit_form_bottom', $this->getObject()); ?>

            </form>

        </div>

        <?php
    }

}