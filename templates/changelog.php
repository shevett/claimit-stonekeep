<?php
/**
 * Changelog Template
 * 
 * Displays the site's changelog from git commit history.
 */

// Load changelog data
$changelogFile = __DIR__ . '/../public/data/changelog.json';
$changelogData = null;

if (file_exists($changelogFile)) {
    $changelogData = json_decode(file_get_contents($changelogFile), true);
}
?>

<style>
    .changelog-container {
        max-width: 900px;
        margin: 2rem auto;
        padding: 0 1rem;
    }

    .changelog-header {
        text-align: center;
        margin-bottom: 3rem;
    }

    .changelog-header h1 {
        color: var(--primary-700);
        margin-bottom: 0.5rem;
    }

    .changelog-header p {
        color: var(--gray-600);
        font-size: 0.9rem;
    }

    .changelog-date-group {
        margin-bottom: 2.5rem;
    }

    .date-header {
        background: var(--primary-50);
        padding: 0.75rem 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        border-left: 4px solid var(--primary-500);
    }

    .date-header h2 {
        color: var(--primary-700);
        font-size: 1.1rem;
        margin: 0;
    }

    .commit-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .commit-item {
        background: white;
        border: 1px solid var(--gray-200);
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 0.75rem;
        transition: all 0.2s;
    }

    .commit-item:hover {
        border-color: var(--primary-300);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .commit-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 0.5rem;
    }

    .commit-hash {
        font-family: 'Courier New', monospace;
        background: var(--gray-100);
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
        color: var(--gray-600);
    }

    .commit-time {
        font-size: 0.85rem;
        color: var(--gray-500);
    }

    .commit-subject {
        color: var(--gray-900);
        font-weight: 500;
        margin-bottom: 0.5rem;
        line-height: 1.4;
    }

    .commit-body {
        color: var(--gray-700);
        font-size: 0.9rem;
        line-height: 1.6;
        white-space: pre-wrap;
        margin-top: 0.5rem;
        padding-left: 1rem;
        border-left: 2px solid var(--gray-200);
    }

    .commit-author {
        font-size: 0.85rem;
        color: var(--gray-500);
        margin-top: 0.5rem;
    }

    .no-changelog {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--gray-600);
    }

    .changelog-stats {
        text-align: center;
        background: var(--gray-50);
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 2rem;
        font-size: 0.9rem;
        color: var(--gray-600);
    }
</style>

<div class="changelog-container">
    <div class="changelog-header">
        <h1>📝 Changelog</h1>
        <p>Recent updates and improvements to ClaimIt</p>
    </div>

    <?php if ($changelogData && isset($changelogData['groups'])): ?>
        <div class="changelog-stats">
            <strong><?php echo $changelogData['total_commits']; ?></strong> total commits
            · Last updated: <?php echo htmlspecialchars($changelogData['generated_at_formatted']); ?>
        </div>

        <?php foreach ($changelogData['groups'] as $group): ?>
            <div class="changelog-date-group">
                <div class="date-header">
                    <h2><?php echo htmlspecialchars($group['date_formatted']); ?></h2>
                </div>
                
                <ul class="commit-list">
                    <?php foreach ($group['commits'] as $commit): ?>
                        <li class="commit-item">
                            <div class="commit-header">
                                <span class="commit-hash"><?php echo htmlspecialchars($commit['short_hash']); ?></span>
                                <span class="commit-time"><?php echo htmlspecialchars($commit['time_formatted']); ?></span>
                            </div>
                            
                            <div class="commit-subject">
                                <?php echo nl2br(htmlspecialchars($commit['subject'])); ?>
                            </div>
                            
                            <?php if (!empty($commit['body'])): ?>
                                <div class="commit-body">
                                    <?php echo nl2br(htmlspecialchars($commit['body'])); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="commit-author">
                                by <?php echo htmlspecialchars($commit['author']); ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="no-changelog">
            <p>Changelog is not available yet.</p>
            <p style="font-size: 0.9rem; margin-top: 0.5rem;">Run <code>php scripts/generate-changelog.php</code> to generate it.</p>
        </div>
    <?php endif; ?>
</div>
