<?php

declare(strict_types=1);

namespace Sloth\Layotter;

use Brain\Hierarchy\Finder\ByFolders;
use Brain\Hierarchy\QueryTemplate;
use Sloth\Facades\Configure;
use Sloth\Facades\View;
use Sloth\Singleton\Singleton;
use Sloth\Utility\Utility;

/**
 * Layotter page builder integration.
 *
 * @since 1.0.0
 */
class Layotter extends Singleton
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
    public static array $layoutsForPostType = [];

    /**
     * Enabled post types.
     *
     * @since 1.0.0
     * @var array<string>
     */
    public static array $enabledPostTypes = [];

    /**
     * Get custom column classes for Layotter.
     *
     * @since 1.0.0
     *
     * @param array<string> $defaultClasses Default column classes
     *
     * @return array<string, string>
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
     * @since 1.0.0
     *
     * @param array<string> $postTypes The post types to filter
     *
     * @return array<string>
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
     * @since 1.0.0
     *
     * @param string $postType The post type to disable
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
     * @since 1.0.0
     *
     * @param string $postType The post type to enable
     */
    public static function enable_for_post_type(string $postType): void
    {
        self::$enabledPostTypes[] = $postType;
    }

    /**
     * Set layouts for a specific post type.
     *
     * @since 1.0.0
     *
     * @param string $postType The post type
     * @param array<string> $layouts The layout names
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
     * @since 1.0.0
     *
     * @param array<string> $rowLayouts Default row layouts
     * @return array<string>
     */
    public static function allowedRowLayouts(array $rowLayouts): array
    {
        global $post;
        $postType = $post->post_type ?? 'post';

        if (isset(self::$layoutsForPostType[$postType])) {
            return self::$layoutsForPostType[$postType];
        }

        return Configure::read('theme.layotter.row_layouts')
            ?? $rowLayouts;
    }

    /**
     * Get the default row layout.
     *
     * Returns the first layout configured for the current post type,
     * or the first theme layout, or the provided default.
     *
     * @since 1.0.0
     *
     * @param string $rowLayout The default row layout
     * @return string
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
     * @since 1.0.0
     *
     * @param string          $postType The post type slug
     * @param array<string>   $layouts  The layouts to enable
     */
    public static function setLayoutsForPostType(string $postType, array $layouts): void
    {
        self::$layoutsForPostType[$postType] = $layouts;
    }

    /**
     * Set layouts for a template.
     *
     * @since 1.0.0
     *
     * @param string          $template The template slug
     * @param array<string>   $layouts  The layouts to enable
     */
    public static function setLayoutsForTemplate(string $template, array $layouts): void
    {
        self::$layoutsForTemplate[$template] = $layouts;
    }

    /**
     * Disable Layotter for a post type.
     *
     * @since 1.0.0
     *
     * @param string $postType The post type slug
     */
    public static function disableForPostType(string $postType): void
    {
        self::$disabledPostTypes[] = $postType;
    }

    /**
     * Enable Layotter for a post type.
     *
     * @since 1.0.0
     *
     * @param string $postType The post type slug
     */
    public static function enableForPostType(string $postType): void
    {
        self::$enabledPostTypes[] = $postType;
    }

    /**
     * Render a Layotter element.
     *
     * @since 1.0.0
     *
     * @param string                $elementHtml The rendered element HTML
     * @param array<string, mixed>|null $options    Element options
     * @param array<string, mixed>|null $colOptions Column options
     * @param array<string, mixed>|null $rowOptions Row options
     * @param array<string, mixed>|null $postOptions Post options
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
            'content'      => $elementHtml,
            'options'      => $options,
            'col_options'  => $colOptions,
            'row_options'  => $rowOptions,
            'post_options' => $postOptions,
        ])->render();
    }

    /**
     * Render a Layotter column.
     *
     * @since 1.0.0
     *
     * @param string                $elementsHtml The rendered elements HTML
     * @param string                $class        Column class
     * @param array<string, mixed>|null $options    Column options
     * @param array<string, mixed>|null $rowOptions Row options
     * @param array<string, mixed>|null $postOptions Post options
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
            'content'      => $elementsHtml,
            'class'        => trim($class),
            'options'      => $options,
            'row_options'  => $rowOptions,
            'post_options' => $postOptions,
        ])->render();
    }

    /**
     * Render a Layotter row.
     *
     * @since 1.0.0
     *
     * @param string                $colsHtml      The rendered columns HTML
     * @param array<string, mixed> $options       Row options
     * @param array<string, mixed>|null $postOptions Post options
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
            'content'      => $colsHtml,
            'options'      => $options,
            'post_options' => $postOptions,
        ])->render();
    }

    /**
     * Render a Layotter post.
     *
     * @since 1.0.0
     *
     * @param string                $rowsHtml The rendered rows HTML
     * @param array<string, mixed>  $options  Post options
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
     * @since 1.0.0
     *
     * @param string $for The component type (element, column, row, post)
     */
    final protected function getCurrentView(string $for): string
    {
        $viewParts = ['Layotter', $for];
        $layoutPaths = [];

        foreach ($GLOBALS['sloth']->container['view.finder']->getPaths() as $path) {
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
     * Add Layotter filters.
     *
     * @since 1.0.0
     */
    final public function addFilters(): void
    {
        add_filter('layotter/enable_example_element', '__return_false');
        add_filter('layotter/enable_default_css', '__return_false');
        add_filter('layotter/enable_element_templates', '__return_true');
        add_filter('layotter/enable_post_layouts', '__return_true');

        add_filter('layotter/enabled_post_types', $this->enabledPostTypes(...));
        add_filter('layotter/rows/allowed_layouts', $this->allowedRowLayouts(...));
        add_filter('layotter/rows/default_layout', $this->defaultRowLayout(...));
        add_filter('layotter/columns/classes', $this->customColumnClasses(...));
        add_filter('layotter/view/element', $this->customElementView(...), 10, 5);
        add_filter('layotter/view/column', $this->customColumnView(...), 10, 5);
        add_filter('layotter/view/row', $this->customRowView(...), 10, 3);
        add_filter('layotter/view/post', $this->customPostView(...), 10, 2);
    }
}
