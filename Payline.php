<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Payline;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Exception\TheliaProcessException;
use Thelia\Log\Tlog;
use Thelia\Model\Order;
use Thelia\Module\AbstractPaymentModule;
use Thelia\Tools\URL;

class Payline extends AbstractPaymentModule
{
    /** @var string */
    const DOMAIN_NAME = 'payline';

    const MERCHANT_ID = 'merchant_id';
    const ACCESS_KEY = 'access_key';
    const MODE = 'run_mode';
    const CONTRACT_NUMBER = 'contract_number';
    const ALLOWED_IP_LIST = 'allowed_ip_list';
    const MINIMUM_AMOUNT = 'minimum_amount';
    const MAXIMUM_AMOUNT = 'maximum_amount';
    const MINIMUM_AMOUNT_3X = 'minimum_amount_3x';
    const MAXIMUM_AMOUNT_3X = 'maximum_amount_3x';
    const SEND_CONFIRMATION_MESSAGE_ONLY_IF_PAID = 'send_confirmation_message_only_if_paid';
    const ACTIVATE_PAYMENT_3X = 'activate_payment_3x';

    protected static $alpha3ToNumeric = array(
        'ADP' => 20, 'AED' => 784, 'AFA' => 4, 'AFN' => 971, 'ALL' => 8, 'AMD' => 51, 'ANG' => 532, 'AOA' => 973,
        'AON' => 24, 'AOR' => 982, 'ARA' => 32, 'ARP' => 32, 'ARS' => 32, 'ATS' => 40, 'AUD' => 36, 'AWG' => 533,
        'AZM' => 31, 'AZN' => 944, 'BAD' => 70, 'BAM' => 977, 'BBD' => 52, 'BDT' => 50, 'BEC' => 993, 'BEF' => 56,
        'BEL' => 992, 'BGL' => 100, 'BGN' => 975, 'BHD' => 48, 'BIF' => 108, 'BMD' => 60, 'BND' => 96, 'BOB' => 68,
        'BOV' => 984, 'BRC' => 76, 'BRE' => 76, 'BRL' => 986, 'BRN' => 76, 'BRR' => 987, 'BSD' => 44, 'BTN' => 64,
        'BWP' => 72, 'BYB' => 112, 'BYR' => 974, 'BZD' => 84, 'CAD' => 124, 'CDF' => 976, 'CHE' => 947, 'CHF' => 756,
        'CHW' => 948, 'CLF' => 990, 'CLP' => 152, 'CNY' => 156, 'COP' => 170, 'COU' => 970, 'CRC' => 188, 'CSD' => 891,
        'CSK' => 200, 'CUC' => 931, 'CUP' => 192, 'CVE' => 132, 'CYP' => 196, 'CZK' => 203, 'DDM' => 278, 'DEM' => 276,
        'DJF' => 262, 'DKK' => 208, 'DOP' => 214, 'DZD' => 12, 'ECS' => 218, 'ECV' => 983, 'EEK' => 233, 'EGP' => 818,
        'ERN' => 232, 'ESA' => 996, 'ESB' => 995, 'ESP' => 724, 'ETB' => 230, 'EUR' => 978, 'FIM' => 246, 'FJD' => 242,
        'FKP' => 238, 'FRF' => 250, 'GBP' => 826, 'GEK' => 268, 'GEL' => 981, 'GHC' => 288, 'GHS' => 936, 'GIP' => 292,
        'GMD' => 270, 'GNF' => 324, 'GQE' => 226, 'GRD' => 300, 'GTQ' => 320, 'GWP' => 624, 'GYD' => 328, 'HKD' => 344,
        'HNL' => 340, 'HRD' => 191, 'HRK' => 191, 'HTG' => 332, 'HUF' => 348, 'IDR' => 360, 'IEP' => 372, 'ILS' => 376,
        'INR' => 356, 'IQD' => 368, 'IRR' => 364, 'ISK' => 352, 'ITL' => 380, 'JMD' => 388, 'JOD' => 400, 'JPY' => 392,
        'KES' => 404, 'KGS' => 417, 'KHR' => 116, 'KMF' => 174, 'KPW' => 408, 'KRW' => 410, 'KWD' => 414, 'KYD' => 136,
        'KZT' => 398, 'LAK' => 418, 'LBP' => 422, 'LKR' => 144, 'LRD' => 430, 'LSL' => 426, 'LTL' => 440, 'LTT' => 440,
        'LUC' => 989, 'LUF' => 442, 'LUL' => 988, 'LVL' => 428, 'LVR' => 428, 'LYD' => 434, 'MAD' => 504, 'MDL' => 498,
        'MGA' => 969, 'MGF' => 450, 'MKD' => 807, 'MLF' => 466, 'MMK' => 104, 'MNT' => 496, 'MOP' => 446, 'MRO' => 478,
        'MTL' => 470, 'MUR' => 480, 'MVR' => 462, 'MWK' => 454, 'MXN' => 484, 'MXV' => 979, 'MYR' => 458, 'MZM' => 508,
        'MZN' => 943, 'NAD' => 516, 'NGN' => 566, 'NIO' => 558, 'NLG' => 528, 'NOK' => 578, 'NPR' => 524, 'NZD' => 554,
        'OMR' => 512, 'PAB' => 590, 'PEI' => 604, 'PEN' => 604, 'PES' => 604, 'PGK' => 598, 'PHP' => 608, 'PKR' => 586,
        'PLN' => 985, 'PLZ' => 616, 'PTE' => 620, 'PYG' => 600, 'QAR' => 634, 'ROL' => 642, 'RON' => 946, 'RSD' => 941,
        'RUB' => 643, 'RUR' => 810, 'RWF' => 646, 'SAR' => 682, 'SBD' => 90, 'SCR' => 690, 'SDD' => 736, 'SDG' => 938,
        'SEK' => 752, 'SGD' => 702, 'SHP' => 654, 'SIT' => 705, 'SKK' => 703, 'SLL' => 694, 'SOS' => 706, 'SRD' => 968,
        'SRG' => 740, 'SSP' => 728, 'STD' => 678, 'SVC' => 222, 'SYP' => 760, 'SZL' => 748, 'THB' => 764, 'TJR' => 762,
        'TJS' => 972, 'TMM' => 795, 'TMT' => 934, 'TND' => 788, 'TOP' => 776, 'TPE' => 626, 'TRL' => 792, 'TRY' => 949,
        'TTD' => 780, 'TWD' => 901, 'TZS' => 834, 'UAH' => 980, 'UAK' => 804, 'UGX' => 800, 'USD' => 840, 'USN' => 997,
        'USS' => 998, 'UYI' => 940, 'UYU' => 858, 'UZS' => 860, 'VEB' => 862, 'VEF' => 937, 'VND' => 704, 'VUV' => 548,
        'WST' => 882, 'XAF' => 950, 'XCD' => 951, 'XEU' => 954, 'XOF' => 952, 'XPF' => 953, 'YDD' => 720, 'YER' => 886,
        'YUM' => 891, 'YUN' => 890, 'ZAL' => 991, 'ZAR' => 710, 'ZMK' => 894, 'ZMW' => 967, 'ZRN' => 180, 'ZRZ' => 180,
        'ZWD' => 716, 'ZWL' => 932, 'ZWR' => 935
    );

