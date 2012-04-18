<?php
/**
 * Google Checkout Auto Convert
 * @class GoogleCheckoutAutoConvert
 *
 * @author Matt Kane
 * @copyright Triggertrap Ltd 2012
 * @version 1.0
 * @package Shopp
 * @since 1.2
 * @subpackage GoogleCheckoutAutoConvert
 *
 **/
 require_once 'GoogleCheckout.php';
 
class GoogleCheckoutAutoConvert extends GoogleCheckout implements GatewayModule {
	
	function process () {
        $currency = $this->settings['targetCurrency'];
        
		$oldCurr = $this->settings['currency'];
		
		
		
		$cart = $this->Order->Cart;
		$this->settings['currency'] = $currency;
		
		$rate = $this->convertRate($oldCurr, $currency);
		
		
		foreach($this->Order->Cart->contents as $i => $o) {
			$this->Order->Cart->contents[$i]->unitprice *= $rate;
			$this->Order->Cart->contents[$i]->total *= $rate;
		}
		
		$this->Order->Cart->Totals->discount *= $rate;
		
		foreach ($this->Order->Cart->shipping as $i => $o) { 
			$this->Order->Cart->shipping[$i]->amount *= $rate;
		}
		$ret = parent::process(); 
		$this->Order->Cart = $cart;
		$this->settings['currency'] = $oldCurr;
		return $ret;
	}

	function apiurl () {
		global $Shopp;
		// Build the Google Checkout API URL if Google Checkout is enabled
		if (!empty($_POST['settings']['GoogleCheckoutAutoConvert']['id']) && !empty($_POST['settings']['GoogleCheckoutAutoConvert']['key'])) {
			$GoogleCheckout = new GoogleCheckoutAutoConvert();
			$url = add_query_arg(array(
				'_txnupdate' => 'gc',
				'merc' => $GoogleCheckout->authcode(
										$_POST['settings']['GoogleCheckoutAutoConvert']['id'],
										$_POST['settings']['GoogleCheckoutAutoConvert']['key'])
				),shoppurl(false,'checkout',true));
			$_POST['settings']['GoogleCheckoutAutoConvert']['apiurl'] = $url;
		}
			    
	} 
	
	function settings () {
	    parent::settings();
	    $currencies = array("GBP"=>"British Pound","USD"=>"US Dollar");

		$this->ui->menu(1,array(
			'name' => 'targetCurrency',
			'selected' => $this->settings['targetCurrency'],
			'label' => __('Enter the currency of your Google Checkout account.','Shopp'),
			'keyed' => true
		), $currencies);		
	}
	
	
	function convertRate($from, $to) {
	    if($from == $to) {
	        return 1;
        }
        
        $url = sprintf("http://www.google.com/ig/calculator?hl=en&q=1%s=?%s", $from, $to);
        $data = file_get_contents($url);
		
        $result = 0;
        if($data && $result = json_decode($data)) {   
            if(!$data->error) {
                $rate = floatval($data->rhs);
            }
        }
        
        if(!$rate) {
            // Hack. Caching is a much better idea.
            $defaults = array('USD' => array('GBP' => 0.629960942),
                              'GBP' => array('USD' => 1.5935)
            );
            $rate = $defaults[$from][$to];
        }
        return $rate;
        
    }
}