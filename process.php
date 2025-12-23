<?php
/*
Plugin Name: Doc to Service Page Converter
Description: Converts uploaded HTML files from Google Docs into Service posts, Buyer's Guide posts, or City Service Pages with dynamically generated ACF fields based on content rows, including FAQs.
Version: 2.2
Author: ERM
*/

// Ensure Advanced Custom Fields is active.
if (!function_exists('acf_add_local_field_group')) {
    add_action('admin_notices', function () {
        echo '<div class="error"><p><strong>Doc to Service Page Converter</strong> requires the Advanced Custom Fields plugin.</p></div>';
    });
    return;
}

/**
 * Register the Services, Buyer's Guide, and Near-Me custom post types with thumbnail support.
 */
function doc_service_register_post_types() {
    // Services Post Type
    register_post_type('services', [
        'public' => true,
        'label' => 'Services',
        'supports' => ['title', 'editor', 'custom-fields', 'thumbnail'],
        'show_in_rest' => true,
    ]);

    // Buyer's Guide Post Type
    register_post_type('buyers_guide', [
        'public' => true,
        'label' => 'Buyer\'s Guides',
        'supports' => ['title', 'editor', 'custom-fields', 'thumbnail'],
        'show_in_rest' => true,
        'rewrite' => ['slug' => 'buyers_guide'],
    ]);

    // Near-Me Post Type (for City Service Pages)
    register_post_type('near-me', [
        'public' => true,
        'label' => 'City Service Pages',
        'supports' => ['title', 'editor', 'custom-fields', 'thumbnail'],
        'show_in_rest' => true,
    ]);
}
add_action('init', 'doc_service_register_post_types');

/**
 * Register Admin Menu Page.
 */
function doc_service_add_admin_menu() {
    add_menu_page(
        'Doc Converter',
        'Doc Converter',
        'manage_options',
        'doc-service-converter',
        'doc_service_admin_page',
        'dashicons-media-document',
        20
    );
}
add_action('admin_menu', 'doc_service_add_admin_menu');

/**
 * Admin Page Callback - Interface for uploading and processing HTML files.
 */
