<?php
/**
 * One-time migration script: JSON users to MySQL database
 * 
 * This script migrates all user data from S3 JSON files to the MySQL users table.
 * Safe to run multiple times - will skip users that already exist in the database.
 * 
 * Usage:
 *   php scripts/migrate_users_to_db.php
 * 
 * Environment:
 *   - Respects DEVELOPMENT_MODE to target correct database (claimit_dev or claimit_prod)
 *   - Uses existing AWS credentials from config
 */

// Bootstrap the application
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

echo "========================================\n";
echo "User Migration: JSON to MySQL\n";
echo "========================================\n";
echo "Environment: " . (DEVELOPMENT_MODE ? 'DEVELOPMENT' : 'PRODUCTION') . "\n";
echo "Database: " . DB_NAME . "\n";
echo "S3 Bucket: " . (DEVELOPMENT_MODE ? 'claimit-dev' : 'claimit.stonekeep.com') . "\n";
echo "========================================\n\n";

// Get database connection
try {
    $pdo = getDbConnection();
    echo "✓ Database connection established\n\n";
} catch (Exception $e) {
    die("✗ Failed to connect to database: " . $e->getMessage() . "\n");
}

// Get AWS service
try {
    $awsService = getAwsService();
    $bucketName = $awsService->getBucketName();
    echo "✓ S3 connection established\n\n";
} catch (Exception $e) {
    die("✗ Failed to connect to S3: " . $e->getMessage() . "\n");
}

// Counters for reporting
$stats = [
    'total' => 0,
    'inserted' => 0,
    'skipped' => 0,
    'errors' => 0
];

$errors = [];

// List all JSON files in the users/ directory
echo "Fetching user files from S3...\n";
try {
    // Use the AwsService listObjects method
    $result = $awsService->listObjects('users/', 10000);
    
    if (!isset($result['objects']) || empty($result['objects'])) {
        die("✗ No user files found in S3 bucket\n");
    }
    
    $userFiles = array_filter($result['objects'], function($item) {
        return substr($item['key'], -5) === '.json';
    });
    
    $stats['total'] = count($userFiles);
    echo "✓ Found {$stats['total']} user files\n\n";
    
} catch (Exception $e) {
    die("✗ Failed to list S3 objects: " . $e->getMessage() . "\n");
}

// Process each user file
echo "Processing users...\n";
echo str_repeat('-', 80) . "\n";

foreach ($userFiles as $index => $object) {
    $key = $object['key'];
    $userId = basename($key, '.json');
    
    $progress = ($index + 1) . "/" . $stats['total'];
    echo "[$progress] Processing user: $userId ... ";
    
    try {
        // Download and parse JSON file (account data)
        $result = $awsService->getObject($key);
        
        $jsonContent = $result['content'];
        $userData = json_decode($jsonContent, true);
        
        if ($userData === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to parse JSON: " . json_last_error_msg());
        }
        
        // Check for corresponding YAML file (user preferences)
        $yamlKey = 'users/' . $userId . '.yaml';
        try {
            $yamlResult = $awsService->getObject($yamlKey);
            $yamlContent = $yamlResult['content'];
            $preferences = yaml_parse($yamlContent);
            
            if ($preferences !== false) {
                // Merge preferences into userData
                $userData['display_name'] = $preferences['display_name'] ?? null;
                $userData['zipcode'] = $preferences['zipcode'] ?? null;
                $userData['show_gone_items'] = isset($preferences['show_gone_items']) && $preferences['show_gone_items'] === 'yes';
                $userData['email_notifications'] = isset($preferences['email_notifications']) && $preferences['email_notifications'] === 'yes';
                $userData['new_listing_notifications'] = isset($preferences['new_listing_notifications']) && $preferences['new_listing_notifications'] === 'yes';
            }
        } catch (Exception $e) {
            // No YAML file - use defaults
            $userData['display_name'] = null;
            $userData['zipcode'] = null;
            $userData['show_gone_items'] = true;
            $userData['email_notifications'] = true;
            $userData['new_listing_notifications'] = true;
        }
        
        // Check if user already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        
        if ($stmt->fetch()) {
            echo "SKIPPED (already exists)\n";
            $stats['skipped']++;
            continue;
        }
        
        // Insert user into database
        $sql = "INSERT INTO users (
            id, 
            email, 
            name, 
            picture, 
            verified_email, 
            locale, 
            last_login, 
            created_at,
            display_name,
            zipcode,
            show_gone_items,
            email_notifications,
            new_listing_notifications,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $userId,
            $userData['email'] ?? null,
            $userData['name'] ?? null,
            $userData['picture'] ?? null,
            isset($userData['verified_email']) ? (int)$userData['verified_email'] : null,
            $userData['locale'] ?? null,
            $userData['last_login'] ?? null,
            $userData['created_at'] ?? null,
            $userData['display_name'] ?? null,
            $userData['zipcode'] ?? null,
            isset($userData['show_gone_items']) ? (int)$userData['show_gone_items'] : 1,
            isset($userData['email_notifications']) ? (int)$userData['email_notifications'] : 1,
            isset($userData['new_listing_notifications']) ? (int)$userData['new_listing_notifications'] : 1,
            date('Y-m-d H:i:s') // updated_at
        ]);
        
        echo "INSERTED\n";
        $stats['inserted']++;
        
    } catch (Exception $e) {
        echo "ERROR\n";
        $stats['errors']++;
        $errors[] = [
            'user_id' => $userId,
            'error' => $e->getMessage()
        ];
    }
}

// Print summary
echo str_repeat('-', 80) . "\n";
echo "\n";
echo "========================================\n";
echo "Migration Summary\n";
echo "========================================\n";
echo "Total users found:  {$stats['total']}\n";
echo "Successfully inserted: {$stats['inserted']}\n";
echo "Skipped (existing):    {$stats['skipped']}\n";
echo "Errors:                {$stats['errors']}\n";
echo "========================================\n";

// Print errors if any
if (!empty($errors)) {
    echo "\nErrors encountered:\n";
    echo str_repeat('-', 80) . "\n";
    foreach ($errors as $error) {
        echo "User {$error['user_id']}: {$error['error']}\n";
    }
    echo str_repeat('-', 80) . "\n";
}

echo "\n✓ Migration complete!\n";

// Verify count in database
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "\nTotal users now in database: {$result['count']}\n";
} catch (Exception $e) {
    echo "\n✗ Could not verify database count: " . $e->getMessage() . "\n";
}

