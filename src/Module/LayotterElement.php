<?php

declare(strict_types=1);

namespace Sloth\Module;

use Sloth\Facades\Configure;
use Sloth\Facades\View;
use Sloth\Field\Image;

/**
 * Layotter element wrapper for modules.
 *
 * @since 1.0.0
 */
class LayotterElement extends \Layotter_Element
{
    /**
     * Module class name.
     *
     * @since 1.0.0
     * @var string
     */
    public static $module = '';

    /**
     * Set element attributes from module configuration.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function attributes(): void
    {
        $className = get_class($this);
        $moduleName = $className::$module;
        $module = new $moduleName();

        $layotterData = $module->getLayotterAttributes();
        $this->field_group = $layotterData['field_group'];
        $this->title = $layotterData['title'];
        $this->description = $layotterData['description'];
        $this->icon = $layotterData['icon'];
    }

    /**
     * Render the frontend view.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $fields The field values
     *
     * @return void
     */
    public function frontend_view($fields)
    {
        $fields = $this->prepareFields($fields);

        $options = func_get_args();
        array_shift($options);

        $keys = [
            'class',
            'col_options',
            'row_options',
            'post_options',
            'element_options',
        ];
        $fields['_layotter'] = [];
        $fields['_layotter']['passed'] = array_combine(
            array_intersect_key($keys, $options),
            array_intersect_key($options, $keys)
        );

        $className = get_class($this);
        $moduleName = $className::$module;
        $module = new $moduleName();
        $module->set($fields);

        $module->render();
    }

    /**
     * Render the backend preview.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $fields The field values
     *
     * @return void
     */
    public function backend_view($fields)
    {
        $fields = $this->prepareFields($fields);

        echo '<h1><i class="fa fa-' . $this->icon . '"></i> ' . $this->title . ' </h1>';

        echo '<table class="layotter-preview">';
        foreach ($this->getFields() as $field) {
            if (isset($fields[$field['name']])) {
                if (is_a($fields[$field['name']], 'Sloth\Field\Image')) {
                    $v = '<img src="' . $fields[$field['name']] . '" width="100"/>';
                } elseif ($field['type'] === 'file') {
                    $v = $fields[$field['name']]['filename'];
                } elseif ($field['type'] === 'repeater') {
                    $v = count($fields[$field['name']]) . ' ' . __('Elemente', 'sloth');
                } elseif (is_object($fields[$field['name']]) || $field['type'] === 'true_false' || $field['type'] === 'taxonomy') {
                    continue;
                } elseif ($field['type'] === 'image' && $fields[$field['name']]['url'] !== null) {
                    $v = '<img src="' . $fields[$field['name']]['url'] . '" width="100"/>';
                } elseif (is_array($fields[$field['name']])) {
                    $v = implode('<br />', $fields[$field['name']]);
                } else {
                    $v = wp_trim_words($fields[$field['name']], 30);
                }

                echo '<tr>';
                echo "<th style=\"text-align: left;border-bottom: 1px solid red;\" valign='top'>" . $field['label'] . ':</th>';
                echo '<td>' . $v . '</td>';
                echo '</tr>';
            }
        }
        echo '</table>';
    }

    /**
     * Prepare fields for output.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $values The field values
     *
     * @return array<string, mixed>
     */
    final protected function prepareFields(array $values): array
    {
        $fields = $this->getFields();
        if (Configure::read('layotter_prepare_fields') === true && $fields) {
            foreach ($fields as $field) {
                if ($field['type'] === 'image') {
                    $v = new Image($values[$field['name']]);
                    $values[$field['name']] = $v;
                }
            }
        }

        return $values;
    }

    /**
     * Get the prepared values.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    final public function getValues(): array
    {
        return $this->prepareFields($this->formatted_values);
    }
}
