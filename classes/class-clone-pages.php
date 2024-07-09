<?php
class CAHNRS_Clone_Pages {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_menu'));
    }

    public function add_settings_menu() {
        add_submenu_page(
            'options-general.php',
            'CAHNRS Clone Pages',
            'CAHNRS Clone Pages',
            'manage_options',
            'cahnrs-clone-pages-settings',
            array($this, 'settings_page')
        );
    }

    public function settings_page() {
        if (isset($_POST['action']) && $_POST['action'] === 'clone_pages') {
            $this->handle_clone_pages();
        }
        $this->display_settings_page();
    }

    private function handle_clone_pages() {
        $selected_site = isset($_POST['selected_site']) ? intval($_POST['selected_site']) : 0;
        $selected_pages = isset($_POST['pages_to_clone']) ? $_POST['pages_to_clone'] : array();

        echo "Selected Site: $selected_site<br>";
        echo "Selected Pages: " . implode(', ', $selected_pages) . "<br>";

        if ($selected_site && !empty($selected_pages)) {
            switch_to_blog($selected_site);
            echo "Switched to site ID: $selected_site<br>";

            $parent_pages_to_clone = array();
            foreach ($selected_pages as $page_id) {
                $this->clone_ancestor_pages($page_id, $parent_pages_to_clone);
            }

            $selected_pages = array_unique(array_merge($selected_pages, $parent_pages_to_clone));

            $new_page_id_menu_items = array();

            foreach ($selected_pages as $page_id) {
                $page = get_post($page_id);
                if ($page) {
                    echo "Cloning page ID: $page_id from site ID: $selected_site<br>";
                    $updated_content = $this->clean_content($page->post_content);
                    $original_parent_title = get_post_field('post_title', $page->post_parent);

                    restore_current_blog();

                    $new_page_id = $this->clone_page($page, $updated_content, $original_parent_title);
                    if ($new_page_id) {
                        $new_page_id_menu_items[] = $new_page_id;
                        $cloned_pages[] = $page_id;
                    }

                    switch_to_blog($selected_site);
                }
            }

            restore_current_blog();
            $this->create_menu_from_cloned_pages($new_page_id_menu_items);
        } else {
            echo "No selected pages to clone.<br>";
        }
    }

    private function clean_content($content) {
        $content = wp_filter_content_tags($content);
        $content = preg_replace('/\s(decoding="async"|srcset="[^"]*"|loading="[^"]*"|sizes="[^"]*"|width="[^"]*"|height="[^"]*")/', '', $content);
        $content = str_replace("\\", "\\\\", $content);

        preg_match_all('/"imageId"\s*:\s*(\d+).*?"imageSrc"\s*:\s*"(.*?)"/s', $content, $block_matches, PREG_SET_ORDER);

        foreach ($block_matches as $match) {
            $image_id = intval($match[1]);
            $image_url = $match[2];
            $image_id = attachment_url_to_postid($image_url);
            $content = preg_replace('/"imageId":\d+/', '"imageId":' . $image_id, $content);
        }

        return $content;
    }

    private function clone_page($page, $updated_content, $original_parent_title) {
        $new_page = array(
            'post_title' => $page->post_title,
            'post_content' => $updated_content,
            'post_status' => 'draft',
            'post_type' => 'page'
        );
        $new_page_id = wp_insert_post($new_page);

        if ($new_page_id) {
            echo "Page cloned successfully. New page ID: $new_page_id<br>";
            $this->set_page_parent($new_page_id, $page->post_parent, $original_parent_title);
        } else {
            echo "Failed to clone page.<br>";
        }

        return $new_page_id;
    }

    private function set_page_parent($new_page_id, $original_parent_id, $original_parent_title) {
        if ($original_parent_id) {
            $new_parent = get_page_by_title($original_parent_title, OBJECT, 'page');
            if ($new_parent) {
                wp_update_post(array(
                    'ID' => $new_page_id,
                    'post_parent' => $new_parent->ID
                ));
                echo "Parent page set for cloned page.<br>";
            } else {
                echo "Parent page not found for cloned page.<br>";
            }
        }
    }

    private function clone_ancestor_pages($page_id, &$cloned_pages) {
        $page = get_post($page_id);
        if ($page && $page->post_parent && !in_array($page->post_parent, $cloned_pages)) {
            $this->clone_ancestor_pages($page->post_parent, $cloned_pages);
            $cloned_pages[] = $page->post_parent;
            echo "Cloned ancestor page ID: {$page->post_parent}<br>";
        }
    }

    private function create_menu_from_cloned_pages($new_page_id_menu_items) {
        if (!empty($new_page_id_menu_items)) {
            $menu_name = 'Master Gardener Menu';
            $menu_exists = wp_get_nav_menu_object($menu_name);
    
            if (!$menu_exists) {
                $menu_id = wp_create_nav_menu($menu_name);
                if (is_wp_error($menu_id)) {
                    echo 'Failed to create menu: ' . $menu_id->get_error_message() . '<br>';
                    return;
                }
            } else {
                $menu_id = $menu_exists->term_id;
            }
    
            $menu_item_ids = array();
    
            foreach ($new_page_id_menu_items as $page_id) {
                $original_page = get_post($page_id);
                if (!$original_page) continue;
    
                $parent_id = $original_page->post_parent;
    
                $item = array(
                    'menu-item-object-id' => $page_id,
                    'menu-item-object' => 'page',
                    'menu-item-type' => 'post_type',
                    'menu-item-title' => get_the_title($page_id),
                    'menu-item-status' => 'publish',
                    'menu-item-parent-id' => isset($menu_item_ids[$parent_id]) ? $menu_item_ids[$parent_id] : 0
                );
    
                $menu_item_id = wp_update_nav_menu_item($menu_id, 0, $item);
    
                if (is_wp_error($menu_item_id)) {
                    echo 'Failed to add menu item for page ID ' . $page_id . ': ' . $menu_item_id->get_error_message() . '<br>';
                } else {
                    echo 'Added menu item for page ID ' . $page_id . '<br>';
                    $menu_item_ids[$page_id] = $menu_item_id; 
                }
            }
    
            echo 'Menu created successfully.<br>';
        } else {
            echo 'No pages cloned to create menu.<br>';
        }
    }
    
    

    public function display_settings_page() {
        ?>
        <div class="wrap">
            <h2>CAHNRS Clone Pages Settings</h2>
            <form method="post" action="">
                <?php
                $this->display_site_selector();
                ?>
                <input type="submit" name="load_pages" class="button-primary" value="Load Pages">
                <input type="hidden" name="action" value="load_pages">
            </form>

            <?php
            if (isset($_POST['load_pages'])) {
                $this->display_pages_form();
            }
            ?>
        </div>
        <?php
    }

    private function display_site_selector() {
        echo '<select name="selected_site">';
        $sites = get_sites();
        foreach ($sites as $site) {
            echo '<option value="' . esc_attr($site->blog_id) . '">' . esc_html(get_blog_details($site->blog_id)->blogname) . '</option>';
        }
        echo '</select>';
    }

    private function display_pages_form() {
        $selected_site = isset($_POST['selected_site']) ? intval($_POST['selected_site']) : 0;
        if ($selected_site) {
            switch_to_blog($selected_site);
            $site_title = get_blog_details($selected_site)->blogname;
            $pages = get_posts(array('post_type' => 'page', 'posts_per_page' => -1, 'post_status' => 'publish'));

            if ($pages) {
                echo '<h3>' . esc_html($site_title) . ' Pages:</h3>';
                echo '<form method="post" action="">';
                $selected_pages = isset($_POST['pages_to_clone']) ? $_POST['pages_to_clone'] : array();
                $this->display_pages_with_hierarchy($pages, $selected_pages);
                echo '<input type="submit" name="clone_pages" class="button-primary" value="Clone Pages">';
                echo '<input type="hidden" name="selected_site" value="' . esc_attr($selected_site) . '">';
                echo '<input type="hidden" name="action" value="clone_pages">';
                echo '</form>';
            } else {
                echo 'No pages found on this site.<br>';
            }
            restore_current_blog();
        }
    }

    private function display_pages_with_hierarchy($pages, $selected_pages = array(), $indent = 0, $parent_id = 0, $displayed_pages = array()) {
        foreach ($pages as $page) {
            if (is_array($displayed_pages) && in_array($page->ID, $displayed_pages)) {
                continue;
            }

            if ($page->post_parent != $parent_id) {
                continue;
            }

            $selected = is_array($selected_pages) && in_array($page->ID, $selected_pages) ? 'checked' : '';
            echo str_repeat('&nbsp;&nbsp;&nbsp;', $indent);
            echo '<label><input type="checkbox" name="pages_to_clone[]" value="' . esc_attr($page->ID) . '" ' . $selected . '>' . esc_html($page->post_title) . '</label><br>';
            $this->display_pages_with_hierarchy($pages, $selected_pages, $indent + 1, $page->ID, $displayed_pages);
            $displayed_pages[] = $page->ID;
        }
    }
}

new CAHNRS_Clone_Pages();
?>
