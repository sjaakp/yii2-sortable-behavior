<?php
/**
 * MIT licence
 * Version 1.2
 * Sjaak Priester, Amsterdam 15-02-2021.
 *
 * Sortable Grid/ListView for Yii 2.0
 */

namespace sjaakp\sortable;

use yii\web\AssetBundle;

/**
 * Class SortableAsset
 * @package sjaakp\loadmore
 */
class SortableAsset extends AssetBundle
{
    public $sourcePath = __DIR__ . DIRECTORY_SEPARATOR . 'assets';

    public $js = [
        'sortable.min.js'
    ];

    public $css = [
        'sortable.css'
    ];
}
