<?php
namespace integrations\ecopayz;

use dao\platform\PlayersDepositTransactionDao;

require_once __DIR__ . '/../../dao/platform/PlayersDepositTransactionDao.php';
require_once __DIR__ . '/../../account/AccountManager.php';
require __DIR__ . '/../../webincludes/classAccount.php';
require 'definesECOPAYZ.php';

/**
 *
 */
class EcopayzGateway
{
    const ECOPAYZ = 'ecoPayz';
    /**
     * @var PlayersDepositTransactionDao $playersDepositTransDao
     */
    private $playersDepositTransDao = null;

    /**
     * @var player $playerAccount
     */
    private $playerAccount = null;


    public function __construct()
    {
        $this->playersDepositTransDao = PlayersDepositTransactionDao::getInstance();
        $this->playerAccount          = new player();
    }

    /**
     * Method to be called as first step to open the playier account on ecopayz.
     *
     * @param integer $playerId The player identifier.
     * @param float   $amount The transaction amount.
     * @param string  $currency The ISO 4217 three letter code.
     *
     * @return mixed
     */
    public function redirectToEcoPayz($playerId, $amount, $currency)
    {
        $txId = 0;
        try {
            $txId = $this->playersDepositTransDao->bookTransaction(
                $playerId,
                $amount,
                $currency
            );
            $format = 'PaymentPageID=%d&MerchantAccountNumber=%d&CustomerIdAtMerchant=%d&'.
                'TxID=%d&Amount=%s&Currency=%s&Checksum=%s';
            $params = PAYMENT_PAGE_ID.MERCHANT_ACCOUNT_NUMBER.$playerId.$txId.number_format($amount, 2)
                .$currency.MERCHANT_PASSWORD;
            $checksum = md5($params);
            file_put_contents('log.log', print_r('params: '. $params."\n".$checksum."\n", true), FILE_APPEND);
            $req = sprintf(
                $format,
                PAYMENT_PAGE_ID,
                MERCHANT_ACCOUNT_NUMBER,
                $playerId,
                $txId,
                number_format($amount, 2),
                $currency,
                $checksum
            );

            //return $this->sendHTTPRequest($req);
            return REDIRECT_URL.$req;

        } catch (\Exception $e) {
            if ($txId > 0) {
                $this->playersDepositTransDao->delete($txId);
            }
            throw new \Exception($e->getMessage(), $e->getCode());
        }
    }
    /**
     * To manage ecopayz requests.
     *
     * @param string $xml
     * @return \SimpleXMLElement
     */
    public function manageRequest($aXML)
    {
        try {
            $this->verifyChecksum($aXML);
            $xml         = simplexml_load_string($aXML);
            $status      = (int) $xml->xpath('StatusReport/Status')[0];
            $statusDescr = $this->mapStatus($status);
            $myTxId      = (string) $xml->xpath('Request/TxID')[0];
            $fields = array(
                'id_transaction' => $myTxId,
                'payment_state'  => $statusDescr,
                'payment_type'   => self::ECOPAYZ,
                'financial_institution' =>  self::ECOPAYZ
                //'ip_address_user'   => $xml->xpath('StatusReport/IP'),
            );
            $flag = false;
            if ($status === 4) {
                $fields['order_number'] = (string) $xml->xpath('StatusReport/SVSTransaction/BatchNumber')[0];
                $fields['order_reference'] = (string) $xml->xpath('StatusReport/SVSTransaction/Id')[0];
                $fields['payment_state'] = 'SUCCESS';
                $flag = true;
            } elseif ($status === 2 || $status === 5) {
                $fields['ip_adress_user'] = (string) $xml->xpath('StatusReport/SVSCustomer/IP')[0];
                $fields['payment_state'] = 'CANCELLED';
            } elseif ($status === 3) {
                $fields['payment_state'] = 'FAILED';
            }

            if ($flag) {
                $playerId   = (int) $xml->xpath('Request/CustomerIdAtMerchant')[0];
                $amount     = (float) $xml->xpath('Request/Amount')[0];
                $accManager = \account\AccountManager::getInstance();
                $accManager->makeDeposit(
                    $playerId,
                    self::ECOPAYZ,
                    $amount,
                    false
                );
                $fields['balance_was'] = $accManager->findUserBalance($playerId);
                $fields['balance_is']  = $fields['balance_was'] + $amount;
                // to calculate the bonus amount on deposti
                $this->playerAccount->setBonusToPlayer($playerId, $amount);
                $this->playerAccount->addDepositToCashback($playerId, $amount);
            }
            $this->playersDepositTransDao->updateTable($fields);
            $statusInResp = $this->setStatusInResp($status);
            $resp = $this->buildResponse($myTxId, $statusInResp);
        } catch (\Exception $e) {
            file_put_contents('log.log', $e->getMessage(), FILE_APPEND);
            $resp = $this->buildErrorResp();
        }

        return $resp;
    }
    /**
     * To validate the xml checksum value.
     *
     * @return boolean
     */
    private function verifyChecksum($xml)
    {
        $start  = strpos($xml, '<Checksum>') + 10;
        $end  = strpos($xml, '</Checksum>');
        $checksum = substr($xml, $start, $end - $start);
        $xml = str_replace($checksum, MERCHANT_PASSWORD, $xml);
        $isOk = md5($xml) == $checksum;
        if (!$isOk) {
            throw new \Exception('Checksum missmatch.');
        }

        return true;
    }

