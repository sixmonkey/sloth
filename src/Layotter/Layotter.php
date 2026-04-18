<?php

declare(strict_types=1);

namespace Sloth\Layotter;

use Brain\Hierarchy\Finder\ByFolders;
use Brain\Hierarchy\QueryTemplate;
use Sloth\Facades\Configure;
use Sloth\Facades\View;
use Sloth\Utility\Utility;

/**
 * Layotter page builder integration.
 *
 * This class provides integration with the Layotter page builder plugin,
 * handling:
 * - Post type enable/disable for page builder
 * - Row layout configuration per post type
 * - Custom view rendering for elements, columns, rows, and posts
 * - Admin CSS styling for Layotter interface
 *
 * @since 1.0.0
 * @see https://github.com/layotter/layotter Layotter plugin
 */
class Layotter
{
    /**
     * Disabled post types.
     *
     * @since 1.0.0
     * @var array<string>
     */
    public static array $disabledPostTypes = ['attachement'];

    /**
     * Layouts configured per post type.
     *
     * @since 1.0.0
     * @var array<string, array<string>>
     */
    public static array $layoutsForPostType = [];

    /**
     * Layouts configured per template.
     *
     * @since 1.0.0
     * @var array<string, array<string>>
     */
    public static array $layoutsForTemplate = [];

    /**
     * Enabled post types.
     *
     * @since 1.0.0
     * @var array<string>
     */
    public static array $enabledPostTypes = [];

    /**
     * Constructor for Layotter.
     *
     * @since 1.0.0
     */
    public function __construct() {}

    /**
     * Get custom column classes for Layotter.
     *
     * @param array<string> $defaultClasses Default column classes
     *
     * @return array<string, string>
     * @since 1.0.0
     *
     */
    public function customColumnClasses(array $defaultClasses): array
    {
        $layotterCustomClasses = Configure::read('layotter_custom_classes');
        $columnClasses = [];

        for ($i = 1; $i <= 12; $i++) {
            $columnClasses[Utility::float2fraction($i / 12)] = 'col-lg-' . $i;
        }

        if ($layotterCustomClasses) {
            return array_merge($columnClasses, $layotterCustomClasses);
        }

        return $columnClasses;
    }

    /**
     * Get enabled post types.
     *
     * @param array<string> $postTypes The post types to filter
     *
     * @return array<string>
     * @since 1.0.0
     *
     */
    public static function enabledPostTypes(array $postTypes): array
    {
        $postTypes = self::$enabledPostTypes;
        $allTypes = get_post_types(['public' => true]);

        foreach ($allTypes as $postType => $data) {
            if (!in_array($postType, self::$disabledPostTypes, true)) {
                $postTypes[] = $postType;
            }
        }

        return $postTypes;
    }

    /**
     * Disable Layotter for a specific post type.
     *
     * @param string $postType The post type to disable
     * @since 1.0.0
     *
     */
    public static function disable_for_post_type(string $postType): void
    {
        if (!in_array($postType, self::$disabledPostTypes, true)) {
            self::$disabledPostTypes[] = $postType;
        }
    }

    /**
     * Enable Layotter for a specific post type.
     *
     * @param string $postType The post type to enable
     * @since 1.0.0
     *
     */
    public static function enable_for_post_type(string $postType): void
    {
        self::$enabledPostTypes[] = $postType;
    }

    /**
     * Set layouts for a specific post type.
     *
     * @param string $postType The post type
     * @param array<string> $layouts The layout names
     * @since 1.0.0
     *
     */
    public static function set_layouts_for_post_type(string $postType, array $layouts): void
    {
        self::$layoutsForPostType[$postType] = $layouts;
    }

    /**
     * Get allowed row layouts.
     *
     * Returns layouts configured for the current post type, falling back
     * to theme configuration or default layouts.
     *
     * @param array<string> $rowLayouts Default row layouts
     * @return array<string>
     * @since 1.0.0
     *
     */
    public static function allowedRowLayouts(array $rowLayouts): array
    {
        global $post;
        $postType = $post->post_type ?? 'post';

        return self::$layoutsForPostType[$postType] ?? Configure::read('theme.layotter.row_layouts')
            ?? $rowLayouts;
    }

    /**
     * Get the default row layout.
     *
     * Returns the first layout configured for the current post type,
     * or the first theme layout, or the provided default.
     *
     * @param string $rowLayout The default row layout
     * @since 1.0.0
     *
     */
    public static function defaultRowLayout(string $rowLayout): string
    {
        global $post;
        $postType = $post->post_type ?? 'post';

        if (isset(self::$layoutsForPostType[$postType])) {
            return reset(self::$layoutsForPostType[$postType]);
        }

        $themeLayouts = Configure::read('theme.layotter.row_layouts');

        if (is_array($themeLayouts)) {
            return (string) reset($themeLayouts);
        }

        return $rowLayout;
    }

