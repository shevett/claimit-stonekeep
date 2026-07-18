<?php

/**
 * Admin activity log page template
 */

if (!isLoggedIn()) {
    redirect('login');
}

if (!isAdmin()) {
    setFlashMessage('You do not have permission to access this page', 'error');
    redirect('home');
}

$activityRows = getActivityLog();
$actionLabels = getActivityActionLabels();

$userMap = [];
foreach (getAllUsers() as $user) {
    $userMap[$user['id']] = $user['display_name'] ?: $user['name'];
}

$communityMap = [];
foreach (getAllCommunities() as $community) {
    $communityMap[$community['id']] = $community['full_name'];
}

$itemTitleCache = [];
$getItemTitle = function ($itemId) use (&$itemTitleCache) {
    if (!array_key_exists($itemId, $itemTitleCache)) {
        $item = getItemFromDb($itemId);
        $itemTitleCache[$itemId] = $item ? $item['title'] : null;
    }
    return $itemTitleCache[$itemId];
};

$flashMessage = showFlashMessage();
?>

<div class="page-header">
    <div class="container">
        <h1>📜 Activity Log</h1>
        <p class="page-subtitle">Recent activity across the site</p>
    </div>
</div>

<div class="content-section">
    <div class="container">
        <?php if ($flashMessage) : ?>
            <div class="alert alert-<?php echo escape($flashMessage['type']); ?>">
                <?php echo escape($flashMessage['text']); ?>
            </div>
        <?php endif; ?>

        <div class="action-bar">
            <a href="?page=admin" class="btn btn-secondary">← Back to Admin</a>
        </div>

        <div class="activity-log-section">
            <div class="table-container">
                <table class="activity-table">
                    <thead>
                        <tr>
                            <th class="sortable" data-col="0">Timestamp <span class="sort-indicator"></span></th>
                            <th class="sortable" data-col="1">Action <span class="sort-indicator"></span></th>
                            <th class="sortable" data-col="2">User <span class="sort-indicator"></span></th>
                            <th class="sortable" data-col="3">Item <span class="sort-indicator"></span></th>
                            <th class="sortable" data-col="4">Community <span class="sort-indicator"></span></th>
                            <th>IP</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($activityRows)) : ?>
                            <tr>
                                <td colspan="7" class="no-data">No activity recorded yet</td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($activityRows as $row) : ?>
                                <tr>
                                    <td class="date-cell" data-sort-value="<?php echo $row['occurred_at'] ? strtotime($row['occurred_at']) : 0; ?>">
                                        <?php
                                        if ($row['occurred_at']) {
                                            $date = new DateTime($row['occurred_at']);
                                            echo escape($date->format('M j, Y g:i A'));
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo escape($actionLabels[$row['action']] ?? $row['action']); ?></td>
                                    <td><?php echo $row['user_id'] ? escape($userMap[$row['user_id']] ?? $row['user_id']) : '-'; ?></td>
                                    <td>
                                        <?php if ($row['item_id']) : ?>
                                            <a href="?page=item&id=<?php echo escape($row['item_id']); ?>">
                                                <?php echo escape($getItemTitle($row['item_id']) ?? $row['item_id']); ?>
                                            </a>
                                        <?php else : ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $row['community_id'] ? escape($communityMap[$row['community_id']] ?? $row['community_id']) : '-'; ?></td>
                                    <td class="ip-cell"><?php echo escape($row['ip_address'] ?? '-'); ?></td>
                                    <td class="details-cell">
                                        <?php
                                        if ($row['details']) {
                                            $details = json_decode($row['details'], true);
                                            echo $details ? escape(json_encode($details)) : '';
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="activity-stats">
                <strong>Showing:</strong> <?php echo count($activityRows); ?> most recent events
            </div>
        </div>
    </div>
</div>

<style>
.action-bar {
    max-width: 1400px;
    margin: 0 auto 1.5rem;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 6px;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #545b62;
}

.activity-log-section {
    max-width: 1400px;
    margin: 0 auto;
}

.table-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    overflow-x: auto;
}

.activity-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}

.activity-table thead {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
}

.activity-table th {
    padding: 1rem 0.75rem;
    text-align: left;
    font-weight: 600;
    color: #495057;
    white-space: nowrap;
}

.activity-table td {
    padding: 0.875rem 0.75rem;
    border-bottom: 1px solid #f1f3f4;
    color: #333;
}

.activity-table tbody tr:hover {
    background: #f8f9fa;
}

.activity-table tbody tr:last-child td {
    border-bottom: none;
}

.date-cell {
    color: #666;
    font-size: 0.85rem;
    white-space: nowrap;
}

.ip-cell {
    color: #666;
    font-size: 0.85rem;
    white-space: nowrap;
}

.details-cell {
    color: #666;
    font-size: 0.8rem;
    max-width: 300px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.no-data {
    text-align: center;
    color: #999;
    padding: 2rem !important;
    font-style: italic;
}

.activity-stats {
    padding: 1rem;
    background: white;
    border-radius: 0 0 12px 12px;
    border-top: 1px solid #e9ecef;
    color: #666;
}

.activity-table th.sortable {
    cursor: pointer;
    user-select: none;
}

.activity-table th.sortable:hover {
    background: #e9ecef;
}

.sort-indicator {
    display: inline-block;
    width: 1em;
    text-align: center;
    font-size: 0.75em;
    color: #adb5bd;
}

.activity-table th.sort-asc .sort-indicator::after {
    content: '▲';
    color: #495057;
}

.activity-table th.sort-desc .sort-indicator::after {
    content: '▼';
    color: #495057;
}

@media (max-width: 768px) {
    .activity-table {
        font-size: 0.8rem;
    }

    .activity-table th,
    .activity-table td {
        padding: 0.5rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    initTableSort();
});

function initTableSort() {
    const table = document.querySelector('.activity-table');
    if (!table) return;

    const headers = table.querySelectorAll('th.sortable');
    let currentCol = 0; // Timestamp
    let currentAsc = false; // descending by default

    headers.forEach(th => {
        th.addEventListener('click', function() {
            const col = parseInt(this.dataset.col);
            if (col === currentCol) {
                currentAsc = !currentAsc;
            } else {
                currentCol = col;
                currentAsc = true;
            }
            sortTable(table, currentCol, currentAsc);
            headers.forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
            this.classList.add(currentAsc ? 'sort-asc' : 'sort-desc');
        });
    });

    // Apply default sort
    sortTable(table, currentCol, currentAsc);
    headers[currentCol].classList.add('sort-desc');
}

function sortTable(table, col, asc) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));

    // Skip the "no data" row
    if (rows.length === 1 && rows[0].querySelector('.no-data')) return;

    rows.sort((a, b) => {
        const cellA = a.querySelectorAll('td')[col];
        const cellB = b.querySelectorAll('td')[col];
        if (!cellA || !cellB) return 0;

        const sortValA = cellA.dataset.sortValue;
        const sortValB = cellB.dataset.sortValue;

        let valA, valB;
        if (sortValA !== undefined && sortValB !== undefined) {
            valA = parseFloat(sortValA);
            valB = parseFloat(sortValB);
        } else {
            valA = cellA.textContent.trim().toLowerCase();
            valB = cellB.textContent.trim().toLowerCase();
        }

        if (valA < valB) return asc ? -1 : 1;
        if (valA > valB) return asc ? 1 : -1;
        return 0;
    });

    rows.forEach(row => tbody.appendChild(row));
}
</script>
