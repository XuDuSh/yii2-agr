<?php

namespace app\controllers;

use egamov\agr-yii2\models\Transaction;
use egamov\agr-yii2\models\Order;
use yii\web\Controller;

class AgrController extends Controller
{
    const AGR_SUCCESS = 0;
    const PAYMENT_STATUS_PAID = 2;
    const PAYMENT_STATUS_CANCELLED = 3;
    const SIGN_CHECK_FAILED = -1;
    const INCORRECT_AMOUNT = - 2;
    const NOT_ENOUGH_PARAMS = -3;
    const ALREADY_PAID = -4;
    const ORDER_NOT_FOUND = -5;
    const TRANSACTION_NOT_FOUND = -6;
    const UPDATE_FAILED = -7;
    const IN_REQUEST = -8;
    const TRANSACTION_CANCELLED = -9;
    const VENDOR_NOT_FOUND = -10;

    private $secretKey = 'asdjaklsdfjsdlkfjsldkfjsdlkfjsldkjf';
    private $vendor_id = 000004;
    private $stat = true;

    private $params = array();
    public static $errorMessages = array(
        self::SIGN_CHECK_FAILED => 'SIGN CHECK FAILED!',
        self::INCORRECT_AMOUNT => 'Incorrect parameter amount',
        self::NOT_ENOUGH_PARAMS => 'Not enough parameters',
        self::ALREADY_PAID => 'Already paid',
        self::ORDER_NOT_FOUND => 'The order does not exist',
        self::TRANSACTION_NOT_FOUND => 'The transaction does not exist',
        self::UPDATE_FAILED => 'Failed to update user',
        self::IN_REQUEST => 'Error in request',
        self::TRANSACTION_CANCELLED => 'The transaction cancelled',
        self::VENDOR_NOT_FOUND => 'The vendor is not found',
    );

    public function __construct() {
        $this->_setParam('info', array('MERCHANT_TRANS_ID'));
        $this->_setParam('notify', array('AGR_TRANS_ID', 'VENDOR_TRANS_ID', 'STATUS'));
        $this->_setParam('statement', array('FROM', 'TO'));
        $this->_setParam('pay', array(
                'AGR_TRANS_ID',
                'VENDOR_ID',
                'PAYMENT_ID',
                'PAYMENT_NAME',
                'MERCHANT_TRANS_ID',
                'MERCHANT_TRANS_AMOUNT',
                'ENVIRONMENT',
                'MERCHANT_TRANS_DATA'
            )
        );
        $this->_setParam('methodotm', array('AGR_TRANS_ID', 'VENDOR_TRANS_ID'));
    }

    public function actionInfo(){
        $posted = $this->query();
        $this->check_sign(__FUNCTION__, $posted);

        $order = Order::find()->where(['id' => $posted['MERCHANT_TRANS_ID']])->one();
        if (is_null($order)) {
            return $this->printError(self::ORDER_NOT_FOUND);
        }

        return $this->printSuccess(array(
            'PARAMETERS' => array(
                'first_name' => isset($order['firstname'])?$order['firstname']:'Name',
                'last_name' => isset($order['lastname'])?$order['lastname']:'LastName',
                'company' => isset($order['payment_company'])?$order['payment_company']:'Company',
                'address' => isset($order['payment_address_1'])?$order['payment_address_1']:'Address',
                'email' => isset($order['email'])?$order['email']:'unknown'
            )
        ));
    }

