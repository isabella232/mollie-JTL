<?php


namespace ws_mollie;


use Composer\CaBundle\CaBundle;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Mollie\Api\MollieApiClient;
use ws_mollie\Traits\Plugin;

class API
{

    use Plugin;

    /**
     * @var MollieApiClient
     */
    protected $client;

    /**
     * @var bool
     */
    protected $test;

    /**
     * API constructor.
     * @param bool $test
     */
    public function __construct($test)
    {
        $this->test = (bool)$test;
    }

    /**
     * @return bool
     */
    public static function getMode()
    {
        require_once PFAD_ROOT . PFAD_ADMIN . PFAD_INCLUDES . 'benutzerverwaltung_inc.php';
        if (\Shop::isAdmin()) {
            // TODO: Check Option!
            return true;
        }
        return false;
    }

    public function Client()
    {
        if (!$this->client) {
            $this->client = new MollieApiClient(new Client([
                RequestOptions::VERIFY => CaBundle::getBundledCaBundlePath(),
                RequestOptions::TIMEOUT => 60
            ]));
            $this->client->setApiKey($this->isTest() ? self::Plugin()->oPluginEinstellungAssoc_arr['test_api_key'] : self::Plugin()->oPluginEinstellungAssoc_arr['api_key'])
                ->addVersionString('JTL-Shop/' . JTL_VERSION . JTL_MINOR_VERSION)
                ->addVersionString('ws_mollie/' . self::Plugin()->nVersion);
        }
        return $this->client;
    }

    /**
     * @return bool
     */
    public function isTest()
    {
        return $this->test;
    }


}