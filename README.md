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
- Front-end filters now auto-apply in real time on selection/input change (no manual “Filter Products” submit button required).
- Each created filter now has dedicated shortcode options:
  - Generic: `[productmaster_filter label="FILTER_LABEL"]`
  - Dynamic: `[productmaster_filter_FILTER_LABEL]` (sanitized label slug)
- Filter labels are enforced as unique to prevent shortcode collisions.
- Added per-filter **Edit** controls to customize filter presentation and preview output before placing shortcodes, including:
  - Theme/default colors vs custom colors
  - Font size and display text
  - Hierarchical mode (`Disabled` / `Enabled`) with parent-only display and nested child-category toggles when enabled
  - Checkbox icon customization
  - Include/exclude specific taxonomy terms via per-term toggle controls
  - Per-filter Custom CSS box with preview visibility (`{{WRAPPER}}` selector placeholder supported)
- Front-end filter styles are now enqueued to keep hierarchical indentation/toggles consistent between preview and rendered shortcodes.
- Legacy filter settings now auto-merge missing presentation keys (including `custom_css`) to prevent undefined index notices on shop pages.
- Added a Manual Hierarchy Map field so users can define parent/child relationships when hierarchical mode is enabled but taxonomy terms do not include native parent assignments.
- Hierarchical filter branches now stay open after refresh when a child value is selected, preserving context for active selections.
- When Manual Hierarchy Map is provided, only mapped terms are rendered/accepted for that filter and Included taxonomy term toggles are ignored.
- Included taxonomy term toggles are displayed in a scrollable textarea-style container with one term per line for easier administration on large taxonomies.
- When Manual Hierarchy Map is filled, Included taxonomy term toggles automatically sync to the mapped terms.
- For dropdown filter type with Manual Hierarchy Map enabled, preview/front-end now show parent dropdown plus child dropdown grouped under mapped parents.
- Included taxonomy term list now sorts selected items to the top, shows slug in a dedicated column, and (for Image boxes filters) includes a per-term “Select image” control.
- Image boxes filters now render parent-term images (default 40x40, adjustable in form-table) and reveal mapped child values in a hover menu.
- Filter term loading now uses non-empty and empty taxonomy terms so image-box parent values/images still render during setup before products are fully assigned.
- Parent label captions were moved into the child hover menu as a header for cleaner image-tile presentation.
- Child-menu headers in Image boxes now include a master checkbox toggle that selects/deselects all mapped child values for that parent and reflects mixed states during selection.
- Child values in Image boxes now render as image-backed options (thumbnail + label) rather than plain text rows.
- Image resolution for Image boxes now follows a consistent fallback chain: configured ProductMaster term image first, then SyncMaster/Smart Swatches term meta (`smart-swatches-framework--src`) when available.
- Taxonomy filters now support per-filter value matching mode (`OR` default or `AND`) for multi-value selection inside a single filter, while different filters are always combined with `AND`.
- Image-box child hover menus are now aligned to the start edge of the full image-box grid (instead of the hovered tile) for consistent menu placement.
- Image-box size controls in the filter editor now show width/height on one line for parent tiles, with a dedicated child tile size row (default `54x40`) for child values.
- Clicking a parent image tile now also toggles that parent’s child-menu master toggle and all mapped child values.
- 2026-04-29: Product Loop now uses a single saved loop layout with one shortcode target, includes backend card preview with a sample product, and supports per-element visibility + ordering controls for image, title, price, description, shop button, brand names, and categories.
- 2026-04-29: Product Loop builder now includes per-element HTML tag selectors (to inherit theme-default tag styling) and backend preview now respects configured column count by rendering multiple sample cards in the selected grid.
- 2026-04-29: Product Loop card/grid CSS now constrains each card to its grid column (`minmax(0,1fr)`, `min-width:0`, image max-width) so cards no longer stack as full-width rows when column counts are set.

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
- Add a dated bullet in **Current project status** for each user-requested change merged on this branch so upcoming iterations remain traceable.
- If a change is partially complete or depends on another plugin (for example SyncMaster metadata), explicitly note fallback behavior and follow-up work in README before opening a PR.