    /**
     *  Method used by payment gateway.
     *
     *  If this method return a \Thelia\Core\HttpFoundation\Response instance, this response is sent to the
     *  browser.
     *
     *  In many cases, it's necessary to send a form to the payment gateway. On your response you can return this form already
     *  completed, ready to be sent
     *
     * @param  Order $order processed order
     * @return \Symfony\Component\HttpFoundation\Response the HTTP response
     * @throws \Exception
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function pay(Order $order)
    {
        $payline3x = $this->getRequest()->getSession()->get('isPayline3x');
        $this->getRequest()->getSession()->remove('isPayline3x');
        // check if the configuration is valid
        if(!$this->checkValidConfiguration()){
            return new RedirectResponse(
                $this->getPaymentFailurePageUrl(
                    $order->getId(),
                    $doWebPaymentResponse->result['longMessage'] ?? "Erreur de configuration"
                )
            );
        }
        // create an instance
        $paylineSDK = new PaylineSDK(
            Payline::getConfigValue(Payline::MERCHANT_ID),
            Payline::getConfigValue(Payline::ACCESS_KEY),
            '',
            '',
            '',
            '',
            Payline::getConfigValue(Payline::MODE) === 'PRODUCTION' ?
                PaylineSDK::ENV_PROD :
                PaylineSDK::ENV_HOMO,
            THELIA_LOG_DIR . 'payline.log'
        );

        // call a web service, for example doWebPayment
        $doWebPaymentRequest = [];

        $doWebPaymentRequest['cancelURL'] = $this->getPaymentFailurePageUrl($order->getId(), "Vous avez annulé le paiement");
        $doWebPaymentRequest['returnURL'] = $this->getPaymentSuccessPageUrl($order->getId());
        $doWebPaymentRequest['notificationURL'] =
            URL::getInstance()->absoluteUrl('/payline/notification');

        $totalAmount = round(100 * $order->getTotalAmount());

        // PAYMENT
        $doWebPaymentRequest['payment']['amount'] = $totalAmount; // this value has to be an integer amount is sent in cents
        $doWebPaymentRequest['payment']['currency'] = self::$alpha3ToNumeric[$order->getCurrency()->getCode()]; // ISO 4217 code for euro
        $doWebPaymentRequest['payment']['action'] = 101; // 101 stand for "authorization+capture"
        $doWebPaymentRequest['payment']['mode'] = 'CPT'; // one shot payment

        if ($payline3x){
            $doWebPaymentRequest['payment']['mode'] = 'NX';
            $doWebPaymentRequest['recurring']['firstAmount'] = floor($totalAmount/3) + ($totalAmount % 3);
            $doWebPaymentRequest['recurring']['amount'] = floor($totalAmount/3);
            $doWebPaymentRequest['recurring']['billingCycle'] = 40; // monthly
            $doWebPaymentRequest['recurring']['billingLeft'] = 3; // nb of payment
        }

        // ORDER
        $doWebPaymentRequest['order']['ref'] = $order->getRef(); // the reference of your order
        $doWebPaymentRequest['order']['amount'] = $totalAmount; // may differ from payment.amount if currency is different
        $doWebPaymentRequest['order']['currency'] = self::$alpha3ToNumeric[$order->getCurrency()->getCode()]; // ISO 4217 code for euro
        $doWebPaymentRequest['order']['date'] = $order->getCreatedAt("d/m/Y H:i");

        // CONTRACT NUMBERS
        $doWebPaymentRequest['payment']['contractNumber'] = Payline::getConfigValue(PayLine::CONTRACT_NUMBER);

        $paylineSDK->addPrivateData([ 'key' => 'orderId', 'value' => $order->getId() ]);

        $doWebPaymentResponse = $paylineSDK->doWebPayment($doWebPaymentRequest);

        if (empty($doWebPaymentResponse['redirectURL'])) {
            return new RedirectResponse(
                $this->getPaymentFailurePageUrl(
                    $order->getId(),
                    $doWebPaymentResponse->result['longMessage'] ?? "Erreur indeterminée"
                )
            );
        }

        return new RedirectResponse($doWebPaymentResponse['redirectURL']);
    }

    public function isValidPayment()
    {
        // check if the configuration is valid
        if(!$this->checkValidConfiguration()){
            Tlog::getInstance()->error('The configuration in module Payline is invalid');
            return false;
        }

        $mode = self::getConfigValue(Payline::MODE);
        $valid = false;
        if ($mode === 'TEST'){
            $raw_ips = explode("\n", self::getConfigValue(self::ALLOWED_IP_LIST, ''));
            $allowed_client_ips = array();

            foreach ($raw_ips as $ip) {
                $allowed_client_ips[] = trim($ip);
            }

            $client_ip = $this->getRequest()->getClientIp();

            $valid = in_array($client_ip, $allowed_client_ips);
        }

        if ($valid) {
            // Check if total order amount is in the module's limits
            $valid = $this->checkMinMaxAmount(self::MINIMUM_AMOUNT, self::MAXIMUM_AMOUNT);
        }
        if (Payline::getConfigValue(Payline::ACTIVATE_PAYMENT_3X)){
            $this->getRequest()->getSession()->set('ValidPayline3x', $this->checkMinMaxAmount(self::MINIMUM_AMOUNT_3X, self::MAXIMUM_AMOUNT_3X));
        }

        return $valid;
    }

    protected function checkValidConfiguration()
    {
        return (
            Payline::getConfigValue(Payline::MERCHANT_ID) &&
            Payline::getConfigValue(Payline::ACCESS_KEY) &&
            Payline::getConfigValue(Payline::CONTRACT_NUMBER)
        );

    }

    protected function checkMinMaxAmount($min, $max)
    {
        $order_total = $this->getCurrentOrderTotalAmount();

        $min_amount = self::getConfigValue($min, 0);
        $max_amount = self::getConfigValue($max, 0);

        return $order_total > 0 && ($min_amount <= 0 || $order_total >= $min_amount) && ($max_amount <= 0 || $order_total <= $max_amount);
    }

}
