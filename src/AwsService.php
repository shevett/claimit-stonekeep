<?php

namespace ClaimIt;

use Aws\S3\S3Client;
use Aws\CloudFront\CloudFrontClient;
use Aws\Exception\AwsException;

/**
 * AWS Service class for handling S3 operations
 */
class AwsService
{
    private S3Client $s3Client;
    private ?CloudFrontClient $cloudFrontClient = null;
    private array $config;

    public function __construct()
    {
        // Temporarily suppress all warnings during AWS initialization
        $oldErrorReporting = error_reporting(E_ERROR | E_PARSE);

        try {
            $this->loadConfig();
            $this->initializeS3Client();
            $this->initializeCloudFrontClient();
        } finally {
            // Restore original error reporting
            error_reporting($oldErrorReporting);
        }
    }

    /**
     * Load AWS configuration from credentials file
     */
    private function loadConfig(): void
    {
        $credentialsFile = __DIR__ . '/../config/aws-credentials.php';

        if (!file_exists($credentialsFile)) {
            throw new \Exception(
                "AWS credentials file not found. Please copy 'aws-credentials.example.php' to 'aws-credentials.php' and configure your credentials."
            );
        }

        $this->config = require $credentialsFile;

        // Validate required configuration
        if (empty($this->config['credentials']['key']) || empty($this->config['credentials']['secret'])) {
            throw new \Exception('AWS credentials are not properly configured.');
        }
    }

    /**
     * Initialize S3 client with credentials
     */
    private function initializeS3Client(): void
    {
        try {
            $clientConfig = [
                'version' => $this->config['version'],
                'region'  => $this->config['region'],
                'credentials' => $this->config['credentials'],
                'use_aws_shared_config_files' => false  // Don't load ~/.aws/config and ~/.aws/credentials
            ];

            $this->s3Client = new S3Client($clientConfig);
        } catch (\Exception $e) {
            throw new \Exception('Failed to initialize AWS S3 client: ' . $e->getMessage());
        }
    }

    /**
     * Initialize CloudFront client with credentials
     */
    private function initializeCloudFrontClient(): void
    {
        try {
            // Only initialize if CloudFront is configured
            if (isset($this->config['cloudfront']['distribution_id'])) {
                $clientConfig = [
                    'version' => $this->config['version'],
                    'region'  => $this->config['region'],
                    'credentials' => $this->config['credentials'],
                    'use_aws_shared_config_files' => false  // Don't load ~/.aws/config and ~/.aws/credentials
                ];

                $this->cloudFrontClient = new CloudFrontClient($clientConfig);
            }
        } catch (\Exception $e) {
            // Log error but don't fail - CloudFront is optional
            error_log('Failed to initialize AWS CloudFront client: ' . $e->getMessage());
        }
    }

    /**
     * List objects in the S3 bucket
     *
     * @param string $prefix Optional prefix to filter objects
     * @param int $maxKeys Maximum number of objects to return
     * @return array List of objects
     */
    public function listObjects(string $prefix = '', int $maxKeys = 100): array
    {
        try {
            $params = [
                'Bucket' => $this->config['s3']['bucket'],
                'MaxKeys' => $maxKeys
            ];

            // Add prefix if specified or use default from config
            $fullPrefix = $prefix ?: ($this->config['s3']['prefix'] ?? '');
            if ($fullPrefix) {
                $params['Prefix'] = $fullPrefix;
            }

            $result = $this->s3Client->listObjectsV2($params);

            $objects = [];
            if (isset($result['Contents'])) {
                foreach ($result['Contents'] as $object) {
                    $objects[] = [
                        'key' => $object['Key'],
                        'size' => $object['Size'],
                        'last_modified' => $object['LastModified']->format('Y-m-d H:i:s'),
                        'etag' => trim($object['ETag'], '"'),
                        'storage_class' => $object['StorageClass'] ?? 'STANDARD'
                    ];
                }
            }

            return [
                'objects' => $objects,
                'total_count' => count($objects),
                'is_truncated' => $result['IsTruncated'] ?? false,
                'next_token' => $result['NextContinuationToken'] ?? null
            ];
        } catch (AwsException $e) {
            throw new \Exception('Failed to list S3 objects: ' . $e->getMessage());
        }
    }

    /**
     * Download/get an object from S3
     *
     * @param string $key S3 object key
     * @return array Object data and metadata
     */
    public function getObject(string $key): array
    {
        try {
            $result = $this->s3Client->getObject([
                'Bucket' => $this->config['s3']['bucket'],
                'Key' => $key
            ]);

            return [
                'content' => (string) $result['Body'],
                'content_type' => $result['ContentType'] ?? 'application/octet-stream',
                'content_length' => $result['ContentLength'] ?? 0,
                'last_modified' => $result['LastModified']->format('Y-m-d H:i:s'),
                'etag' => trim($result['ETag'] ?? '', '"'),
                'metadata' => $result['Metadata'] ?? []
            ];
        } catch (AwsException $e) {
            throw new \Exception('Failed to get S3 object: ' . $e->getMessage());
        }
    }

