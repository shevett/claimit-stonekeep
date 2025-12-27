<?php

/**
 * ClaimIt Application Functions
 * 
 * REFACTORED STRUCTURE:
 * Functions are now organized in modular files for better maintainability.
 * 
 * All functions have been successfully migrated to the following modules:
 * - core.php: Database, escaping, redirects, CSRF
 * - auth.php: Authentication and authorization
 * - users.php: User management
 * - items.php: Item management
 * - claims.php: Claims system
 * - images.php: Image handling and AWS S3/CloudFront
 * - cache.php: Caching functions (placeholder for future use)
 * - utilities.php: Helper utilities (formatting, logging, Open Graph, etc.)
 * - communities.php: Community management
 */

// Load all modular function libraries
require_once __DIR__ . '/core.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/users.php';
require_once __DIR__ . '/items.php';
require_once __DIR__ . '/claims.php';
require_once __DIR__ . '/images.php';
require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/utilities.php';
require_once __DIR__ . '/communities.php';

// ============================================================================
// DATABASE HELPER FUNCTIONS FOR ITEMS AND CLAIMS
// ============================================================================

