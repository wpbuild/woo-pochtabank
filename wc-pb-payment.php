<?php
/*
    Plugin Name: Платежный шлюз Почта Банк для Woocommerce
    Plugin URI: http://wpbuild.ru
    Description: Позволяет использовать платежный шлюз Почта Банк с Инструментом электронной торговли WooCommerce.
    Version: 1.0.6
	Author: WPBuild
	Author URI: http://wpbuild.ru
 */

if (!defined('ABSPATH')) exit;

require_once(ABSPATH . 'wp-admin/includes/plugin.php');

define('PBPAYMENT_NAME', 'Почта Банк');
define('PB_API_PROD_URL', 'https://my.pochtabank.ru/pos-credit?');
define('PB_API_TEST_URL', 'https://mytest.pochtabank.ru:945/pos-credit?');
define('PBPAYMENT_TITLE_1', PBPAYMENT_NAME );
define('PBPAYMENT_TITLE_2', 'Настройка приема электронных платежей через ' . PBPAYMENT_NAME);

function pbpayment_init() {

    load_plugin_textdomain( 'woo-pochtabank', false, dirname( plugin_basename( __FILE__ ) ). '/languages/' );
	require_once('statuses.php'); 

}
add_action( 'plugins_loaded', 'pbpayment_init' );

add_filter('plugin_row_meta', 'pb_register_plugin_links', 10, 2);
function pb_register_plugin_links($links, $file)
{
    $base = plugin_basename(__FILE__);
    if ($file == $base) {
        $links[] = '<a href="admin.php?page=wc-settings&tab=checkout&section=pbpayment">' . __('Settings', 'woocommerce') . '</a>';
    }
    return $links;
}

