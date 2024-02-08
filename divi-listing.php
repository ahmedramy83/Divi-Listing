<?php
/*
Plugin Name: Divi Listing
Description: This plugin creates listing functionality for Divi Builder.
Version: 1.0
Author: Your Name
*/

// Activation hook
register_activation_hook(__FILE__, 'divi_listing_activate');

function divi_listing_activate() {
    // Activation tasks, if any
}

// Register custom taxonomy for hierarchical locations
function divi_listing_register_location_taxonomy() {
    $labels = array(
        'name' => 'Locations',
        'singular_name' => 'Location',
        'search_items' => 'Search Locations',
        'all_items' => 'All Locations',
        'parent_item' => 'Parent Location',
        'parent_item_colon' => 'Parent Location:',
        'edit_item' => 'Edit Location',
        'update_item' => 'Update Location',
        'add_new_item' => 'Add New Location',
        'new_item_name' => 'New Location Name',
        'menu_name' => 'Locations',
    );

    $args = array(
        'hierarchical' => true,
        'labels' => $labels,
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'location'),
    );

    register_taxonomy('location', array('post'), $args);
}
add_action('init', 'divi_listing_register_location_taxonomy');

// Add submenu item for Locations management
function divi_listing_menu() {
    add_menu_page(
        'Divi Listing',
        'Divi Listing',
        'manage_options',
        'divi_listing_menu',
        'divi_listing_menu_callback'
    );

    add_submenu_page(
        'divi_listing_menu',
        'Locations',
        'Locations',
        'manage_options',
        'divi_listing_locations',
        'divi_listing_locations_callback'
    );

    // Add submenu item to edit location
    add_submenu_page(
        null,
        'Edit Location',
        'Edit Location',
        'manage_options',
        'divi_listing_edit_location',
        'divi_listing_edit_location_callback'
    );
}
add_action('admin_menu', 'divi_listing_menu');

