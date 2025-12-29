#!/usr/bin/env php
<?php
/**
 * Migrate items from S3 YAML files to MySQL database
 * 
 * This script reads all item YAML files from S3 and inserts them into the database.
 * It handles both items and their claims.
 */

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load configuration
require_once __DIR__ . '/../config/config.php';

// Load includes
require_once __DIR__ . '/../includes/functions.php';

echo "========================================\n";
echo "Item Migration Script\n";
echo "========================================\n\n";

// Check database connection
$pdo = getDbConnection();
if (!$pdo) {
    echo "ERROR: Could not connect to database\n";
    exit(1);
}
echo "✓ Database connection established\n";

// Check AWS service
$awsService = getAwsService();
if (!$awsService) {
    echo "ERROR: Could not initialize AWS service\n";
    exit(1);
}
echo "✓ AWS service initialized\n\n";

// Get all objects from S3
echo "Fetching items from S3...\n";
$result = $awsService->listObjects('', 1000);
$objects = $result['objects'] ?? [];
echo "Found " . count($objects) . " total objects in S3\n";

// Filter for YAML files (exclude users/)
$yamlFiles = [];
foreach ($objects as $object) {
    $key = $object['key'];
    if (str_ends_with($key, '.yaml') && !str_starts_with($key, 'users/')) {
        $yamlFiles[] = $object;
    }
}

echo "Found " . count($yamlFiles) . " item YAML files to migrate\n\n";

// Statistics
$stats = [
    'items_migrated' => 0,
    'items_skipped' => 0,
    'claims_migrated' => 0,
    'errors' => 0
];

// Begin transaction
$pdo->beginTransaction();