add_action('plugins_loaded', 'woocommerce_pbpayment', 0);
function woocommerce_pbpayment()
{
    if (!class_exists('WC_Payment_Gateway'))
        return;
    if (class_exists('WC_PBPAYMENT'))
        return;

    class WC_PBPAYMENT extends WC_Payment_Gateway
    {

        public function __construct()
        {

            $this->id = 'pbpayment';
            $this->method_title = __( 'Почта Банк', 'woocommerce' );
            $this->icon = plugin_dir_url(__FILE__) . 'assets/images/pb.svg';

            // Load the settings
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title = $this->get_option('title');
            $this->ttcode = $this->get_option('ttcode');
            $this->ttname = $this->get_option('ttname');
            $this->category = $this->get_option('category');
            $this->returnurl = $this->get_option('returnurl');
//            $this->merchant = $this->get_option('merchant');
//            $this->password = $this->get_option('password');
            $this->test_mode = $this->get_option('test_mode');
//            $this->stage = $this->get_option('stage');
            $this->description = $this->get_option('description');

//            $this->send_order = $this->get_option('send_order');
            $this->tax_system = $this->get_option('tax_system');
            $this->tax_type = $this->get_option('tax_type');

            $this->pData = get_plugin_data(__FILE__);

            // Actions
            add_action('valid-pbpayment-standard-ipn-reques', array($this, 'successful_request'));
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

            // Save options
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // filters
//            add_filter('woocommerce_order_button_text', 'woo_custom_order_button_text');
//            function woo_custom_order_button_text()
//            {
//                return __('Перейти к оплате', 'woocommerce');
//            }

            if (!$this->is_valid_for_use()) {
                $this->enabled = false;
            }

            $this->callb();
        }


        public function callb()
        {

            if (isset($_GET['pbpayment']) AND $_GET['pbpayment'] == 'result') {
				if ( isset($_GET['id']) AND $_GET['id'] !== '') {
		        	$pb_id = $_GET['id'];
		        	$order_id = $_GET['order_id'];
		        	$note = 'ID заявки в ПочтаБанк: ' . $pb_id;

                    $order = new WC_Order($order_id);
                    $order->update_status('pochta-bank', __('ПочтаБанк', 'woocommerce'));
					$order->add_order_note( $note );
					$order->save();
					wc_add_notice(__('ID заявки в ПочтаБанк: ' . $pb_id, 'woocommerce'), 'success');
                    wp_redirect($this->get_return_url($order));
                    exit;
                } else {
                    $order_id = $_GET['order_id'];
                    $order = new WC_Order($order_id);
                    $order->update_status('failed', __('Платеж не оплачен', 'woocommerce'));
                    add_filter('woocommerce_add_to_cart_message', 'my_cart_messages', 99);
                    $order->cancel_order();
                     wc_add_notice(__('Ошибка в проведении оплаты<br/>' . $response['actionCodeDescription'], 'woocommerce'), 'error');
                    wp_redirect($order->get_cancel_order_url());
                    exit;
                }
            }
        }


        /**
         * Check if this gateway is enabled and available in the user's country
         */
        function is_valid_for_use()
        {
            if (!in_array(get_option('woocommerce_currency'), array('RUB'))) {
                return false;
            }
            return true;
        }

        /*
         * Admin Panel Options
         */
        public function admin_options()
        {
            ?>
            <h3><?php _e(PBPAYMENT_TITLE_1, 'woocommerce'); ?></h3>
            <p><?php _e(PBPAYMENT_TITLE_2, 'woocommerce'); ?></p>

            <?php if ($this->is_valid_for_use()) : ?>

            <table class="form-table">

                <?php
                // Generate the HTML For the settings form.
                $this->generate_settings_html();
                ?>
            </table>

        <?php else : ?>
			<div class="inline error">
            	<p>
                    <strong><?php _e('Шлюз отключен', 'woocommerce'); ?></strong>: <?php _e($this->id . ' не поддерживает валюты Вашего магазина.', 'woocommerce'); ?>
                </p>
			</div>
            <?php
        endif;

        }

        /*
         * Initialise Gateway Settings Form Fields
         */
        function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Включить/Выключить', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Включен', 'woocommerce'),
                    'default' => 'yes'
                ),
                'test_mode' => array(
                    'title' => __('Тест режим', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Включен', 'woocommerce'),
                    'description' => __('В этом режиме плата за товар не снимается.', 'woocommerce'),
                    'default' => 'no'
                ),

                'title' => array(
                    'title' => __('Название', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Заголовок, который видит пользователь в процессе оформления заказа.', 'woocommerce'),
                    'default' => __(PBPAYMENT_NAME, 'woocommerce'),
                    'desc_tip' => true,
                ),
                /*
                'merchant' => array(
                    'title' => __('Логин', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Пожалуйста введите Логин', 'woocommerce'),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'password' => array(
                    'title' => __('Пароль', 'woocommerce'),
                    'type' => 'password',
                    'description' => __('Пожалуйста введите пароль.', 'woocommerce'),
                    'default' => '',
                    'desc_tip' => true,
                ),
                */
                'ttcode' => array(
                    'title' => __('Код торговой точки, полученной от Почта Банк', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Пожалуйста введите код', 'woocommerce'),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'ttname' => array(
                    'title' => __('Адрес пункта выдачи товара', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Пожалуйста введите Адрес пункта выдачи товара', 'woocommerce'),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'returnurl' => array(
                    'title' => __('Страница перенаправления после оформления заявки', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Страница перенаправления после оформления заявки', 'woocommerce'),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'category' => array(
                    'title' => __('Категория', 'woocommerce'),
                    'type' => 'select',
                    'default' => '223',
                    'options' => array(
                        '223' => __('Офисная мебель', 'woocommerce'),
                    ),
                ),

                /*
                'stage' => array(
                    'title' => __('Стадийность платежей', 'woocommerce'),
                    'type' => 'select',
                    'default' => 'one-stage',
                    'options' => array(
                        'one-stage' => __('Одностадийные платежи', 'woocommerce'),
                        'two-stage' => __('Двухстадийные платежи', 'woocommerce'),
                    ),
                ),
                */
                'description' => array(
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('Описание метода оплаты которое клиент будет видеть на Вашем сайте.', 'woocommerce'),
                    'default' => 'Оплата с помощью ' . PBPAYMENT_NAME                ),
/*
                'send_order' => array(
                    'title' => __('Передача корзины товаров', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Включена', 'woocommerce'),
                    'description' => __('При выборе опции, будет сформирован и отправлен в налоговую и клиенту чек. Опция платная, за подключением обратитесь в сервисную службу банка. При использовании необходимо настроить НДС продаваемых товаров. НДС рассчитывается согласно законодательству РФ, возможны расхождения в размере НДС с суммой рассчитанной магазином.', 'woocommerce'),
                    'default' => 'no'
                ),

                'tax_system' => array(
                    'title' => __('Система налогообложения', 'woocommerce'),
                    'type' => 'select',
                    'default' => '0',
                    'options' => array(
                        '0' => __('Общая', 'woocommerce'),
                        '1' => __('Упрощённая, доход', 'woocommerce'),
                        '2' => __('Упрощённая, доход минус расход', 'woocommerce'),
                        '3' => __('Eдиный налог на вменённый доход', 'woocommerce'),
                        '4' => __('Eдиный сельскохозяйственный налог', 'woocommerce'),
                        '5' => __('Патентная система налогообложения', 'woocommerce'),
                    ),
                ),
*/
                'tax_type' => array(
                    'title' => __('Ставка НДС по умолчанию', 'woocommerce'),
                    'type' => 'select',
                    'default' => '0',
                    'options' => array(
                        '0' => __('Без НДС', 'woocommerce'),
                        '1' => __('НДС по ставке 0%', 'woocommerce'),
                        '2' => __('НДС чека по ставке 10%', 'woocommerce'),
                        '3' => __('НДС чека по ставке 18%', 'woocommerce'),
                        '4' => __('НДС чека по расчетной ставке 10/110', 'woocommerce'),
                        '5' => __('НДС чека по расчетной ставке 10/118', 'woocommerce'),
                    ),
                ),


            );
        }


        function get_product_price_with_discount($price, $type, $c_amount, &$order_data)
        {

            switch ($type) {
                case 'percent':
                    $new_price = ceil($price * ( 1 - $c_amount / 100 ));

                    // remove this discount from discount_total
                    $order_data['discount_total'] -= ($price - $new_price);
                    break;

//                case 'fixed_cart':
//                    //wrong
//                    $new_price = $price;
//                    break;

                case 'fixed_product':
                    $new_price = $price - $c_amount;

                    // remove this discount from discount_total
                    $order_data['discount_total'] -= $c_amount / 100;
                    break;

                default:
                    $new_price = $price;
            }
            return $new_price;
        }

        /*
         * Generate the dibs button link
         */
        public function generate_form($order_id)
        {

            $order = new WC_Order($order_id);
            $amount = $order->order_total;

            // COUPONS
            $coupons = array();
            global $woocommerce;
            if (!empty($woocommerce->cart->applied_coupons)) {
                foreach ($woocommerce->cart->applied_coupons as $code) {
                    $coupons[] = new WC_Coupon($code);
                }
            };


            if ($this->test_mode == 'yes') {
                $action_adr = PB_API_TEST_URL;
            } else {
                $action_adr = PB_API_PROD_URL;
            }

            $extra_url_param = '&wc-callb=callback_function';

            $order_data = $order->get_data();
//                'returnUrl' => get_option('siteurl') . '?wc-api=WC_PBPAYMENT&pbpayment=result&order_id=' . $order_id . $extra_url_param,
//                'amount' => $amount,
            // prepare args array


			$order_items = $order->get_items();
			$data = $order->get_data();			
			$items = array();

			foreach ($order_items as $value) {
                    $item = array();
                    $category = 223;
                    $product_variation_id = $value['variation_id'];

                    if ($product_variation_id) {
                        $product = new WC_Product_Variation($value['variation_id']);
                        $item_code = $value['variation_id'];
                    } else {
                        $product = new WC_Product($value['product_id']);
                        $item_code = $value['product_id'];
                    }
					
					$top_term = _get_top_term( 'product_cat',  $value['product_id'] );
					if ($top_term->term_id !== $category) {
								if ($top_term->term_id == 242) {$category = 223;}//Офисные стулья и кресла
								if ($top_term->term_id == 244) {$category = 229;}//Детская мебель
								if ($top_term->term_id == 238) {$category = 226;}//Кухонная мебель
								if ($top_term->term_id == 160) {$category = 230;}//Металлическая мебель
								if ($top_term->term_id == 165) {$category = 227;}//Мягкая офисная мебель
								//if ($top_term->term_id == 165) {$category = 225;}//Корпусная мебель
					}

                    $product_price = $product->get_price();//round(($product->get_price()) * 100);
//                  $item['positionId'] = $itemsCnt++;
                    $item['category'] = $category;
                    $item['model'] = $value['name'];
                    $item['quantity'] = $value['quantity'];
                    $item['price'] = $product_price;
//                  $item['itemAmount'] = $product_price * $value['quantity'];
//                  $item['itemCode'] = $item_code;

                    $items[] = $item;
			}

            
			$eorder = array(
			    'ttCode' => $this->ttcode,
			    'ttName' => $this->ttname,
			    'fullName' => $data['billing']['first_name'] . ' ' . $data['billing']['last_name'],
			    'phone' => '',//$data['billing']['phone'],
//			    'manualOrderInput' => false,
			    'returnUrl'=> get_option('siteurl') . '?wc-api=WC_PBPAYMENT&pbpayment=result&order_id=' . $order_id . $extra_url_param,
				'order' => $items,
			);

				//$pay_url = $action_adr . json_encode($eorder);//http_build_query($eorder, '', '&');
				$pay_url = $action_adr . http_build_query($eorder, '', '&');
//				$script = "<script> var options = ''; jQuery('#pos-credit-link').attr('href', 'https://my.pochtabank.ru/pos-credit?' + jQuery.param(options)); </script>";

                return $script.
                '<p>' . __('Спасибо за Ваш заказ, пожалуйста, нажмите кнопку ниже, чтобы заплатить.', 'woocommerce') . '</p>' .
                '<a id="pos-credit-link" class="button cancel" href= "'.$pay_url.'">' . __('Оплатить', 'woocommerce') . '</a>' .
                '<a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __('Отказаться от оплаты & вернуться в корзину', 'woocommerce') . '</a>';













        }


        function correctBundleItem(&$item, $discount) {

            $item['itemAmount'] -= $discount;
            $item['itemPrice'] = $item['itemAmount'] % $item['quantity']['value'];
            if ($item['itemPrice'] != 0)  {
                $item['itemAmount'] += $item['quantity']['value'] - $item['itemPrice'];
            };

            $item['itemPrice'] = $item['itemAmount'] / $item['quantity']['value'];
        }


        /*
         * Process the payment and return the result
         */
        function process_payment($order_id)
        {
            $order = new WC_Order($order_id);

            return array(
                'result' => 'success',
				'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, $action_adr)),
                //'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(wc_get_page_id('pay'))))
            );
        }

        /*
         * Receipt page
         */
        function receipt_page($order)
        {
            echo $this->generate_form($order);
        }



    }

    
    
    
    
    
    
    add_filter('woocommerce_payment_gateways', 'add_pbpayment_gateway');
    function add_pbpayment_gateway($methods)
    {
        $methods[] = 'WC_PBPAYMENT';
        return $methods;
    }


    function wpbo_get_woo_version_number()
    {
        // If get_plugins() isn't available, require it
        if (!function_exists('get_plugins'))
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');

        // Create the plugins folder and file variables
        $plugin_folder = get_plugins('/' . 'woocommerce');
        $plugin_file = 'woocommerce.php';

        // If the plugin version number is set, return it
        if (isset($plugin_folder[$plugin_file]['Version'])) {
            return $plugin_folder[$plugin_file]['Version'];

        } else {
            // Otherwise return null
            return NULL;
        }
    }

    
	/**
	 * Получает термин верхнего уровня, для указанного или текущего поста в цикле
	 * @param  string          $taxonomy      Название таксономии
	 * @param  integer/object  [$post_id = 0] ID или объект поста
	 * @return string/wp_error Объект термина или false
	 */
	function _get_top_term( $taxonomy, $post_id = 0 ) 
	{
		if( isset($post_id->ID) ) $post_id = $post_id->ID;
		if( ! $post_id )          $post_id = get_the_ID();

		$terms = get_the_terms( $post_id, $taxonomy );

		if( ! $terms || is_wp_error($terms) ) return $terms;

		// только первый
		$term = array_shift( $terms );

		// найдем ТОП
		$parent_id = $term->parent;
		while( $parent_id ){
			$term = get_term_by( 'id', $parent_id, $term->taxonomy );
			$parent_id = $term->parent;
		}

		return $term;
	}
}
