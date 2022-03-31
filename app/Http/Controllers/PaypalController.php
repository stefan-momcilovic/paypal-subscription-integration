<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Srmklive\PayPal\Services\ExpressCheckout;


class PaypalController extends Controller
{
    public function processPaypal(Request $request){
        $provider = new ExpressCheckout;

        $cart = $this->getCheckoutData();

        $response = $provider->setExpressCheckout($cart, true);

        if ((isset($response['ACK']) && $response['ACK'] != null) && $response['ACK'] == "Success") {
            if(isset($response['paypal_link']) && $response['paypal_link'] != null) {
                return redirect($response['paypal_link']);
            }

            return redirect()
                ->route('createpaypal')
                ->with('error', 'Sorry, something went wrong. Please try again later!');

        } else {
            return redirect()
                ->route('createpaypal')
                ->with('error', 'Sorry, something went wrong. Please try again later!');
        }
    }
    
    public function processSuccess(Request $request){
        $provider = new ExpressCheckout;
        $response = $provider->getExpressCheckoutDetails($request['token']);
        $cart = $this->getCheckoutData();

        if(isset($response['ACK']) && $response['ACK'] != null && in_array(strtoupper($response['ACK']), ['SUCCESS', 'SUCCESSWITHWARNING'])){
            $createSubcription = $provider->createMonthlySubscription($response['TOKEN'], env('APP_PRICE'), $cart['subscription_desc']);

            // Here is all response data //
            // dd($response);
            // dd($createSubcription);

            if (!empty($createSubcription['PROFILESTATUS']) && in_array($createSubcription['PROFILESTATUS'], ['ActiveProfile', 'PendingProfile']) && in_array(strtoupper($createSubcription['ACK']), ['SUCCESS', 'SUCCESSWITHWARNING'])) {
                // Save data that you need in database //

                return redirect()
                    ->route('createpaypal')
                    ->with('success', 'Your transaction is complete!');

            }

            return redirect()
                ->route('createpaypal')
                ->with('error', 'Sorry, something went wrong. Please try again later!');
          
        } else {
            return redirect()
                ->route('createpaypal')
                ->with('error', 'Sorry, something went wrong. Please try again later!');
        }

            
    }
    
    public function processCancel(){
        return redirect()
            ->route('createpaypal')
                ->with('error', 'We are sorry to hear that you canceled your transaction');
    }

    protected function getCheckoutData(){
        $data = [];

        $order_id = 1;

        $data['items'] = [
            [
                'name'  => 'Application premium subscription to get fully-featured app '.config('paypal.invoice_prefix').' #'.$order_id,
                'price' => env('APP_PRICE'),
                'qty'   => 1,
            ],
        ];

        $data['return_url'] = route('processSuccess');
        $data['subscription_desc'] = 'Application premium subscription '.config('paypal.invoice_prefix').' #'.$order_id;

        $data['invoice_id'] = config('paypal.invoice_prefix').'_'.$order_id;
        $data['invoice_description'] = "Order #$order_id Invoice";
        $data['cancel_url'] = route('processCancel');

        $total = 0;
        foreach ($data['items'] as $item) {
            $total += $item['price'] * $item['qty'];
        }

        $data['total'] = $total;

        return $data;
    }
}
