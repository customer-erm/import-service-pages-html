# Doc to Service Page Converter - Plugin Documentation

**Version:** 2.2
**Author:** Elite Results Marketing
**License:** GPL v2 or later

---

## Table of Contents

1. [Overview](#overview)
2. [Installation & Setup](#installation--setup)
3. [User Guide](#user-guide)
4. [Developer Reference](#developer-reference)
5. [LLM Context for Development](#llm-context-for-development)

---

## Overview

### What This Plugin Does

Doc to Service Page Converter automates the creation of content-rich WordPress posts from HTML files exported from Google Docs. It parses HTML structure and creates posts with dynamically generated ACF (Advanced Custom Fields) field groups.

### Key Features

- **HTML to Post Conversion** - Converts Google Docs HTML exports to WordPress posts
- **3 Post Types Supported** - Service Pages, Buyer's Guides, City Service Pages
- **Dynamic ACF Fields** - Automatically creates field groups based on content
- **FAQ Extraction** - Parses Q&A sections for Buyer's Guide posts
- **Batch Processing** - Upload and process multiple files at once
- **Content Structure** - Extracts titles, intros, sections with bullets

### Use Cases

- Converting documentation to service pages
- Creating buyer's guide content from structured docs
- Building city/location-based service pages
- Bulk content migration from Google Docs

---

## Installation & Setup

### Requirements

- WordPress 5.0+
- **Advanced Custom Fields (ACF)** plugin - REQUIRED
- PHP with DOMDocument extension

### Installation Steps

1. **Install ACF First**
   - Install and activate Advanced Custom Fields plugin
   - Free or Pro version works

2. **Upload Plugin**
   - Upload `import-service-pages-html` folder to `/wp-content/plugins/`
   - OR upload as ZIP via Plugins → Add New → Upload

3. **Activate**
   - Go to Plugins in WordPress admin
   - Click "Activate" on Doc to Service Page Converter
   - Menu item "Doc Converter" appears

### No Additional Configuration

- Plugin is ready to use after activation
- No settings page or options to configure
- ACF field groups created automatically on first use

---

## User Guide

### Preparing Google Docs for Export

**Required Document Structure:**

```
[H1] Page Title
[Paragraph] Introduction text

[H2] Section 1 Title
[Paragraph] Section content...
[Bullet List] Optional bullet points

[H2] Section 2 Title
[Paragraph] More content...

[H2] FAQs (for Buyer's Guides)
Question 1?
Answer to question 1
Question 2?
Answer to question 2
```

**Export Steps:**
1. In Google Docs, go to File → Download → Web Page (.html, zipped)
2. Extract the HTML file from the ZIP
3. Use that HTML file for import

### Converting Documents

1. **Go to Doc Converter** in WordPress admin

2. **Select Post Type:**
   - **Service Pages** - General service content
   - **Buyer's Guides** - Product/service guides with FAQs
   - **City Service Pages** - Location-based service pages

3. **Upload HTML Files:**
   - Click "Choose Files"
   - Select one or more HTML files
   - Files must be Google Docs HTML exports

4. **Process:**
   - Click "Process HTML Files"
   - Plugin creates posts with extracted content
   - Success/error messages displayed

### What Gets Extracted

| HTML Element | Becomes |
|--------------|---------|
| First `<h1>` | Post title |
| First `<p>` | Introduction field |
| Each `<h2>` | Row title |
| `<p>` after `<h2>` | Row copy |
| `<ul><li>` items | Bullet fields |
| FAQ section | Question/Answer fields |

### Post Types Created

**Service Pages** (`services`)
- Service Name, Short Description
- H1 Heading, Intro Copy
- Dynamic rows (title, copy, photo)
- FAQ repeater

**Buyer's Guides** (`buyers_guide`)
- Headline, Intro
- Up to 23 rows with varying fields
- Special FAQ fields (questions 1-5, answers 1-5)
- Bullet fields on certain rows

**City Service Pages** (`near-me`)
- Headline, Intro Text
- 6 content rows (title, copy, photo, link)
- Directions Map, Geo Referencing
- Places of Interest
- CTA Row

### After Import

- Posts created as drafts or published
- Images set to empty (add manually via media library)
- Edit posts to add featured images
- Review and adjust content as needed

---

## Developer Reference

### File Structure

```
import-service-pages-html/
└── process.php    # Single file containing all plugin code
```

### Custom Post Types

| Post Type | Slug | Supports |
|-----------|------|----------|
| Service Pages | `services` | title, editor, custom-fields, thumbnail |
| Buyer's Guides | `buyers_guide` | title, editor, custom-fields, thumbnail |
| City Service Pages | `near-me` | title, editor, custom-fields, thumbnail |

All are public, REST API enabled, and support ACF.

### Key Functions

| Function | Purpose |
|----------|---------|
| `doc_service_register_post_types()` | Registers 3 custom post types |
| `doc_service_add_admin_menu()` | Adds Doc Converter menu |
| `doc_service_admin_page()` | Renders admin form, handles uploads |
| `doc_service_parse_html($path, $type)` | Extracts content from HTML |
| `doc_service_register_dynamic_service_fields($count)` | Creates service ACF fields |
| `doc_service_register_dynamic_buyers_guide_fields($count)` | Creates buyer's guide fields |
| `doc_service_register_dynamic_city_service_fields($count)` | Creates city service fields |
| `doc_service_update_service_acf_fields($id, $data)` | Populates service fields |
| `doc_service_update_buyers_guide_acf_fields($id, $data)` | Populates buyer's guide fields |
| `doc_service_update_city_service_acf_fields($id, $data)` | Populates city fields |

### HTML Parsing Logic

Uses PHP's DOMDocument and DOMXPath:

```php
// Load HTML
$doc = new DOMDocument();
$doc->loadHTMLFile($html_path);
$xpath = new DOMXPath($doc);

// Extract title (first h1)
$h1 = $xpath->query('//h1')->item(0);

// Extract intro (first p)
$p = $xpath->query('//p')->item(0);

// Extract sections (h2 elements)
$h2s = $xpath->query('//h2');
```

### ACF Field Structure

**Service Pages:**
```
service_name (text)
short_description (textarea)
icon (image)
h1_heading (text)
intro_copy (wysiwyg)
row_1 (group)
  ├── title (text)
  ├── copy (wysiwyg)
  └── photo (image)
row_2 ... row_N
faqs (repeater)
```

**Buyer's Guides:**
```
headline (text)
intro (wysiwyg)
row_1 ... row_23 (groups)
  ├── title (text)
  ├── copy (wysiwyg)
  ├── photo (image)
  └── bullet_1-5 (text) [certain rows]
row_21 has: question_1-5, answer_1-5
```

**City Service Pages:**
```
headline (text)
intro_text (wysiwyg)
row_1 ... row_6 (groups)
  ├── title (text)
  ├── copy (wysiwyg)
  ├── photo (image)
  └── link (url)
row_7 (CTA)
  └── link (url)
directions_map (group)
geo_referencing (group)
places_of_interest (group)
```

### WordPress Hooks Used

| Hook | Function | Purpose |
|------|----------|---------|
| `init` | `doc_service_register_post_types` | Register post types |
| `admin_menu` | `doc_service_add_admin_menu` | Add admin menu |
| `admin_notices` | (inline) | ACF dependency warning |

### Security Implementation

- `wp_verify_nonce()` - Form submission verification
- `sanitize_text_field()` - Text input sanitization
- `sanitize_file_name()` - File name sanitization
- `wp_upload_bits()` - Safe file upload handling
- Capability checks before processing

### Error Logging

All operations logged to WordPress debug.log with `[Doc Converter]` prefix:
- HTML file processing start/finish
- File upload status
- Parsing results
- Post creation
- ACF field updates

---

## LLM Context for Development

### Quick Reference for AI Assistants

**Architecture:**
- Single-file plugin (process.php)
- Procedural PHP (no classes)
- Depends on ACF plugin
- Creates 3 custom post types
- Dynamically generates ACF field groups

**Key Sections in process.php:**

| Line Range | Content |
|------------|---------|
| 1-50 | Plugin header, constants |
| 50-120 | Post type registration |
| 120-200 | Admin menu and page |
| 200-350 | HTML parsing function |
| 350-500 | Service page ACF fields |
| 500-700 | Buyer's guide ACF fields |
| 700-850 | City service ACF fields |
| End | ACF update functions |

**Data Flow:**
1. User uploads HTML file(s) via admin form
2. `doc_service_admin_page()` handles upload
3. File saved via `wp_upload_bits()`
4. `doc_service_parse_html()` extracts content
5. New post created with `wp_insert_post()`
6. Dynamic ACF fields registered based on row count
7. ACF fields populated with parsed content

**Common Modifications:**

*Adding a new post type:*
1. Add to `doc_service_register_post_types()`
2. Add option to admin form dropdown
3. Create `doc_service_register_dynamic_NEWTYPE_fields()` function
4. Create `doc_service_update_NEWTYPE_acf_fields()` function
5. Add case in admin page processing

*Adding a new field to existing type:*
1. Add field definition in register function
2. Handle in update function
3. Optionally parse from HTML

*Modifying HTML parsing:*
1. Edit `doc_service_parse_html()` function
2. Add new XPath queries for elements
3. Add to returned data array
4. Handle in appropriate update function

**Parsed Data Structure:**
```php
[
    'title' => 'Page Title',
    'intro' => 'Introduction paragraph',
    'rows' => [
        [
            'title' => 'Section Title',
            'copy' => 'Paragraph content',
            'bullets' => ['bullet 1', 'bullet 2']
        ],
        // ... more rows
    ],
    'faqs' => [  // Buyer's guide only
        ['question' => '...', 'answer' => '...'],
    ]
]
```

**Testing:**
1. Ensure ACF is active
2. Create test Google Doc with proper structure
3. Export as HTML
4. Go to Doc Converter in admin
5. Select post type, upload file
6. Check created post and ACF fields
7. View debug.log for any errors

### Prompt Template for Development Tasks

```
I need to modify the Doc to Service Page Converter WordPress plugin.

TASK: [describe what you want to change]

CONTEXT:
- Single file: process.php (all code in one file)
- Procedural PHP, no classes
- Requires ACF plugin

Post types created:
- services (Service Pages)
- buyers_guide (Buyer's Guides)
- near-me (City Service Pages)

Key functions:
- doc_service_register_post_types() - registers post types
- doc_service_admin_page() - admin UI and file handling
- doc_service_parse_html($path, $type) - HTML parsing with DOMDocument
- doc_service_register_dynamic_*_fields($count) - creates ACF fields
- doc_service_update_*_acf_fields($id, $data) - populates fields

HTML parsing uses DOMDocument/DOMXPath to extract:
- First h1 = title
- First p = intro
- Each h2 = section/row title
- p after h2 = row copy
- ul/li = bullets

ACF fields created dynamically based on number of rows parsed.

Please provide the specific code changes needed.
```

### ACF Field Key Generation

Field keys use MD5 hash of row count for uniqueness:
```php
$hash = md5($row_count);
$field_key = 'field_service_row_' . $i . '_' . $hash;
```

This ensures field groups are recreated if structure changes.

---

## Troubleshooting

### Common Issues

| Issue | Solution |
|-------|----------|
| "ACF not active" notice | Install and activate Advanced Custom Fields |
| No content extracted | Check HTML structure matches expected format |
| Missing fields | Check row count matches content sections |
| FAQs not parsing | Ensure FAQ section title contains "faq" or "question" |

### Debug Logging

Enable WordPress debug logging to see detailed processing:
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check `/wp-content/debug.log` for `[Doc Converter]` entries.

---

## Support

**Author:** Elite Results Marketing
**Website:** https://www.eliteresultsmarketing.com