function doc_service_admin_page() {
    ?>
    <div class="wrap">
        <h1>Doc to Service Page Converter</h1>
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['doc_service_process'])) {
            error_log('[Doc Converter] Starting HTML processing...');
            if (!isset($_POST['doc_service_admin_nonce']) || !wp_verify_nonce($_POST['doc_service_admin_nonce'], 'doc_service_admin_process')) {
                echo '<div class="error"><p>Security check failed.</p></div>';
                error_log('[Doc Converter] Nonce verification failed.');
            } else {
                $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'services';
                if (isset($_FILES['doc_service_files']) && !empty($_FILES['doc_service_files']['name'][0])) {
                    $files = $_FILES['doc_service_files'];
                    $total_files = count($files['name']);
                    echo '<p>Processing ' . intval($total_files) . ' HTML file(s) as ' . esc_html($post_type) . '...</p>';
                    error_log("[Doc Converter] Processing $total_files HTML file(s) as $post_type.");
                    $processed = 0;
                    for ($i = 0; $i < $total_files; $i++) {
                        error_log("[Doc Converter] Processing file $i: " . $files['name'][$i]);
                        if ($files['error'][$i] === UPLOAD_ERR_OK) {
                            $file_tmp = $files['tmp_name'][$i];
                            $file_name = sanitize_file_name($files['name'][$i]);
                            error_log("[Doc Converter] Uploading file: $file_name");
                            $upload = wp_upload_bits($file_name, null, file_get_contents($file_tmp));
                            if (!$upload['error']) {
                                $html_path = $upload['file'];
                                error_log("[Doc Converter] File uploaded to: $html_path");
                                $parsed = doc_service_parse_html($html_path, $post_type);
                                if ($parsed) {
                                    error_log("[Doc Converter] HTML parsed. Title: " . $parsed['title'] . ", Rows: " . count($parsed['rows']));
                                    if ($post_type === 'services') {
                                        doc_service_register_dynamic_service_fields(count($parsed['rows']));
                                    } elseif ($post_type === 'buyers_guide') {
                                        doc_service_register_dynamic_buyers_guide_fields(count($parsed['rows']));
                                    } elseif ($post_type === 'near-me') {
                                        doc_service_register_dynamic_city_service_fields(count($parsed['rows']));
                                    }
                                    $new_post = array(
                                        'post_title' => $parsed['title'],
                                        'post_status' => 'publish',
                                        'post_type' => $post_type,
                                    );
                                    $new_post_id = wp_insert_post($new_post);
                                    if ($new_post_id) {
                                        error_log("[Doc Converter] New post created with ID: $new_post_id");
                                        // Update ACF fields based on post type
                                        if ($post_type === 'services') {
                                            doc_service_update_service_acf_fields($new_post_id, $parsed);
                                        } elseif ($post_type === 'buyers_guide') {
                                            doc_service_update_buyers_guide_acf_fields($new_post_id, $parsed);
                                        } elseif ($post_type === 'near-me') {
                                            doc_service_update_city_service_acf_fields($new_post_id, $parsed);
                                        }
                                        $processed++;
                                    } else {
                                        echo '<div class="error"><p>Failed to create post for HTML: ' . esc_html($file_name) . '.</p></div>';
                                        error_log("[Doc Converter] Failed to create post for HTML: $file_name.");
                                    }
                                } else {
                                    echo '<div class="error"><p>Failed to parse HTML: ' . esc_html($file_name) . '.</p></div>';
                                    error_log("[Doc Converter] Failed to parse HTML: $file_name.");
                                }
                            } else {
                                echo '<div class="error"><p>File upload failed for ' . esc_html($file_name) . ': ' . esc_html($upload['error']) . '</p></div>';
                                error_log("[Doc Converter] File upload failed for $file_name: " . $upload['error']);
                            }
                        } else {
                            echo '<div class="error"><p>Upload error for ' . esc_html($files['name'][$i]) . ': ' . esc_html($files['error'][$i]) . '</p></div>';
                            error_log("[Doc Converter] Upload error for " . $files['name'][$i] . ": " . $files['error'][$i]);
                        }
                    }
                    echo '<div class="updated"><p>Successfully processed ' . intval($processed) . ' HTML file(s).</p></div>';
                    error_log("[Doc Converter] Successfully processed $processed HTML file(s).");
                } else {
                    echo '<div class="error"><p>No HTML files were uploaded.</p></div>';
                    error_log("[Doc Converter] No HTML files were uploaded.");
                }
            }
        }
        ?>
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('doc_service_admin_process', 'doc_service_admin_nonce'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Select Post Type</th>
                    <td>
                        <select name="post_type">
                            <option value="services">Service Pages</option>
                            <option value="buyers_guide">Buyer's Guide</option>
                            <option value="near-me">City Service Pages</option>
                        </select>
                        <p class="description">Choose the type of content to import.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Upload HTML Files</th>
                    <td>
                        <input type="file" name="doc_service_files[]" multiple accept="text/html" />
                        <p class="description">Export your Google Doc as Web Page (.html) via File > Download > Web Page (.html, zipped), then upload the .html file here.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Process HTML Files', 'primary', 'doc_service_process'); ?>
        </form>
    </div>
    <?php
}

/**
 * Parse the HTML file to extract content based on post type.
 */
