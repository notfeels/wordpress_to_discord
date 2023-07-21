<?php
/*
 * Plugin Name: Wordpress to Discord
 * Plugin URI: https://github.com/notfeels/wordpress_to_discord
 * Description: Automatically send posts from your Wordpress blog to Discord.
 * Version: 1.0
 * Author: notfeels
 * Author URI: https://github.com/notfeels/
*/

defined('ABSPATH') or die;

function post_to_discord($new_status, $old_status, $post) {
  if(get_option('discord_webhook_url') == null)
      return;

  if ($new_status != 'publish' || $old_status == 'publish' || $post->post_type != 'post')
      return;

  // get the necessary infos
  $webhook_url = get_option('discord_webhook_url');
  $id = $post->ID;
  $author_url = get_the_author_meta('user_url', $post->post_author);
  $author_name = get_the_author_meta('display_name', $post->post_author);
  $author_avatar = get_avatar_url($post->post_author);
  $post_link = get_permalink($id);
  $post_title = get_the_title($id);
  $post_excerpt = wp_strip_all_tags(get_the_excerpt($id));
  $message = "**NEW POST:** " . $post_link;
  $post_image = get_the_post_thumbnail_url($id, 'large');

  // check if post has thumbnail if not put a placeholder
  if (!$post_image)
    $post_image = "https://files.catbox.moe/jluzyp.png";

  // fix avatar size with local avatars
  $replace = '-96x96.';
  $pos = strpos($author_avatar, $replace, -(strlen($replace)+5)); // +5 for extension
  $final_author_avatar = substr_replace($author_avatar, '.', $pos, strlen($replace));

  $post_data = [
    "content" => $message,
    "embeds" => [
      [
        "title" => $post_title,
        "description" => $post_excerpt,
        "url" => $post_link,
        "color" => 15010068,
        "author" => [
          "name" => $author_name,
          "url" => $author_url,
          "icon_url" => $final_author_avatar
      ],
        "image" => [
          "url" => $post_image
        ]
      ]
    ]
  ];

  $curl = curl_init($webhook_url);
  curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
  curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($post_data));
  curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
  curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

  $response = curl_exec($curl);
  $errors = curl_error($curl);

  log_message($errors);
}

function log_message($log) {
  if (true === WP_DEBUG) {
    if (is_array($log) || is_object($log)) {
        error_log(print_r($log, true));
    } else {
        error_log($log);
    }
  }
}

add_action('transition_post_status', 'post_to_discord', 10, 3);

function post_to_discord_section_callback() {
  echo "<p>A valid Discord Webhook URL to the announcements channel is required.</p>";
}

function post_to_discord_input_callback() {
  echo '<input name="discord_webhook_url" id="discord_webhook_url" type="text" value="' . get_option('discord_webhook_url') . '">';
}

function post_to_discord_settings_init() {
 add_settings_section(
   'discord_webhook_url',
   'Post to Discord',
   'post_to_discord_section_callback',
   'general'
 );

 add_settings_field(
   'discord_webhook_url',
   'Discord Webhook URL',
   'post_to_discord_input_callback',
   'general',
   'discord_webhook_url'
 );

 register_setting( 'general', 'discord_webhook_url' );
}

add_action( 'admin_init', 'post_to_discord_settings_init' );
