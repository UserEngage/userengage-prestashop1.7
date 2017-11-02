<?php
if  (!defined('_PS_VERSION_'))
{
    exit;
}

class UEINTEGRATION extends Module
{
    public function __construct()
    {
        $this->name = 'ueintegration';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Userengage @KarolMilewski';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Userengage integration');
        $this->description = $this->l('Userengage integration with ');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');

        if (!Configuration::get('UEINTEGRATION'))
            $this->warning = $this->l('No name provided');
    }

    public function install() {
        if (Shop::isFeatureActive())
            Shop::setContext(Shop::CONTEXT_ALL);

        if (!parent::install() || !$this->registerHook(array('displayHeader', 'productFooter', 'displayProductButtons', 'displayInvoice', 'displayOrderConfirmation', 'displayCustomerAccountForm', 'productActions')) ||
            !Configuration::updateValue('UE_APIKEY', '')
        )
            return false;
        return true;
    }

    public function hookDisplayHeader()
    {
        $customer = $this->context->customer;
        $address_delivery = new Address($this->context->cart->id_address_delivery);

        $isLogged =  $customer->isLogged();
        $widget = '<script data-cfasync="false" type="text/javascript">';

        if ($isLogged) {
            $widget .= 'window.civchat = {
                            apiKey: "'.Configuration::get('UE_APIKEY').'",
                            name: "'.Tools::substr($customer->firstname, 0, 1)." ".$customer->lastname.'",
                            email: "'.$customer->email.'",
                            cart_value: "",
                            product_to_cart_url: "",
                            product_to_cart_image: "",
                            last_order_date: "",
                            total_revenue: "",
                            address1: "'.Tools::htmlentitiesUTF8($address_delivery->address1).'",
                            address2: "'.Tools::htmlentitiesUTF8($address_delivery->address2).'",
                            city: "'.Tools::htmlentitiesUTF8($address_delivery->city).'",
                            country: "'.(string)$address_delivery->country.'",
                            state_id: "'.(int)$address_delivery->id_state.'",
                            province_code: "",
                            postal_code: "'.Tools::htmlentitiesUTF8($address_delivery->postcode).'",
                            company: "'.Tools::htmlentitiesUTF8($address_delivery->company).'",
                            phone: "'.Tools::htmlentitiesUTF8($address_delivery->phone).'",
                            newsletter: "'.$customer->newsletter.'",
                        }';
        } else {
            $widget .= 'window.civchat = {
                            apiKey: "'.Configuration::get('UE_APIKEY').'",
                        }';
        }

        $widget .= '</script>';
        $widget .= '<script data-cfasync="false" type="text/javascript" src="https://widget.userengage.com/widget.js"></script>';

        return $widget;
    }

    public function getContent() {
        $output = '';
        if (Tools::isSubmit('submit' . $this->name)) {
            $ue_apikey = Tools::getValue('UE_APIKEY');
            if (!empty($ue_apikey)) {
                Configuration::updateValue('UE_APIKEY', $ue_apikey);
                $output .= $this->displayConfirmation($this->l('UserEngage ApiKey updated successfully.'));
            }
        }
        return $output .= $this->displayForm();
    }

    public function displayForm()
    {
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        // Init fields from array
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('UserEngage Settings'),
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('ApiKey'),
                    'name' => 'UE_APIKEY',
                    'required' => true
                )
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            )
        );

        $helper = new HelperForm();

        // module, token and currentindex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        // language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submit'.$this->name;

        $helper->toolbar_btn = array(
            'save' =>
                array(
                    'desc' => $this->l('Save'),
                    'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                        '&token='.Tools::getAdminTokenLite('AdminModules'),
                ),
            'back' =>
                array(
                    'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                    'desc' => $this->l('Back to list')
                )
        );

        // load current value
        $helper->fields_value['UE_APIKEY'] = Configuration::get('UE_APIKEY');

        return $helper->generateForm($fields_form);

    }

    public function hookProductActions($params)
    {
        $context = Context::getContext();
        $product = $context->controller->getProduct();
        $image = Product::getCover((int)Tools::getValue('id_product'));
        $qty = Tools::getValue('quantity_wanted');

        if (!$qty) {
            $qty = 1;
        }

        $product_detail = array(
            'price' => Tools::ps_round($product->getPrice(), 2).' '.$context->currency->sign,
            'image_url' => $context->link->getImageLink($product->link_rewrite, $image['id_image'], 'medium_default'),
            'id' => $product->id,
            'product_url' => $context->link->getProductLink((int)$product->id, $product->link_rewrite, $product->category, $product->ean13),
            'name' => $product->name,
            'category' => $product->category,
            'quantity' => $qty
        );

        $this->smarty->assign($product_detail);

        # productClick
        $event = '<script type="text/javascript"  data-cfasync="false">';
        $event .= 'var timecheck =  setInterval(function() { if (typeof userengage == "function") { ';
        $event .= 'userengage("event.productClick", '.json_encode($product_detail).');';
        $event .= 'window.civchat.product_view_image = "'.$context->link->getImageLink($product->link_rewrite, $image['id_image'], 'medium_default').'";';
        $event .= 'window.civchat.product_view_url = "'.$context->link->getProductLink((int)$product->id, $product->link_rewrite, $product->category, $product->ean13).'";';
        $event .= ' clearInterval(timecheck);} },500);';
        $event .= '</script>';
        echo $event;

        # addToCart
        return $this->display(__FILE__, 'views/addToCart.tpl');
    }

    protected function transformDescriptionWithImg($desc)
    {
        $reg = '/\[img\-([0-9]+)\-(left|right)\-([a-zA-Z0-9-_]+)\]/';
        while (preg_match($reg, $desc, $matches)) {
            $link_lmg = $this->context->link->getImageLink($this->product->link_rewrite, $this->product->id.'-'.$matches[1], $matches[3]);
            $class = $matches[2] == 'left' ? 'class="imageFloatLeft"' : 'class="imageFloatRight"';
            $html_img = '<img src="'.$link_lmg.'" alt="" '.$class.'/>';
            $desc = str_replace($matches[0], $html_img, $desc);
        }
        return $desc;
    }

    public function hookDisplayOrderConfirmation($params)
    {
        $context = Context::getContext();
        $order = $params['order'];
        $currency = new Currency((int)$order->id_currency, (int)$context->language->id);

        $order_details = array(
            'id' => (int)$order->id,
            'revenue' => Tools::ps_round(Tools::convertPrice($order->total_paid_tax_incl, (int)$order->id_currency, false), 2).' '.$currency->getSign(),
            'tax' => Tools::ps_round($order->total_products_wt, 2).' '.$currency->getSign(),
            'shipping' => Tools::ps_round($order->total_shipping, 2).' '.$currency->getSign(),
        );

        $event = '<script type="text/javascript"  data-cfasync="false">';
        $event .= 'var timecheck =  setInterval(function() { if (typeof userengage == "function") { ';
        $event .= 'userengage("event.purchase", '.json_encode($order_details).');';
        $event .= ' clearInterval(timecheck);} },500);';
        $event .= '</script>';
        echo $event;
    }

    public function hookDisplayCustomerAccountForm($params)
    {

        if (Tools::isSubmit('submitCreate')) {
            $email = Tools::getValue('email');
            $name = Tools::getValue('firstname')." ".Tools::getValue('lastname');
            $register = '<script>';
            $register .= 'userengage("event.registration", {email: "'.$email.'", name: "'.$name.'"});';
            $register .= '</script>';
            echo $register;

        }

    }


}