function doc_service_parse_html($html_path, $post_type) {
    error_log("[Doc Converter] Starting HTML parsing for: $html_path, Post Type: $post_type");
    $html_content = file_get_contents($html_path);
    if ($html_content === false) {
        error_log("[Doc Converter] Failed to read HTML file: $html_path");
        return false;
    }
    error_log("[Doc Converter] HTML content loaded. Length: " . strlen($html_content) . " characters.");

    $doc = new DOMDocument();
    @$doc->loadHTML($html_content);
    $xpath = new DOMXPath($doc);

    // Extract title (first <h1>)
    $h1s = $xpath->query('//h1');
    $title = $h1s->length > 0 ? trim($h1s->item(0)->textContent) : 'Untitled';
    error_log("[Doc Converter] Title set: $title");

    // Extract intro (first paragraph after <h1>)
    $paragraphs = $xpath->query('//p');
    $intro = $paragraphs->length > 0 ? trim($paragraphs->item(0)->textContent) : '';
    error_log("[Doc Converter] Intro set: " . substr($intro, 0, 50) . "...");

    // Extract rows (<h2> and following <p> or lists)
    $rows = [];
    $h2s = $xpath->query('//h2');
    foreach ($h2s as $index => $h2) {
        $row_title = trim($h2->textContent);
        $copy = '';
        $bullets = [];
        $next = $h2->nextSibling;
        while ($next && $next->nodeName !== 'h2') {
            if ($next->nodeName === 'p') {
                $copy .= trim($next->textContent) . "\n";
            } elseif ($next->nodeName === 'ul') {
                $lis = $xpath->query('.//li', $next);
                foreach ($lis as $li) {
                    $bullet_text = trim($li->textContent);
                    if (!empty($bullet_text)) {
                        $bullets[] = $bullet_text;
                    }
                }
            }
            $next = $next->nextSibling;
        }
        $copy = trim($copy);
        if (!empty($row_title)) {
            $rows[] = [
                'title' => $row_title,
                'copy' => $copy,
                'bullets' => $bullets,
            ];
            error_log("[Doc Converter] Row " . ($index + 1) . " added: $row_title, Bullets: " . count($bullets));
        }
    }

    // Extract FAQs for Buyer's Guide (last section with questions and answers)
    $faqs = [];
    if ($post_type === 'buyers_guide' && !empty($rows)) {
        $last_row = end($rows);
        if (stripos($last_row['title'], 'question') !== false || stripos($last_row['title'], 'faq') !== false) {
            $faq_lines = explode("\n", $last_row['copy']);
            $current_question = '';
            foreach ($faq_lines as $line) {
                $line = trim($line);
                if (preg_match('/^[^-].*\?$/', $line)) {
                    $current_question = $line;
                } elseif (!empty($current_question) && !empty($line)) {
                    $faqs[] = [
                        'question' => $current_question,
                        'answer' => $line,
                    ];
                    $current_question = '';
                }
            }
            array_pop($rows); // Remove FAQ section from rows
        }
    }

    $parsed = [
        'title' => $title,
        'intro' => $intro,
        'rows' => $rows,
    ];
    if ($post_type === 'services') {
        $parsed['service_name'] = $title;
        $parsed['short_description'] = $intro;
        $parsed['h1_heading'] = $title;
        $parsed['intro_copy'] = $intro;
    } elseif ($post_type === 'buyers_guide') {
        $parsed['faqs'] = $faqs;
    } elseif ($post_type === 'near-me') {
        $parsed['intro_text'] = $intro;
    }
    error_log("[Doc Converter] HTML parsing completed. Rows found: " . count($rows) . ", FAQs: " . count($faqs));
    return $parsed;
}

/**
 * Register dynamic ACF fields for Service Pages.
 */
