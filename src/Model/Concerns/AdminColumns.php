<?php

declare(strict_types=1);

namespace Sloth\Model\Concerns;

/**
 * Trait for managing WordPress admin list table columns.
 *
 * This trait provides functionality to customize the admin post list columns
 * for custom post types. It handles:
 * - Adding custom columns to the posts list table
 * - Populating column content with model data
 * - Making columns sortable
 * - Reordering columns (date column placed last)
 * - Handling the primary column when title is hidden
 *
 * ## Usage
 *
 * Models using this trait should define:
 * - $admin_columns: Array of column_key => column_label pairs
 * - $admin_columns_hidden: Array of column keys to hide from default columns
 *
 * Each custom column requires a getter method named get{ColumnKey}Column()
 * that returns the HTML content to display in the cell.
 *
 * @example
 * ```php
 * class Project extends Model
 * {
 *     use AdminColumns;
 *
 *     public array $admin_columns = [
 *         'client' => 'Client',
 *         'status' => 'Status',
 *     ];
 *
 *     public function getClientColumn(): string
 *     {
 *         return esc_html($this->client);
 *     }
 *
 *     public function getStatusColumn(): string
 *     {
 *         return '<span class="status-' . esc_attr($this->status) . '">' . esc_html($this->status) . '</span>';
 *     }
 * }
 * ```
 *
 * @since 1.0.0
 * @see \Sloth\Model\Model
 */
trait AdminColumns
{
    /**
     * Custom columns to display in the admin list table.
     *
     * Defines additional columns beyond the default WordPress columns
     * (title, author, date, categories, tags, etc.).
     *
     * ## Array Format
     * ```php
     * [
     *     'column_key' => 'Column Label',
     *     'client' => 'Client',
     *     'status' => 'Status',
     * ]
     * ```
     *
     * ## Method Requirement
     * For each column key, the model must implement a corresponding
     * getter method: get{ColumnKey}Column()
     *
     * Example for 'client' column:
     * ```php
     * public function getClientColumn(): string
     * {
     *     return esc_html($this->client ?? '-');
     * }
     * ```
     *
     * ## Usage Example
     * ```php
     * class Project extends Model
     * {
     *     use AdminColumns;
     *
     *     public array $admin_columns = [
     *         'client' => 'Client',
     *         'deadline' => 'Deadline',
     *     ];
     *
     *     public function getClientColumn(): string
     *     {
     *         return $this->client
     *             ? '<a href="/clients/' . $this->client->ID . '">' . esc_html($this->client->post_title) . '</a>'
     *             : '-';
     *     }
     * }
     * ```
     *
     * @since 1.0.0
     * @see AdminColumns::registerColumns() For how columns are registered
     * @see AdminColumns::populateColumns() For how column content is rendered
     * @var array<string, string> Column key to label mapping
     */
    public array $admin_columns = [];

    /**
     * Default WordPress columns to hide from the admin list table.
     *
     * Specifies which of the standard columns should be hidden from
     * the admin interface. Common columns include:
     * - 'title' — The post title (required for primary column logic)
     * - 'author' — The post author
     * - 'date' — The publish date
     * - 'categories' — Category assignment
     * - 'tags' — Tag assignment
     * - 'comments' — Comment count
     * - 'thumbnail' — Featured image
     *
     * ## Usage Example
     * ```php
     * class Project extends Model
     * {
     *     use AdminColumns;
     *
     *     public array $admin_columns_hidden = ['title', 'author', 'date'];
     * }
     * ```
     *
     * ## Primary Column Handling
     * When 'title' is hidden, the first column in $admin_columns
     * becomes the primary column (used for row actions like Edit, Trash).
     *
     * @since 1.0.0
     * @see AdminColumns::registerColumnHooks() For how primary column is handled
     * @var array<string> List of column keys to hide
     */
    public array $admin_columns_hidden = [];

    /**
     * Register custom columns with WordPress admin list table.
     *
     * Adds custom columns defined in $admin_columns to the post type's
     * admin list table. Custom columns are inserted before the 'date' column.
     * Columns listed in $admin_columns_hidden are not registered.
     *
     * Uses the 'manage_{post_type}_posts_columns' filter hook to modify
     * the column configuration before rendering.
     *
     * @since 1.0.0
     * @see registerColumnHooks() For registering all column-related hooks at once
     */
    public function registerColumns(): void
    {
        $postType = $this->getPostType();

        add_filter(
            'manage_' . $postType . '_posts_columns',
            function (array $columns): array {
                $newColumns = [];

                foreach ($columns as $key => $value) {
                    if ($key === 'date') {
                        foreach ($this->admin_columns as $columnKey => $columnLabel) {
                            if (!in_array($columnKey, $this->admin_columns_hidden, true)) {
                                $newColumns[$columnKey] = $columnLabel;
                            }
                        }
                    }

                    $newColumns[$key] = $value;
                }

                return $newColumns;
            }
        );
    }

