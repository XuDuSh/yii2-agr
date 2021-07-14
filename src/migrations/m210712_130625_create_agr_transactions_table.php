<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%agr_transactions}}`.
 */
class m210712_130625_create_agr_transactions_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('agr_transactions', [
            'VENDOR_TRANS_ID' => $this->primaryKey(20),
            'VENDOR_ID' => $this->bigInteger(20)->unsigned()->notNull(),
            'AGR_TRANS_ID' => $this->bigInteger(20)->unsigned()->notNull(),
            'STATUS' => $this->integer()->notNull(),
            'PAYMENT_ID' => $this->bigInteger(20)->unsigned()->notNull(),
            'PAYMENT_NAME' => $this->string(40)->notNull(),
            'ENVIRONMENT' => $this->string(20)->notNull(),
            'MERCHANT_TRANS_ID' => $this->bigInteger(20)->unsigned()->notNull(),
            'MERCHANT_TRANS_AMOUNT' => $this->float(2)->notNull(),
            'MERCHANT_TRANS_DATA' => $this->string(2048),
            'SIGN_TIME' => $this->timestamp()->defaultValue(null)->append('ON UPDATE CURRENT_TIMESTAMP')
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('agr_transactions');
    }
}
