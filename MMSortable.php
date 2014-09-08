<?php
/**
 * MIT licence
 * Version 1.0
 * Sjaak Priester, Amsterdam 28-08-2014.
 */

namespace sjaakp\sortable;

use yii\base\Behavior;
use yii\helpers\StringHelper;
use yii\helpers\Inflector;
use yii\db\ActiveRecord;

/**
 * Class MMSortable
 * @package sjaakp\sortable
 * @var $owner ActiveRecord
 */
class MMSortable extends Behavior {
    /**
     * @var string  - fully qualified class name of the pivot class.
     */
    public $pivotClass;

    /**
     * @var string  - attribute name of the owner's id in the pivot class
     * If this is not set, it will be derived from the owner's class name;
     * for instance: if the owner is class Movie, $pivotIdAttr will be "movie_id".
     */
    public $pivotIdAttr;

    public function events()    {
        return [
            ActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
        ];
    }

    protected function idAttr() {
        if (is_null($this->pivotIdAttr))    {
            $owner = $this->owner;
            $this->pivotIdAttr = Inflector::camel2id(StringHelper::basename($owner->className()), '_') . '_id';
        }
        return $this->pivotIdAttr;
    }

    public function getPivots() {
        /**
         * @var $owner ActiveRecord
         */
        $owner = $this->owner;
        return $owner->hasMany($this->pivotClass, [$this->idAttr() => $owner->primaryKey()[0]]);
    }

    public function beforeDelete($event)  {
        /**
         * @var $piv ActiveRecord
         */
        // Make sure PivotRecord::beforeDelete is called
        foreach ($this->getPivots()->all() as $piv) $piv->delete();
    }
}
