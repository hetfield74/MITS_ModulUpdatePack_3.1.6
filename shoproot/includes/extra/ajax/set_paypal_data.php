<?php
/**
 * $Id: set_paypal_data.php 12566 2020-02-16 06:54:48Z GTB $
 *
 * modified eCommerce Shopsoftware
 * http://www.modified-shop.org
 *
 * Copyright (c) 2009 - 2013 [www.modified-shop.org]
 *
 * Released under the GNU General Public License
 */

if (isset($_REQUEST['speed'])) {
  // BOF - Fallback for shop version 1.0x
  // external
  defined('DIR_WS_EXTERNAL') OR define('DIR_WS_EXTERNAL', DIR_WS_CATALOG . 'includes/external/');
  defined('DIR_FS_EXTERNAL') OR define('DIR_FS_EXTERNAL', DIR_FS_CATALOG . 'includes/external/');
  // EOF - Fallback for shop version 1.0x

  // BOF - Fallback for shop version 1.0x
  if (is_file(DIR_FS_INC.'auto_include.inc.php')) require_once (DIR_FS_INC.'auto_include.inc.php');
  // EOF - Fallback for shop version 1.0x
  require_once (DIR_FS_INC.'xtc_not_null.inc.php');
  require_once (DIR_FS_INC.'xtc_input_validation.inc.php');
  // BOF - Fallback for shop version 1.0x
  if (is_file(DIR_FS_INC.'html_encoding.php')) require_once (DIR_FS_INC.'html_encoding.php');
  // EOF - Fallback for shop version 1.0x

  // Database
  if (defined('DB_MYSQL_TYPE')) {
    require_once (DIR_FS_INC.'db_functions_'.DB_MYSQL_TYPE.'.inc.php');
    require_once (DIR_FS_INC.'db_functions.inc.php');
  } else {
    // BOF - Fallback for shop version 1.0x
    require_once (DIR_FS_INC.'xtc_db_connect.inc.php');
    require_once (DIR_FS_INC.'xtc_db_close.inc.php');
    require_once (DIR_FS_INC.'xtc_db_error.inc.php');
    require_once (DIR_FS_INC.'xtc_db_perform.inc.php');
    require_once (DIR_FS_INC.'xtc_db_query.inc.php');
    require_once (DIR_FS_INC.'xtc_db_queryCached.inc.php');
    require_once (DIR_FS_INC.'xtc_db_fetch_array.inc.php');
    require_once (DIR_FS_INC.'xtc_db_num_rows.inc.php');
    require_once (DIR_FS_INC.'xtc_db_data_seek.inc.php');
    require_once (DIR_FS_INC.'xtc_db_insert_id.inc.php');
    require_once (DIR_FS_INC.'xtc_db_free_result.inc.php');
    require_once (DIR_FS_INC.'xtc_db_fetch_fields.inc.php');
    require_once (DIR_FS_INC.'xtc_db_output.inc.php');
    require_once (DIR_FS_INC.'xtc_db_input.inc.php');
    require_once (DIR_FS_INC.'xtc_db_prepare_input.inc.php');
    // EOF - Fallback for shop version 1.0x
  }

  require_once (DIR_WS_INCLUDES.'database_tables.php');

  // BOF - Fallback for shop version 1.0x
  // move to xtc_db_queryCached.inc.php
  if (!function_exists('xtDBquery')) {
    function xtDBquery($query) {
      if (defined('DB_CACHE') && DB_CACHE == 'true') {
        $result = xtc_db_queryCached($query);
      } else {
        $result = xtc_db_query($query);
      }
      return $result;
    }
  }
  // EOF - Fallback for shop version 1.0x
}

// autoload
require_once(DIR_FS_EXTERNAL.'paypal/classes/PayPalAdmin.php');

// used classes
use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;

function set_paypal_data() {  
  xtc_db_connect() or die('Unable to connect to database server!');

  $configuration_query = xtc_db_query('select configuration_key as cfgKey, configuration_value as cfgValue from ' . TABLE_CONFIGURATION . '');
  while ($configuration = xtc_db_fetch_array($configuration_query)) {
    if (!defined($configuration['cfgKey'])) {
      define($configuration['cfgKey'], stripslashes($configuration['cfgValue']));
    }
  }
  
  $request_params = array(
    'authCode' => '',
    'sharedId' => '',
    'mode' => '',
    'sec' => '',
  );
  
  foreach ($request_params as $_key => $_val) {
    if (isset($request_params[$_key])) {
      $request_params[$_key] = ((isset($_REQUEST[$_key])) ? $_REQUEST[$_key] : '');
    }
  }
  
  if (!isset($request_params['sec'])
      || $request_params['sec'] != MODULE_PAYMENT_PAYPAL_SECRET
      )
  {
    return;
  }

  $paypal = new PayPalAdmin();
  $partner = $paypal->get_partner_details($request_params['mode']);
  
  $credential = new OAuthTokenCredential();
  $credential::$AUTH_HANDLER = 'PayPal\Handler\OnboardingHandler';
  
  $payload_array = array(
    'code' => $request_params['authCode'],
    'code_verifier' => $paypal->get_seller_nonce(),
  );

  $apiContext = new ApiContext($credential);
  $apiContext->setConfig(
    array(
      'mode' => $request_params['mode'],
      'log.LogEnabled' => (($paypal->get_config('PAYPAL_LOG_ENALBLED') == '1') ? true : false),
      'log.FileName' => DIR_FS_LOG.'mod_paypal_onboarding_'.date('Y-m-d') .'.log',
      'log.LogLevel' => $paypal->loglevel,
      'validation.level' => 'log',
      'cache.enabled' => false,
    )
  );
  $apiContext->addRequestHeader('PayPal-Partner-Attribution-Id', 'Modified_Cart_1stURLonboarding');
  
  $config = $apiContext->getConfig();
  
  $response = array('success' => false);
  
  try {
    $credential->getSellerAccessToken($config, $request_params['sharedId'], $payload_array);

    try {
      $credential->getSellerCredentials($config, $partner['partnerID']);
    
      $sql_data_array = array(
        array(
          'config_key' => 'PAYPAL_CLIENT_ID_'.strtoupper($request_params['mode']),
          'config_value' => $credential->getClientId(),
        ),
        array(
          'config_key' => 'PAYPAL_SECRET_'.strtoupper($request_params['mode']),
          'config_value' => $credential->getClientSecret(),
        ),
      );
      $paypal->save_config($sql_data_array);
      $response['success'] = true;

    } catch (Exception $ex) {
      $paypal->LoggingManager->log('DEBUG', 'getSellerCredentials', array('exception' => $ex));
    }
  } catch (Exception $ex) {
    $paypal->LoggingManager->log('DEBUG', 'getUserToken', array('exception' => $ex));
  }
  
  return $response;
}
?>