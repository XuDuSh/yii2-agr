<?php

namespace egamov\agr-yii2\models;

use yii\db\ActiveRecord;

class Transaction extends ActiveRecord
{
    public static function tableName()
    {
        return 'agr_transactions';
    }

    public function rules()
    {
        return [
            [['VENDOR_ID', 'AGR_TRANS_ID', 'STATUS', 'PAYMENT_ID', 'MERCHANT_TRANS_ID', 'PAYMENT_NAME', 'ENVIRONMENT', 'MERCHANT_TRANS_ID', 'MERCHANT_TRANS_AMOUNT'], 'required'],
            [['VENDOR_ID', 'AGR_TRANS_ID', 'STATUS', 'PAYMENT_ID', 'MERCHANT_TRANS_ID'], 'integer'],
            [['PAYMENT_NAME'], 'string', 'max' => 40],
            [['ENVIRONMENT'], 'string', 'max' => 20],
            [['MERCHANT_TRANS_DATA'], 'string', 'max' => 2048],
        ];
    }
}