<?php
/**
 * @noinspection GlobalVariableUsageInspection
 * @noinspection JsonEncodingApiUsageInspection
 * @noinspection PhpUnusedPrivateMethodInspection
 */

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
        __DIR__ . '/error.log',
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

new class {
    private const IMAGE_DIR = __DIR__ . '/images';

    private ?string $imageFile = null;
    private ?string $logFile = null;

    private array $params;

    public function __construct()
    {
        $action = $_GET['action'] ?? null;

        $this->params = (array)json_decode(file_get_contents('php://input'), true);

        if (array_key_exists('logFile', $this->params)) {
            $this->logFile = self::IMAGE_DIR . '/' . $this->params['logFile'];
            $this->imageFile = substr($this->logFile, 0, -4);
        }

        $result = ['status' => false];

        if (method_exists($this, $action)) {
            $result = $this->$action();
        }

        echo json_encode($result);

        exit;
    }

    private function clean(): array
    {
        if ($this->imageFile !== null && file_exists($this->imageFile)) {
            unlink($this->imageFile);
        }

        if ($this->logFile !== null && file_exists($this->logFile)) {
            unlink($this->logFile);
        }

        return ['status' => true];
    }

    private function cancel(): array
    {
        shell_exec('kill $(pgrep scanimage)');

        return $this->clean();
    }

    private function checkProgress(): array
    {
        if ($this->imageFile === null || !file_exists($this->imageFile)) {
            return ['status' => false];
        }

        clearstatcache(true, $this->imageFile);

        $file = explode("\r", file_get_contents($this->logFile));

        $progress = 0.0;
        foreach ($file as $line) {
            if (strpos($line, 'Progress') !== false) {
                $progress = (float)preg_replace('~Progress: (\d+\.?\d*)%[\s\S]*~', '$1', $line);
            }
        }

        if ($progress === 100.0 && !file_exists($this->imageFile)) {
            $progress -= 0.1;
        }

        return [
            'status' => true,
            'progress' => $progress,
        ];
    }

    private function getImage(): array
    {
        if ($this->imageFile === null || !file_exists($this->imageFile)) {
            return ['status' => false];
        }

        return [
            'status' => true,
            'filename' => basename($this->imageFile),
            'src' => 'data:' . mime_content_type($this->imageFile) . ';charset=utf-8;base64,' . base64_encode(file_get_contents($this->imageFile)),
        ];
    }

    private function copy(): array
    {
        if (!file_exists($this->logFile)) {
            return ['status' => false];
        }

//        shell_exec('lp ' . $this->imageFile);

        return ['status' => true];
    }

    private function listDevices(): array
    {
        $scanimageACacheFile = __DIR__ . '/scanimage-A.cache.log';
        $scanimageLCacheFile = __DIR__ . '/scanimage-L.cache.log';

        if (array_key_exists('nocache', $_GET) || !file_exists($scanimageACacheFile)) {
            $shellOutput = shell_exec('scanimage -A');
            file_put_contents($scanimageACacheFile, $shellOutput);
        } else {
            $shellOutput = file_get_contents($scanimageACacheFile);
        }

        $output = explode(PHP_EOL, $shellOutput);

        $devices = [];
        $deviceLabel = null;

        foreach ($output as $line) {
            $line = trim($line);

            if (preg_match('~All options specific to device `(.+)\':~', $line, $deviceMatches)) {
                $deviceLabel = $deviceMatches[1];
            }

            $params = [];
            $delimiter = ' ';
            $boolean = false;
            $range = false;
            $rangeStep = 0;
            $default = '';
            $postfix = '';

            if (strpos($line, '-') === 0) {
                preg_match('~--?([\w-]+)(.*)~', $line, $matches);

                $flag = $matches[1];

                if (strpos($matches[2], '[=(yes|no)]') === 0) {
                    // example: --custom-gamma[=(yes|no)] [no]

                    $boolean = true;
                    $delimiter = '=';
                    $default = strpos($matches[2], '[yes]') !== false;
                } elseif (preg_match('~^ ([\d.-]+?)\.\.([\d.-]+).+\[(.+)]~', $matches[2], $rangeMatches)) {
                    // example: --red-gamma-table 0..65535,... [inactive]
                    // example: --lamp-off-time 0..60 [15]
                    // example: --expiration-time -1..30000 (in steps of 1) [60]

                    $params = [$rangeMatches[1], $rangeMatches[2]];
                    $default = $rangeMatches[3] === 'inactive' ? null : $rangeMatches[3];
                    $range = true;

                    if (preg_match('~\(in steps of (.+?)\)~', $line, $rangeStepMatches)) {
                        $rangeStep = $rangeStepMatches[1];
                    } elseif (strpos($params[0], '.') !== false || strpos($params[1], '.') !== false) {
                        $rangeStep = 0.1;
                    } else {
                        $rangeStep = 1;
                    }

                    if (preg_match('~^.+(mm|dpi)$~', $params[1], $rangePostfixMatches)) {
                        $postfix = $rangePostfixMatches[1];
                        $params[1] = str_replace($postfix, '', $params[1]);
                    }
                } elseif (preg_match('~^ ([\S\s+?|]+) \[(.+)]~', $matches[2], $orMatches)) {
                    // example: --color-filter Red|Green|Blue [Green]

                    $params = explode('|', $orMatches[1]);
                    $default = $orMatches[2];

                    $lastParamKey = count($params) - 1;

                    if (preg_match('~^.+(mm|dpi)$~', $params[$lastParamKey], $rangePostfixMatches)) {
                        $postfix = $rangePostfixMatches[1];
                        $params[$lastParamKey] = str_replace($postfix, '', $params[$lastParamKey]);
                    }
                }

                foreach ($params as $index => $param) {
                    if (!is_numeric($param)) {
                        continue;
                    }

                    if ((string)(int)$param === $param) {
                        $params[$index] = (int)$param;
                    } else {
                        $params[$index] = (float)$param;
                    }
                }

                if ($flag === 'mode') {
                    $newParams = [];

                    foreach ($params as $param) {
                        if ($param === 'Color') {
                            $newParams[$param] = 'Цветное';
                        } elseif ($param === 'Gray') {
                            $newParams[$param] = 'Черно-белое';
                        } elseif ($param === 'auto') {
                            $newParams[$param] = 'Автоматически';
                        } else {
                            $newParams[$param] = $param;
                        }
                    }

                    $params = $newParams;
                }

                if ($flag === 'source') {
                    $newParams = [];

                    foreach ($params as $param) {
                        if ($param === 'Flatbed') {
                            $newParams[$param] = 'Планшетный';
                        } elseif ($param === 'Automatic Document Feeder') {
                            $newParams[$param] = 'Автоподатчик';
                        } else {
                            $newParams[$param] = $param;
                        }
                    }

                    $params = $newParams;
                }

                $devices[$deviceLabel]['flags'][$flag] = [
                    'params' => $params,
                    'delimiter' => $delimiter,
                    'boolean' => $boolean,
                    'range' => $range,
                    'rangeStep' => $rangeStep,
                    'default' => $default,
                    'postfix' => $postfix,
                ];
            }
        }


        if (array_key_exists('nocache', $_GET) || !file_exists($scanimageLCacheFile)) {
            $shellOutput = shell_exec('scanimage -L');
            file_put_contents($scanimageLCacheFile, $shellOutput);
        } else {
            $shellOutput = file_get_contents($scanimageLCacheFile);
        }

        preg_match_all('~`(.+)\' is a (.+)~', $shellOutput, $devicesMatches);

        foreach ($devicesMatches[1] as $index => $deviceName) {
            $devices[$deviceName]['name'] = $devicesMatches[2][$index];
        }

        return [
            'status' => true,
            'devices' => $devices,
        ];
    }

    private function scan(): array
    {
        return $this->scanImage();
    }

    private function preview(): array
    {
        return $this->scanImage(true);
    }

    private function scanImage(bool $preview = false): array
    {
        $command = [
            'scanimage' => '',
            '-v' => '',
            '--progress' => '',
            '--depth ' => '8',
        ];

        if (random_int(0, 3) === 0) {
            $command['--force-calibration'] = '';
        }

        if ($this->params['device'] !== null) {
            $command[] = '--device-name=' . $this->params['device'];
        }

        $command['--mode '] = $this->params['mode'];
        $command['--source '] = $this->params['source'];
        $command['-l '] = $this->params['left'];
        $command['-t '] = $this->params['top'];
        $command['-x '] = $this->params['width'];
        $command['-y '] = $this->params['height'];

        if ($preview) {
            $this->params['format'] = 'jpeg';
            $this->params['resolution'] = 75;
        }

        $scanFile = self::IMAGE_DIR . '/scan_' . date('Y-m-d_H:i:s') . '.' . $this->params['format'];
        $logFile = $scanFile . '.log';

        $command['--preview='] = $preview ? 'yes' : 'no';
        $command['--format='] = $this->params['format'];
        $command['--output-file='] = $scanFile;
        $command['--resolution '] = $this->params['resolution'] . 'dpi';

        $shell = '';

        foreach ($command as $key => $value) {
            $shell .= $key . $value . ' ';
        }

        $shell .= ' 1>' . $logFile . ' 2>&1 &';

        shell_exec($shell);

        return [
            'status' => true,
            'command' => $shell,
            'logFile' => basename($logFile),
        ];
    }
};