function doc_service_register_dynamic_service_fields($row_count) {
    $group_key = 'group_dynamic_service_' . md5($row_count);
    if (!acf_get_field_group($group_key)) {
        $fields = [
            [
                'key' => 'field_63e531e72812b',
                'label' => 'Service Name',
                'name' => 'service_name',
                'type' => 'text',
            ],
            [
                'key' => 'field_64d3d57ed8f35',
                'label' => 'Short Description',
                'name' => 'short_description',
                'type' => 'textarea',
            ],
            [
                'key' => 'field_64d3d2265cb9a',
                'label' => 'Mobile Hero',
                'name' => 'icon',
                'type' => 'image',
                'return_format' => 'id',
            ],
            [
                'key' => 'field_63e6c89541742',
                'label' => 'H1 Heading',
                'name' => 'h1_heading',
                'type' => 'text',
            ],
            [
                'key' => 'field_63f2aa1a72c61',
                'label' => 'Intro Copy',
                'name' => 'intro_copy',
                'type' => 'wysiwyg',
            ],
        ];

        for ($i = 1; $i <= $row_count; $i++) {
            $row_key = 'field_67b910' . sprintf('%02d', $i + 53) . 'adae8';
            $fields[] = [
                'key' => $row_key,
                'label' => "Row $i",
                'name' => "row_$i",
                'type' => 'group',
                'sub_fields' => [
                    [
                        'key' => $row_key . '_title',
                        'label' => 'Title',
                        'name' => 'title',
                        'type' => 'text',
                    ],
                    [
                        'key' => $row_key . '_copy',
                        'label' => 'Copy',
                        'name' => 'copy',
                        'type' => 'wysiwyg',
                    ],
                    [
                        'key' => $row_key . '_photo',
                        'label' => 'Photo',
                        'name' => 'photo',
                        'type' => 'image',
                        'return_format' => 'id',
                    ],
                ],
            ];
        }

        $fields[] = [
            'key' => 'field_644af6f260f76',
            'label' => 'FAQs',
            'name' => 'faqs',
            'type' => 'repeater',
            'sub_fields' => [
                [
                    'key' => 'field_644af6fe60f77',
                    'label' => 'Question',
                    'name' => 'question',
                    'type' => 'textarea',
                ],
                [
                    'key' => 'field_644af70a60f78',
                    'label' => 'Answer',
                    'name' => 'answer',
                    'type' => 'wysiwyg',
                ],
            ],
        ];

        acf_add_local_field_group([
            'key' => $group_key,
            'title' => 'Dynamic Service Fields (' . $row_count . ' Rows)',
            'fields' => $fields,
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'services',
                    ],
                ],
            ],
            'position' => 'acf_after_title',
            'style' => 'seamless',
            'label_placement' => 'left',
        ]);
        error_log("[Doc Converter] Dynamic Service ACF field group '$group_key' registered with $row_count rows.");
    }
}

/**
 * Register dynamic ACF fields for Buyer's Guide based on provided JSON.
 */
