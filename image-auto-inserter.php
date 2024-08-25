<?php
/*
Plugin Name: Image Auto Inserter
Description: Automatically insert images before each H2 tag in your blog posts.
Version: 1.2
Author: Majdi M. S. Awad
Author URI: https://github.com/majdi-php-sql?tab=repositories
*/

// Hook to add admin menu items
add_action('admin_menu', 'iai_add_admin_menu');

// Hook to enqueue scripts and styles
add_action('admin_enqueue_scripts', 'iai_enqueue_scripts');

// Function to enqueue scripts and styles
function iai_enqueue_scripts($hook) {
    if ($hook !== 'toplevel_page_image-auto-inserter' && $hook !== 'image-auto-inserter_page_iai-settings') {
        return;
    }

    // Enqueue custom JS
    wp_enqueue_script('iai-media-uploader', plugin_dir_url(__FILE__) . 'js/media-uploader.js', array('jquery'), '1.2', true);

    // Enqueue custom CSS
    wp_enqueue_style('iai-style', plugin_dir_url(__FILE__) . 'css/style.css');
}

// Function to add the admin menu and settings page
function iai_add_admin_menu() {
    // Add top-level menu page
    add_menu_page(
        'Image Auto Inserter',
        'Image Inserter',
        'manage_options',
        'image-auto-inserter',
        'iai_admin_page',
        'dashicons-format-gallery',
        20
    );

    // Add settings submenu page
    add_submenu_page(
        'image-auto-inserter',
        'Image Inserter Settings',
        'Settings',
        'manage_options',
        'iai-settings',
        'iai_settings_page'
    );
}

// Callback function for the main admin page
function iai_admin_page() {
    ?>
    <div class="wrap">
        <h1>Image Auto Inserter</h1>
        <form method="post" action="">
            <?php
            // Check if form has been submitted
            if (isset($_POST['iai_insert_images'])) {
                try {
                    iai_insert_images_to_posts();
                    echo '<div class="updated"><p>Images have been successfully inserted into posts!</p></div>';
                } catch (Exception $e) {
                    echo '<div class="error"><p>Error: ' . esc_html($e->getMessage()) . '</p></div>';
                }
            }
            ?>
            <p>Click the button below to insert images before each H2 tag in your existing posts.</p>
            <input type="submit" name="iai_insert_images" class="button-primary" value="Insert Images">
        </form>
    </div>
    <?php
}

// Callback function for the settings page
function iai_settings_page() {
    if (isset($_POST['save_iai_settings'])) {
        // Handle file upload
        if (!empty($_FILES['iai_image_files']['name'][0])) {
            $uploaded_files = $_FILES['iai_image_files'];
            $image_ids = iai_handle_file_uploads($uploaded_files);
            update_option('iai_selected_images', implode(',', $image_ids));
            echo '<div class="updated"><p>Settings saved successfully!</p></div>';
        }
    }

    // Retrieve saved image IDs
    $selected_images = get_option('iai_selected_images', '');
    $image_ids = explode(',', $selected_images);
    ?>
    <div class="wrap">
        <h1>Image Inserter Settings</h1>
        <form method="post" action="" enctype="multipart/form-data">
            <p>Select images to upload:</p>
            <input type="file" name="iai_image_files[]" multiple>
            <input type="submit" name="save_iai_settings" class="button-primary" value="Upload Images">
        </form>
    </div>
    <?php
}

// Function to handle file uploads and return image IDs
function iai_handle_file_uploads($files) {
    $image_ids = array();
    $file_count = count($files['name']);

    for ($i = 0; $i < $file_count; $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $file = array(
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i]
            );
            
            // Upload file
            $attachment_id = media_handle_sideload($file, 0);
            if (is_wp_error($attachment_id)) {
                continue;
            }
            
            $image_ids[] = $attachment_id;
        }
    }

    return $image_ids;
}

// Function to insert images into posts
function iai_insert_images_to_posts() {
    $args = array(
        'numberposts' => -1,
        'post_type'   => 'post',
        'post_status' => 'publish'
    );

    $posts = get_posts($args);

    foreach ($posts as $post) {
        $content = $post->post_content;
        $images = iai_get_selected_images();

        if (empty($images)) {
            continue;
        }

        $content_parts = explode('<h2>', $content);
        $new_content = array_shift($content_parts);

        foreach ($content_parts as $index => $part) {
            $image_id = isset($images[$index % count($images)]) ? $images[$index % count($images)] : '';
            $image_url = $image_id ? wp_get_attachment_url($image_id) : '';

            if ($image_url) {
                $img_tag = '<img src="' . esc_url($image_url) . '" alt="Auto Inserted Image" style="margin: 10px 0;">';
                $new_content .= $img_tag . '<h2>' . $part;
            } else {
                $new_content .= '<h2>' . $part;
            }
        }

        wp_update_post(array(
            'ID' => $post->ID,
            'post_content' => $new_content
        ));
    }
}

// Function to get selected images from settings
function iai_get_selected_images() {
    $image_ids = get_option('iai_selected_images', '');
    return empty($image_ids) ? array() : explode(',', $image_ids);
}
?>
