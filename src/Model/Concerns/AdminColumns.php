<?php

declare(strict_types=1);

namespace Sloth\Model\Concerns;

trait AdminColumns
{
    public array $admin_columns = [];

    public array $admin_columns_hidden = [];

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