function doc_service_register_dynamic_buyers_guide_fields($row_count) {
    $group_key = 'group_6807e16816080'; // Match JSON group key
    if (!acf_get_field_group($group_key)) {
        $fields = [
            [
                'key' => 'field_6807e169336e2',
                'label' => 'Headline',
                'name' => 'headline',
                'type' => 'text',
                'default_value' => '',
                'maxlength' => '',
                'allow_in_bindings' => 0,
                'placeholder' => '',
                'prepend' => '',
                'append' => '',
            ],
            [
                'key' => 'field_6807e3ff336e3',
                'label' => 'Intro',
                'name' => 'intro',
                'type' => 'wysiwyg',
                'default_value' => '',
                'allow_in_bindings' => 0,
                'tabs' => 'all',
                'toolbar' => 'full',
                'media_upload' => 1,
                'delay' => 0,
            ],
        ];

        // Define row configurations based on JSON
        $row_configs = [
            1 => ['key' => 'field_6807e41b336e4', 'sub_fields' => ['title_', 'copy', 'photo']],
            2 => ['key' => 'field_6807e43b336e8', 'sub_fields' => ['title_', 'copy', 'photo']],
            3 => ['key' => 'field_6807e43f336ec', 'sub_fields' => ['title_', 'copy', 'photo']],
            4 => ['key' => 'field_6807e442336f0', 'sub_fields' => ['title_', 'copy', 'photo']],
            5 => ['key' => 'field_6807e444336f4', 'sub_fields' => ['title_', 'copy', 'photo']],
            6 => ['key' => 'field_6807e5f3336f8', 'sub_fields' => ['title_', 'copy', 'photo', 'bullet_1', 'photo_2', 'bullet_2', 'photo_3', 'bullet_3', 'photo_4', 'bullet_4', 'photo_5', 'bullet_5', 'copy2']],
            7 => ['key' => 'field_68081e85c01af', 'sub_fields' => ['title_', 'copy', 'photo']],
            8 => ['key' => 'field_68081eadc01bd', 'sub_fields' => ['title_', 'copy', 'photo', 'bullet_1', 'photo_2', 'bullet_2', 'photo_3', 'bullet_3', 'photo_4', 'bullet_4', 'copy2']],
            9 => ['key' => 'field_68081edfc01cb', 'sub_fields' => ['title_', 'copy', 'photo', 'bullet_1', 'photo_2', 'bullet_2', 'photo_3', 'bullet_3', 'photo_4', 'bullet_4', 'copy2']],
            10 => ['key' => 'field_68081ee1c01d7', 'sub_fields' => ['title_', 'copy', 'photo', 'bullet_1', 'photo_2', 'bullet_2', 'photo_3', 'bullet_3', 'copy2']],
            11 => ['key' => 'field_68081f02c01e3', 'sub_fields' => ['title_', 'copy', 'photo']],
            12 => ['key' => 'field_68081fb066141', 'sub_fields' => ['title_', 'copy', 'photo']],
            13 => ['key' => 'field_68081fb366145', 'sub_fields' => ['title_', 'copy', 'photo']],
            14 => ['key' => 'field_68081fb566149', 'sub_fields' => ['title_', 'copy', 'photo']],
            15 => ['key' => 'field_68081fb76614d', 'sub_fields' => ['title_', 'copy', 'photo']],
            16 => ['key' => 'field_68081fba66151', 'sub_fields' => ['title_', 'copy', 'photo']],
            17 => ['key' => 'field_68081fbc66155', 'sub_fields' => ['title_', 'copy', 'photo']],
            18 => ['key' => 'field_68081fbe66159', 'sub_fields' => ['title_', 'copy', 'photo']],
            19 => ['key' => 'field_68081fc06615d', 'sub_fields' => ['title_', 'copy', 'photo']],
            20 => ['key' => 'field_68081fc366161', 'sub_fields' => ['title_', 'copy', 'photo']],
            21 => ['key' => 'field_68081fca66165', 'sub_fields' => ['title_', 'copy', 'copy2', 'question_1', 'answer_1', 'question_2', 'answer_2', 'question_3', 'answer_3', 'question_4', 'answer_4', 'question_5', 'answer_5']],
            22 => ['key' => 'field_6808202166174', 'sub_fields' => ['title_', 'copy', 'photo']],
            23 => ['key' => 'field_6808204666183', 'sub_fields' => ['title_', 'copy', 'photo']],
        ];

        // Add fields for the number of rows parsed (up to 23)
        for ($i = 1; $i <= min($row_count, 23); $i++) {
            $row_key = $row_configs[$i]['key'];
            $sub_fields = [];
            foreach ($row_configs[$i]['sub_fields'] as $sub_field_name) {
                $sub_field_key = $row_key . '_' . $sub_field_name;
                if ($sub_field_name === 'title_') {
                    $sub_fields[] = [
                        'key' => $sub_field_key,
                        'label' => 'Title',
                        'name' => 'title_',
                        'type' => 'text',
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ];
                } elseif ($sub_field_name === 'copy' || $sub_field_name === 'copy2' || strpos($sub_field_name, 'bullet_') === 0) {
                    $sub_fields[] = [
                        'key' => $sub_field_key,
                        'label' => ucfirst(str_replace('_', ' ', $sub_field_name)),
                        'name' => $sub_field_name,
                        'type' => 'wysiwyg',
                        'default_value' => '',
                        'allow_in_bindings' => 0,
                        'tabs' => 'all',
                        'toolbar' => 'full',
                        'media_upload' => 1,
                        'delay' => 0,
                    ];
                } elseif (strpos($sub_field_name, 'photo') === 0) {
                    $sub_fields[] = [
                        'key' => $sub_field_key,
                        'label' => ucfirst(str_replace('_', ' ', $sub_field_name)),
                        'name' => $sub_field_name,
                        'type' => 'image',
                        'return_format' => 'id',
                        'library' => 'all',
                        'preview_size' => 'medium',
                        'allow_in_bindings' => 0,
                    ];
                } elseif (strpos($sub_field_name, 'question_') === 0 || strpos($sub_field_name, 'answer_') === 0) {
                    $sub_fields[] = [
                        'key' => $sub_field_key,
                        'label' => ucfirst(str_replace('_', ' ', $sub_field_name)),
                        'name' => $sub_field_name,
                        'type' => 'text',
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                    ];
                }
            }
            $fields[] = [
                'key' => $row_key,
                'label' => "Row $i",
                'name' => "row_$i",
                'type' => 'group',
                'layout' => 'block',
                'sub_fields' => $sub_fields,
            ];
        }

        acf_add_local_field_group([
            'key' => $group_key,
            'title' => 'Buyer\'s Guide',
            'fields' => $fields,
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'buyers_guide',
                    ],
                ],
            ],
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ]);
        error_log("[Doc Converter] Dynamic Buyer's Guide ACF field group '$group_key' registered with $row_count rows.");
    }
}

