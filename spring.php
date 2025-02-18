<?php

declare(strict_types=1);

namespace Spring;

// TODO: validation, errors, tests, ui

class Shipment
{
    /**
     * @return string tracking number
     * @throws Error
     */
    public function newPackage(array $order, array $params): string
    {
        $error = $this->validateOrderShipmentInput($order, $params);
        if ($error) {
            throw $error;
        }

        $data = $this->createOrderShipmentData($order, $params['label_format'], $params['service']);

        $response = new Request($params['url'], $params['api_key'])->fire('OrderShipment', $data);
        if ($response->error) {
            throw $response->error;
        }

        if (!isset($response->result['Shipment']['TrackingNumber'])) {
            throw new Error('tracking number missing in response', Error::INVALID_RESPONSE);
        }

        return $response->result['Shipment']['TrackingNumber'];
    }

    /**
     * @return string label image
     * @throws Error
     */
    public function getLabelImage(string $trackingNumber, array $params): string
    {
        $error = $this->validateLabelInput($trackingNumber, $params);
        if ($error) {
            throw $error;
        }

        $data = $this->createLabelData($trackingNumber, $params['label_format']);

        $response = new Request($params['url'], $params['api_key'])->fire('GetShipmentLabel', $data);
        if ($response->error) {
            throw $response->error;
        }

        if (!isset($response->result['Shipment']['LabelImage'])) {
            throw new Error('label image missing in response', Error::INVALID_RESPONSE);
        }

        return $response->result['Shipment']['LabelImage'];
    }

    private function validateOrderShipmentInput(array $order, array $params): ?Error
    {
        // TODO
        return null;
    }

    private function validateLabelInput(string $trackingNumber, array $params): ?Error
    {
        // TODO
        return null;
    }

    private function createOrderShipmentData(array $order, string $labelFormat, string $springService): array
    {
        $shipperReference = $this->generateShipperReference($order['sender_fullname']);

        return [
            "Shipment" => [
                "LabelFormat" => $labelFormat,
                "ShipperReference" => $shipperReference,
                "OrderDate" => new \DateTimeImmutable()->format("Y-m-d"),
                "Service" => $springService,

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

    private function generateShipperReference(string $name): string
    {
        $salt = "BaseLinker";
        $timestamp = time();
        $hash = hash('sha256', $name . $salt . $timestamp);
        return substr($hash, 0, 16);
    }

    private function createLabelData(string $trackingNumber, string $labelFormat): array
    {
        return [
            "Shipment" => [
                "LabelFormat" => $labelFormat,
                "TrackingNumber" => $trackingNumber,
            ],
        ];
    }
}

readonly class Request {
    public function __construct(
        private string $url,
        private string $apiKey,
    ) {
    }

    public function fire(string $command, array $postData): Response
    {
        $postData["Apikey"] = $this->apiKey;
        $postData["Command"] = $command;
        $body = json_encode($postData);
        if ($body === false) {
            return new Response(new Error('json_encode body: ' . json_last_error_msg(), Error::INTERNAL));
        }

        $options = [
            'http' => [
                'header' => "Content-Type: text/json\r\n",
                'method' => 'POST',
                'content' => $body,
            ],
        ];

        // TODO - switch to curl/other?
        $context = stream_context_create($options);
        $response = @file_get_contents($this->url, false, $context);
        if ($response === false) {
            return new Response(new Error('request failed', Error::REQUEST_FAILED));
        }

        /** @var array|null $result */
        $result = json_decode($response, true);
        if ($result === null) {
            return new Response(new Error('json_decode response: ' . json_last_error_msg(), Error::INVALID_RESPONSE));
        }

        if (isset($result['ErrorLevel']) && $result['ErrorLevel'] !== 0) {
            $msg = $result['Error'] ?? 'network connection error';
            $code = $result['ErrorLevel'] === 1 ? Error::API_ERROR : Error::API_FATAL_ERROR;
            return new Response(new Error($msg, $code));
        }

        return new Response(null, $result);
    }
}

readonly class Response
{
    public function __construct(
        public ?Error $error,
        public array $result = [],
    ) {
    }
}

class Error extends \Exception
{
    public const int INTERNAL = 1;
    public const int REQUEST_FAILED = 2;
    public const int INVALID_RESPONSE = 3;
    public const int API_FATAL_ERROR = 4;
    public const int API_ERROR = 5;
}
