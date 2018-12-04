<?php

require_once __DIR__ . '/../class/Helper.php';
try {
    \ws_mollie\Helper::init();

    $svgQuery = http_build_query([
        'p' => \ws_mollie\Helper::oPlugin()->cPluginID,
        'v' => \ws_mollie\Helper::oPlugin()->nVersion,
        's' => defined('APPLICATION_VERSION') ? APPLICATION_VERSION : JTL_VERSION,
        'd' => \ws_mollie\Helper::getDomain(),
        'php' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION,
    ]);

    echo "<script type='application/javascript' src='//cdn.webstollen.com/plugin/js/ws.js?p=" . \ws_mollie\Helper::oPlugin()->cPluginID . '&v=' . \ws_mollie\Helper::oPlugin()->nVersion . "'></script>";
    echo "<div id='ws-head-bar' class='row'>" .
        "  <div class='col-md-4 text-center'>" .
        "    <object data='//lic.dash.bar/info/licence?{$svgQuery}' type='image/svg+xml'>" .
        "      <img src='//lic.dash.bar/info/licence.png?{$svgQuery}' width='370' height='20' alt='Lizenz Informationen'>" .
        '    </object>' .
        '  </div>' .
        "  <div class='col-md-4 text-center'>" .
        "    <object data='//lic.dash.bar/info/version?{$svgQuery}' type='image/svg+xml'>" .
        "      <img src='//lic.dash.bar/info/version.png?{$svgQuery}' width='370' height='20' alt='Update Informationen'>" .
        '    </object>' .
        '  </div>' .
        "  <div class='col-md-4 text-center'>" .
        "    <object data='//lic.dash.bar/info/help?{$svgQuery}' type='image/svg+xml'>" .
        "      <img src='//lic.dash.bar/info/help.png?{$svgQuery}' width='370' height='20' alt='Plugin informationen'>" .
        '    </object>' .
        '  </div>' .
        '</div>';

    if (\ws_mollie\Helper::_licCache()->disabled) {
        echo "<div class='alert alert-danger'>Die Pluginlizenz und das Plugin wurden deaktiviert. "
            . "<a href='?kPlugin=" . \ws_mollie\Helper::oPlugin()->kPlugin . "&_licActivate=" . \ws_mollie\Helper::oPlugin()->cPluginID . "'>"
            . "Klicke hier um die Lizenz erneut zu &uuml;berpr&uuml;fen.</a></div>";
    }

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Fehler: {$e->getMessage()}</div>";
    \ws_mollie\Helper::logExc($e);
}