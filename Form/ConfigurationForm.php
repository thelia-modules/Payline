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
use Symfony\Component\Validator\Constraints\NotBlank;
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
                    'label' => 'ID Marchant',
                    'data' => Payline::getConfigValue(Payline::MERCHANT_ID, '12345678'),
                ]
            )
            ->add(
                Payline::ACCESS_KEY,
                'text',
                [
                    'constraints' => [new NotBlank()],
                    'required' => true,
                    'label' => 'Access Key',
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
                    'label' => 'Mode de fonctionnement',
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
        ;
    }

    public function getName()
    {
        return 'payline_configuration_form';
    }
}
