<?php


namespace ws_mollie\Checkout;


use JsonSerializable;
use ws_mollie\Traits\Plugin;
use ws_mollie\Traits\RequestData;

abstract class AbstractResource implements JsonSerializable
{
    use Plugin;
    use RequestData;



}