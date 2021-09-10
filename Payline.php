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

        if ($order->getCurrency()->getCode() !== 'EUR') {
            throw new TheliaProcessException("La seule devis supportée est l'Euro");
        }

        $totalAmount = round(100 * $order->getTotalAmount());

        // PAYMENT
        $doWebPaymentRequest['payment']['amount'] = $totalAmount; // this value has to be an integer amount is sent in cents
        $doWebPaymentRequest['payment']['currency'] = 978; // ISO 4217 code for euro
        $doWebPaymentRequest['payment']['action'] = 101; // 101 stand for "authorization+capture"
        $doWebPaymentRequest['payment']['mode'] = 'CPT'; // one shot payment

        // ORDER
        $doWebPaymentRequest['order']['ref'] = $order->getRef(); // the reference of your order
        $doWebPaymentRequest['order']['amount'] = $totalAmount; // may differ from payment.amount if currency is different
        $doWebPaymentRequest['order']['currency'] = 978; // ISO 4217 code for euro
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
        return true;
    }

}
