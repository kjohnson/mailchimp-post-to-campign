<?php if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * Plugin Name: MailChimp - Post to Campaign
 * Plugin URI: http://kylebjohnson.me
 * Description: Create a MailChimp Campaign from a Post
 * Version: 0.0.1
 * Author: Kyle B. Johnson
 * Author URI: http://kylebjohnson.me
 */

if ( ! class_exists( 'Mailchimp' ) ) {
    require_once 'Mailchimp.php';
}

class KBJ_MailChimpPostToCampaign
{
    private $api_key = '0b69b108e80dcca17c6e53545def6836-us11';

    private $list_id = '48bb3f22c7';

    private $template_id = 54265;

    private $send = true;

    public function __construct()
    {
        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
        add_action( 'save_post', array( $this, 'save' ), 10, 3 );

        add_action( 'admin_menu', array( $this, 'submenu_page' ) );
    }

    public function add_meta_box()
    {
        add_meta_box(
            'KBJ_MailChimpPostToCampaign',
            __( 'Post to Campaign', 'KBJ_MailChimpPostToCampaign' ),
            array( $this, 'meta_box_callback' ),
            'post',
            'side'
        );
    }

    public function meta_box_callback( $post ) {

        // Add a nonce field so we can check for it later.
        //wp_nonce_field( 'myplugin_meta_box', 'myplugin_meta_box_nonce' );

        /*
         * Use get_post_meta() to retrieve an existing value
         * from the database and use the value for the form.
         */
        $value = get_post_meta( $post->ID, '_my_meta_value_key', true );

        include 'views/post_meta_box.html.php';
    }

    function save( $post_id, $post, $update )
    {
        if( ! $this->verify_nonce( $post_id ) )
            return $post_id;

        // If this is an autosave, our form has not been submitted,
        //      so we don't want to do anything.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
            return $post_id;

        // Check the user's permissions.
        if ( ! current_user_can( 'edit_post', $post_id ) )
            return $post_id;

        // Sanitize the user input.
        $mydata = sanitize_text_field( $_POST['myplugin_new_field'] );

        // Update the meta field.
        update_post_meta( $post_id, '_my_meta_value_key', $mydata );





        // If this is just a revision, don't send the email.
        if ( $update && $this->settings['create_campaign'] )
            return $post_id;
    }

    public function submenu_page()
    {
        add_submenu_page(
            'options-general.php',
            'MailChimp Post to Campaign',
            'Post to Campaign',
            'manage_options',
            'mailchimp-post-to-campaign',
            array( $this, 'submenu_page_callback' )
        );
    }

    public function submenu_page_callback()
    {
        if( isset( $_POST['settings'] ) ){
            $this->submenu_page_save( $_POST['settings'] );
        }

        include 'views/submenu_page.html.php';
    }

    private function verify_nonce( $post_id )
    {
        /*
         * We need to verify this came from the our screen and with proper authorization,
         * because save_post can be triggered at other times.
         */

        // Check if our nonce is set.
        if ( ! isset( $_POST['myplugin_inner_custom_box_nonce'] ) )
            return $post_id;

        $nonce = $_POST['myplugin_inner_custom_box_nonce'];

        return wp_verify_nonce( $nonce, 'myplugin_inner_custom_box' );
    }

    private function submenu_page_save( $settings )
    {
        foreach( $settings as $setting => $value ){
            // Sanitize the user input.
            $value = sanitize_text_field( $value );

            // Update the meta field.
            update_option( $setting, $value );
        }
    }

    private function create_campaign( $post )
    {
        $opts = array(
            'ssl_verifypeer' => false,
        );

        $api = new Mailchimp( $this->api_key, $opts );

        $options = array(
            'list_id' => $this->list_id,
            'subject' => $post->post_title,
            'from_email' => 'me@kylebjohnson.me',
            'from_name' => 'Kyle B. Johnson',
            'to_name' => 'John Doe',
            'template_id' => $this->template_id,
        );

        $content['sections']['post_title'] = '<a href="' . post_permalink( $post->id ) . '">' . $post->post_title . '</a>';
        $content['sections']['post_excerpt'] = ( $post->post_excerpt ) ? $post->post_excerpt : $post->post_content ;
        $content['sections']['after_post_excerpt'] = '<a href="' . post_permalink( $post->id ) . '">Continue reading...</a>';

        try {
            $campaign = $api->campaigns->create( 'regular', $options, $content );
        } catch (Mailchimp_Error $e) {
            wp_die( 'Unable to create campaign.', 'MailChimp Error' );
        }

        if( $this->send ){
            try {
                $send = $api->campaigns->send( $campaign['id'] );
            } catch (Mailchimp_Error $e) {
                wp_die('Unable to send campaign.', 'MailChimp Error');
            }
        }

    }

}

new KBJ_MailChimpPostToCampaign();