# ProductMaster

ProductMaster is a WordPress plugin for WooCommerce stores that need a focused backend portal to manage apparel inventory visibility.

## Current project status (v0.1.0)

Implemented:

- WordPress plugin bootstrap (`productmaster.php`) with localization support and version constants.
- Admin menu page: **ProductMaster** with submenu items for **Inventory**, **Review Tools**, **Taxonomy Filters**, and **Review Builder**.
- Initial backend portal for WooCommerce admins (`manage_woocommerce`) that displays:
  - All published variable products.
  - Pagination (20 products per page) to keep load times manageable for large catalogs.
  - A per-product toggle to reveal variation inventory.
  - Variations grouped by color with hidden-by-default toggles per color.
  - Size-specific cards in an 8-column grid, each showing SKU, stock status, inventory quantity, and a visual stock bar.
  - Clearly labeled **Inventory Value** controls that allow real-time stock updates per size variation directly from the Inventory screen.
  - Grid/card spacing tweaks to prevent overlap between size cards in dense catalogs.
- Basic admin styling for readability.
- Taxonomy Filters admin workspace now supports creating shortcode-based filters from existing product categories/attributes with default filter UIs for:
  - Checkboxes
  - Image boxes
  - Drop down selectors
  - Sliders (price range)
  - Search Fields
  - Currently Selected Filters
  - Reset Products Button
- Each created filter now has dedicated shortcode options:
  - Generic: `[productmaster_filter label="FILTER_LABEL"]`
  - Dynamic: `[productmaster_filter_FILTER_LABEL]` (sanitized label slug)
- Filter labels are enforced as unique to prevent shortcode collisions.
- Added per-filter **Edit** controls to customize filter presentation and preview output before placing shortcodes, including:
  - Theme/default colors vs custom colors
  - Font size and display text
  - Hierarchical mode (`Disabled` / `Enables`) with parent-only display and child-category toggle support when enabled
  - Checkbox icon customization
  - Include/exclude specific taxonomy terms

## Requirements

- WordPress 6.0+
- PHP 7.4+
- WooCommerce active

## Installation (development)

1. Place this project in your WordPress plugins directory.
2. Activate **ProductMaster** in WordPress admin.
3. Ensure products are created as variable products and use attributes for size/color as needed.
4. Open **ProductMaster** from the WordPress admin sidebar.

## Product direction / roadmap

### 1) Elementor-integrated taxonomy filter tooling

Goal:

- Provide shortcode-powered taxonomy filters for product archive pages.
- Support style customization to blend with active theme.
- Integrate with product loops/grids for near real-time filtering UX.

Planned work:

- Shortcode API design (`[productmaster_filters ...]`).
- Front-end filtering behavior and Elementor compatibility testing.
- Admin configuration screen for visual style tokens.

### 2) Product review import + moderation system

Goal:

- Support import and moderation of 1–5 star reviews tied to products.
- Render reviews in a way that is SEO-friendly and supports better search visibility.

Planned work:

- Import adapters and validation flow.
- Moderation queue in admin.
- Structured-data friendly output patterns for single product pages.

### 3) Visual review display builder

Goal:

- Add visual controls for layout and presentation of reviews on product pages.

Planned work:

- Template presets and style controls.
- Placement configuration for WooCommerce single product hooks.
- Consistency checks across responsive breakpoints.

## Notes for contributors

- Keep this README updated as each feature is added, changed, or removed.
- Document new admin screens, shortcode options, hooks, and any data model changes.
- For every release increment, update the “Current project status” and roadmap progress.
