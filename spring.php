<?php

declare(strict_types=1);

namespace Spring;

// TODO: *validation, *errors, tests, ui, *scripts to composer.json, *README?, phpdoc types and comments, switch to curl?
// TODO: input sanitization

/**
 * Create shipment package in Spring system
 */
readonly class NewPackage
{
    private const DEFAULT_CURRENCY = 'PLN';

    private const SUPPORTED_COUNTRY_CODES = [
        'AU','AT','BE','BG','BR','BY','CA','CH','CN','CY','CZ','DK','DE','EE','ES','FI','FR','GB','GF','GI',
        'GP','GR','HK','HR','HU','ID','IE','IL','IS','IT','JP','KR',' LB','LT','LU','LV','MQ','MT','MY','NL',
        'NO','NZ','PL','PT','RE','RO','RS','RU','SA','SE','SG','SI','SK','TH TR','US',
    ];

    /**
     * @return string tracking number
     * @throws Error
     */
    public function __invoke(array $order, array $params): string
    {
        foreach ($order as $key => $value) {
            $order[$key] = trim($value);
        }

        $error = $this->validate($order, $params['service']);
        if ($error) {
            throw $error;
        }

        $data = $this->createPostData($order, $params['label_format'], $params['service']);

        $response = new Request($params['url'], $params['api_key'])->fire('OrderShipment', $data);
        if ($response->error) {
            throw $response->error;
        }

        if (!isset($response->result['Shipment']['TrackingNumber'])) {
            throw new Error('tracking number missing in response', Error::INTERNAL);
        }

        return $response->result['Shipment']['TrackingNumber'];
    }

    /**
     * Keeps all validation rules in one place
     */
    private function servicesRequirements(string $springService): ?array
    {
        $services = [
            'PPTT' => [
                'input' => [
                    // NOTE: 'weight' and 'value' is validated by Spring API
                    'sender_fullname' => [30, 'sender name'],
                    'sender_company' => [30, 'sender company'],
                    'sender_address' => [90, 'sender address'], // 3 lines 30 characters each
                    'sender_city' => [30, 'sender city'],
                    'sender_postalcode' => [20, 'sender postal code'],
                    'sender_phone' => [15, 'sender phone'],
                    'delivery_fullname' => [30, 'delivery name'],
                    'delivery_company' => [30, 'delivery company'],
                    'delivery_address' => [90, 'delivery address'],
                    'delivery_city' => [30, 'delivery city'],
                    'delivery_postalcode' => [20, 'delivery postal code'],
                    'delivery_phone' => [15, 'delivery phone'],
                ],
                'max_address_line_length' => 30,
                'country_codes' => self::SUPPORTED_COUNTRY_CODES,
            ],
            'default' => [
                'max_address_line_length' => 30,
                'country_codes' => self::SUPPORTED_COUNTRY_CODES,
            ],
        ];

        if (!isset($services[$springService])) {
            return $services['default'];
        }

        return $services[$springService];
    }

    private function validate(array $order, string $springService): ?Error
    {
        if (!isset($order['weight']) || !filter_var($order['weight'], FILTER_VALIDATE_FLOAT)) {
            return new Error('please provide weight in kg', Error::INVALID_INPUT);
            // no check for max weight because Spring API enforces it - but may add in the future to avoid unnecessary request
        }
        if (!isset($order['value']) || !filter_var($order['value'], FILTER_VALIDATE_FLOAT)) {
            return new Error('please provide package value', Error::INVALID_INPUT);
        }
        if (!isset($order['delivery_email']) || !filter_var($order['delivery_email'], FILTER_VALIDATE_EMAIL)) {
            return new Error('please provide delivery email', Error::INVALID_INPUT);
        }


        // simple validation of maximum length allowed by the API
        $serviceRequirements = $this->servicesRequirements($springService);
        if (!isset($serviceRequirements['input'])) {
            return new Error("Unsupported service", Error::INTERNAL);
        }
        foreach ($serviceRequirements['input'] as $key => $valid) {
            if (
                !isset($order[$key])
                || $order[$key] === ''
                || mb_strlen($order[$key]) > $valid[0]
                || '' === htmlspecialchars($order[$key], ENT_QUOTES | ENT_HTML401, 'UTF-8')
            ) {
                return new Error("please provide {$valid[1]} with maximum of {$valid[0]} characters", Error::INVALID_INPUT);
            }
        }

        // need to check country because API does not validate it
        if (!isset($order['sender_country']) || !in_array($order['sender_country'], $serviceRequirements['country_codes'], true)) {
            return new Error('please provide valid ISO 4217 sender country code', Error::INVALID_INPUT);
        }
        if (!isset($order['delivery_country']) || !in_array($order['delivery_country'], $serviceRequirements['country_codes'], true)) {
            return new Error('please provide valid ISO 4217 delivery country code', Error::INVALID_INPUT);
        }

        return null;
    }

    private function createPostData(array $order, string $labelFormat, string $springService): array
    {
        $shipperReference = $this->generateShipperReference($order['sender_fullname']);

        $consignorAddressLines = $this->splitAddress($order['sender_address'], $springService);
        $consigneeAddressLines = $this->splitAddress($order['delivery_address'], $springService);

        return [
            "Shipment" => [
                "LabelFormat" => $labelFormat,
                "ShipperReference" => $shipperReference,
                "OrderDate" => new \DateTimeImmutable()->format("Y-m-d"),
                "Service" => $springService,
                "Weight" => $order['weight'],
                "Value" => $order['value'],
                "Currency" => self::DEFAULT_CURRENCY,
                "ConsignorAddress" => [
                    "Name" => $order['sender_fullname'],
                    "Company" => $order['sender_company'],
                    "City" => $order['sender_city'],
                    "Zip" => $order['sender_postalcode'],
                    "Country" => $order['sender_country'],
                    "Phone" => $order['sender_phone'],
                ] + $consignorAddressLines,
                "ConsigneeAddress" => [
                    "Name" => $order['delivery_fullname'],
                    "Company" => $order['delivery_company'],
                    "City" => $order['delivery_city'],
                    "Zip" => $order['delivery_postalcode'],
                    "Country" => $order['delivery_country'],
                    "Phone" => $order['delivery_phone'],
                    "Email" => $order['delivery_email'],
                ] + $consigneeAddressLines,
            ],
        ];
    }

    private function splitAddress(string $address, string $springService): array
    {
        $serviceRequirements = $this->servicesRequirements($springService);
        $maxLength = $serviceRequirements['max_address_line_length'];

        $lines = [];

        $wrapped = wordwrap($address, $maxLength, "\n", true);
        foreach (explode("\n", $wrapped) as $no => $line) {
            $lines['AddressLine'.($no + 1)] = $line;
            // max 3 lines
            if ($no >= 3) {
                break;
            }
        }

        return $lines;
    }

    private function generateShipperReference(string $name): string
    {
        $salt = "BaseLinker";
        $timestamp = time();
        $hash = hash('sha256', $name . $salt . $timestamp);
        return substr($hash, 0, 16);
    }
}

