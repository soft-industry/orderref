<?php
/**
* 2018 Soft Industry
*
*   @author    Skorobogatko Alexei <a.skorobogatko@soft-industry.com>
*   @copyright 2018 Soft-Industry
*   @license   http://opensource.org/licenses/afl-3.0.php
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

class Orderref extends Module
{
    const ORDERREF_LENGTH_MIN = 6;
    const ORDERREF_LENGTH_MAX = 12;
    const ORDERREF_LENGTH_DEFAULT = 8;
    
    const ORDERREF_MODE_RANDOM = 1; // Generate a random number reference.
    const ORDERREF_MODE_CONSEQUENT = 2; // Consequent reference based on order id.
    const ORDERREF_MODE_PS = 3; // Don't change order reference.
    
    /**
     * @var string Configuration form contents.
     */
    private $_html = '';
    
    public function __construct()
    {
        $this->name = 'orderref';
        $this->tab = 'administration';
        $this->version = '0.1.0';
        $this->author = 'Skorobogatko Alexei';
        $this->need_instance = 0;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Order reference');
        $this->description = $this->l('Customize your order reference.');
        $this->confirmUninstall = $this->l('By uninstalling this module order reference generation will be served by Prestashop. Are you sure to continute ?');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        return parent::install()
            && $this->registerHook('actionObjectOrderAddBefore')
            && Configuration::updateValue('ORDERREF_LENGTH', self::ORDERREF_LENGTH_DEFAULT)
            && Configuration::updateValue('ORDERREF_MODE', self::ORDERREF_MODE_RANDOM)
        ;
    }

    /**
     * Module uninstallation.
     *
     * @return bool
     */
    public function uninstall()
    {
        return Configuration::deleteByName('ORDERREF_LENGTH')
            && Configuration::deleteByName('ORDERREF_MODE')
            && parent::uninstall()
        ;
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        $this->_html = '';
        
        /**
         * If values have been submitted in the form, validate and process.
         */
        if (((bool)Tools::isSubmit('submitOrderrefModule')) == true) {
            $errors = $this->postValidation();
            if (!count($errors)) {
                $this->postProcess();
            } else {
                $this->_html .= $this->displayError($errors);
            }
        }
        
        $this->_html .= $this->renderForm();

        return $this->_html;
    }

    /**
     * Create the form that will be displayed in the module configuration.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitOrderrefModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Creates the structure of the module form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'radio',
                        'name' => 'ORDERREF_MODE',
                        'label' => $this->l('How to generate order references'),
                        'desc' => $this->l('Choose between generation methods'),
                        'values' => array(
                            array(
                                'id' => 'ORDERREF_MODE_RANDOM',
                                'value' => self::ORDERREF_MODE_RANDOM,
                                'label' => $this->l('Random numbers'),
                            ),
                            array(
                                'id' => 'ORDERREF_MODE_CONSEQUENT',
                                'value' => self::ORDERREF_MODE_CONSEQUENT,
                                'label' => $this->l('Consequent'),
                            ),
                            array(
                                'id' => 'ORDERREF_MODE_PS',
                                'value' => self::ORDERREF_MODE_PS,
                                'label' => $this->l('Don\'t change an order reference'),
                            ),
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'ORDERREF_LENGTH',
                        'label' => $this->l('Length of reference'),
                        'desc' => $this->l('Length of generated reference value'),
                        'required' => true,
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the form inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'ORDERREF_LENGTH' => Configuration::get('ORDERREF_LENGTH'),
            'ORDERREF_MODE' => Configuration::get('ORDERREF_MODE'),
        );
    }
    
    /**
     * Validates the form post data.
     *
     * @return array Returns a list of error messages. An empty list means that
     * no validation errors.
     */
    protected function postValidation()
    {
        $errors = array();

        // "Length of reference" field.
        if (!($length = Tools::getValue('ORDERREF_LENGTH'))) {
            $errors[] = $this->l('The "Length of reference" field is required.');
        }
        elseif (!Validate::isInt($length)) {
            $errors[] = $this->l('The "Length of reference" field must be an integer number.');
        }
        elseif (!($length >= self::ORDERREF_LENGTH_MIN && $length <= self::ORDERREF_LENGTH_MAX)) {
            $errors[] = $this->l('The "Length of reference" field limits exceed. Value must be between')
                . ' ' . self::ORDERREF_LENGTH_MIN . ' '
                . $this->l('and')
                . ' ' . self::ORDERREF_LENGTH_MAX;
        }
        
        // "How to generate" field.
        if (!($mode = Tools::getValue('ORDERREF_MODE'))) {
            $errors[] = $this->l('The "How to generate order references" field is required.');
        }
        elseif (!(Validate::isInt($mode) &&
            ($mode == self::ORDERREF_MODE_RANDOM
                || $mode == self::ORDERREF_MODE_CONSEQUENT
                || $mode == self::ORDERREF_MODE_PS))) {
            $errors[] = $this->l('The "How to generate order references" field has illegal value.');
        }

        return $errors;
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
        
        $this->_html .= $this->displayConfirmation($this->l('Settings updated'));
    }

    /**
     * Implementation of actionObjectOrderAddBefore hook.
     *
     * @param array $params
     */
    public function hookActionObjectOrderAddBefore($params)
    {
        if (!isset($params['object'])) {
            return;
        }
        
        /** @var Order $order */
        $order = $params['object'];
        
        // Skip orders that already created.
        if (isset($order->id)) {
            return;
        }
        
        $length = Configuration::get('ORDERREF_LENGTH');
        $mode = Configuration::get('ORDERREF_MODE');
        
        if ($mode == self::ORDERREF_MODE_PS) {
            // Don't change a reference.
            return;
        }
        
        // Generate a new order reference.
        do {
            switch ($mode) {
                case self::ORDERREF_MODE_RANDOM:
                    $reference = Tools::passwdGen($length, 'NUMERIC');
                    break;
                
                case self::ORDERREF_MODE_CONSEQUENT:
                    $reference = $this->generateConsequent($length);
                    break;
                
                default:
                    return; // Leave reference as is.
            }
        } while (Order::getByReference($reference)->count());
        
        $order->reference = $reference;
    }
    
    /**
     * Generates a consequent order reference based on maximum order id.
     *
     * @param int $length Length of the reference.
     * @param string $char A character for padding.
     *
     * @return string A reference padded by specified character.
     */
    protected function generateConsequent($length, $char = '0')
    {
        $sql = 'SELECT MAX(id_order) FROM `'._DB_PREFIX_.'orders`';
        $max = (int) Db::getInstance()->getValue($sql);
        $max++; // Plus a new order.
        return str_pad((string) $max, $length, $char, STR_PAD_LEFT);
    }
}
