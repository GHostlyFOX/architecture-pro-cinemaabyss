<?php
$monolithUrl = getenv('MONOLITH_URL');
$moviesServiceUrl = getenv('MOVIES_SERVICE_URL');
$eventsServiceUrl = getenv('EVENTS_SERVICE_URL');
$migrationPercent = getenv('MOVIES_MIGRATION_PERCENT');

$requestUri = $_SERVER['REQUEST_URI'];

$targetUrl = '';

if (strpos($requestUri, '/api/movies') === 0) {
    if ((int)$migrationPercent > 0 && rand(1, 100) <= (int)$migrationPercent) {
        $targetUrl = $moviesServiceUrl . $requestUri;
    } else {
        $targetUrl = $monolithUrl . $requestUri;
    }
} elseif (strpos($requestUri, '/api/events') === 0) {
        $targetUrl = $eventsServiceUrl . $requestUri;
} else {
    $targetUrl = $monolithUrl . $requestUri;
}

$ch = curl_init($targetUrl);

$headers = [];
foreach (getallheaders() as $name => $value) {
    $headers[] = "$name: $value";
}

curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

// Forward the request body
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents('php://input'));
}


$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

http_response_code($httpcode);

echo $response;