    /**
     * Populate custom column cells with data from the model.
     *
     * For each row in the admin list table, this method calls the
     * corresponding get{ColumnKey}Column() method on the model instance
     * to render the cell content.
     *
     * The method name is derived from the column key: 'client' -> getClientColumn()
     *
     * Uses the 'manage_{post_type}_posts_custom_column' action hook to
     * output content for each cell.
     *
     * @since 1.0.0
     * @see getColumn() For the default column renderer that wraps values in edit links
     */
    public function populateColumns(): void
    {
        $postType = $this->getPostType();

        add_action(
            'manage_' . $postType . '_posts_custom_column',
            function (string $column, int $postId): void {
                if (!in_array($column, $this->admin_columns, true)) {
                    return;
                }

                $model = static::find($postId);
                if ($model === null) {
                    return;
                }

                $methodName = 'get' . ucfirst($column) . 'Column';
                if (method_exists($model, $methodName)) {
                    echo $model->$methodName();
                }
            },
            10,
            2
        );
    }

    /**
     * Make custom columns sortable in the admin list table.
     *
     * Adds all custom columns defined in $admin_columns to the sortable
     * columns array. This allows users to click column headers to sort
     * by that column's values.
     *
     * Uses the 'manage_edit-{post_type}_sortable_columns' filter hook.
     *
     * @since 1.0.0
     * @note Actual sorting logic must be implemented separately via pre_get_posts hook
     */
    public function makeColumnsSortable(): void
    {
        $postType = $this->getPostType();

        add_filter(
            'manage_edit-' . $postType . '_sortable_columns',
            function (array $sortableColumns): array {
                foreach (array_keys($this->admin_columns) as $column) {
                    if (!in_array($column, $this->admin_columns_hidden, true)) {
                        $sortableColumns[$column] = $column;
                    }
                }

                return $sortableColumns;
            }
        );
    }

    /**
     * Reorder columns to place custom columns before the date column.
     *
     * Moves all custom columns defined in $admin_columns to appear
     * immediately before the date column in the admin list table.
     * This ensures consistent column ordering across different post types.
     *
     * Uses the 'manage_{post_type}_posts_columns' filter hook.
     *
     * @since 1.0.0
     * @see registerColumns() For adding new columns (called separately)
     */
    public function orderColumns(): void
    {
        $postType = $this->getPostType();

        add_filter(
            'manage_' . $postType . '_posts_columns',
            function (array $columns): array {
                if (empty($this->admin_columns)) {
                    return $columns;
                }

                $ordered = [];
                $idx = 1;

                foreach ($columns as $key => $value) {
                    if ($key === 'date') {
                        foreach ($this->admin_columns as $columnKey => $columnLabel) {
                            if (!in_array($columnKey, $this->admin_columns_hidden, true)) {
                                $ordered[$columnKey] = $columnLabel;
                                $idx++;
                            }
                        }
                    }

                    $ordered[$key] = $value;

                    if ($key !== 'date') {
                        $idx++;
                    }
                }

                return $ordered;
            }
        );
    }

    /**
     * Register all admin column hooks at once.
     *
     * Convenience method that registers all column-related WordPress hooks:
     * - Custom column registration
     * - Column population with model data
     * - Column sorting
     * - Column reordering
     * - Primary column handling (when title is hidden)
     *
     * This is the main entry point for enabling custom columns on a post type.
     * Call this method after registering the post type.
     *
     * @since 1.0.0
     * @see registerColumns() For custom column registration
     * @see populateColumns() For column content population
     * @see makeColumnsSortable() For sortable column support
     * @see orderColumns() For column reordering
     */
    public function registerColumnHooks(): void
    {
        $this->registerColumns();
        $this->populateColumns();
        $this->makeColumnsSortable();
        $this->orderColumns();

        if (in_array('title', $this->admin_columns_hidden, true)) {
            $firstColumn = array_key_first($this->admin_columns);

            if ($firstColumn !== null) {
                $postType = $this->getPostType();
                add_filter(
                    'list_table_primary_column',
                    function (string $default, string $screen) use ($firstColumn, $postType): string {
                        if ('edit-' . $postType === $screen) {
                            return $firstColumn;
                        }

                        return $default;
                    },
                    10,
                    2
                );
            }
        }
    }
}
