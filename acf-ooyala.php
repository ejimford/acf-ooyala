<?php
/**
 * Plugin Name: Advanced Custom Fields - Ooyala
 * Plugin URI: https://web.ejimford.com/
 * Description: Impement Ooyala into Advanced Custom Fields
 * Version: 1.0
 * Author: e. james ford
 * Author URI: https://web.ejimford.com
 *
 * This program is free software; you can redistribute it and/or modify it under the terms of the GNU
 * General Public License version 2, as published by the Free Software Foundation.  You may NOT assume
 * that you can use any other version of the GPL.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without
 * even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 */

global $ooyala_api_key;
global $ooyala_secret_key;
global $ooyala_player_id;

/**
 * IMPORTANT! Enter your Ooyala API Key, Ooyala Secret Key, and Ooyala Player Below
 */

$ooyala_api_key = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';
$ooyala_secret_key = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';
$ooyala_player_id = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';



/**
 * Load Ooyala SDK.
 */
require plugin_dir_path( __FILE__ ) . '/OoyalaApi.php';


/**
 * Add Ooyala Field Group
 * You can delete this if you want to create your own Field Group
 * Just make sure to create the field called ooyala_video_id
 */
function ooyala_register_fields() {
    if(function_exists("register_field_group"))
    {
        register_field_group(array (
            'id' => 'acf_video',
            'title' => 'video',
            'fields' => array (
                array (
                    'key' => 'field_ooyala_video_id',
                    'label' => 'Ooyala Video ID',
                    'name' => 'ooyala_video_id',
                    'type' => 'text',
                    'instructions' => 'Make sure there are no spaces at the beginning or end of your ID!',
                    'default_value' => '',
                    'placeholder' => '',
                    'prepend' => '',
                    'append' => '',
                    'formatting' => 'none',
                    'maxlength' => '',
                ),
            ),
            'location' => array (
                array (
                    array (
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'post',
                        'order_no' => 0,
                        'group_no' => 0,
                    ),
                ),
            ),
            'options' => array (
                'position' => 'normal',
                'layout' => 'default',
                'hide_on_screen' => array (
                ),
            ),
            'menu_order' => 0,
        ));
    }
}
add_action( 'init', 'ooyala_register_fields', 0 );


/**
 * Get Ooyala Core 
 */
function ooyala_scripts() {
    wp_enqueue_script( 'ooyala-core', '//player.ooyala.com/core/' . $ooyala_player_id, array(), '20151215', false );
}
add_action( 'wp_enqueue_scripts', 'ooyala_scripts' );


/**
 * Sideload the Ooyala Thumbnail as the WordPress Thumbnail
 */
function ooyala_image_thumbnail( $post_id, $post, $update ) {

    $this_post_id = get_the_ID($post_id);
    $this_post_title = get_the_title($post_id);
    $this_post_slug = get_post_field('post_name', $this_post_id);
    
    if (strlen($this_post_slug) > 36) {
        $this_post_slug = substr($this_post_slug, 0, 36);
    }    
    
    $ooyala_video_id = get_field('ooyala_video_id', $post_id);
    
    
    if (has_post_thumbnail($post)) {

    }
    else {

        //Get the Thumbnail Image From Ooyala API
        $api = new OoyalaApi($ooyala_api_key, $ooyala_secret_key);
        $parameters = array("where" => "embed_code='" . $ooyala_video_id . "'");
        try {
            $results = $api->get("assets", $parameters);
            $assets = $results->items;
            foreach($assets as $asset) {
                $api_img_url = $asset->preview_image_url_ssl; // Video Thumbnail
            }

            //If there IS an image from the API
            if ($api_img_url) {
                
                // ** IMPORTANT: Set Save Variables
                $save_name = $this_post_slug . '-thumbnail.jpg';
                $save_directory = plugin_dir_path( __FILE__ ) . '/ooyalathumbnails/';
                $save_url = plugin_dir_url( __FILE__ ) . $save_name;
                $save_full_path_to_temp_file = $save_directory . $save_name;


                //Save the File to a temporary directory.
                if(is_writable($save_directory)) {
                    file_put_contents($save_full_path_to_temp_file, file_get_contents($api_img_url));
                }
                
                // Sideload the image
                $new_att_id = media_sideload_image($save_url, $this_post_id, $this_post_title, 'id');
                // Attach the sideloaded image the post_thumbnail
                if(!is_wp_error($new_att_id)) {
                    set_post_thumbnail($this_post_id, $new_att_id); 
                }
                
                // Now Delete the Temporary File
                unlink($save_full_path_to_temp_file);
            }
        } 
        catch (OoyalaRequestErrorException $e) {
            return 0;
        }
    }
}

add_action( 'save_post', 'ooyala_image_thumbnail', 10, 3 );


/**
 * Add Ooyala Player to Posts
 */

function ooyala_player_content($content) {
    
    $ooyala_video_id = get_field(ooyala_video_id);
    $player_content_output = '';
    
    $player_content_output .= '<script>';
    $player_content_output .= "\n";
    $player_content_output .= '     OO.ready(function() {';
    $player_content_output .= "\n";
    $player_content_output .= '         window.pp = OO.Player.create("container", "' . $ooyala_video_id . '");';
    $player_content_output .= "\n";
    $player_content_output .= '     });';
    $player_content_output .= "\n";
    $player_content_output .= ' </script>';
    $player_content_output .= "\n";
            
              
    $fullcontent = $player_content_output . $content;
    
    return $fullcontent;
}
add_filter('the_content', 'ooyala_player_content');
