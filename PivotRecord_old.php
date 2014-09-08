<?php
/**
 * MIT licence
 * Version 1.0
 * Sjaak Priester, Amsterdam 31-08-2014.
 */

namespace sjaakp\sortable;

use yii\db\ActiveRecord;
use yii\helpers\StringHelper;
use yii\helpers\Inflector;
use yii\base\InvalidConfigException;

/**
 * Class PivotRecord
 * @package sjaakp\sortable
 */
class PivotRecord extends ActiveRecord {

    public static $_tableName;

    public static $_main_id_attr;
    public static $_main_order_attr;
    public static $_related_id_attr;
    public static $_related_order_attr;

    /**
     * @throws \yii\base\InvalidConfigException
     * @return string
     */
    protected static function mainClass()   {
        throw new InvalidConfigException(get_called_class() . '::mainClass() is not implemented.');
    }

    /**
     * @throws \yii\base\InvalidConfigException
     * @return string
     */
    protected static function relatedClass()   {
        throw new InvalidConfigException(get_called_class() . '::relatedClass() is not implemented.');
    }

    public static function tableName()  {
        if (is_null(static::$_tableName))   {
            $base_main = StringHelper::basename(static::mainClass());
            $base_related = StringHelper::basename(static::relatedClass());
            $base = $base_main < $base_related ? $base_main . $base_related : $base_related . $base_main;
            static::$_tableName = '{{%' . Inflector::camel2id($base, '_') . '}}';
        }
        return static::$_tableName;
    }
    
    public static function mainIdAttr() {
        if (is_null(static::$_main_id_attr))
            static::$_main_id_attr = Inflector::camel2id(StringHelper::basename(static::mainClass()), '_') . '_id';
        return static::$_main_id_attr;
    }
    
    public static function mainOrderAttr() {
        if (is_null(static::$_main_order_attr))
            static::$_main_order_attr = Inflector::camel2id(StringHelper::basename(static::mainClass()), '_') . '_ord';
        return static::$_main_order_attr;
    }
    
    public static function relatedIdAttr() {
        if (is_null(static::$_related_id_attr))
            static::$_related_id_attr = Inflector::camel2id(StringHelper::basename(static::relatedClass()), '_') . '_id';
        return static::$_related_id_attr;
    }
    
    public static function relatedOrderAttr() {
        if (is_null(static::$_related_order_attr))
            static::$_related_order_attr = Inflector::camel2id(StringHelper::basename(static::relatedClass()), '_') . '_ord';
        return static::$_related_order_attr;
    }

    public function rules()    {
        return [
            [[static::mainIdAttr(), static::relatedIdAttr()], 'unique', 'targetAttribute' => [static::mainIdAttr(), static::relatedIdAttr()]]
        ];
    }

    public function beforeSave($insert)    {
        if ($insert) {
            $this->saveHelper(static::mainIdAttr(), static::relatedOrderAttr());
            $this->saveHelper(static::relatedIdAttr(), static::mainOrderAttr());
        }
        return parent::beforeSave($insert);
    }

    protected function saveHelper($idAttr, $orderAttr)  {
        if ($orderAttr !== false)
            $this->setAttribute($orderAttr, $this->find()->where([$idAttr => $this->getAttribute($idAttr)])->count());
    }

    public function beforeDelete()    {
        $this->delHelper(static::mainIdAttr(), static::relatedOrderAttr());
        $this->delHelper(static::relatedIdAttr(), static::mainOrderAttr());
        return parent::beforeDelete();
    }

    protected function delHelper($idAttr, $orderAttr) {
        if ($orderAttr !== false)   {
            static::updateAllCounters([$orderAttr => -1], [
                'and',
                [$idAttr => $this->getAttribute($idAttr)],
                "{{{$orderAttr}}} > :a_order"
            ], [
                ':a_order' => $this->getAttribute($orderAttr)
            ]);
        }
    }

    public function orderMain($newPosition) {
        $this->orderHelper(static::relatedIdAttr(), static::mainOrderAttr(), $newPosition);
    }

    public function orderRelated($newPosition) {
        $this->orderHelper(static::mainIdAttr(), static::relatedOrderAttr(), $newPosition);
    }

    protected function orderHelper($idAttr, $orderAttr, $newPosition)   {
        if ($orderAttr !== false)   {
            $fixedId = $this->getAttribute($idAttr);
            $oldPosition = $this->getAttribute($orderAttr);
            if ($newPosition > $oldPosition)  {
                // new position greater than old position,
                // so all positions from old position + 1 up to and including new position should decrement
                static::updateAllCounters([$orderAttr => -1], [
                    'and',
                    [$idAttr => $fixedId],
                    ['between', $orderAttr, $oldPosition + 1, $newPosition]
                ]);
            }
            else    {
                // new position smaller than or equal to old position,
                // so all positions from new position up to and including old position - 1 should increment
                static::updateAllCounters([$orderAttr => 1],[
                    'and',
                    [$idAttr => $fixedId],
                    ['between', $orderAttr, $newPosition, $oldPosition - 1]
                ]);
            }
            $this->setAttribute($orderAttr, $newPosition);
            $this->save(false, [$orderAttr]);
        }
    }

    public static function getMainPivots(ActiveRecord $mainModel)  {
        return $mainModel->hasMany(static::className(), [static::mainIdAttr() => $mainModel->primaryKey()[0]]);
    }

    public static function getRelatedPivots(ActiveRecord $relatedModel)  {
        return $relatedModel->hasMany(static::className(), [static::relatedIdAttr() => $relatedModel->primaryKey()[0]]);
    }

    public static function getMain(ActiveRecord $relatedModel)  {
        /**
         * @var $class ActiveRecord
         */
        $class = static::mainClass();
        $classPk = $class::primaryKey()[0];
        $pivId = static::mainIdAttr();
        $r = $class::find()->leftJoin(static::tableName() . ' p', "{{{$classPk}}}={{p}}.{{{$pivId}}}")
            ->where([static::relatedIdAttr() => $relatedModel->getPrimaryKey()]);
        if (static::mainOrderAttr() !== false)
            $r->orderBy(static::mainOrderAttr());
        return $r;
    }

    public static function getRelated(ActiveRecord $mainModel)  {
        /**
         * @var $class ActiveRecord
         */
        $class = static::relatedClass();
        $classPk = $class::primaryKey()[0];
        $pivId = static::relatedIdAttr();
        $r = $class::find()->leftJoin(static::tableName() . ' p', "{{{$classPk}}}={{p}}.{{{$pivId}}}")
            ->where([static::mainIdAttr() => $mainModel->getPrimaryKey()]);
        if (static::relatedOrderAttr() !== false)
            $r->orderBy(static::relatedOrderAttr());
        return $r;
    }
}
