<?php

require_once 'EcopayzGateway.php';

$gateway = new integrations\ecopayz\EcopayzGateway();
?>

<iframe src="<?= $gateway->redirectToEcoPayz(1001280757, 1.00, 'EUR');?>"
    frameborder="0" 
    scrolling="auto"
    width="1024"
    height="768"
    align="top"></iframe>