    private function setStatusInResp($status)
    {
        switch ($status) {
            case 1:
            case 2:
            case 3:
                $statusInResp = 'OK';
                break;
            case 4:
                $statusInResp = 'Confirmed';
                break;
            case 5:
                $statusInResp = 'Cancelled';
                break;
            default:
                $statusInResp = '';
                break;
        }

        return $statusInResp;
    }
    /**
     * undocumented function
     *
     * @return \SimpleXMLElement
     */
    public function buildResponse($txId, $status)
    {
        $resp = new \SimpleXMLElement(
            '<?xml version="1.0" encoding="utf-8"?><SVSPurchaseStatusNotificationResponse/>'
        );
        $transRes = $resp->addChild('TransactionResult');
        $transRes->addChild('Description', 'Paid ID '. $txId);
        $transRes->addChild('Code', $txId);
        $resp->addChild('Status', $status);
        $resp->addChild('Authentication')->addChild('Checksum', MERCHANT_PASSWORD);
        $xmlAsString = trim(str_replace("\n", '', $resp->asXML()));
        file_put_contents('log.log', $xmlAsString."--\n", FILE_APPEND);
        $checksum = md5($xmlAsString);
        $resp->Authentication->Checksum = $checksum;

        return $resp;
    }
    /**
     * To build an error xml response.
     *
     * @return void
     */
    private function buildErrorResp()
    {
        $resp = new \SimpleXMLElement(
            '<?xml version="1.0" encoding="utf-8"?><SVSPurchaseStatusNotificationResponse/>'
        );
        $resp->addChild('Code', -1);
        $resp->addChild('Description', INVALID_REQUEST);
        $resp->addChild('Status', INVALID_REQUEST);
        $resp->addChild('Authentication')->addChild('Checksum', MERCHANT_PASSWORD);
        $checksum = md5(trim($resp->asXML()));
        $resp->Authentication->Checksum = $checksum;
        
        return $resp;
    }
    /**
     * To get the status description based on the status value.
     *
     * @paraa int $status
     * @return string
     */
    private function mapStatus($status)
    {
        $descr = null;
        switch ($status) {
            case 1:
                $descr = INVALID_REQUEST;
                break;
            case 2:
                $descr = DECLINED_BY_CUSTOMER;
                break;
            case 3:
                $descr = TRANSACTION_FAILED;
                break;
            case 4:
                $descr = TRANSACTION_REQUIRES_MERCHANT_CONFIRMETION;
                break;
            case 5:
                $descr = TRANSACTION_CANCELLED;
                break;
        }

        return $descr;
    }
    private function sendHTTPRequest($req)
    {
        $options= [
            'http' => [
                'method' => 'POST',
                'header' => 'Content-Length: ' . strlen($req)."\r\n",
                'content' => $req
            ]
        ];
        $context = stream_context_create($options);
        $resp = file_get_contents(REDIRECT_URL, false, $context);
        //var_dump($resp);

        return $resp;
    }
}
/* End of file EcopayzGateway.php */