/**
 * Get label of package from Spring system in specified format
 */
readonly class GetLabelImage
{
    /**
     * @return string label
     * @throws Error
     */
    public function __invoke(string $trackingNumber, array $params): string
    {
        $data = $this->createPostData($trackingNumber, $params['label_format']);

        $response = new Request($params['url'], $params['api_key'])->fire('GetShipmentLabel', $data);
        if ($response->error) {
            throw $response->error;
        }

        if (!isset($response->result['Shipment']['LabelImage'])) {
            throw new Error('label image missing in response', Error::INTERNAL);
        }

        $label = base64_decode($response->result['Shipment']['LabelImage']);

        if ($label === false) {
            throw new Error('API responded with invalid label', Error::INTERNAL);
        }

        return $label;
    }

    private function createPostData(string $trackingNumber, string $labelFormat): array
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
        $body = json_encode($postData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
        // 'curl' may be used here instead (if more control is needed) - 'curl' lib needs to be installed as php extension
        $context = stream_context_create($options);
        $response = @file_get_contents($this->url, false, $context);
        if ($response === false) {
            return new Response(new Error('request failed', Error::INTERNAL));
        }

        /** @var array|null $result */
        $result = json_decode($response, true);
        if ($result === null) {
            return new Response(new Error('json_decode response: ' . json_last_error_msg(), Error::INTERNAL));
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
    public const int INTERNAL = 1;          // message not for end user - only for logging purposes
    public const int API_FATAL_ERROR = 2;   // message suitable for end user (note it comes from Spring API)
    public const int API_ERROR = 3;         // message suitable for end user (note it comes from Spring API)
    public const int INVALID_INPUT = 4;     // message suitable for end user
}
