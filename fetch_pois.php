<?php
header('Content-Type: application/json');

$apiUrl = "https://api.gpsiam.app/pois";
$apiKey = "13dade62-5bd6-4082-b0ce-36757dec0d47";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $apiKey",
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
curl_close($ch);

echo $response;
?>