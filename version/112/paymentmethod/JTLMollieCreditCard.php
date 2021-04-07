<?php

use ws_mollie\API;

require_once __DIR__ . '/JTLMollie.php';

class JTLMollieCreditCard extends JTLMollie
{
    const METHOD = \Mollie\Api\Types\PaymentMethod::CREDITCARD;

    const CACHE_PREFIX = 'creditcard';
    const CACHE_TOKEN = 'creditcard:token';
    const CACHE_TOKEN_TIMESTAMP = 'creditcard:token:timestamp';

    public function handleNotification($order, $hash, $args)
    {
        parent::handleNotification($order, $hash, $args);
        $this->clearToken();
    }

    protected function clearToken()
    {
        $this->unsetCache(self::CACHE_TOKEN)
            ->unsetCache(self::CACHE_TOKEN_TIMESTAMP);
        return true;
    }

    public function handleAdditional($post)
    {

        $components = self::Plugin()->oPluginEinstellungAssoc_arr[$this->moduleID . '_components'];
        $profileId = self::Plugin()->oPluginEinstellungAssoc_arr['profileId'];

        if ($components === 'N' || !$profileId || trim($profileId) === '') {
            return parent::handleAdditional($post);
        }

        $cleared = false;
        if (array_key_exists('clear', $post) && (int)$post['clear']) {
            $cleared = $this->clearToken();
        }

        if ($components === 'S' && array_key_exists('skip', $post) && (int)$post['skip']) {
            return parent::handleAdditional($post);
        }

        try {
            $trustBadge = (bool)self::Plugin()->oPluginEinstellungAssoc_arr[$this->moduleID . '_trustBadge'];
            $locale = \ws_mollie\Checkout\Payment\Locale::getLocale(Session::getInstance()->Language()->getIso(), Session::getInstance()->Customer() ? Session::getInstance()->Customer()->cLand : null);
            $mode = API::getMode();
            $errorMessage = json_encode(self::Plugin()->oPluginSprachvariableAssoc_arr['mcErrorMessage']);
        } catch (Exception $e) {
            Jtllog::writeLog($e->getMessage() . "\n" . print_r(['e' => $e], 1));
            return parent::handleAdditional($post);
        }

        if (!$cleared && array_key_exists('cardToken', $post) && ($token = trim($post['cardToken']))) {
            return $this->setToken($token) && parent::handleAdditional($post);
        }

        $token = false;
        if (($ctTS = (int)$this->getCache(self::CACHE_TOKEN_TIMESTAMP)) && $ctTS > time()) {
            $token = $this->getCache(self::CACHE_TOKEN);
        }

        Shop::Smarty()->assign('profileId', $profileId)
            ->assign('trustBadge', $trustBadge ?: false)
            ->assign('components', $components)
            ->assign('locale', $locale ?: 'de_DE')
            ->assign('token', $token ?: false)
            ->assign('testMode', $mode ?: false)
            ->assign('errorMessage', $errorMessage ?: null)
            ->assign('mollieLang', self::Plugin()->oPluginSprachvariableAssoc_arr);

        return false;

//        if (array_key_exists('skip', $aPost_arr) && (int)$aPost_arr['skip']) {
//            unset($_SESSION['mollieCardToken'], $_SESSION['mollieCardTokenTS']);
//            return true;
//        }
//
//        $profileId = trim(Helper::getSetting('profileId'));
//        if ($profileId === '' || strpos($profileId, 'pfl_') !== 0) {
//            return true;
//        }
//        if (array_key_exists('mollieCardTokenTS', $_SESSION) && (int)$_SESSION['mollieCardTokenTS'] > time()
//            && array_key_exists('mollieCardToken', $_SESSION) && trim($_SESSION['mollieCardToken']) !== '') {
//            return true;
//        }
//
//        unset($_SESSION['mollieCardToken'], $_SESSION['mollieCardTokenTS']);
//
//        if (array_key_exists('cardToken', $aPost_arr) && trim($aPost_arr['cardToken'])) {
//            $_SESSION['mollieCardToken'] = trim($aPost_arr['cardToken']);
//            $_SESSION['mollieCardTokenTS'] = time() + 3600;
//            return true;
//        }
//
//        Shop::Smarty()->assign('profileId', $profileId)
//            ->assign('errorMessage', json_encode(utf8_encode(Helper::oPlugin()->oPluginSprachvariableAssoc_arr['mcErrorMessage'])))
//            ->assign('locale', self::getLocale($_SESSION['cISOSprache'], $_SESSION['Kunde']->cLand))
//            ->assign('skipComponents', Helper::getSetting('skipComponents'))
//            ->assign('testmode', strpos(trim(Helper::getSetting('api_key')), 'test_') === 0)
//            ->assign('mollieLang', Helper::oPlugin()->oPluginSprachvariableAssoc_arr)
//            ->assign('trustBadge', Helper::getSetting('loadTrust') === 'Y' ? Helper::oPlugin()->cFrontendPfadURLSSL . 'img/trust_' . $_SESSION['cISOSprache'] . '.png' : false);
//
//        return false;
    }

    protected function setToken($token)
    {
        $this->addCache(self::CACHE_TOKEN, $token)
            ->addCache(self::CACHE_TOKEN_TIMESTAMP, time() + 3600);
        return true;
    }

    public function getPaymentOptions(Bestellung $order, $apiType)
    {

        $paymentOptions = [];

        if ($apiType === 'payment') {
            if ($order->Lieferadresse !== null) {
                if (!$order->Lieferadresse->cMail) {
                    $order->Lieferadresse->cMail = $order->oRechnungsadresse->cMail;
                }
                $paymentOptions['shippingAddress'] = new \ws_mollie\Checkout\Payment\Address($order->Lieferadresse);
            }

            $paymentOptions['billingAddress'] = new \ws_mollie\Checkout\Payment\Address($order->oRechnungsadresse);
        }
        if ((int)$this->getCache(self::CACHE_TOKEN_TIMESTAMP) > time() && ($token = trim($this->getCache(self::CACHE_TOKEN)))) {
            $paymentOptions['cardToken'] = $token;
        }
        return $paymentOptions;
    }

}
