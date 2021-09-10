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

use Payline\Form\ConfigurationForm;
use Payline\Payline;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Tools\URL;

/**
 * Payline payment module
 *
 * @author Franck Allimant <franck@cqfdev.fr>
 */
class ConfigurationController extends BaseAdminController
{

    /**
     * @return mixed an HTTP response, or
     */
    public function configure()
    {
        if (null !== $response = $this->checkAuth(AdminResources::MODULE, 'Payline', AccessManager::UPDATE)) {
            return $response;
        }

        // Create the Form from the request
        $configurationForm = new ConfigurationForm($this->getRequest());

        try {
            // Check the form against constraints violations
            $form = $this->validateForm($configurationForm, "POST");

            // Get the form field values
            $data = $form->getData();

            foreach ($data as $name => $value) {
                if (is_array($value)) {
                    $value = implode(';', $value);
                }

                Payline::setConfigValue($name, $value);
            }

            // Log configuration modification
            $this->adminLogAppend(
                "payline.configuration.message",
                AccessManager::UPDATE,
                "Payline configuration updated"
            );

            // Redirect to the success URL,
            if ($this->getRequest()->get('save_mode') === 'stay') {
                // If we have to stay on the same page, redisplay the configuration page/
                $route = '/admin/module/Payline';
            } else {
                // If we have to close the page, go back to the module back-office page.
                $route = '/admin/modules';
            }

            return $this->generateRedirect(URL::getInstance()->absoluteUrl($route));

            // An exit is performed after redirect.+
        } catch (FormValidationException $ex) {
            // Form cannot be validated. Create the error message using
            // the BaseAdminController helper method.
            $error_msg = $this->createStandardFormValidationErrorMessage($ex);
        } catch (\Exception $ex) {
            // Any other error
             $error_msg = $ex->getMessage();
        }

        // At this point, the form has errors, and should be redisplayed. We do not redirect,
        // just redisplay the same template.
        // Set up the Form error context, to make error information available in the template.
        $this->setupFormErrorContext(
            $this->getTranslator()->trans("Payline configuration", [], Payline::DOMAIN_NAME),
            $error_msg,
            $configurationForm,
            $ex
        );

        // Do not redirect at this point, or the error context will be lost.
        // Just redisplay the current template.
        return $this->render('module-configure', array('module_code' => 'Payline'));
    }
}
