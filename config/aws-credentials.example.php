<?php
/**
 * AWS Credentials Configuration Example
 * 
 * Copy this file to 'aws-credentials.php' and fill in your actual AWS credentials.
 * The actual credentials file will be ignored by git for security.
 */

return [
    'credentials' => [
        'key'    => 'YOUR_AWS_ACCESS_KEY_ID',
        'secret' => 'YOUR_AWS_SECRET_ACCESS_KEY',
        // Optional: uncomment if using temporary credentials
        // 'token'  => 'YOUR_AWS_SESSION_TOKEN',
    ],
    'region' => 'us-east-1', // Change to your preferred AWS region
    'version' => 'latest',
    
    // S3 Configuration
    's3' => [
        'bucket' => 'your-bucket-name-here',
        // Optional: prefix for organizing files
        // 'prefix' => 'claimit/',
    ],
    
    // CloudFront Configuration
    // Required for cache invalidation when images are rotated
    // Find your Distribution ID in AWS Console -> CloudFront -> Your Distribution -> General tab
    'cloudfront' => [
        'distribution_id' => 'YOUR_CLOUDFRONT_DISTRIBUTION_ID', // e.g., 'E1234ABCDEFGH'
    ]
]; 