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

namespace Payline\Form;

use Payline\Payline;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;

/**
 * Payline payment module
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class ConfigurationForm extends BaseForm
{
    protected function buildForm()
    {
        $this->formBuilder
            ->add(
                Payline::MERCHANT_ID,
                'text',
                [
                    'constraints' => [new NotBlank()],
                    'required' => true,
                    'label' => $this->trans('ID Marchant'),
                    'data' => Payline::getConfigValue(Payline::MERCHANT_ID, '12345678'),
                ]
            )
            ->add(
                Payline::ACCESS_KEY,
                'text',
                [
                    'constraints' => [new NotBlank()],
                    'required' => true,
                    'label' => $this->trans('Access Key'),
                    'data' => Payline::getConfigValue(Payline::ACCESS_KEY, '1111111111111111'),
                ]
            )
            ->add(
                Payline::MODE,
                'choice',
                [
                    'constraints' => [new NotBlank()],
                    'required' => true,
                    'choices' => [
                        'TEST' => 'Test',
                        'PRODUCTION' => 'Production',
                    ],
                    'label' => $this->trans('Mode de fonctionnement'),
                    'data' => Payline::getConfigValue(Payline::MODE),
                ]
            )
            ->add(
                PayLine::CONTRACT_NUMBER,
                'text',
                [
                    'required' => true,
                    'constraints' => [new NotBlank()],
                    'label' => 'Numéro de contrat',
                    'data' => Payline::getConfigValue(PayLine::CONTRACT_NUMBER),
                ]
            )
            ->add(
                Payline::ALLOWED_IP_LIST,
                'textarea',
                [
                    'required' => false,
                    'label' => $this->trans('Allowed IPs in test mode'),
                    'data' => Payline::getConfigValue(PayLine::ALLOWED_IP_LIST),
                    'label_attr' => array(
                        'for' => Payline::ALLOWED_IP_LIST,
                        'help' => $this->trans(
                            'List of IP addresses allowed to use this payment on the front-office when in test mode (your current IP is %ip). One address per line',
                            array('%ip' => $this->getRequest()->getClientIp())
                        ),
                        'rows' => 3
                    )
                ]
            )
            ->add(
                Payline::MINIMUM_AMOUNT,
                'number',
                array(
                    'constraints' => array(
                        new NotBlank(),
                        new GreaterThanOrEqual(array('value' => 0))
                    ),
                    'required' => true,
                    'label' => $this->trans('Minimum order total'),
                    'data' => Payline::getConfigValue(Payline::MINIMUM_AMOUNT, 0),
                    'label_attr' => array(
                        'for' => 'minimum_amount',
                        'help' => $this->trans('Minimum order total in the default currency for which this payment method is available. Enter 0 for no minimum')
                    ),
                    'attr' => [
                        'step' => 'any'
                    ]
                )
            )
            ->add(
                Payline::MAXIMUM_AMOUNT,
                'number',
                array(
                    'constraints' => array(
                        new NotBlank(),
                        new GreaterThanOrEqual(array('value' => 0))
                    ),
                    'required' => true,
                    'label' => $this->trans('Maximum order total'),
                    'data' => Payline::getConfigValue(Payline::MAXIMUM_AMOUNT, 0),
                    'label_attr' => array(
                        'for' => 'maximum_amount',
                        'help' => $this->trans('Maximum order total in the default currency for which this payment method is available. Enter 0 for no maximum')
                    ),
                    'attr' => [
                        'step' => 'any'
                    ]
                )
            )
            ->add(
                Payline::SEND_CONFIRMATION_MESSAGE_ONLY_IF_PAID,
                'checkbox',
                [
                    'value' => 1,
                    'required' => false,
                    'label' => $this->trans('Send order confirmation on payment success'),
                    'data' => (boolean)(Payline::getConfigValue(Payline::SEND_CONFIRMATION_MESSAGE_ONLY_IF_PAID, true)),
                    'label_attr' => [
                        'help' => $this->trans(
                            'If checked, the order confirmation message is sent to the customer only when the payment is successful. The order notification is always sent to the shop administrator'
                        )
                    ]
                ]
            )
            ->add(
                Payline::ACTIVATE_PAYMENT_3X,
                'checkbox',
                [
                    'value' => 1,
                    'required' => false,
                    'label' => $this->trans('Activate payment in 3 installments'),
                    'data' => (boolean)(Payline::getConfigValue(Payline::ACTIVATE_PAYMENT_3X, false)),
                ]
            )
            ->add(
                Payline::MINIMUM_AMOUNT_3X,
                'number',
                array(
                    'constraints' => array(
                        new NotBlank(),
                        new GreaterThanOrEqual(array('value' => 0))
                    ),
                    'required' => true,
                    'label' => $this->trans('Minimum order total'),
                    'data' => Payline::getConfigValue(Payline::MINIMUM_AMOUNT_3X, 0),
                    'label_attr' => array(
                        'for' => 'minimum_amount',
                        'help' => $this->trans('Minimum order total in the default currency for which this payment method is available. Enter 0 for no minimum')
                    ),
                    'attr' => [
                        'step' => 'any'
                    ]
                )
            )
            ->add(
                Payline::MAXIMUM_AMOUNT_3X,
                'number',
                array(
                    'constraints' => array(
                        new NotBlank(),
                        new GreaterThanOrEqual(array('value' => 0))
                    ),
                    'required' => true,
                    'label' => $this->trans('Maximum order total'),
                    'data' => Payline::getConfigValue(Payline::MAXIMUM_AMOUNT_3X, 0),
                    'label_attr' => array(
                        'for' => 'maximum_amount',
                        'help' => $this->trans('Maximum order total in the default currency for which this payment method is available. Enter 0 for no maximum')
                    ),
                    'attr' => [
                        'step' => 'any'
                    ]
                )
            )
        ;
    }

    protected function trans($str, $params = [])
    {
        return Translator::getInstance()->trans($str, $params, Payline::DOMAIN_NAME);
    }

    public function getName()
    {
        return 'payline_configuration_form';
    }
}
