<?php

use integrations\utils\HTTPRequest;
use integrations\ecopayz\EcopayzGateway;

require_once __DIR__ . '/../utils/HTTPRequest.php';
require_once 'EcopayzGateway.php';

function validateRemoteAddress($req)
{
    $whitelistArray = parse_ini_file(__DIR__.'/whitelist.ini');
    $whitelist = explode(', ', $whitelistArray['ips']);
    //echo $req->remote_addr."\n";
    if (!array_search($req->remote_addr, $whitelist)) {
        echo 'IP not allowed.';
        exit;
    }

    return true;
}

$req = new HTTPRequest();

validateRemoteAddress($req);

// remove the xml= key
$body = substr($req->getBody(), 4);

file_put_contents('log.log', print_r(date('Y-m-d H:i:s').'---'.$body."\n", true), FILE_APPEND);

$ecopayz = new EcopayzGateway();
$resp = $ecopayz->manageRequest($body);
$xmlResp = trim(str_replace("\n", '', $resp->asXML()));
file_put_contents('log.log', print_r(date('Y-m-d H:i:s').'---'.$xmlResp."\n", true), FILE_APPEND);
echo $xmlResp;

/* End of file services.php */
