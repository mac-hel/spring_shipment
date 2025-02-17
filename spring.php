<?php

declare(strict_types=1);

namespace Spring;

class Shipment
{
    // TODO - create it somehow?
    private const string SHIPPER_REFERENCE = "BL_JanKowalski_002";

    /**
     * @throws Error
     */
    public function newPackage(array $order, array $params): Package
    {
        $error = $this->validateShipmentInput($order, $params);
        if ($error) {
            throw $error;
        }

        $data = $this->createShipmentData($order, $params);

        $result = $this->postRequest($params['url'], $data);

        if (!isset($result['Shipment']['TrackingNumber'])) {
            throw new Error('network connection error');
        }

        return new Package($result['Shipment']['TrackingNumber']);
    }

    /**
     * @throws Error
     */
    public function getLabel(string $trackingNumber, array $params): Label
    {
        $error = $this->validateLabelInput($trackingNumber, $params);
        if ($error) {
            throw $error;
        }

        $data = $this->createLabelData($trackingNumber, $params);

        $result = $this->postRequest($params['url'], $data);

        if (!isset($result['Shipment']['TrackingNumber'])) {
            throw new Error('network connection error');
        }

        return new Label($result['Shipment']['LabelImage']);
    }

    private function validateShipmentInput(array $order, array $params): ?Error
    {
        // TODO
        return null;
    }

    private function validateLabelInput(string $trackingNumber, array $params): ?Error
    {
        // TODO
        return null;
    }

    private function createShipmentData(array $order, array $params): array
    {
        return [
            "Apikey" => $params['api_key'],
            "Command" => 'OrderShipment',
            "Shipment" => [
                "LabelFormat" => $params['label_format'],
                "ShipperReference" => self::SHIPPER_REFERENCE,
                "OrderDate" => new \DateTimeImmutable()->format("Y-m-d"),
                "Service" => $params['service'],

                "Weight" => $order['weight'],
                "Value" => $order['value'],
                "Currency" => $order['currency'],
                //"CustomsDuty" => "DDU",
                "Description" => $order['description'],
                "DeclarationType" => $order['declaration_type'],
                "ConsignorAddress" => [
                    "Name" => $order['sender_fullname'],
                    "Company" => $order['sender_company'],
                    "AddressLine1" => $order['sender_address'],
                    "City" => $order['sender_city'],
                    "Zip" => $order['sender_postalcode'],
                    "Country" => $order['sender_country'],
                    "Phone" => $order['sender_phone'],
                ],
                "ConsigneeAddress" => [
                    "Name" => $order['delivery_fullname'],
                    "Company" => $order['delivery_company'],
                    "AddressLine1" => $order['delivery_address'],
                    "City" => $order['delivery_city'],
                    "Zip" => $order['delivery_postalcode'],
                    "Country" => $order['delivery_country'],
                    "Phone" => $order['delivery_phone'],
                    "Email" => $order['delivery_email'],
                ],
            ],
        ];
    }

    private function createLabelData(string $trackingNumber, array $params): array
    {
        return [
            "Apikey" => $params['api_key'],
            "Command" => 'GetShipmentLabel',
            "Shipment" => [
                "LabelFormat" => $params['label_format'],
                "TrackingNumber" => $trackingNumber,
            ],
        ];
    }

    /**
     * @throws Error
     */
    private function postRequest(string $url, array $postData): array
    {
        $body = json_encode($postData);
        if ($body === false) {
            throw new Error('invalid input');
        }

        $options = [
            'http' => [
                'header' => "Content-Type: text/json\r\n",
                'method' => 'POST',
                'content' => $body,
            ],
        ];

        $context = stream_context_create($options);
        // TODO - check if error can be checked
        $response = file_get_contents($url, false, $context);
        if ($response === false) {
            throw new Error('network connection error');
        }
        // TODO - check if error can be checked

        /** @var array|null $result */
        $result = json_decode($response, true);
        if ($result === null) {
            throw new Error('network connection error');
        }

        if (isset($result['ErrorLevel']) && $result['ErrorLevel'] !== 0) {
            // TODO - check levels
            $msg = $result['Error'] ?? 'network connection error';
            throw new Error($msg);
        }

        return $result;
    }
}

//readonly class Request
//{
//    public function __construct(
//        private string $url,
//        private string $api_key,
//        private string $command,
//        private array $data,
//    ) {
//    }
//}

readonly class Package
{
    public function __construct(
        public string $trackingNumber,
        public ?Error $error = null,
    ) {
    }
}

readonly class Label
{
    public function __construct(
        public string $label,
        public ?Error $error = null,
    ) {
    }
}

class Error extends \Exception
{
}
