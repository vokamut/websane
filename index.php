<?php
/** @noinspection GlobalVariableUsageInspection */

declare(strict_types=1);

set_error_handler(
    static function (int $number, string $message, string $filename, int $line): void {
        logger('ERROR: ' . $message . ' | ' . $filename . ':' . $line);
    },
    E_ALL
);

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error) {
        logger('SHUTDOWN: ' . $error['message'] . ' | ' . $error['file'] . ':' . $error['line']);
    }
});

set_exception_handler(static function (Throwable $exception): void {
    logger('EXCEPTION: ' . $exception->getMessage() . ' | ' . $exception->getFile() . ':' . $exception->getLine() . PHP_EOL . $exception->getTraceAsString());
});

function logger(string $message): void
{
    file_put_contents(
        __DIR__ . 'error.log',
        date('d.m.Y H:i:s') . PHP_EOL . $message . PHP_EOL . PHP_EOL,
        FILE_APPEND
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo file_get_contents(__DIR__ . '/index.html');

    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: *');
header('Access-Control-Allow-Headers: *');

if (empty($_GET)) {
    echo json_encode([
        'status' => false,
    ]);

    exit;
}

$isListDevices = array_key_exists('listDevices', $_GET);
$isPreview = array_key_exists('preview', $_GET);
$isScan = array_key_exists('scan', $_GET);
$isCheckProgress = array_key_exists('checkProgress', $_GET);
$isGetImage = array_key_exists('getImage', $_GET);
$isCancel = array_key_exists('cancel', $_GET);

if (!$isListDevices && !$isPreview && !$isScan && !$isCheckProgress && !$isGetImage && !$isCancel) {
    echo json_encode([
        'status' => false,
    ]);

    exit;
}

$params = json_decode(file_get_contents('php://input'), true);

$imagesDir = __DIR__ . '/images';

if (!is_dir($imagesDir) && !mkdir($imagesDir, 0755) && !is_dir($imagesDir)) {
    throw new RuntimeException(sprintf('Directory "%s" was not created', $imagesDir));
}

if ($isCancel) {
    shell_exec('kill $(pgrep scanimage)');

    $logFile = $imagesDir . '/' . $params['logFile'];
    $imageFile = substr($logFile, 0, -4);

    unlink($logFile);
    unlink($imageFile);

    echo json_encode([
        'status' => true,
    ]);

    exit;
}

if ($isCheckProgress) {
    $logFile = $imagesDir . '/' . $params['logFile'];
    $imageFile = substr($logFile, 0, -4);

    if (!file_exists($logFile)) {
        echo json_encode([
            'status' => false,
        ]);

        exit;
    }

    clearstatcache(true, $imageFile);

    $file = explode("\r", file_get_contents($logFile));

    $progress = 0.0;
    foreach ($file as $line) {
        if (strpos($line, 'Progress') !== false) {
            $progress = (float)preg_replace('~Progress: (\d+\.?\d*)%[\s\S]*~', '$1', $line);
        }
    }

    if ($progress === 100.0 && !file_exists($imageFile)) {
        $progress -= 0.1;
    }

    echo json_encode([
        'status' => true,
        'progress' => $progress,
    ]);

    exit;
}

if ($isGetImage) {
    $logFile = $imagesDir . '/' . $params['logFile'];
    $imageFile = substr($logFile, 0, -4);

    if (!file_exists($imageFile)) {
        echo json_encode([
            'status' => false,
        ]);

        exit;
    }

    echo json_encode([
        'status' => true,
        'filename' => basename($imageFile),
        'src' => 'data:' . mime_content_type($imageFile) . ';charset=utf-8;base64,' . base64_encode(file_get_contents($imageFile)),
    ]);

    unlink($logFile);
    unlink($imageFile);

    exit;
}

if ($isListDevices) {
    $shell = 'scanimage -L';

    $output = shell_exec($shell);

    preg_match_all('~[\'`](.+)[\'`] is a (.+)~', $output, $devicesMatches);

    echo json_encode([
        'status' => true,
        'command' => $shell,
        'devices' => array_combine($devicesMatches[1], $devicesMatches[2]),
    ]);

    exit;
}

$command = [
    'scanimage' => '',
    '-v' => '',
    '--progress' => '',
    '--depth ' => '8',
];

if (random_int(0, 3) === 0) {
    $command['--force-calibration'] = '';
}

if ($params['device'] !== null) {
    $command[] = '--device-name=' . $params['device'];
}

$command['--mode '] = $params['mode'];
$command['--source '] = $params['source'];
$command['-l '] = $params['left'];
$command['-t '] = $params['top'];
$command['-x '] = $params['width'];
$command['-y '] = $params['height'];

if ($isPreview) {
    $params['format'] = 'jpeg';
    $params['resolution'] = 75;
}

$scanFile = $imagesDir . '/scan_' . date('Y-m-d_H:i:s') . '.' . $params['format'];
$logFile = $scanFile . '.log';

$command['--preview='] = $isScan ? 'no' : 'yes';
$command['--format='] = $params['format'];
$command['--output-file='] = $scanFile;
$command['--resolution '] = $params['resolution'] . 'dpi';

$shell = '';

foreach ($command as $key => $value) {
    $shell .= $key . $value . ' ';
}

$shell .= ' 1>' . $logFile . ' 2>&1 &';

shell_exec($shell);

echo json_encode([
    'status' => true,
    'command' => $shell,
    'logFile' => basename($logFile),
]);

exit;
