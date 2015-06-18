<?php
/**
 * MIT licence
 * Version 1.0.1
 * Sjaak Priester, Amsterdam 28-08-2014.
 *
 * Sortable ListView for Yii 2.0
 *
 * ListView which is made sortable by means of the jQuery Sortable widget.
 * After each order operation, order data are posted to $orderUrl in the following format:
 * - $_POST["key"] - the primary key of the sorted ActiveRecord,
 * - $_POST["pos"] - the new position, zero-indexed.
 *
 */

namespace sjaakp\sortable;

use Yii;
use yii\widgets\ListView;
use yii\jui\JuiAsset;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\JsExpression;

/**
 * Class SortableListView
 * @package sjaakp\sortable
 */
class SortableListView extends ListView {
    /**
     * @var array|string
     * The url which is called after an order operation.
     * The format is that of yii\helpers\Url::toRoute.
     * The url will be called with the POST method and the following data:
     * - key    the primary key of the ordered ActiveRecord,
     * - pos    the new, zero-indexed position.
     *
     * Example: ['movie/order-actor', 'id' => 5]
     */
    public $orderUrl;

    /**
     * @var array
     * The options for the jQuery sortable object.
     * @link http://api.jqueryui.com/sortable/ .
     * Notice that the options 'items' and 'update' will be overwritten.
     * Default: empty array.
     */
    public $sortOptions = [];

    /**
     * @var boolean|string
     * The 'axis'-option of the jQuery sortable. If false, it is not set.
     */
    public $sortAxis = 'y';

    public function init()
    {
        parent::init();

        $id = $this->getId();
        $this->options['id'] = $id;

        $classes = isset($this->options['class']) ? $this->options['class'] : '';
        $classes .= ' sortable';
        $this->options['class'] = trim($classes);

        $view = $this->getView();
        JuiAsset::register($view);

        $url = Url::toRoute($this->orderUrl);

        $sortOpts = array_merge($this->sortOptions, [
            'items' => '[data-key]',
            'update' => new JsExpression("function(e, ui) {
                jQuery('#{$this->id}').addClass('sorting');
                jQuery.ajax({
                    type: 'POST',
                    url: '$url',
                    data: {
                        key: ui.item.data('key'),
                        pos: ui.item.index('[data-key]')
                    },
                    complete: function() {
                        jQuery('#{$this->id}').removeClass('sorting');
                    }
                });
            }")
        ]);

        if ($this->sortAxis) $sortOpts['axis'] = $this->sortAxis;

        $sortJson = Json::encode($sortOpts);

        $view->registerJs("jQuery('#{$id}').sortable($sortJson);");
    }
}
