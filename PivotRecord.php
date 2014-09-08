<?php
/**
 * MIT licence
 * Version 1.0
 * Sjaak Priester, Amsterdam 31-08-2014.
 */

namespace sjaakp\sortable;

use yii\db\ActiveRecord;
use yii\db\ActiveQuery;
use yii\db\Transaction;
use yii\helpers\StringHelper;
use yii\helpers\Inflector;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * Class PivotRecord
 * @package sjaakp\sortable
 */
class PivotRecord extends ActiveRecord {

    public static $_tableName;

    public static $a_id_attr;
    public static $a_order_attr;
    public static $b_id_attr;
    public static $b_order_attr;

    /**
     * aClass() and bClass() should return the fully qualified class names of the linked classes.
     * Should be implemented in derived class
     * @throws \yii\base\InvalidConfigException
     * @return string - full class name of one of the linked ActiveRecord classes
     */
    protected static function aClass()   {
        throw new InvalidConfigException(get_called_class() . '::aClass() is not implemented.');
    }

    /**
     * @throws \yii\base\InvalidConfigException
     * @return string - full class name of the other linked ActiveRecord class
     */
    protected static function bClass()   {
        throw new InvalidConfigException(get_called_class() . '::bClass() is not implemented.');
    }

    public static function tableName()  {
        if (is_null(static::$_tableName))   {
            $base_a = StringHelper::basename(static::aClass());
            $base_b = StringHelper::basename(static::bClass());
            $base = $base_a < $base_b ? $base_a . $base_b : $base_b . $base_a;
            static::$_tableName = '{{%' . Inflector::camel2id($base, '_') . '}}';
        }
        return static::$_tableName;
    }
    
    public static function aIdAttr() {
        if (is_null(static::$a_id_attr))
            static::$a_id_attr = Inflector::camel2id(StringHelper::basename(static::aClass()), '_') . '_id';
        return static::$a_id_attr;
    }
    
    public static function aOrderAttr() {
        if (is_null(static::$a_order_attr))
            static::$a_order_attr = Inflector::camel2id(StringHelper::basename(static::aClass()), '_') . '_ord';
        return static::$a_order_attr;
    }
    
    public static function bIdAttr() {
        if (is_null(static::$b_id_attr))
            static::$b_id_attr = Inflector::camel2id(StringHelper::basename(static::bClass()), '_') . '_id';
        return static::$b_id_attr;
    }
    
    public static function bOrderAttr() {
        if (is_null(static::$b_order_attr))
            static::$b_order_attr = Inflector::camel2id(StringHelper::basename(static::bClass()), '_') . '_ord';
        return static::$b_order_attr;
    }

    public function rules()    {
        return [
            [[static::aIdAttr(), static::bIdAttr()], 'unique', 'targetAttribute' => [static::aIdAttr(), static::bIdAttr()]]
        ];
    }

    public function beforeSave($insert)    {
        if ($insert) {
            $this->saveHelper(static::aIdAttr(), static::bOrderAttr());
            $this->saveHelper(static::bIdAttr(), static::aOrderAttr());
        }
        return parent::beforeSave($insert);
    }

    protected function saveHelper($idAttr, $orderAttr)  {
        if ($orderAttr !== false)
            $this->setAttribute($orderAttr, $this->find()->where([$idAttr => $this->getAttribute($idAttr)])->count());
    }

    public function beforeDelete()    {
        $r = $this->delHelper(static::aIdAttr(), static::bOrderAttr());
        if ($r) $r = $this->delHelper(static::bIdAttr(), static::aOrderAttr());
        return $r ? parent::beforeDelete() : false;
    }

    protected function delHelper($idAttr, $orderAttr) {
        $r = true;
        if ($orderAttr !== false)   {

            /**
             * @var $trans Transaction
             */
            $trans = $this->db->beginTransaction();

            try {
                static::updateAllCounters([$orderAttr => -1], [
                    'and',
                    [$idAttr => $this->getAttribute($idAttr)],
                    "{{{$orderAttr}}} > :_order"
                ], [
                    ':_order' => $this->getAttribute($orderAttr)
                ]);
                $trans->commit();
            } catch (Exception $e)  {
                $trans->rollBack();
                $r = false;
            }
        }
        return $r;
    }

    public function orderA($newPosition) {
        $this->orderHelper(static::bIdAttr(), static::aOrderAttr(), $newPosition);
    }

    public function orderB($newPosition) {
        $this->orderHelper(static::aIdAttr(), static::bOrderAttr(), $newPosition);
    }

    protected function orderHelper($idAttr, $orderAttr, $newPosition)   {
        if ($orderAttr !== false)   {
            $fk = $this->getAttribute($idAttr);
            $oldPosition = $this->getAttribute($orderAttr);

            /**
             * @var $trans Transaction
             */
            $trans = $this->db->beginTransaction();

            try {
                if ($newPosition > $oldPosition)  {
                    // new position greater than old position,
                    // so all positions from old position + 1 up to and including new position should decrement
                    static::updateAllCounters([$orderAttr => -1], [
                        'and',
                        [$idAttr => $fk],
                        ['between', $orderAttr, $oldPosition + 1, $newPosition]
                    ]);
                }
                else    {
                    // new position smaller than or equal to old position,
                    // so all positions from new position up to and including old position - 1 should increment
                    static::updateAllCounters([$orderAttr => 1],[
                        'and',
                        [$idAttr => $fk],
                        ['between', $orderAttr, $newPosition, $oldPosition - 1]
                    ]);
                }
                $this->setAttribute($orderAttr, $newPosition);
                $this->save(false, [$orderAttr]);
                $trans->commit();
            } catch (Exception $e)  {
                $trans->rollBack();
            }
        }
    }

    /**
     * @param ActiveRecord $b
     * @return ActiveQuery with all the A's belonging to B
     */
    public static function getAs(ActiveRecord $b)  {
        /**
         * @var $class ActiveRecord
         */
        $class = static::aClass();
        $classPk = $class::primaryKey()[0];
        $pivId = static::aIdAttr();
        $r = $class::find()->leftJoin(static::tableName() . ' p', "{{{$classPk}}}={{p}}.{{{$pivId}}}")
            ->where([static::bIdAttr() => $b->getPrimaryKey()]);
        $r->multiple = true;
        if (static::aOrderAttr() !== false)
            $r->orderBy(static::aOrderAttr());
        return $r;
    }

    public static function getBs(ActiveRecord $a)  {
        /**
         * @var $class ActiveRecord
         */
        $class = static::bClass();
        $classPk = $class::primaryKey()[0];
        $pivId = static::bIdAttr();
        $r = $class::find()->leftJoin(static::tableName() . ' p', "{{{$classPk}}}={{p}}.{{{$pivId}}}")
            ->where([static::aIdAttr() => $a->getPrimaryKey()]);
        $r->multiple = true;
        if (static::bOrderAttr() !== false)
            $r->orderBy(static::bOrderAttr());
        return $r;
    }
}