    public function actionPay() {

        $posted = $this->query();
        $this->check_sign(__FUNCTION__, $posted);

        if ($this->vendor_id != $posted['VENDOR_ID'])
            $this->printError(AgrError::VENDOR_NOT_FOUND);

        $order = Order::find()->where(['id' => $posted['MERCHANT_TRANS_ID']])->one();
        if (is_null($order))
            return $this->printError(self::ORDER_NOT_FOUND);


        $amount = (float)$posted['MERCHANT_TRANS_AMOUNT'];

        if ((float)$order->total_cost != $amount)
            return $this->printError(self::INCORRECT_AMOUNT);

        if ($order->status == 'canceled')
            return $this->printError(self::TRANSACTION_CANCELLED);


        if ($order->status == 'payed')
            return $this->printError(self::ALREADY_PAID);


        $transaction = Transaction::find()->where(['VENDOR_ID' => (int) $posted['VENDOR_ID']])
            ->andWhere(['AGR_TRANS_ID' => (int) $posted['AGR_TRANS_ID']])
            ->andWhere(['PAYMENT_ID' => (int) $posted['PAYMENT_ID']])
            ->andWhere(['MERCHANT_TRANS_ID' => $posted['MERCHANT_TRANS_ID']])->one();


        if (!empty($transaction) and $transaction->status == self::PAYMENT_STATUS_PAID)
            return $this->printError(self::ALREADY_PAID);

        if (!empty($transaction) and $transaction->status == self::PAYMENT_STATUS_CANCELLED)
            return $this->printError(self::TRANSACTION_CANCELLED);



        $trans_id = 0;
        if (is_null($transaction)) {
            $transaction = new Transaction();

            $transaction->agr_trans_id = intval($posted['AGR_TRANS_ID']);
            $transaction->vendor_id = $this->vendor_id;
            $transaction->payment_id = $posted['PAYMENT_ID'];
            $transaction->payment_name = $posted['PAYMENT_NAME'];
            $transaction->environment = $posted['ENVIRONMENT'];
            $transaction->merchant_trans_id = $posted['MERCHANT_TRANS_ID'];
            $transaction->merchant_trans_amount = $posted['MERCHANT_TRANS_AMOUNT'];
            $transaction->merchant_trans_data = isset($posted['MERCHANT_TRANS_DATA'])?$posted['MERCHANT_TRANS_DATA']:'';
            $transaction->status = self::PAYMENT_STATUS_PAID;
            $transaction->sign_time = floatval($posted['SIGN_TIME']);

            $transaction->save();


            $trans_id = \Yii::$app->db->getLastInsertID();
            return $this->printSuccess(array('VENDOR_TRANS_ID' => (int)$trans_id));


        }
        else {

            $transaction->agr_trans_id = intval($posted['AGR_TRANS_ID']);
            $transaction->vendor_id = $this->vendor_id;
            $transaction->payment_id = $posted['PAYMENT_ID'];
            $transaction->payment_name = $posted['PAYMENT_NAME'];
            $transaction->environment = $posted['ENVIRONMENT'];
            $transaction->merchant_trans_id = $posted['MERCHANT_TRANS_ID'];
            $transaction->merchant_trans_amount = $posted['MERCHANT_TRANS_AMOUNT'];
            $transaction->merchant_trans_data = isset($posted['MERCHANT_TRANS_DATA'])?$posted['MERCHANT_TRANS_DATA']:'';
            $transaction->status = self::PAYMENT_STATUS_PAID;
            $transaction->sign_time = floatval($posted['SIGN_TIME']);

            $transaction->save();

            $trans_id = $transaction->vendor_trans_id;
            return $this->printSuccess(array('VENDOR_TRANS_ID' => $trans_id));

        }


    }

    public function actionNotify() {
        $posted = $this->query();
        $this->check_sign(__FUNCTION__, $posted);

        $transaction = Transaction::find()->where(['VENDOR_TRANS_ID' => $posted['VENDOR_TRANS_ID']])->one();


        if (is_null($transaction)) {
            return $this->printError(self::TRANSACTION_NOT_FOUND);
        }

        if ($posted['STATUS'] == self::PAYMENT_STATUS_PAID) {
            $orders = Order::find()->where('id', $transaction->MERCHANT_TRANS_ID)->all();
            foreach ($orders as $key => $item){
                $item->status = 'payed';
                $item->save();
            }
        }

        if ($posted['STATUS'] == self::PAYMENT_STATUS_CANCELLED) {
            $orders = Order::find()->where('id', $transaction->MERCHANT_TRANS_ID)->all();
            foreach ($orders as $key => $item){
                $item->status = 'calceled';
                $item->save();
            }
        }

        $transactions = Transaction::find()->where(['MERCHANT_TRANS_ID' => $transaction->MERCHANT_TRANS_ID])
            ->andWhere(['VENDOR_ID' => $this->vendor_id])
            ->andWhere(['VENDOR_TRANS_ID' => $posted['VENDOR_TRANS_ID']])
            ->andWhere(['AGR_TRANS_ID' => $posted['AGR_TRANS_ID']])->all();

        foreach ($transactions as $key => $item){
            $item->STATUS = $posted['STATUS'];
            $item->save();
        }



        return $this->printSuccess();
    }

