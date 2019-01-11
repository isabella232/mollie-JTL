<?php

/**
 * Da Webhook nicht 100% sicher vor dem Redirekt ausgeführt wird:
 * - IF Bestellung bereits abgesclossen ? => Update Payment, stop skript
 * - ELSE weiter mit der
 */

if (array_key_exists('hash', $_REQUEST)) {
    require_once __DIR__ . '/../class/Helper.php';
    try {
        \ws_mollie\Helper::init();

        $pza = Shop::DB()->select('tpluginzahlungsartklasse', 'cClassName', 'JTLMollie');
        if (!$pza) {
            return;
        }
        require_once __DIR__ . '/../paymentmethod/JTLMollie.php';

        $oPaymentMethod = new JTLMollie($pza->cModulId);
        $payment = \ws_mollie\Model\Payment::getPaymentHash($_REQUEST['hash']);

        if ($payment && $payment->kBestellung) {

            $mollie = new \Mollie\Api\MollieApiClient();
            $mollie->setApiKey(\ws_mollie\Helper::getSetting("api_key"));
            $order = $mollie->orders->get($payment->kID);
            $oBestellung = new Bestellung($payment->kBestellung);
            $order->orderNumber = $oBestellung->cBestellNr;
            \ws_mollie\Model\Payment::updateFromPayment($order, $payment->kBestellung);

            $logData = '#' . $payment->kBestellung . '$' . $payment->kID . "§" . $oBestellung->cBestellNr;
            $oPaymentMethod->doLog('Received Notification<br/><pre>' . print_r([$order, $payment], 1) . '</pre>', $logData, LOGLEVEL_DEBUG);

            switch ($order->status) {
                case \Mollie\Api\Types\PaymentStatus::STATUS_PAID:
                    $oPaymentMethod->doLog('PaymentStatus: ' . $order->status . ' => Zahlungseingang (' . $order->amount->value . ')', $logData, LOGLEVEL_DEBUG);
                    $oIncomingPayment = new stdClass();
                    $oIncomingPayment->fBetrag = $order->amount->value;
                    $oIncomingPayment->cISO = $order->amount->curreny;
                    $oIncomingPayment->cHinweis = $order->id;
                    $oPaymentMethod->addIncomingPayment($oBestellung, $oIncomingPayment);
                case \Mollie\Api\Types\PaymentStatus::STATUS_AUTHORIZED:
                    $oPaymentMethod->doLog('PaymentStatus: ' . $order->status . ' => Bestellung bezahlt', $logData, LOGLEVEL_DEBUG);
                    $oPaymentMethod->setOrderStatusToPaid($oBestellung);
                    break;
            }
            // stop notify.php script
            exit();
        }
    } catch (Exception $e) {
        \ws_mollie\Helper::logExc($e);
    }
}
