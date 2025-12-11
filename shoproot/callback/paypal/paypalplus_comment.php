<?php
/* -----------------------------------------------------------------------------------------
   $Id: paypalplus_comment.php 13462 2021-03-11 07:48:20Z GTB $

   modified eCommerce Shopsoftware
   http://www.modified-shop.org

   Copyright (c) 2009 - 2013 [www.modified-shop.org]
   -----------------------------------------------------------------------------------------
   Released under the GNU General Public License
   ---------------------------------------------------------------------------------------*/

chdir('../../');
include('includes/application_top.php');

if (!isset($_SESSION['customer_id'])) {
  xtc_redirect(xtc_href_link(FILENAME_SHOPPING_CART, '', 'NONSSL'));
}

if (is_file(DIR_WS_INCLUDES.'checkout_requirements.php')) {
  require(DIR_WS_INCLUDES.'checkout_requirements.php');
} else {
  // BOF - Fallback for shop version 1.0x
  // if there is nothing in the customers cart, redirect them to the shopping cart page
  if ($_SESSION['cart']->count_contents() < 1) {
    xtc_redirect(xtc_href_link(FILENAME_SHOPPING_CART));
  }

  // avoid hack attempts during the checkout procedure by checking the internal cartID
  if (isset ($_SESSION['cart']->cartID) && isset ($_SESSION['cartID'])) {
    if ($_SESSION['cart']->cartID != $_SESSION['cartID'])
      xtc_redirect(xtc_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL'));
  }

  // if no shipping method has been selected, redirect the customer to the shipping method selection page
  if (!isset ($_SESSION['shipping'])) {
    xtc_redirect(xtc_href_link(FILENAME_CHECKOUT_SHIPPING, '', 'SSL'));
  }
  // EOF - Fallback for shop version 1.0x
}

$url_request = parse_url($_SERVER['HTTP_REFERER']);
$url_host = parse_url(constant(strtoupper($url_request['scheme']).'_SERVER'));

if ($url_host['host'] == $url_request['host']
    && basename($url_request['path']) == FILENAME_CHECKOUT_PAYMENT
    && isset($_POST['comments'])
    )
{
  $_SESSION['comments'] = decode_utf8($_POST['comments'],'',true);
  session_write_close();
  xtc_db_close();
}
?>