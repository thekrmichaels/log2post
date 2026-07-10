# log2post

Reads Apache access logs, identifies events using configurable rules, counts their occurrences, and sends the results to a REST API.
Processing is incremental, log2post stores a checkpoint for each processed log file and resumes from the last saved file offset on subsequent runs, making it suitable for view counters, download counters, usage analytics, and other event-driven integrations.

## How it works

```text
Apache access.log  →  Event Aggregation  →  HTTP POST
```

## Configuration

`config.php` defines how log entries are mapped to HTTP requests.

### Example

```php
return [
    'views' => [
        'pattern' => '#GET\s/cs-db-migration/listings/(\d+).*200#',
        'capturingGroup' => 1,
        'endpoint' => '/listings/:id/views',
        'payload' => 'quantity',
    ],
];
```

## Usage

```bash
php aggregator.php \
    --source <access.log> \
    --target <api-base>
```
