<?php

/**
 * Redirect url to ecopayz
 */
define('REDIRECT_URL', 'https://secure.ecopayz.com/PrivateArea/WithdrawOnlineTransfer.aspx?');
/**
 * Merchant ID (MID) provided bt ecoPayz.
 */
define('PAYMENT_PAGE_ID', '');
/**
 * The merchant's ecoPayz account number which will be credited by the purchase
 * transaction. The number is provided by ecoPayz.
 */
define('MERCHANT_ACCOUNT_NUMBER', '');
/**
 * The merchant password.
 */
define('MERCHANT_PASSWORD', '');
/**
 * Invalid parameters received by the ecoPayz
 * payment page, Status 1.
 */
define('INVALID_REQUEST', 'InvalidRequest');
/**
 * The customer has logged in the ecoPayz site and
 * explicitly declined to make the payment, Status 2.
 */
define('DECLINED_BY_CUSTOMER', 'CANCEL');
/**
 * The customer has logged in the ecopayz site and
 * confirmed the payment, but the transaction has failed,
 * Status 3.
 */
define('TRANSACTION_FAILED', 'TransactionFailed');
/**
 * The payment has been confirmed by the customer and
 * ecopayz transaction has been successfully initiated,
 * Status 4.
 */
define('TRANSACTION_REQUIRES_MERCHANT_CONFIRMETION', 'CONFIRMED');
/**
 * The previously successfully processed ecopayz transaction
 * has been cancelled via ecopayz backend.
 */
define('TRANSATION_CANCELLED', 'CANCEL');

/* End of file EcopayzGateway.php */
