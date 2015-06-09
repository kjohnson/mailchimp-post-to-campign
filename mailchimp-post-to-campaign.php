<?php if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * Plugin Name: MailChimp - Post to Campaign
 * Plugin URI: http://kylebjohnson.me
 * Description: Create a MailChimp Campaign from a Post
 * Version: 0.0.1
 * Author: Kyle B. Johnson
 * Author URI: http://kylebjohnson.me
 */

class KBJ_MailChimpPostToCampaign
{
    private $api_key = '0b69b108e80dcca17c6e53545def6836-us11';

    private $list_id = '48bb3f22c7';

    private $template_id = 54265;

    private $send = true;

    public function __construct()
    {
        add_action( 'save_post', array( $this, 'create_campaign'), 10, 3 );
    }

    function create_campaign( $post_id, $post, $update )
    {
        // If this is just a revision, don't send the email.
        if ( ! $update )
            return;

        if ( ! class_exists( 'Mailchimp' ) ) {
            require_once 'Mailchimp.php';
        }

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

        $content['sections']['post_title'] = '<a href="' . post_permalink( $post_id ) . '">' . $post->post_title . '</a>';
        $content['sections']['post_excerpt'] = ( $post->post_excerpt ) ? $post->post_excerpt : $post->post_content ;
        $content['sections']['after_post_excerpt'] = '<a href="' . post_permalink( $post_id ) . '">Continue reading...</a>';

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