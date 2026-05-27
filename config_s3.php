<?php
/**
 * SHARP - S3 Storage Configuration
 */

if (!defined('S3_KEY')) define('S3_KEY', 'UU9PYJ0J5EFS9B73DK9E');
if (!defined('S3_SECRET')) define('S3_SECRET', 'FrDPLkHGzUCfLJIrtRhervJtisBUb1qyFPf0vOtb');
if (!defined('S3_BUCKET')) define('S3_BUCKET', 'sharp'); 
if (!defined('S3_ENDPOINT')) define('S3_ENDPOINT', 'is3.cloudhost.id');
if (!defined('S3_BASE_URL')) define('S3_BASE_URL', 'https://is3.cloudhost.id/sharp/');

/**
 * Generate Presigned URL for private S3 objects (IDCloudHost Compatible)
 */
function getPresignedUrl($objectKey) {
    if (empty($objectKey) || $objectKey == 'NULL') return 'assets/images/no-image.png';
    
    $bucket = S3_BUCKET;
    $accessKey = S3_KEY;
    $secretKey = S3_SECRET;
    $endpoint = S3_ENDPOINT;
    $region = 'id-jakarta';
    $service = 's3';
    $expires = 3600; // 1 hour
    $timestamp = gmdate('Ymd\THis\Z');
    $date = gmdate('Ymd');
    
    $canonicalUri = '/' . $bucket . '/' . ltrim($objectKey, '/');
    $canonicalQueryString = "X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Credential=" . urlencode("$accessKey/$date/$region/$service/aws4_request") . "&X-Amz-Date=$timestamp&X-Amz-Expires=$expires&X-Amz-SignedHeaders=host";
    
    $canonicalRequest = "GET\n" . $canonicalUri . "\n" . $canonicalQueryString . "\n" . "host:" . $endpoint . "\n\nhost\nUNSIGNED-PAYLOAD";
    
    $credentialScope = "$date/$region/$service/aws4_request";
    $stringToSign = "AWS4-HMAC-SHA256\n$timestamp\n$credentialScope\n" . hash('sha256', $canonicalRequest);
    
    $kDate = hash_hmac('sha256', $date, 'AWS4' . $secretKey, true);
    $kRegion = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', $service, $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
    $signature = hash_hmac('sha256', $stringToSign, $kSigning);
    
    return "https://$endpoint$canonicalUri?$canonicalQueryString&X-Amz-Signature=$signature";
}

if (!function_exists('getImageUrl')) {
    function getImageUrl($fileName) {
        if (empty($fileName) || $fileName == 'NULL' || $fileName == '#') {
            return 'assets/images/no-image.png';
        }
        // Jika sudah URL penuh, langsung kembalikan
        if (strpos($fileName, 'http') === 0) {
            return $fileName;
        }
        
        // Selalu gunakan Presigned URL untuk akses yang lebih handal (menghindari error 403)
        return getPresignedUrl($fileName);
    }
}
?>