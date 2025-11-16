<?php
/**
 * Changelog Generator
 * 
 * Reads git commit history and generates a JSON file for the changelog page.
 * Should be run during deployment or manually when needed.
 * 
 * Usage: php scripts/generate-changelog.php
 */

// Determine the project root directory
$projectRoot = dirname(__DIR__);
$outputFile = $projectRoot . '/public/data/changelog.json';

// Change to project root to ensure git commands work
chdir($projectRoot);

echo "Generating changelog from git history...\n";

// Get git log with specific format
// Format: hash|date|author|subject|body
$gitCommand = 'git log --pretty=format:"%H|%ci|%an|%s|%b" --no-merges';

exec($gitCommand, $output, $returnCode);

if ($returnCode !== 0) {
    die("Error: Failed to execute git log command. Make sure you're in a git repository.\n");
}

$commits = [];
$currentCommit = null;

foreach ($output as $line) {
    if (empty($line)) {
        continue;
    }
    
    // Check if this is a new commit line (starts with hash|date|...)
    if (strpos($line, '|') !== false) {
        $parts = explode('|', $line, 5);
        
        if (count($parts) >= 4) {
            // Save previous commit if exists
            if ($currentCommit !== null) {
                $commits[] = $currentCommit;
            }
            
            // Start new commit
            $currentCommit = [
                'hash' => $parts[0],
                'short_hash' => substr($parts[0], 0, 7),
                'date' => $parts[1],
                'date_formatted' => date('F j, Y', strtotime($parts[1])),
                'time_formatted' => date('g:i A', strtotime($parts[1])),
                'author' => $parts[2],
                'subject' => $parts[3],
                'body' => isset($parts[4]) ? trim($parts[4]) : ''
            ];
        }
    } else {
        // This is a continuation of the body
        if ($currentCommit !== null) {
            $currentCommit['body'] .= "\n" . trim($line);
        }
    }
}

// Don't forget the last commit
if ($currentCommit !== null) {
    $commits[] = $currentCommit;
}

// Group commits by date
$groupedCommits = [];
foreach ($commits as $commit) {
    $dateKey = date('Y-m-d', strtotime($commit['date']));
    if (!isset($groupedCommits[$dateKey])) {
        $groupedCommits[$dateKey] = [
            'date' => $dateKey,
            'date_formatted' => date('F j, Y', strtotime($commit['date'])),
            'commits' => []
        ];
    }
    $groupedCommits[$dateKey]['commits'][] = $commit;
}

// Convert to indexed array and sort by date (newest first)
$groupedCommits = array_values($groupedCommits);
usort($groupedCommits, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Prepare final output
$changelog = [
    'generated_at' => date('c'),
    'generated_at_formatted' => date('F j, Y g:i A'),
    'total_commits' => count($commits),
    'groups' => $groupedCommits
];

// Ensure output directory exists
$outputDir = dirname($outputFile);
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

// Write JSON file
$jsonOutput = json_encode($changelog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (file_put_contents($outputFile, $jsonOutput) === false) {
    die("Error: Failed to write changelog file to {$outputFile}\n");
}

echo "Success! Generated changelog with {$changelog['total_commits']} commits.\n";
echo "Output: {$outputFile}\n";

