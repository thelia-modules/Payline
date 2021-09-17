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

use Payline\Exception\PaymentException;
use Payline\Payline;
use Payline\PaylineSDK;
use Symfony\Component\Routing\Router;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Exception\TheliaProcessException;
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

    public function payment3x(): void
    {
        $this->getSession()->set('isPayline3x', 1);
    }

    /**
     * Traiter la notification reçue du serveur PayLine
     *
     * @return Response
     * @throws \Exception
     */
    public function notificationAction(): Response
    {
        try {
            $this->processPaylineReturn('token');

            return new Response('OK');
        } catch (\Exception $ex) {
            // Echec: redirection vers la order-failed
            return new Response($ex->getMessage());
        }
    }

    /**
     * Au retour du formulaire de paiement, analyser la réponse de payline
     * et rediriger vers la page d'erreur ou de succes, selon le résultat.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function analyzePaymentReturnAction(): \Symfony\Component\HttpFoundation\Response
    {
        $frontOfficeRouter = $this->getContainer()->get('router.front');

        try {
            $order = $this->processPaylineReturn('paylinetoken');

            // Succès: redirection vers la page order-placed
            return $this->generateRedirect(
                URL::getInstance()->absoluteUrl(
                    $frontOfficeRouter->generate(
                        "order.placed",
                        ["order_id" => $order->getId()],
                        Router::ABSOLUTE_URL
                    )
                )
            );
        } catch (PaymentException $ex) {
            // Echec du paiement: redirection vers order-failed
            return $this->generateRedirect(
                URL::getInstance()->absoluteUrl(
                    $frontOfficeRouter->generate(
                        "order.failed",
                        [
                            "order_id" => $ex->getOrder()->getId(),
                            "message" => $ex->getMessage()
                        ],
                        Router::ABSOLUTE_URL
                    )
                )
            );
        } catch (\Exception $ex) {
            // Ici on n'a pas pu retrouver la commande : pas possible donc d'afficher order-failed
            // on redirige vers la page d'erreur générique.
            return $this->generateRedirect(URL::getInstance()->absoluteUrl('error'));
        }
    }

    /**
     * @param $tokenName
     * @return Order
     * @throws PaymentException|\Propel\Runtime\Exception\PropelException
     */
    protected function processPaylineReturn($tokenName): Order
    {
        $response = $this->getWebPaymentDetails($tokenName);

        $order = $this->getOrderFromResponse($response);

        // Si la commande est déjà payée (cas ou la notif de Payline arrive avant le retour sur le site),
        // on ne fait rien, le traitement du paiement a déjà été effectué.
        if (! $order->isPaid()) {
            $this->checkPaymentResult($response, $order);
        }

        return $order;
    }

    /**
     * @throws PaymentException
     * @throws \Exception
     */
    protected function checkPaymentResult(array $response, Order $order): void
    {
        $message = $this->getTranslator()->trans(
            "Erreur technique: ID de transaction absent"
        );

        if (isset($response['transaction']['id'])) {
            if ($response['result']['code'] === '00000') {
                Tlog::getInstance()->info("Paiement de la commande " . $order->getRef() . " confirmé, transaction ID " . $response['transaction']['id']);

                $order
                    ->setTransactionRef($response['transaction']['id'])
                    ->save()
                ;

                $this->confirmPayment($order->getId());

                return;
            }

            Tlog::getInstance()->info("Echec du paiement de la commande ".$order->getRef().", raison: ".$response['result']['code']);

            $message = $this->getTranslator()->trans(
                "Votre paiement a été refusé (code %code - %message)",
                [
                    '%code' => $response['result']['code'],
                    '%message' => $response['result']['longMessage'] ?? 'raison inconnue'
                ]
            );
        }

        Tlog::getInstance()->info("Echec du paiement de la commande ".$order->getRef(). ": $message");

        // Cancel the order
        $event = (new OrderEvent($order))
            ->setStatus(OrderStatusQuery::getCancelledStatus()->getId());

        $this->dispatch(TheliaEvents::ORDER_UPDATE_STATUS, $event);

        throw new PaymentException($order, $message);
    }

    /**
     * @param array $response
     * @return Order
     * @throws TheliaProcessException
     */
    protected function getOrderFromResponse(array $response): Order
    {
        $privateData = $response['privateDataList']['privateData'] ?? [];
        $key = $privateData['key'] ?? null;
        $orderId = $privateData['value'] ?? null;

        if ($key !== 'orderId') {
            Tlog::getInstance()->error("Order ID absent de la réponse Payline.");
            throw new TheliaProcessException(
                $this->getTranslator()->trans("Echec du paiement: la référence de commande est absente", [], Payline::DOMAIN_NAME)
            );
        }

        if (null === $order = OrderQuery::create()->findPk($orderId)) {
            Tlog::getInstance()->error("Pas de commande trouvée pour l'ID: ". $orderId);
            throw new TheliaProcessException(
                $this->getTranslator()->trans("Echec du paiement: la commande n'a pas pu être retrouvée", [], Payline::DOMAIN_NAME)
            );
        }

        return $order;
    }

    /**
     * @param string $tokenName
     * @return array
     * @throws TheliaProcessException
     */
    protected function getWebPaymentDetails(string $tokenName): array
    {
        if (null === $token = $this->getRequest()->get($tokenName)) {
            Tlog::getInstance()->error("Notification Payline appelée sans token. Réponse:".print_r($this->getRequest(), 1));

            throw new TheliaProcessException(
                $this->getTranslator()->trans("Erreur technique: le token PayLien est absent.", [], Payline::DOMAIN_NAME)
            );
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

        return $paylineSDK->getWebPaymentDetails(
            [ 'token' => $token ]
        );
    }

    protected function getModuleCode(): string
    {
        return 'Paylib';
    }
}
