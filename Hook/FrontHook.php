<?php


namespace Payline\Hook;


use Payline\Payline;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

class FrontHook extends BaseHook
{
    public function onOrderInvoiceJavascriptInitialization(HookRenderEvent $event)
    {
        if (Payline::getConfigValue(Payline::ACTIVATE_PAYMENT_3X, false) && $this->getRequest()->getSession()->get('ValidPayline3x')) {
            $this->getRequest()->getSession()->remove('ValidPayline3x');
            $event->add($this->render(
                'payment-3x.html',
                [
                    'paylineId' => Payline::getModuleId(),
                ]
            ));
        }

    }
}