<?php
/**
 * MIT licence
 * Version 1.0
 * Sjaak Priester, Amsterdam 31-08-2014.
 *
 * ActiveRecord Behavior for Yii 2.0
 *
 * Makes an ActiveRecord sortable.
 *
 * Sortable maintains one or more attributes defined in Sortable::orderAttribute (default: "ord").
 * The values are contiguous from 0, and define the order of the record, 0 being the first.
 * The function order($newPosition, $foreignKeyName) can be used to modify the position of the record in the order.
 * If $foreignKeyName is given, the ordering is restricted to records with the same value of $foreignKeyName.
 * A new record is inserted at the end of the order.
 */

namespace sjaakp\sortable;

use yii\base\Behavior;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\db\ActiveRecord;
use yii\db\Transaction;

/**
 * Class Sortable
 * @package sjaakp\sortable
 * @var $owner ActiveRecord
 */
class Sortable extends Behavior {
    /**
     * @var string|array
     * The order attribute(s) of the ActiveRecord.
     * This can take the following values:
     * - string - the order attribute name
     * - array of:
     *       - string - the order attribute name,
     *       - foreignKeyName => orderAttrName - limit ordering to ActiveRecords with the same foreign key value
     */
    public $orderAttribute = 'ord';

    public function events()    {
        return [
            ActiveRecord::EVENT_BEFORE_INSERT => 'beforeInsert',
            ActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
        ];
    }

    public function beforeInsert($event)  {
        $this->saveDelHelper(function($orderAttr, $where) {
            /**
             * @var $owner ActiveRecord
             */
            $owner = $this->owner;

            $owner->setAttribute($orderAttr, $owner->find()->where($where)->count());
        });
    }

    public function beforeDelete($event)  {
        $this->saveDelHelper(function($orderAttr, $where) {
            /**
             * @var $owner ActiveRecord
             */
            $owner = $this->owner;

            $owner->UpdateAllCounters([$orderAttr => -1], [
                    'and',
                    $where,
                    "{{{$orderAttr}}} > :order"
                ],
                [':order' => $owner->getAttribute($orderAttr)]
            );
        });
    }

    protected function saveDelHelper($func) {
        $conf = $this->orderAttribute;
        if (is_string($conf)) $conf = [$conf];

        /**
         * @var $trans Transaction
         */
        $trans = $this->owner->db->beginTransaction();

        try {
            foreach ($conf as $fk => $orderAttr)   {
                $where = is_integer($fk) ? 1 : [$fk => $this->owner->getAttribute($fk)];
                call_user_func($func, $orderAttr, $where);
            }
            $trans->commit();
        } catch (Exception $e)  {
            $trans->rollBack();
        }
    }

    /**
     * @param integer $newPosition          zero indexed position
     * @param null|string $foreignKeyName   if null, all records are ordered
     *                                      if string, ordering is restricted to records with the same foreign key value
     * @throws \yii\base\InvalidConfigException
     */
    public function order($newPosition, $foreignKeyName = null) {
        /**
         * @var $owner ActiveRecord
         */
        $owner = $this->owner;

        if ($foreignKeyName)    {   // restrict order to records with the same foreign key value
            if (! is_array($this->orderAttribute) || ! isset($this->orderAttribute[$foreignKeyName]))
                throw new InvalidConfigException(get_called_class() . "::orderAttribute[$foreignKeyName] is not set.");

            $orderAttr = $this->orderAttribute[$foreignKeyName];
            $where = [$foreignKeyName => $owner->getAttribute($foreignKeyName)];
        }
        else    {   // order all records
            $orderAttr = null;
            if (is_array($this->orderAttribute))    {
                foreach ($this->orderAttribute as $k => $v) {   // search for non-associative array entry
                    if (is_integer($k)) {
                        $orderAttr = $v;
                        break;
                    }
                }
            }
            else $orderAttr = $this->orderAttribute;
            if (! $orderAttr)
                throw new InvalidConfigException('No default order attribute found in '. get_called_class());
            $where = 1;
        }

        $oldPosition = $owner->getAttribute($orderAttr);

        /**
         * @var $trans Transaction
         */
        $trans = $owner->db->beginTransaction();

        try {
            if ($newPosition > $oldPosition)  {
                // new position greater than old position,
                // so all positions from old position + 1 up to and including new position should decrement
                $owner->updateAllCounters([$orderAttr => -1],[
                    'and',
                    $where,
                    ['between', $orderAttr, $oldPosition + 1, $newPosition]
                ]);
            }
            else    {
                // new position smaller than or equal to old position,
                // so all positions from new position up to and including old position - 1 should increment
                $owner->updateAllCounters([$orderAttr => 1],[
                    'and',
                    $where,
                    ['between', $orderAttr, $newPosition, $oldPosition - 1]
                ]);
            }
            $owner->setAttribute($orderAttr, $newPosition);
            $owner->save(false, [$orderAttr]);
            $trans->commit();
        } catch (Exception $e)  {
            $trans->rollBack();
        }
    }
}