    public function actionMethodotm() {
        $posted = $this->query();
        $this->check_sign(__FUNCTION__, $posted);

        $transaction = Transaction::find()->where(['VENDOR_TRANS_ID' => $posted['VENDOR_TRANS_ID']])->one();


        if (is_null($transaction)) {
            return $this->printError(self::TRANSACTION_NOT_FOUND);
        }

        $orders = Order::find()->where('id', $transaction->MERCHANT_TRANS_ID)->all();

        foreach ($orders as $key => $item){
            $item->status = 'calceled';
            $item->save();
        }


        $transactions = Transaction::find()->where(['MERCHANT_TRANS_ID' => $transaction->MERCHANT_TRANS_ID])
            ->andWhere(['VENDOR_ID' => $this->vendor_id])
            ->andWhere(['VENDOR_TRANS_ID' => $posted['VENDOR_TRANS_ID']])
            ->andWhere(['AGR_TRANS_ID' => $posted['AGR_TRANS_ID']])->all();

        foreach ($transactions as $key => $item){
            $item->STATUS = self::PAYMENT_STATUS_CANCELLED;
            $item->save();
        }

        return $this->printSuccess();
    }

    public function actionStatement() {
        $result = array();
        $posted = $this->query();
        $this->check_sign(__FUNCTION__, $posted);
        $transactions = Transaction::find()->filterWhere(['>=', 'SIGN_TIME',  self::getTimeStamp($posted['FROM'])])
            ->andFilterWhere(['<=', 'SIGN_TIME', self::getTimeStamp($posted['TO'])])
            ->all();
        foreach ($transactions as $transaction) {
            array_push($result, array(
                'ENVIRONMENT' => $transaction->ENVIRONMENT,
                'AGR_TRANS_ID' => intval($transaction->AGR_TRANS_ID),
                'VENDOR_TRANS_ID' => intval($transaction->VENDOR_TRANS_ID),
                'MERCHANT_TRANS_ID' => $transaction->MERCHANT_TRANS_ID,
                'MERCHANT_TRANS_AMOUNT' => intval($transaction->MERCHANT_TRANS_AMOUNT),
                'STATE' => intval($transaction->STATE),
                'DATE' => strtotime($transaction->SIGN_TIME) * 1000
            ));
        }
        return $this->printSuccess(array('TRANSACTIONS' => $result));
    }


    public function _setParam($key, $val = array()) {
        $this->params[$key] = array_merge($val, array('SIGN_TIME', 'SIGN_STRING'));
    }

    public function query() {
        $postData =  (string)file_get_contents("php://input");
        $data = json_decode($postData, true);
        if ($data == null)
            return $this->printError(self::IN_REQUEST);
        return $data;
    }

    public static function getTimeStamp($tm) {
        return date('Y-m-d H:i:s',intval($tm));
    }
    public function check_sign($action, $posted) {
        if (!$this->stat)
            return $this->printError(-11, 'Plugin not active');

        foreach ($this->params[$action] as $param)
            if (!array_key_exists($param, $posted) and ($param != 'MERCHANT_TRANS_DATA'))
                return $this->printError(self::NOT_ENOUGH_PARAMS);

        $sign_string = $this->secretKey;
        foreach ($this->params[$action] as $param)
            if (!in_array($param, array('MERCHANT_TRANS_DATA', 'SIGN_STRING')))
                $sign_string .= $posted[$param];

        if (strtolower(md5($sign_string)) !== strtolower($posted['SIGN_STRING']))
            return $this->printError(self::SIGN_CHECK_FAILED);
        return true;
    }
    public static function agrPrint($param) {
        header("Content-type: application/json; charset=utf-8");
        die(json_encode($param));
    }

    public static function printSuccess($param = array()) {
        return self::agrPrint(array_merge(array(
            'ERROR' => self::AGR_SUCCESS,
            'ERROR_NOTE' => 'Success'
        ), $param));
    }

    public static function printError($errNum = 0, $errNote = '') {
        if (empty($errNote))
            $message = array_key_exists($errNum, self::$errorMessages)?self::$errorMessages[$errNum]:'Unknown Error';
        else
            $message = $errNote;
        self::agrPrint(array('ERROR' => $errNum, 'ERROR_NOTE' => $message));
    }
}