try {
    // Prepare SQL statements
    $itemInsertStmt = $pdo->prepare("
        INSERT INTO items (
            tracking_number, title, description, price, contact_email,
            image_file, image_width, image_height,
            user_id, user_name, user_email,
            submitted_at, submitted_timestamp,
            gone, gone_at, gone_by, relisted_at, relisted_by,
            created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            description = VALUES(description),
            price = VALUES(price),
            contact_email = VALUES(contact_email),
            image_file = VALUES(image_file),
            image_width = VALUES(image_width),
            image_height = VALUES(image_height),
            user_name = VALUES(user_name),
            user_email = VALUES(user_email),
            gone = VALUES(gone),
            gone_at = VALUES(gone_at),
            gone_by = VALUES(gone_by),
            relisted_at = VALUES(relisted_at),
            relisted_by = VALUES(relisted_by),
            updated_at = VALUES(updated_at)
    ");
    
    $claimInsertStmt = $pdo->prepare("
        INSERT INTO claims (
            item_tracking_number, user_id, user_name, user_email,
            claimed_at, status, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    // Check for existing claims to avoid duplicates
    $claimCheckStmt = $pdo->prepare("
        SELECT id FROM claims 
        WHERE item_tracking_number = ? AND user_id = ? AND claimed_at = ?
    ");
    
    // Process each YAML file
    foreach ($yamlFiles as $index => $object) {
        $key = $object['key'];
        $trackingNumber = str_replace('.yaml', '', $key);
        
        // Progress indicator
        if (($index + 1) % 10 == 0) {
            echo "Processing item " . ($index + 1) . " of " . count($yamlFiles) . "...\n";
        }
        
        try {
            // Get YAML content
            $yamlObject = $awsService->getObject($key);
            $yamlContent = $yamlObject['content'];
            
            // Parse YAML
            $data = parseSimpleYaml($yamlContent);
            
            if (!$data || !isset($data['description'])) {
                echo "  ⚠ Skipping {$trackingNumber} - Invalid YAML format\n";
                $stats['items_skipped']++;
                continue;
            }
            
            // Extract item data with defaults for missing fields
            $title = $data['title'] ?? $data['description'];
            if (strlen($title) > 255) {
                $title = substr($title, 0, 252) . '...';
            }
            
            $price = isset($data['price']) ? floatval($data['price']) : 0;
            $contactEmail = $data['contact_email'] ?? $data['user_email'] ?? 'unknown@example.com';
            
            $submittedAt = $data['submitted_at'] ?? date('Y-m-d H:i:s');
            $submittedTimestamp = $data['submitted_timestamp'] ?? time();
            
            $userId = $data['user_id'] ?? 'legacy_user';
            $userName = $data['user_name'] ?? 'Legacy User';
            $userEmail = $data['user_email'] ?? $contactEmail;
            
            $gone = isset($data['gone']) ? (bool)$data['gone'] : false;
            $goneAt = $data['gone_at'] ?? null;
            $goneBy = $data['gone_by'] ?? null;
            $relistedAt = $data['relisted_at'] ?? null;
            $relistedBy = $data['relisted_by'] ?? null;
            
            $imageFile = $data['image_file'] ?? null;
            $imageWidth = $data['image_width'] ?? null;
            $imageHeight = $data['image_height'] ?? null;
            
            $now = date('Y-m-d H:i:s');
            
            // Insert/update item
            $itemInsertStmt->execute([
                $trackingNumber,
                $title,
                $data['description'],
                $price,
                $contactEmail,
                $imageFile,
                $imageWidth,
                $imageHeight,
                $userId,
                $userName,
                $userEmail,
                $submittedAt,
                $submittedTimestamp,
                $gone ? 1 : 0,
                $goneAt,
                $goneBy,
                $relistedAt,
                $relistedBy,
                $now,
                $now
            ]);
            
            $stats['items_migrated']++;
            
            // Migrate claims if present
            if (isset($data['claims']) && is_array($data['claims'])) {
                foreach ($data['claims'] as $claim) {
                    if (!isset($claim['user_id']) || !isset($claim['claimed_at'])) {
                        continue; // Skip invalid claims
                    }
                    
                    // Check if claim already exists
                    $claimCheckStmt->execute([
                        $trackingNumber,
                        $claim['user_id'],
                        $claim['claimed_at']
                    ]);
                    
                    if ($claimCheckStmt->fetch()) {
                        continue; // Skip duplicate
                    }
                    
                    $claimInsertStmt->execute([
                        $trackingNumber,
                        $claim['user_id'],
                        $claim['user_name'] ?? 'Unknown',
                        $claim['user_email'] ?? '',
                        $claim['claimed_at'],
                        $claim['status'] ?? 'active',
                        $now,
                        $now
                    ]);
                    
                    $stats['claims_migrated']++;
                }
            }
            
        } catch (Exception $e) {
            echo "  ✗ Error processing {$trackingNumber}: " . $e->getMessage() . "\n";
            $stats['errors']++;
        }
    }
    
    // Commit transaction
    $pdo->commit();
    echo "\n✓ Transaction committed successfully\n";
    
} catch (Exception $e) {
    // Rollback on error
    $pdo->rollBack();
    echo "\n✗ ERROR: Migration failed - " . $e->getMessage() . "\n";
    echo "All changes have been rolled back.\n";
    exit(1);
}

// Display statistics
echo "\n========================================\n";
echo "Migration Complete!\n";
echo "========================================\n";
echo "Items migrated:  " . $stats['items_migrated'] . "\n";
echo "Items skipped:   " . $stats['items_skipped'] . "\n";
echo "Claims migrated: " . $stats['claims_migrated'] . "\n";
echo "Errors:          " . $stats['errors'] . "\n";
echo "========================================\n";

// Verify the migration
echo "\nVerifying migration...\n";
$itemCount = $pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
$claimCount = $pdo->query("SELECT COUNT(*) FROM claims")->fetchColumn();
echo "Database now contains:\n";
echo "  - {$itemCount} items\n";
echo "  - {$claimCount} claims\n";

echo "\n✓ Migration complete!\n";