/**
 * Register dynamic ACF fields for City Service Pages.
 */
function doc_service_register_dynamic_city_service_fields($row_count) {
    $group_key = 'group_dynamic_city_service_' . md5($row_count);
    if (!acf_get_field_group($group_key)) {
        $fields = [
            [
                'key' => 'field_63af748c642f0',
                'label' => 'Headline',
                'name' => 'headline',
                'type' => 'text',
            ],
            [
                'key' => 'field_68100cf1e3ee3',
                'label' => 'Intro Text',
                'name' => 'intro_text',
                'type' => 'wysiwyg',
            ],
        ];

        for ($i = 1; $i <= $row_count; $i++) {
            $row_key = 'field_68100c' . sprintf('%02d', $i + 99) . 'e3ec' . ($i + 1);
            $fields[] = [
                'key' => $row_key,
                'label' => "Row $i",
                'name' => "row_$i",
                'type' => 'group',
                'sub_fields' => [
                    [
                        'key' => $row_key . '_title',
                        'label' => 'Title',
                        'name' => 'title',
                        'type' => 'text',
                    ],
                    [
                        'key' => $row_key . '_copy',
                        'label' => 'Copy',
                        'name' => 'copy',
                        'type' => 'wysiwyg',
                    ],
                    [
                        'key' => $row_key . '_photo',
                        'label' => 'Photo',
                        'name' => 'photo',
                        'type' => 'image',
                        'return_format' => 'id',
                    ],
                    [
                        'key' => $row_key . '_link',
                        'label' => 'Link',
                        'name' => 'link',
                        'type' => 'url',
                    ],
                ],
            ];
        }

        $fields[] = [
            'key' => 'field_63f3a443f3eaf',
            'label' => 'Directions Map',
            'name' => 'directions_map',
            'type' => 'wysiwyg',
        ];
        $fields[] = [
            'key' => 'field_63f3a45bf3eb0',
            'label' => 'Geo Referencing',
            'name' => 'geo_referencing',
            'type' => 'group',
            'sub_fields' => [
                [
                    'key' => 'field_63f3a4e9f3eb1',
                    'label' => 'Geo Location Image',
                    'name' => 'geo_location_image',
                    'type' => 'image',
                    'return_format' => 'id',
                ],
                [
                    'key' => 'field_63f3a4fdf3eb2',
                    'label' => 'Image Attribution',
                    'name' => 'image_attribution',
                    'type' => 'text',
                ],
            ],
        ];
        $fields[] = [
            'key' => 'field_63f3a508f3eb3',
            'label' => 'Places of Interest',
            'name' => 'places_of_interest',
            'type' => 'wysiwyg',
        ];
        $fields[] = [
            'key' => 'field_68100ce3e3ede',
            'label' => 'CTA Row',
            'name' => 'row_7',
            'type' => 'group',
            'sub_fields' => [
                [
                    'key' => 'field_68100ce3e3edf',
                    'label' => 'Title',
                    'name' => 'title',
                    'type' => 'text',
                ],
                [
                    'key' => 'field_68100ce3e3ee0',
                    'label' => 'Copy',
                    'name' => 'copy',
                    'type' => 'wysiwyg',
                ],
                [
                    'key' => 'field_68100ce3e3ee1',
                    'label' => 'Photo',
                    'name' => 'photo',
                    'type' => 'image',
                    'return_format' => 'id',
                ],
                [
                    'key' => 'field_68100ce3e3ee2',
                    'label' => 'Link',
                    'name' => 'link',
                    'type' => 'url',
                ],
            ],
        ];

        acf_add_local_field_group([
            'key' => $group_key,
            'title' => 'Dynamic City Service Fields (' . $row_count . ' Rows)',
            'fields' => $fields,
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'near-me',
                    ],
                ],
            ],
            'position' => 'acf_after_title',
            'style' => 'seamless',
        ]);
        error_log("[Doc Converter] Dynamic City Service ACF field group '$group_key' registered with $row_count rows.");
    }
}

