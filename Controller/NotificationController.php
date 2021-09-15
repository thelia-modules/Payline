<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia                                                                       */
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
/*      along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace Payline\Controller;

use Payline\Payline;
use Payline\PaylineSDK;
use Symfony\Component\Routing\Router;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Log\Tlog;
use Thelia\Model\Order;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;
use Thelia\Module\BasePaymentModuleController;
use Thelia\Tools\URL;

/**
 * Payline payment module
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class NotificationController extends BasePaymentModuleController
{
    /**
     * @return Response|null
     * @throws \Exception
     */
    public function notificationAction(): ?Response
    {
        $resp = new Response();

        if (null === $token = $this->getRequest()->get('token')) {
            Tlog::getInstance()->error("Notification Payline appelée sans token. Réponse:".print_r($this->getRequest(), 1));
            $resp->setContent("No token");
            return $resp;
        }

        $response = $this->getWebPaymentDetails($token);

        if (null !== $resp = $this->getOrderFromResponse($response, $order)) {
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

    public function payment3x(): void
    {
        $this->getSession()->set('isPayline3x', 1);
    }


    /**
     * Analyser la réponse de payline et rediriger vers la page d'erreur ou de succes, selon le résultat.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function analyzePaymentReturnAction()
    {
        $resp = new Response();

        if (null === $token = $this->getRequest()->get('paylinetoken')) {
            Tlog::getInstance()->error("Notification Payline appelée sans token. Réponse:".print_r($this->getRequest(), 1));
            $resp->setContent("No token");
            return $resp;
        }

        $response = $this->getWebPaymentDetails($token);

        /** @var Order $order */
        if (null !== $resp = $this->getOrderFromResponse($response, $order)) {
            return $resp;
        }

        $frontOfficeRouter = $this->getContainer()->get('router.front');

        // Succès: redirection vers la page order-placed
        if ($response['result']['code'] === '00000') {
            return $this->generateRedirect(
                URL::getInstance()->absoluteUrl(
                    $frontOfficeRouter->generate(
                        "order.placed",
                        ["order_id" => $order->getId()],
                        Router::ABSOLUTE_URL
                    )
                )
            );
        }

        // Echec: redirection vers la order-failed
        return $this->generateRedirect(
            URL::getInstance()->absoluteUrl(
                $frontOfficeRouter->generate(
                    "order.failed",
                    [
                        "order_id" => $order->getId(),
                        "message" => $this->getTranslator()->trans(
                            "Votre paiement a été refusé (code %code - %message)",
                            [
                                '%code' => $response['result']['code'],
                                '%message' => $response['result']['longMessage'] ?? 'raison inconnue'
                            ]
                        )
                    ],
                    Router::ABSOLUTE_URL
                )
            )
        );
    }

    /**
     * @param array $response
     * @param Order|null $order
     * @return Response|null
     */
    protected function getOrderFromResponse(array $response, ?Order &$order): ?Response
    {
        $resp = new Response();

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

        return null;
    }

    /**
     * @param string $token
     * @return array
     */
    protected function getWebPaymentDetails(string $token): array
    {
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

        return $paylineSDK->getWebPaymentDetails(
            [ 'token' => $token ]
        );
    }

    protected function getModuleCode(): string
    {
        return "Paylib";
    }
}