// Callback function for Locations and View Locations page
function divi_listing_locations_callback() {
    // Handle form submission to add location
    if (isset($_POST['add_location'])) {
        if (!isset($_POST['divi_listing_location_nonce']) || !wp_verify_nonce($_POST['divi_listing_location_nonce'], 'divi_listing_add_location')) {
            wp_die('Security check failed');
        }

        $location_name = sanitize_text_field($_POST['location_name']);
        $parent_location = isset($_POST['parent_location']) ? intval($_POST['parent_location']) : 0;

        // Add new location
        $result = wp_insert_term($location_name, 'location', array('parent' => $parent_location));

        if (!is_wp_error($result)) {
            $location_id = $result['term_id'];

            // Handle the featured image upload
            if (!empty($_FILES['location_image']['name'])) {
                $upload_overrides = array('test_form' => false);
                $uploaded_file = wp_handle_upload($_FILES['location_image'], $upload_overrides);

                if ($uploaded_file && !isset($uploaded_file['error'])) {
                    $upload_url = $uploaded_file['url'];
                    // Update the location term meta with the uploaded image URL
                    update_term_meta($location_id, 'location_image', $upload_url);
                }
            }

            echo '<div class="updated"><p>Location added successfully!</p></div>';
        } else {
            echo '<div class="error"><p>Failed to add location. Please try again.</p></div>';
        }
    }

    // Display Locations
    ?>
    <div class="wrap">
        <h1>Manage Locations</h1>
        <h2>Add Location</h2>
        <form method="post" action="" enctype="multipart/form-data">
            <?php wp_nonce_field('divi_listing_add_location', 'divi_listing_location_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="location_name">Location Name</label></th>
                    <td><input type="text" name="location_name" id="location_name" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="parent_location">Parent Location</label></th>
                    <td>
                        <select name="parent_location" id="parent_location">
                          <option value="0">Top Level</option>
                          <?php
                          // Function to recursively generate hierarchical options
                          function divi_listing_get_location_options($locations, $parent_id = 0, $prefix = '') {
                              foreach ($locations as $location) {
                                  if ($location->parent == $parent_id) {
                                      echo '<option value="' . $location->term_id . '">' . $prefix . $location->name . '</option>';
                                      divi_listing_get_location_options($locations, $location->term_id, $prefix . '-- ');
                                  }
                              }
                          }

                          // Retrieve locations
                          $locations = get_terms(array(
                              'taxonomy' => 'location',
                              'hide_empty' => false,
                          ));

                          // Generate hierarchical options
                          divi_listing_get_location_options($locations);
                          ?>
                      </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="location_image">Location Image</label></th>
                    <td><input type="file" name="location_image" id="location_image"></td>
                </tr>
            </table>
            <input type="submit" name="add_location" class="button-primary" value="Add Location">
        </form>

        <h2>View Locations</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Location Name</th>
                    <th>Number of Child Locations</th>
                    <th>Parent Location</th>
                    <th>Previous Level</th>
                    <th>Next Level</th>
                    <th>Edit</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Get the parent location ID from the URL query parameter
                $parent_location_id = isset($_GET['parent_location']) ? intval($_GET['parent_location']) : 0;

                // Display a link to the previous level if the current level is not the top level
                if ($parent_location_id !== 0) {
                    $parent_location = get_term($parent_location_id, 'location');
                    echo '<tr>';
                    echo '<td><a href="admin.php?page=divi_listing_locations&parent_location=' . $parent_location->parent . '">Previous Level</a></td>';
                    echo '<td></td>';
                    echo '<td></td>';
                    echo '<td></td>';
                    echo '<td></td>';
                    echo '<td></td>';
                    echo '</tr>';
                }

                // Retrieve locations based on the parent location ID
                $locations = get_terms(array(
                    'taxonomy' => 'location',
                    'hide_empty' => false,
                    'parent' => $parent_location_id
                ));

                // Modify the existing code in your divi_listing_locations_callback() function:

                foreach ($locations as $location) {
                  // Display each location and the number of child locations
                  echo '<tr id="location-' . $location->term_id . '">';
                  echo '<td>' . $location->name . '</td>';
                  echo '<td>';
                  // Get the number of child locations for the current location
                  $child_locations_count = count(get_terms(array(
                      'taxonomy' => 'location',
                      'hide_empty' => false,
                      'parent' => $location->term_id
                  )));
                  echo $child_locations_count;
                  echo '</td>';
                  echo '<td>' . get_parent_location_name($location->parent) . '</td>';
                  echo '<td></td>';
                  echo '<td>';
                  if ($child_locations_count > 0) {
                      echo '<a href="admin.php?page=divi_listing_locations&parent_location=' . $location->term_id . '">Next Level</a>';
                  }
                  echo '</td>';
                  echo '<td>';
                  echo '<a href="admin.php?page=divi_listing_edit_location&location_id=' . $location->term_id . '">Edit</a> | ';
                  echo '<a href="admin.php?page=divi_listing_quick_edit_location&location_id=' . $location->term_id . '" class="quick-edit-link">Quick Edit</a> | ';
                  echo '<a href="' . get_delete_post_link($location->term_id) . '">Trash</a> | ';
                  echo '<a href="admin.php?page=divi_listing_view_location&location_id=' . $location->term_id . '">View</a></td>';
                  echo '</tr>';
              }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}

// Helper function to get parent location name
function get_parent_location_name($parent_id) {
    if ($parent_id === 0) {
        return 'Top Level';
    }
    $parent_location = get_term($parent_id, 'location');
    return $parent_location ? $parent_location->name : '';
}

// Callback function to edit location
function divi_listing_edit_location_callback() {
    if (!isset($_GET['location_id'])) {
        wp_die('Invalid location ID');
    }

    $location_id = intval($_GET['location_id']);
    $location = get_term($location_id, 'location');
    $location_name = $location->name;
    $parent_location_id = $location->parent;
    $location_image = get_term_meta($location_id, 'location_image', true);

    ?>
    <div class="wrap">
        <h1>Edit Location</h1>
        <form method="post" action="" enctype="multipart/form-data">
            <?php wp_nonce_field('divi_listing_edit_location', 'divi_listing_edit_location_nonce'); ?>
            <input type="hidden" name="location_id" value="<?php echo $location_id; ?>">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="location_name">Location Name</label></th>
                    <td><input type="text" name="location_name" id="location_name" class="regular-text" value="<?php echo esc_attr($location_name); ?>" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="parent_location">Parent Location</label></th>
                    <td>
                        <select name="parent_location" id="parent_location">
                            <option value="0">Top Level</option>
                            <?php
                            $locations = get_terms('location', array('hide_empty' => false));
                            foreach ($locations as $location) {
                                $selected = ($location->term_id == $parent_location_id) ? 'selected' : '';
                                echo '<option value="' . $location->term_id . '" ' . $selected . '>' . get_parent_location_name($location->parent) . ' - ' . $location->name . '</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="location_image">Location Image</label></th>
                    <td>
                        <input type="file" name="location_image" id="location_image">
                        <?php if (!empty($location_image)) : ?>
                            <br><img src="<?php echo $location_image; ?>" style="max-width: 200px;">
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            <input type="submit" name="edit_location" class="button-primary" value="Save Changes">
        </form>
    </div>
    <?php
}