/**
 * Update ACF fields for Service Pages.
 */
function doc_service_update_service_acf_fields($post_id, $parsed) {
    update_field('field_63e531e72812b', $parsed['service_name'], $post_id);
    update_field('field_64d3d57ed8f35', $parsed['short_description'], $post_id);
    update_field('field_64d3d2265cb9a', 0, $post_id); // No image
    update_field('field_63e6c89541742', $parsed['h1_heading'], $post_id);
    update_field('field_63f2aa1a72c61', $parsed['intro_copy'], $post_id);

    foreach ($parsed['rows'] as $index => $row) {
        $row_key = 'field_67b910' . sprintf('%02d', $index + 54) . 'adae8';
        update_field($row_key, [
            'title' => $row['title'],
            'copy' => $row['copy'],
            'photo' => 0, // No image
        ], $post_id);
        error_log("[Doc Converter] Service Row " . ($index + 1) . " updated with title: " . $row['title']);
    }
}

/**
 * Update ACF fields for Buyer's Guide.
 */
function doc_service_update_buyers_guide_acf_fields($post_id, $parsed) {
    update_field('field_6807e169336e2', $parsed['title'], $post_id);
    update_field('field_6807e3ff336e3', $parsed['intro'], $post_id);

    $row_configs = [
        1 => ['key' => 'field_6807e41b336e4', 'fields' => ['title_', 'copy', 'photo']],
        2 => ['key' => 'field_6807e43b336e8', 'fields' => ['title_', 'copy', 'photo']],
        3 => ['key' => 'field_6807e43f336ec', 'fields' => ['title_', 'copy', 'photo']],
        4 => ['key' => 'field_6807e442336f0', 'fields' => ['title_', 'copy', 'photo']],
        5 => ['key' => 'field_6807e444336f4', 'fields' => ['title_', 'copy', 'photo']],
        6 => ['key' => 'field_6807e5f3336f8', 'fields' => ['title_', 'copy', 'photo', 'bullet_1', 'photo_2', 'bullet_2', 'photo_3', 'bullet_3', 'photo_4', 'bullet_4', 'photo_5', 'bullet_5', 'copy2']],
        7 => ['key' => 'field_68081e85c01af', 'fields' => ['title_', 'copy', 'photo']],
        8 => ['key' => 'field_68081eadc01bd', 'fields' => ['title_', 'copy', 'photo', 'bullet_1', 'photo_2', 'bullet_2', 'photo_3', 'bullet_3', 'photo_4', 'bullet_4', 'copy2']],
        9 => ['key' => 'field_68081edfc01cb', 'fields' => ['title_', 'copy', 'photo', 'bullet_1', 'photo_2', 'bullet_2', 'photo_3', 'bullet_3', 'photo_4', 'bullet_4', 'copy2']],
        10 => ['key' => 'field_68081ee1c01d7', 'fields' => ['title_', 'copy', 'photo', 'bullet_1', 'photo_2', 'bullet_2', 'photo_3', 'bullet_3', 'copy2']],
        11 => ['key' => 'field_68081f02c01e3', 'fields' => ['title_', 'copy', 'photo']],
        12 => ['key' => 'field_68081fb066141', 'fields' => ['title_', 'copy', 'photo']],
        13 => ['key' => 'field_68081fb366145', 'fields' => ['title_', 'copy', 'photo']],
        14 => ['key' => 'field_68081fb566149', 'fields' => ['title_', 'copy', 'photo']],
        15 => ['key' => 'field_68081fb76614d', 'fields' => ['title_', 'copy', 'photo']],
        16 => ['key' => 'field_68081fba66151', 'fields' => ['title_', 'copy', 'photo']],
        17 => ['key' => 'field_68081fbc66155', 'fields' => ['title_', 'copy', 'photo']],
        18 => ['key' => 'field_68081fbe66159', 'fields' => ['title_', 'copy', 'photo']],
        19 => ['key' => 'field_68081fc06615d', 'fields' => ['title_', 'copy', 'photo']],
        20 => ['key' => 'field_68081fc366161', 'fields' => ['title_', 'copy', 'photo']],
        21 => ['key' => 'field_68081fca66165', 'fields' => ['title_', 'copy', 'copy2', 'question_1', 'answer_1', 'question_2', 'answer_2', 'question_3', 'answer_3', 'question_4', 'answer_4', 'question_5', 'answer_5']],
        22 => ['key' => 'field_6808202166174', 'fields' => ['title_', 'copy', 'photo']],
        23 => ['key' => 'field_6808204666183', 'fields' => ['title_', 'copy', 'photo']],
    ];

    foreach ($parsed['rows'] as $index => $row) {
        $row_number = $index + 1;
        if ($row_number > 23) {
            break; // Limit to 23 rows
        }
        $row_key = $row_configs[$row_number]['key'];
        $row_data = [
            'title_' => $row['title'],
            'copy' => $row['copy'],
            'photo' => 0, // No image
        ];

        // Handle special rows
        if (in_array($row_number, [6, 8, 9, 10])) {
            // Populate bullets
            for ($i = 1; $i <= 5; $i++) {
                if (isset($row['bullets'][$i - 1])) {
                    $row_data["bullet_$i"] = $row['bullets'][$i - 1];
                } else {
                    $row_data["bullet_$i"] = '';
                }
                $row_data["photo_$i"] = 0; // No image
            }
            // Populate copy2
            $row_data['copy2'] = $row['copy'];
        } elseif ($row_number == 21) {
            // Populate FAQs
            foreach ($parsed['faqs'] as $faq_index => $faq) {
                $faq_number = $faq_index + 1;
                if ($faq_number <= 5) {
                    $row_data["question_$faq_number"] = $faq['question'];
                    $row_data["answer_$faq_number"] = $faq['answer'];
                }
            }
            $row_data['copy2'] = $row['copy'];
        } else {
            // Standard rows
            $row_data['photo'] = 0; // No image
        }

        update_field($row_key, $row_data, $post_id);
        error_log("[Doc Converter] Buyer's Guide Row $row_number updated with title: " . $row['title']);
    }

    // Clear any unused rows
    for ($i = count($parsed['rows']) + 1; $i <= 23; $i++) {
        $row_key = $row_configs[$i]['key'];
        $row_data = array_fill_keys($row_configs[$i]['fields'], '');
        foreach ($row_data as $key => &$value) {
            if (strpos($key, 'photo') === 0) {
                $value = 0;
            }
        }
        update_field($row_key, $row_data, $post_id);
        error_log("[Doc Converter] Buyer's Guide Row $i cleared.");
    }
}

