<?php

declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'spring.php';

// test it runs - happy path
(static function() {

    $params = [
        'url' => 'https://mtapi.net/?testMode=1',
        'api_key' => 'f16753b55cac6c6e',
        'label_format' => 'PDF',
        'service' => 'PPTT',
    ];

    $order = [
        'weight' => '0.7',      // optional, API Validates it: Maximum weight exceeded
        'value' => '120',       // optional, API Validates it: Invalid Value
        'sender_fullname' => 'Lopez the Quick',     // optional; 30
        'sender_company' => 'BaseLinker',           // 30
        'sender_address' => 'Kiszczaka 12A',           // 30
        'sender_city' => 'Abramów',           // 30
        'sender_postalcode' => '67890',         // 20
        'sender_country' => 'PL',               // supported list
        'sender_phone' => '555555555',          // 15
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
        $trackingNumber = new \Spring\NewPackage()($order, $params);
    } catch (Spring\Error $error) {
        fatalError('NewPackage error', $error);
    } catch (\Throwable $e) {
        fatalError('NewPackage unexpected error: ' . $e->getMessage());
    }

    var_dump($trackingNumber);

    try {
        $labelImage = new \Spring\GetLabelImage()($trackingNumber, $params);
    } catch (Spring\Error $error) {
        fatalError('GetLabelImage error', $error);
    } catch (\Throwable $e) {
        fatalError('GetLabelImage unexpected error: ' . $e->getMessage());
    }

    var_dump(strlen($labelImage));
    echo labelImageToText($labelImage) . PHP_EOL;
})();

function fatalError(string $header, ?Spring\Error $error = null): void
{
    echo $header . PHP_EOL;
    if ($error) {
        $msg = match ($error->getCode()) {
            Spring\Error::INTERNAL => "Error occurred, try again later.",
            Spring\Error::API_FATAL_ERROR,
            Spring\Error::API_ERROR,
            Spring\Error::INVALID_INPUT => $error->getMessage(),
        };
        echo $msg . PHP_EOL;
    }
    exit(1);
}

function labelImageToText(string $label): string {
    $tempPdf = tempnam(sys_get_temp_dir(), 'pdf_') . '.pdf';
    file_put_contents($tempPdf, $label);
    $output = shell_exec("pdftotext $tempPdf -"); // - outputs to stdout
    unlink($tempPdf);
    return $output ?? '';
}
