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

// Check if user wants to see gone items
$showGoneItems = false;
if ($currentUser) {
    $showGoneItems = getUserShowGoneItems($currentUser['id']);
}

// Get items for this community
$items = [];
try {
    // Pass the community ID to filter items
    $items = getAllItemsEfficiently($showGoneItems, $communityId);
} catch (Exception $e) {
    error_log("Error loading items for community: " . $e->getMessage());
    $items = [];
}

// Get flash message if any
$flashMessage = showFlashMessage();
?>

<div class="page-header">
    <div class="container">
        <div class="community-header">
            <div class="community-title">
                <h1>🏘️ <?php echo escape($community['full_name']); ?></h1>
            </div>
            <div class="community-header-actions">
                <?php if ($currentUser): ?>
                    <button id="membershipBtn" 
                            class="btn <?php echo $isMember ? 'btn-secondary' : 'btn-primary'; ?>" 
                            onclick="toggleMembership(<?php echo $communityId; ?>, <?php echo $isMember ? 'true' : 'false'; ?>)">
                        <?php echo $isMember ? '✓ Member' : '+ Join'; ?>
                    </button>
                <?php else: ?>
                    <a href="/?page=login" class="btn btn-primary">Log in to join</a>
                <?php endif; ?>
                <a href="/?page=communities" class="btn btn-secondary">← Back</a>
            </div>
        </div>
    </div>
</div>

<div class="content-section">
    <div class="container">
        <?php if ($flashMessage) : ?>
            <div class="alert alert-<?php echo escape($flashMessage['type']); ?>">
                <?php echo escape($flashMessage['text']); ?>
            </div>
        <?php endif; ?>

        <!-- Items Grid -->
        <?php if (empty($items)) : ?>
            <div class="no-items">
                <div class="empty-state">
                    <div class="empty-state-icon">📦</div>
                    <h3>No Items Yet</h3>
                    <p>This community doesn't have any items posted yet.</p>
                    <?php if ($currentUser): ?>
                        <a href="/?page=claim" class="btn btn-primary">Post the First Item</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else : ?>
            <div class="items-grid">
                <?php foreach ($items as $item) : ?>
                    <?php
                    // Set context for the unified template
                    $context = 'listing';
                    $isOwnListings = false;
                    ?>
                    <?php include __DIR__ . '/item-card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Override default page-header padding for tighter spacing */
.page-header {
    padding: 1.5rem 0 !important;
}

.community-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
}

.community-title h1 {
    margin: 0;
}

.community-header-actions {
    display: flex;
    gap: 0.5rem;
    flex-shrink: 0;
}

/* Items Grid */
.items-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 2rem;
    margin-top: 2rem;
}

.item-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: 1px solid #e9ecef;
}

.item-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.no-items {
    text-align: center;
    padding: 4rem 2rem;
}

.empty-state {
    max-width: 400px;
    margin: 0 auto;
}

.empty-state-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
}

.empty-state h3 {
    margin-bottom: 1rem;
    color: var(--gray-700, #333);
}

.empty-state p {
    color: var(--gray-500, #666);
    margin-bottom: 2rem;
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

@media (max-width: 768px) {
    .community-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    .community-header-actions {
        width: 100%;
        justify-content: space-between;
    }
    
    .items-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
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