/**
 * Update ACF fields for City Service Pages.
 */
function doc_service_update_city_service_acf_fields($post_id, $parsed) {
    update_field('field_63af748c642f0', $parsed['title'], $post_id);
    update_field('field_68100cf1e3ee3', $parsed['intro_text'], $post_id);

    $row_count = count($parsed['rows']);
    $max_rows = 6; // Rows 1-6 in ACF
    for ($i = 0; $i < $max_rows; $i++) {
        $row_key = 'field_68100c' . sprintf('%02d', $i + 100) . 'e3ec' . ($i + 2);
        if ($i < $row_count) {
            $row = $parsed['rows'][$i];
            update_field($row_key, [
                'title' => $row['title'],
                'copy' => $row['copy'],
                'photo' => 0,
                'link' => '',
            ], $post_id);
            error_log("[Doc Converter] City Service Row " . ($i + 1) . " updated with title: " . $row['title']);
        } else {
            update_field($row_key, [
                'title' => '',
                'copy' => '',
                'photo' => 0,
                'link' => '',
            ], $post_id);
        }
    }

    // CTA Row (row_7)
    $cta_row_key = 'field_68100ce3e3ede';
    $last_row = end($parsed['rows']);
    update_field($cta_row_key, [
        'title' => $last_row['title'],
        'copy' => $last_row['copy'],
        'photo' => 0,
        'link' => '',
    ], $post_id);
    error_log("[Doc Converter] City Service CTA Row updated with title: " . $last_row['title']);
}