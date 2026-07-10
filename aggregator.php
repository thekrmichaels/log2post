<?php

$rules = require_once __DIR__ . '/config.php';

$options = getopt('', ['source:', 'target:']);

$logFile = $options['source'] ?? null;
$apiBase = $options['target'] ?? null;

if (!$logFile || !$apiBase) {
    fwrite(STDERR, <<<TXT
Usage:
  php aggregator.php --source <access.log> --target <api-base>

Example:
  php aggregator.php \
      --source /var/log/apache2/access.log \
      --target http://localhost/cs-db-migration/listings
TXT);

    exit(1);
}

$checkpointFile = __DIR__ . '/progress.json';
$checkpoint = json_decode(file_get_contents($checkpointFile), true, flags: JSON_THROW_ON_ERROR);
$handle = fopen($logFile, 'r'); // * File pointer for the log file

!$handle && exit("Cannot open log file\n");

$offset = min((int) ($checkpoint[$logFile] ?? 0), filesize($logFile));

fseek($handle, $offset);

$events = [];

while (($line = fgets($handle)) !== false) {
    foreach ($rules as $eventName => $rule) {
        /*
         * If the line matches the pattern, $matches contains:
         * [0] => the entire text matched by the regular expression
         * [1..n] => captured values by each capturing group defined with parentheses.
         *
         * Example:
         * Pattern: GET\s/resources/(\d+).*200
         * Line:    GET /resources/123 HTTP/1.1 200
         *
         * $matches[0] = "GET /resources/123 HTTP/1.1 200"
         * $matches[1] = "123" (captured by (\d+))
         */
        if (preg_match($rule['pattern'], $line, $matches)) {
            $capturedValue = $matches[$rule['capturingGroup']]; // * $rule['capturingGroup'], default: 1
            $events[$eventName][$capturedValue] = ($events[$eventName][$capturedValue] ?? 0) + 1;
        }
    }
}

$summary = [];

foreach ($events as $eventName => $occurrences) {
    $rule = $rules[$eventName];
    $summary[$eventName] = [
        'requests' => count($occurrences),
        'occurrences' => array_sum($occurrences),
    ];

    foreach ($occurrences as $capturedValue => $count) {
        $url = str_replace(':id', (string) $capturedValue, "{$apiBase}{$rule['endpoint']}");
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                $rule['payload'] => $count
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
        ]);

        curl_exec($ch);
    }
}

// * Save the current file offset for the next run
$checkpoint[$logFile] = ftell($handle);

file_put_contents($checkpointFile, json_encode($checkpoint));
fclose($handle);

$output = implode('', array_map(
    fn($eventName) =>
        sprintf("%-10s : %d requests (%d occurrences)\n", $eventName, $summary[$eventName]['requests'], $summary[$eventName]['occurrences']),
    array_keys($summary)
));

echo "Log summary\n-----------\n"
    . $output
    . "Checkpoint : saved\n";
