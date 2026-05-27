<?php
require_once 'config.php';
require_once 'functions_upload.php';

header('Content-Type: text/plain');

echo "--- SHARP S3 DIAGNOSTIC TOOL ---\n";
echo "Endpoint: " . S3_ENDPOINT . "\n";
echo "Bucket: " . S3_BUCKET . "\n";
echo "Access Key: " . S3_KEY . "\n";

$testFile = 'debug_test.txt';
file_put_contents($testFile, "Diagnostic upload test at " . date('Y-m-d H:i:s'));

echo "\nStarting Upload Test...\n";

function uploadToS3Debug($filePath, $fileName) {
    $bucket = S3_BUCKET;
    $accessKey = S3_KEY;
    $secretKey = S3_SECRET;
    $endpoint = S3_ENDPOINT;
    
    $region = 'id-jakarta'; // Try id-jakarta first
    $service = 's3';
    $timestamp = gmdate('Ymd\THis\Z');
    $date = gmdate('Ymd');
    
    $content = file_get_contents($filePath);
    $payloadHash = hash('sha256', $content);
    
    // Path-style: /bucket/filename
    $canonicalUri = '/' . $bucket . '/' . $fileName;
    $canonicalQueryString = '';
    $host = $endpoint;
    
    $canonicalHeaders = "host:" . $host . "\n" . "x-amz-acl:public-read\n" . "x-amz-content-sha256:" . $payloadHash . "\n" . "x-amz-date:" . $timestamp . "\n";
    $signedHeaders = "host;x-amz-acl;x-amz-content-sha256;x-amz-date";
    
    $canonicalRequest = "PUT\n" . $canonicalUri . "\n" . $canonicalQueryString . "\n" . $canonicalHeaders . "\n" . $signedHeaders . "\n" . $payloadHash;
    
    $credentialScope = $date . "/" . $region . "/" . $service . "/aws4_request";
    $stringToSign = "AWS4-HMAC-SHA256\n" . $timestamp . "\n" . $credentialScope . "\n" . hash('sha256', $canonicalRequest);
    
    echo "--- SIGNING DEBUG ---\n";
    echo "Canonical Request:\n$canonicalRequest\n\n";
    echo "String to Sign:\n$stringToSign\n\n";
    
    $kDate = hash_hmac('sha256', $date, 'AWS4' . $secretKey, true);
    $kRegion = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', $service, $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
    $signature = hash_hmac('sha256', $stringToSign, $kSigning);
    
    $authorizationHeader = "AWS4-HMAC-SHA256 Credential=" . $accessKey . "/" . $credentialScope . ", SignedHeaders=" . $signedHeaders . ", Signature=" . $signature;
    
    $contentType = 'text/plain';
    
    $headers = [
        "Host: " . $host,
        "x-amz-acl: public-read",
        "x-amz-content-sha256: " . $payloadHash,
        "x-amz-date: " . $timestamp,
        "Authorization: " . $authorizationHeader,
        "Content-Type: " . $contentType
    ];
    
    $url = "https://" . $host . $canonicalUri;
    echo "URL: $url\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true); // Include response headers
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    //curl_close($ch);
    
    echo "\n--- RESPONSE ---\n";
    echo "HTTP Code: $httpCode\n";
    echo "Curl Error: $curlError\n";
    echo "Full Response:\n$response\n";
}

uploadToS3Debug($testFile, 'diagnostic_test.txt');
unlink($testFile);
?>