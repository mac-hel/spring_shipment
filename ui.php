<?php

declare(strict_types=1);

require __DIR__ . DIRECTORY_SEPARATOR . 'spring.php';

$error = '';
$success = false;
$trackingNumber = '';
$order = [
    'weight' => '',
    'value' => '',
    'sender_fullname' => '',
    'sender_company' => '',
    'sender_address' => '',
    'sender_city' => '',
    'sender_postalcode' => '',
    'sender_country' => '',
    'sender_phone' => '',
    'delivery_fullname' => '',
    'delivery_company' => '',
    'delivery_address' => '',
    'delivery_city' => '',
    'delivery_postalcode' => '',
    'delivery_country' => '',
    'delivery_phone' => '',
    'delivery_email' => '',
];

$envs = [
    'url' => 'https://mtapi.net/?testMode=1',
    'api_key' => 'f16753b55cac6c6e',
    'label_format' => 'PDF',
    'service' => 'PPTT',
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $order = $_POST;

    try {
        $trackingNumber = new \Spring\NewPackage()($order, $envs);
    } catch (Spring\Error $e) {
        $error = errorMessage($e);
    } catch (\Throwable $e) {
        $error = errorMessage();
    }

    if (empty($error)) {
        $success = true;
    }

} elseif (isset($_GET['download_label'])) {

    $labelImage = '';
    try {
        // in real scenario tracking number is checked (or comes from) DB/Session
        $labelImage = new \Spring\GetLabelImage()($_GET['download_label'], $envs);
    } catch (Spring\Error $error) {
        $error = errorMessage($error);
    } catch (\Throwable $e) {
        $error = errorMessage();
    }

    if (!empty($error)) {
        http_response_code(500);
        echo $error;
        exit;
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="label.pdf"');
    header('Content-Length: ' . strlen($labelImage));

    echo $labelImage;
    exit;
}

function errorMessage(?Spring\Error $error = null): string
{
    if ($error) {
        $msg = match ($error->getCode()) {
            Spring\Error::INTERNAL => 'Error occurred, try again later',
            Spring\Error::API_FATAL_ERROR,
            Spring\Error::API_ERROR,
            Spring\Error::INVALID_INPUT => $error->getMessage(),
        };
    } else {
        $msg = 'We are temporarily unable to process your request, please try again later';
    }

    return $msg;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spring Shipment</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex justify-center items-center min-h-screen">
<div class="bg-white p-6 rounded-lg shadow-md w-full max-w-md">
    <h2 class="text-2xl font-bold mb-4 text-center">Create Shipment Package</h2>

    <?php if ($success): ?>
        <div class="bg-green-100 text-green-700 p-3 rounded mb-4">
            ✅ Form submitted successfully!
            Your tracking number: <b><?= htmlspecialchars($trackingNumber) ?></b>
            Package label download will start shortly.
        </div>
    <?php endif ?>

    <form method="POST" class="space-y-4">

        <?php if (!empty($error)): ?>
            <p class="text-red-500 text-sm"><?= $error ?></p>
        <?php endif ?>

        <?php foreach ($order as $field => $value): ?>
            <div>
                <label class="block font-medium capitalize" for="<?= $field ?>">
                    <?= ucfirst(str_replace('_', ' ', $field)) ?>
                </label>
                <input type="text" id="<?= $field ?>" name="<?= $field ?>"
                       value="<?= htmlspecialchars($value) ?>"
                       class="w-full border px-3 py-2 rounded"
                >
            </div>
        <?php endforeach ?>

        <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded hover:bg-blue-600">
            Create Package
        </button>
    </form>
</div>

<?php if ($success): ?>
    <script>
        // redirect to download page
        const url = new URL(window.location.href);
        url.searchParams.set("download_label", "<?= $trackingNumber ?>");
        window.location.href = url.toString();
    </script>
<?php endif ?>

</body>
</html>
