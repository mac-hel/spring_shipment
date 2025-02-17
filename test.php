<?php

declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'spring.php';

$shipment = new Spring\Shipment();

$params = [
    'url' => 'https://mtapi.net/?testMode=1',
    'api_key' => 'f16753b55cac6c6e',
    'label_format' => 'PDF',
    'service' => 'PPTT',
];

$order = [
    'weight' => '0.7',
    'value' => '120',
    'currency' => 'PLN',
    'description' => 'Technical documentation of the project',
    'declaration_type' => 'Documents',
    'sender_fullname' => 'Lopez the Quick',
    'sender_company' => 'BaseLinker',
    'sender_address' => 'Kiszczaka 12A',
    'sender_city' => 'Abramów',
    'sender_postalcode' => '67890',
    'sender_country' => 'PL',
    'sender_phone' => '555555555',
    'delivery_fullname' => 'Maud Driant',
    'delivery_company' => 'Spring GDS',
    'delivery_address' => 'Strada Foisorului, Nr. 16, Bl. F11C, Sc. 1, Ap. 10',
    'delivery_city' => 'Bucuresti, Sector 3',
    'delivery_postalcode' => '031179',
    'delivery_country' => 'RO',
    'delivery_phone' => '333333333',
    'delivery_email' => 'john@doe.com',
];

try {
    $package = $shipment->newPackage($order, $params);
} catch (Spring\Error $error) {
    echo 'newPackage error: ', $error->getMessage() . PHP_EOL;
    exit(1);
}

var_Dump($package);

try {
    $label = $shipment->getLabel($package->trackingNumber, $params);
} catch (Spring\Error $error) {
    echo 'getLabel error: ', $error->getMessage() . PHP_EOL;
    exit(1);
}

// TODO:
//  - decode label + display

var_Dump($label);