    /**
     * Bulk download multiple objects using optimized sequential approach
     *
     * @param array $keys Array of S3 object keys
     * @return array Associative array with key => content
     */
    public function getObjectsBulk(array $keys): array
    {
        if (empty($keys)) {
            return [];
        }

        $startTime = microtime(true);
        $results = [];

        // Use optimized sequential approach with connection reuse
        $client = new \GuzzleHttp\Client([
            'timeout' => 5,
            'connect_timeout' => 2,
            'http_errors' => false
        ]);

        foreach ($keys as $key) {
            try {
                // Generate presigned URL for this specific key
                $url = $this->getPresignedUrl($key, 300);

                // Download with optimized settings
                $response = $client->get($url);

                if ($response->getStatusCode() === 200) {
                    $results[$key] = $response->getBody()->getContents();
                } else {
                    error_log("Failed to download $key: HTTP " . $response->getStatusCode());
                    $results[$key] = null;
                }
            } catch (\Exception $e) {
                error_log("Failed to download $key: " . $e->getMessage());
                $results[$key] = null;
            }
        }

        $endTime = microtime(true);
        $totalTime = round(($endTime - $startTime) * 1000, 2);
        error_log("Optimized Bulk Download: " . count($keys) . " objects in {$totalTime}ms");

        return $results;
    }

    /**
     * Generate a presigned URL for downloading an object
     *
     * @param string $key S3 object key
     * @param int $expiration Expiration time in seconds (default: 1 hour)
     * @return string Presigned URL
     */
    public function getPresignedUrl(string $key, int $expiration = 3600): string
    {
        try {
            $command = $this->s3Client->getCommand('GetObject', [
                'Bucket' => $this->config['s3']['bucket'],
                'Key' => $key
            ]);

            $request = $this->s3Client->createPresignedRequest($command, "+{$expiration} seconds");

            return (string) $request->getUri();
        } catch (AwsException $e) {
            throw new \Exception('Failed to generate presigned URL: ' . $e->getMessage());
        }
    }

    /**
     * Check if an object exists in S3
     *
     * @param string $key S3 object key
     * @return bool True if object exists
     */
    public function objectExists(string $key): bool
    {
        try {
            $this->s3Client->headObject([
                'Bucket' => $this->config['s3']['bucket'],
                'Key' => $key
            ]);

            return true;
        } catch (AwsException $e) {
            return false;
        }
    }

    /**
     * Upload an object to S3
     *
     * @param string $key S3 object key
     * @param string $content File content
     * @param string $contentType MIME type
     * @param array $metadata Optional metadata
     * @return array Upload result
     */
    public function putObject(string $key, string $content, string $contentType = 'application/octet-stream', array $metadata = []): array
    {
        try {
            $params = [
                'Bucket' => $this->config['s3']['bucket'],
                'Key' => $key,
                'Body' => $content,
                'ContentType' => $contentType
            ];

            if (!empty($metadata)) {
                $params['Metadata'] = $metadata;
            }

            $result = $this->s3Client->putObject($params);

            return [
                'etag' => trim($result['ETag'], '"'),
                'version_id' => $result['VersionId'] ?? null,
                'expiration' => $result['Expiration'] ?? null
            ];
        } catch (AwsException $e) {
            throw new \Exception('Failed to upload object to S3: ' . $e->getMessage());
        }
    }

    /**
     * Get S3 bucket name from configuration
     *
     * @return string Bucket name
     */
    public function getBucketName(): string
    {
        return $this->config['s3']['bucket'];
    }

    /**
     * Get AWS region from configuration
     *
     * @return string AWS region
     */
    public function getRegion(): string
    {
        return $this->config['region'];
    }

    /**
     * Get AWS configuration for use by other services
     *
     * @return array AWS configuration
     */
    public function getAwsConfig(): array
    {
        return $this->config;
    }

    /**
     * Delete an object from S3
     *
     * @param string $key S3 object key
     * @return bool True if successful
     */
    public function deleteObject(string $key): bool
    {
        try {
            $this->s3Client->deleteObject([
                'Bucket' => $this->config['s3']['bucket'],
                'Key' => $key
            ]);

            return true;
        } catch (AwsException $e) {
            throw new \Exception('Failed to delete S3 object: ' . $e->getMessage());
        }
    }

    /**
     * Create a CloudFront cache invalidation for specific paths
     *
     * @param array $paths Array of paths to invalidate (e.g., ['/images/12345.jpg'])
     * @return array Invalidation result with ID and status
     */
    public function createInvalidation(array $paths): array
    {
        try {
            if (!$this->cloudFrontClient) {
                throw new \Exception('CloudFront client is not initialized');
            }

            if (empty($this->config['cloudfront']['distribution_id'])) {
                throw new \Exception('CloudFront distribution ID is not configured');
            }

            if (empty($paths)) {
                throw new \Exception('No paths provided for invalidation');
            }

            // Ensure all paths start with /
            $paths = array_map(function ($path) {
                return '/' . ltrim($path, '/');
            }, $paths);

            $result = $this->cloudFrontClient->createInvalidation([
                'DistributionId' => $this->config['cloudfront']['distribution_id'],
                'InvalidationBatch' => [
                    'CallerReference' => time() . '-' . uniqid(),
                    'Paths' => [
                        'Quantity' => count($paths),
                        'Items' => $paths
                    ]
                ]
            ]);

            return [
                'invalidation_id' => $result['Invalidation']['Id'],
                'status' => $result['Invalidation']['Status'],
                'create_time' => $result['Invalidation']['CreateTime']->format('Y-m-d H:i:s')
            ];
        } catch (AwsException $e) {
            throw new \Exception('Failed to create CloudFront invalidation: ' . $e->getMessage());
        }
    }
}
