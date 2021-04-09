<?php


namespace ws_mollie\Checkout;


use Exception;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Types\PaymentStatus;
use RuntimeException;
use Session;
use Shop;
use ws_mollie\Checkout\Payment\Amount;
use ws_mollie\Checkout\Payment\Locale;

class PaymentCheckout extends AbstractCheckout
{

    /**
     * @var Payment
     */
    protected $payment;

    /**
     * @return string
     * @throws ApiException
     * @throws IncompatiblePlatform
     */
    public function cancelOrRefund()
    {
        if ((int)$this->getBestellung()->cStatus === BESTELLUNG_STATUS_STORNO) {
            if ($this->getMollie()->isCancelable) {
                $res = $this->API()->Client()->payments->cancel($this->getMollie()->id);
                return 'Payment cancelled, Status: ' . $res->status;
            }
            $res = $this->API()->Client()->payments->refund($this->getMollie(), ['amount' => $this->getMollie()->amount]);
            return "Payment Refund initiiert, Status: " . $res->status;
        }
        throw new RuntimeException('Bestellung ist derzeit nicht storniert, Status: ' . $this->getBestellung()->cStatus);
    }

    public function getMollie($force = false)
    {
        if ($force || (!$this->payment && $this->getModel()->kID)) {
            try {
                $this->payment = $this->API()->Client()->payments->get($this->getModel()->kID, ['embed' => 'refunds']);
            } catch (Exception $e) {
                throw new RuntimeException('Mollie-Payment konnte nicht geladen werden: ' . $e->getMessage());
            }
        }
        return $this->payment;
    }

    public function create(array $paymentOptions = [])
    {
        if ($this->getModel()->kID) {
            try {
                $this->payment = $this->API()->Client()->payments->get($this->getModel()->kID);
                if ($this->payment->status === PaymentStatus::STATUS_PAID) {
                    throw new RuntimeException(self::Plugin()->oPluginSprachvariableAssoc_arr['errAlreadyPaid']);
                }
                if ($this->payment->status === PaymentStatus::STATUS_OPEN) {
                    $this->updateModel()->saveModel();
                    return $this->payment;
                }
            } catch (RuntimeException $e) {
                //$this->Log(sprintf("PaymentCheckout::create: Letzte Transaktion '%s' konnte nicht geladen werden: %s", $this->getModel()->kID, $e->getMessage()), LOGLEVEL_ERROR);
                throw $e;
            } catch (Exception $e) {
                $this->Log(sprintf("PaymentCheckout::create: Letzte Transaktion '%s' konnte nicht geladen werden: %s, versuche neue zu erstellen.", $this->getModel()->kID, $e->getMessage()), LOGLEVEL_ERROR);
            }
        }

        try {
            $req = $this->loadRequest($paymentOptions)->getRequestData();
            $this->payment = $this->API()->Client()->payments->create($req);
            $this->Log(sprintf("Payment f�r '%s' wurde erfolgreich angelegt: %s", $this->getBestellung()->cBestellNr, $this->payment->id));
            $this->updateModel()->saveModel();
        } catch (Exception $e) {
            $this->Log(sprintf("PaymentCheckout::create: Neue Transaktion f�r '%s' konnte nicht erstellt werden: %s.", $this->getBestellung()->cBestellNr, $e->getMessage()), LOGLEVEL_ERROR);
            throw new RuntimeException(sprintf('Mollie-Payment \'%s\' konnte nicht geladen werden: %s', $this->getBestellung()->cBestellNr, $e->getMessage()));
        }
        return $this->payment;
    }

    public function loadRequest($options = [])
    {

        if ((int)$this->getBestellung()->oKunde->nRegistriert
            && ($customer = $this->getCustomer(array_key_exists('mollie_create_customer', $_SESSION['cPost_arr'] ?: []) || $_SESSION['cPost_arr']['mollie_create_customer'] !== 'Y'))
            && isset($customer)) {
            $options['customerId'] = $customer->id;
        }

        $this->setRequestData('amount', new Amount($this->getBestellung()->fGesamtsumme, $this->getBestellung()->Waehrung, true, true))
            ->setRequestData('description', 'Order ' . $this->getBestellung()->cBestellNr)
            ->setRequestData('redirectUrl', $this->PaymentMethod()->getReturnURL($this->getBestellung()))
            ->setRequestData('webhookUrl', Shop::getURL(true) . '/?mollie=1')
            ->setRequestData('locale', Locale::getLocale(Session::getInstance()->Language()->getIso(), Session::getInstance()->Customer()->cLand))
            ->setRequestData('metadata', [
                'kBestellung' => $this->getBestellung()->kBestellung,
                'kKunde' => $this->getBestellung()->kKunde,
                'kKundengruppe' => Session::getInstance()->CustomerGroup()->kKundengruppe,
                'cHash' => $this->getHash(),
            ]);
        $pm = $this->PaymentMethod();
        if ($pm::METHOD !== '' && (self::Plugin()->oPluginEinstellungAssoc_arr['resetMethod'] !== 'Y' || !$this->getMollie())) {
            $this->setRequestData('method', $pm::METHOD);
        }
        foreach ($options as $key => $value) {
            $this->setRequestData($key, $value);
        }

        return $this;
    }

    public function getIncomingPayment()
    {
        if (!$this->getMollie()) {
            return null;
        }
        if (in_array($this->getMollie()->status, [PaymentStatus::STATUS_AUTHORIZED, PaymentStatus::STATUS_PAID], true)) {
            $data = [];
            $data['fBetrag'] = (float)$this->getMollie()->amount->value;
            $data['cISO'] = $this->getMollie()->amount->currency;
            $data['cZahler'] = $this->getMollie()->details->paypalPayerId ?: $this->getMollie()->customerId;
            $data['cHinweis'] = $this->getMollie()->details->paypalReference ?: $this->getMollie()->id;
            return (object)$data;
        }
        return null;
    }
}