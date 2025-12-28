<?php

/**
 * Community detail page template
 */

// Get community ID from URL
$communityId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$communityId) {
    redirect('communities');
}

// Get community data
$community = getCommunityById($communityId);
if (!$community) {
    setFlashMessage('Community not found', 'error');
    redirect('communities');
}

// Get current user and membership status
$currentUser = isLoggedIn() ? getCurrentUser() : null;
$isMember = $currentUser ? isUserInCommunity($currentUser['id'], $communityId) : false;
$memberCount = getCommunityMemberCount($communityId);

// Get flash message if any
$flashMessage = showFlashMessage();
?>

<div class="page-header">
    <div class="container">
        <h1>🏘️ <?php echo escape($community['full_name']); ?></h1>
        <p class="page-subtitle"><?php echo escape($community['short_name']); ?></p>
    </div>
</div>

<div class="content-section">
    <div class="container">
        <?php if ($flashMessage) : ?>
            <div class="alert alert-<?php echo escape($flashMessage['type']); ?>">
                <?php echo escape($flashMessage['text']); ?>
            </div>
        <?php endif; ?>

        <div class="community-detail">
            <div class="community-info">
                <div class="info-section">
                    <h3>About</h3>
                    <p><?php echo nl2br(escape($community['description'] ?? 'No description available.')); ?></p>
                </div>

                <div class="info-section">
                    <h3>Details</h3>
                    <ul class="community-stats">
                        <li><strong>Members:</strong> <?php echo escape($memberCount); ?></li>
                        <li><strong>Owner:</strong> 
                            <?php if ($community['owner_name']): ?>
                                <a href="/?page=user-listings&id=<?php echo escape($community['owner_id']); ?>" class="owner-link">
                                    <?php echo escape($community['owner_name']); ?>
                                </a>
                            <?php else: ?>
                                <span style="color: #999; font-style: italic;">Unknown</span>
                            <?php endif; ?>
                        </li>
                        <li><strong>Created:</strong> 
                            <?php 
                            if ($community['created_at']) {
                                $date = new DateTime($community['created_at']);
                                echo escape($date->format('F j, Y'));
                            } else {
                                echo '-';
                            }
                            ?>
                        </li>
                    </ul>
                </div>

                <?php if ($currentUser): ?>
                <div class="info-section">
                    <button id="membershipBtn" 
                            class="btn <?php echo $isMember ? 'btn-secondary' : 'btn-primary'; ?>" 
                            onclick="toggleMembership(<?php echo $communityId; ?>, <?php echo $isMember ? 'true' : 'false'; ?>)">
                        <?php echo $isMember ? '✓ Leave Community' : '+ Join Community'; ?>
                    </button>
                </div>
                <?php else: ?>
                <div class="info-section">
                    <p class="login-prompt">
                        <a href="/?page=login" class="btn btn-primary">Log in to join this community</a>
                    </p>
                </div>
                <?php endif; ?>
            </div>

            <div class="community-actions">
                <a href="/?page=communities" class="btn btn-secondary">← Back to Communities</a>
            </div>
        </div>
    </div>
</div>

<style>
.community-detail {
    max-width: 800px;
    margin: 0 auto;
}

.community-info {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    margin-bottom: 1.5rem;
}

.info-section {
    margin-bottom: 2rem;
}

.info-section:last-child {
    margin-bottom: 0;
}

.info-section h3 {
    margin-top: 0;
    margin-bottom: 1rem;
    color: #333;
    font-size: 1.25rem;
}

.info-section p {
    color: #666;
    line-height: 1.6;
}

.community-stats {
    list-style: none;
    padding: 0;
    margin: 0;
}

.community-stats li {
    padding: 0.5rem 0;
    border-bottom: 1px solid #f0f0f0;
}

.community-stats li:last-child {
    border-bottom: none;
}

.community-stats strong {
    color: #333;
    margin-right: 0.5rem;
}

.community-actions {
    text-align: center;
}

.btn {
    display: inline-block;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 6px;
    font-weight: 500;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 1rem;
}

.btn-primary {
    background: #007bff;
    color: white;
}

.btn-primary:hover {
    background: #0056b3;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #545b62;
}

.owner-link {
    color: #007bff;
    text-decoration: none;
    font-weight: 500;
}

.owner-link:hover {
    text-decoration: underline;
}

.login-prompt {
    text-align: center;
    padding: 1rem;
}

@media (max-width: 768px) {
    .community-info {
        padding: 1.5rem;
    }
}
</style>

<script>
function toggleMembership(communityId, isMember) {
    const btn = document.getElementById('membershipBtn');
    const action = isMember ? 'leave' : 'join';
    
    btn.disabled = true;
    btn.textContent = isMember ? 'Leaving...' : 'Joining...';
    
    fetch('/', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=' + encodeURIComponent(action) + '&community_id=' + encodeURIComponent(communityId)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload the page to update membership status
            window.location.reload();
        } else {
            alert('Error: ' + (data.message || 'Unknown error'));
            btn.disabled = false;
            btn.textContent = isMember ? '✓ Leave Community' : '+ Join Community';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating membership');
        btn.disabled = false;
        btn.textContent = isMember ? '✓ Leave Community' : '+ Join Community';
    });
}
</script>

