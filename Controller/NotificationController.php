<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Payline\Controller;

use Payline\Payline;
use Payline\PaylineSDK;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Log\Tlog;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Module\BasePaymentModuleController;

/**
 * Payline payment module
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class NotificationController extends BasePaymentModuleController
{
    /**
     * @return Response
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function notificationAction()
    {
        $resp = new Response();

        if (null === $token = $this->getRequest()->get('paylinetoken')) {
            Tlog::getInstance()->error("Notification Payline appelée sans token.");
            $resp->setContent("No token");
            return $resp;
        }

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

        $response = $paylineSDK->getWebPaymentDetails(
            [ 'token' => $token ]
        );

        $privateData = $response['privateDataList']['privateData'] ?? [];
        $key = $privateData['key'] ?? null;
        $orderId = $privateData['value'] ?? null;

        if ($key !== 'orderId') {
            Tlog::getInstance()->error("Order ID absent de la réponse Payline.");
            $resp->setContent("No order reference");
            return $resp;
        }

        if (null === $order = OrderQuery::create()->findPk($orderId)) {
            Tlog::getInstance()->error("Pas de commande trouvée pour l'ID: ". $orderId);
            $resp->setContent("Order not found");
            return $resp;
        }

        if (isset($response['transaction']['id'])) {
            if ($response['result']['code'] === '00000') {
                Tlog::getInstance()->info("Paiement de la commande " . $order->getRef() . " confirmé, transaction ID " . $response['transaction']['id']);

                $order
                    ->setTransactionRef($response['transaction']['id'])
                    ->save()
                ;

                $this->confirmPayment($order->getId());

                $resp->setContent("OK");
                return $resp;
            }

            Tlog::getInstance()->info("Echec du paiement de la commande ".$order->getRef().", raison: ".$response['result']['code']);
        }

        Tlog::getInstance()->info("Echec du paiement de la commande ".$order->getRef());

        // Cancel the order
        $event = (new OrderEvent($order))
            ->setStatus(OrderStatusQuery::getCancelledStatus()->getId());

        $this->dispatch(TheliaEvents::ORDER_UPDATE_STATUS, $event);

        $resp->setContent("Order canceled");

        return $resp;
    }

    public function payment3x()
    {
        $this->getSession()->set('isPayline3x', 1);
    }

    protected function getModuleCode(): string
    {
        return "Paylib";
    }
}