    /**
     * Set layouts for a post type.
     *
     * @param string $postType The post type slug
     * @param array<string> $layouts The layouts to enable
     * @since 1.0.0
     *
     */
    public static function setLayoutsForPostType(string $postType, array $layouts): void
    {
        self::$layoutsForPostType[$postType] = $layouts;
    }

    /**
     * Set layouts for a template.
     *
     * @param string $template The template slug
     * @param array<string> $layouts The layouts to enable
     * @since 1.0.0
     *
     */
    public static function setLayoutsForTemplate(string $template, array $layouts): void
    {
        self::$layoutsForTemplate[$template] = $layouts;
    }

    /**
     * Disable Layotter for a post type.
     *
     * @param string $postType The post type slug
     * @since 1.0.0
     *
     */
    public static function disableForPostType(string $postType): void
    {
        self::$disabledPostTypes[] = $postType;
    }

    /**
     * Enable Layotter for a post type.
     *
     * @param string $postType The post type slug
     * @since 1.0.0
     *
     */
    public static function enableForPostType(string $postType): void
    {
        self::$enabledPostTypes[] = $postType;
    }

    /**
     * Render a Layotter element.
     *
     * @param string $elementHtml The rendered element HTML
     * @param array<string, mixed>|null $options Element options
     * @param array<string, mixed>|null $colOptions Column options
     * @param array<string, mixed>|null $rowOptions Row options
     * @param array<string, mixed>|null $postOptions Post options
     * @since 1.0.0
     *
     */
    public function customElementView(
        string $elementHtml,
        ?array $options = null,
        ?array $colOptions = null,
        ?array $rowOptions = null,
        ?array $postOptions = null
    ): string {
        $view = View::make($this->getCurrentView('element'));

        return $view->with([
            'content' => $elementHtml,
            'options' => $options,
            'col_options' => $colOptions,
            'row_options' => $rowOptions,
            'post_options' => $postOptions,
        ])->render();
    }

    /**
     * Render a Layotter column.
     *
     * @param string $elementsHtml The rendered elements HTML
     * @param string $class Column class
     * @param array<string, mixed>|null $options Column options
     * @param array<string, mixed>|null $rowOptions Row options
     * @param array<string, mixed>|null $postOptions Post options
     * @since 1.0.0
     *
     */
    public function customColumnView(
        string $elementsHtml,
        string $class,
        ?array $options = null,
        ?array $rowOptions = null,
        ?array $postOptions = null
    ): string {
        $view = View::make($this->getCurrentView('column'));

        return $view->with([
            'content' => $elementsHtml,
            'class' => trim($class),
            'options' => $options,
            'row_options' => $rowOptions,
            'post_options' => $postOptions,
        ])->render();
    }

    /**
     * Render a Layotter row.
     *
     * @param string $colsHtml The rendered columns HTML
     * @param array<string, mixed> $options Row options
     * @param array<string, mixed>|null $postOptions Post options
     * @since 1.0.0
     *
     */
    public function customRowView(string $colsHtml, array $options = [], ?array $postOptions = null): string
    {
        foreach ($options as &$option) {
            if ($option === []) {
                $option = null;
            }

            if (is_array($option) && count($option) === 1) {
                $option = reset($option);
            }
        }

        $view = View::make($this->getCurrentView('row'));

        return $view->with([
            'content' => $colsHtml,
            'options' => $options,
            'post_options' => $postOptions,
        ])->render();
    }

    /**
     * Render a Layotter post.
     *
     * @param string $rowsHtml The rendered rows HTML
     * @param array<string, mixed> $options Post options
     * @since 1.0.0
     *
     */
    public function customPostView(string $rowsHtml, array $options): string
    {
        $view = View::make($this->getCurrentView('post'));

        return $view->with([
            'content' => $rowsHtml,
            'options' => $options,
        ])->render();
    }

    /**
     * Get the current view path for a Layotter component.
     *
     * @param string $for The component type (element, column, row, post)
     * @since 1.0.0
     *
     */
    final protected function getCurrentView(string $for): string
    {
        $viewParts = ['Layotter', $for];
        $layoutPaths = [];

        foreach (app('view.finder')->getPaths() as $path) {
            $layoutPaths[] = $path . DS . implode(DS, $viewParts);
        }

        $finder = new ByFolders($layoutPaths, 'twig');
        $queryTemplate = new QueryTemplate($finder);
        $template = $queryTemplate->findTemplate(null, false);

        $viewParts[] = basename($template, '.twig');
        $viewParts = array_filter($viewParts);

        return implode('.', $viewParts);
    }

    /**
     * Render Layotter admin CSS styles.
     *
     * Outputs inline CSS for styling the Layotter page builder
     * interface in the WordPress admin.
     *
     * @since 1.0.0
     */
    public function renderLayotterStyles(): void
    {
        echo "<style>" . file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '_assets' . DIRECTORY_SEPARATOR . 'layotter.css') . "</style>";
    }
}
