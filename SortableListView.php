<?php
/**
 * MIT licence
 * Version 1.2
 * Sjaak Priester, Amsterdam 28-08-2014 ... 15-02-2021.
 * https://sjaakpriester.nl
 *
 * Sortable ListView for Yii 2.0
 *
 * ListView which is made sortable by means of HTML Drag and Drop.
 * After each order operation, order data are posted to $orderUrl in the following format:
 * - $_POST["key"] - the primary key of the sorted ActiveRecord,
 * - $_POST["pos"] - the new position, zero-indexed.
 *
 */

namespace sjaakp\sortable;

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ListView;

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
     * For compatibility only
     */
    public $sortOptions = [];

    /**
     * @var boolean|string
     * For compatibility only
     */
    public $sortAxis = 'y';

    /**
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {
        parent::init();

        Html::addCssClass($this->options, 'd-sortable');
        $this->options['tag'] = 'ul';

        $this->itemOptions = function($model, $key, $index, $list)   {
            $id = "{$list->id}_$key";
            return [
                'tag' => 'li',
                'id' => $id,
                'draggable' => 'true'
            ];
        };

        $url = Url::toRoute($this->orderUrl);

        $view = $this->getView();
        SortableAsset::register($view);

        $view->registerJs("sortable('$url', 'ul.d-sortable', 'li');");
    }
}
