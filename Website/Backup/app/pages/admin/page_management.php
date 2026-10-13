<?php
Auth::requireAdmin();
$db = Database::getInstance();

if (isPost()) {
    if (!verifyCsrf(post('csrf_token'))) { flash('error', 'Invalid request.'); redirect(pageUrl('admin.page_management')); }
    
    $action = post('action');
    
    if ($action === 'toggle') {
        $id = (int)post('id');
        $db->execute("UPDATE page_management SET is_enabled = NOT is_enabled, updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$id]);
        flash('success', 'Page status updated.');
        redirect(pageUrl('admin.page_management'));
    }
    
    if ($action === 'reorder') {
        $order = post('order', []);
        foreach ($order as $index => $id) {
            $db->execute("UPDATE page_management SET sort_order = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?", [$index, (int)$id]);
        }
        flash('success', 'Page order updated.');
        redirect(pageUrl('admin.page_management'));
    }
    
    if ($action === 'update_modal') {
        $modalTitle = sanitize(post('modal_title', 'Coming Soon!'));
        $modalMessage = sanitize(post('modal_message', 'This page is currently under construction. We\'re working hard to bring you something amazing!'));
        $modalIcon = sanitize(post('modal_icon', 'fa-clock'));
        $modalIconColor = sanitize(post('modal_icon_color', 'text-yellow-500'));
        $modalButtonHome = sanitize(post('modal_button_home', 'Back to Home'));
        $modalButtonClose = sanitize(post('modal_button_close', 'Close'));
        $modalButtonLoafClub = sanitize(post('modal_button_loaf_club', 'Join the Loaf Club'));
        $modalShowLoafClubButton = post('modal_show_loaf_club_button') ? 1 : 0;
        
        $db->execute("INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)", ['coming_soon_title', $modalTitle]);
        $db->execute("INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)", ['coming_soon_message', $modalMessage]);
        $db->execute("INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)", ['coming_soon_icon', $modalIcon]);
        $db->execute("INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)", ['coming_soon_icon_color', $modalIconColor]);
        $db->execute("INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)", ['coming_soon_button_home', $modalButtonHome]);
        $db->execute("INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)", ['coming_soon_button_close', $modalButtonClose]);
        $db->execute("INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)", ['coming_soon_button_loaf_club', $modalButtonLoafClub]);
        $db->execute("INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)", ['coming_soon_show_loaf_club_button', $modalShowLoafClubButton]);
        
        flash('success', 'Coming Soon modal updated.');
        redirect(pageUrl('admin.page_management'));
    }
}

$pages = $db->fetchAll("SELECT * FROM page_management ORDER BY sort_order");

ob_start();
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-800">Page Management</h1>
    <p class="text-gray-500 text-sm mt-1">Enable/disable pages and manage their visibility</p>
</div>

<div class="max-w-4xl">
    <div class="card">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="font-bold text-gray-700">Store Pages</h2>
                <div class="text-sm text-gray-500">
                    <i class="fa-solid fa-info-circle mr-1"></i>
                    Drag to reorder • Toggle to enable/disable
                </div>
            </div>
        </div>
        
        <div id="pagesList" class="divide-y divide-gray-200">
            <?php foreach ($pages as $page): ?>
            <div class="p-4 hover:bg-gray-50 transition-colors" data-id="<?= $page['id'] ?>">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4 flex-1">
                        <div class="cursor-move text-gray-400 hover:text-gray-600">
                            <i class="fa-solid fa-grip-vertical"></i>
                        </div>
                        <div class="flex-1">
                            <div class="font-semibold text-gray-800"><?= e($page['page_name']) ?></div>
                            <div class="text-sm text-gray-500">
                                <span class="font-mono bg-gray-100 px-2 py-0.5 rounded text-xs"><?= e($page['page_key']) ?></span>
                                <span class="mx-2">→</span>
                                <span class="text-blue-600"><?= pageUrl($page['page_url']) ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <form method="POST" action="<?= pageUrl('admin.page_management') ?>" class="inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $page['id'] ?>">
                            <button type="submit" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-brown focus:ring-offset-2 <?= $page['is_enabled'] ? 'bg-green-500' : 'bg-gray-300' ?>">
                                <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform <?= $page['is_enabled'] ? 'translate-x-6' : 'translate-x-1' ?>"></span>
                            </button>
                        </form>
                        <span class="badge <?= $page['is_enabled'] ? 'badge-green' : 'badge-gray' ?>">
                            <?= $page['is_enabled'] ? 'Enabled' : 'Disabled' ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="card p-6 mt-6">
        <h3 class="font-bold text-gray-700 mb-4">Page Status Effects</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="border border-green-200 rounded-lg p-4 bg-green-50">
                <div class="flex items-center gap-2 mb-2">
                    <i class="fa-solid fa-toggle-on text-green-500 text-xl"></i>
                    <span class="font-semibold text-green-800">Enabled Pages</span>
                </div>
                <p class="text-sm text-green-700">Pages are fully accessible and functional. Navigation links work normally.</p>
            </div>
            <div class="border border-yellow-200 rounded-lg p-4 bg-yellow-50">
                <div class="flex items-center gap-2 mb-2">
                    <i class="fa-solid fa-toggle-off text-yellow-500 text-xl"></i>
                    <span class="font-semibold text-yellow-800">Disabled Pages</span>
                </div>
                <p class="text-sm text-yellow-700">Pages show "Coming Soon!" modal with blurred background. Navigation links remain visible but inactive.</p>
            </div>
        </div>
    </div>

    <!-- Coming Soon Modal Customization -->
    <div class="card p-6 mt-6">
        <h3 class="font-bold text-gray-700 mb-4">Coming Soon Modal Customization</h3>
        <form method="POST" action="<?= pageUrl('admin.page_management') ?>">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="update_modal">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="form-label">Modal Title</label>
                    <input type="text" name="modal_title" class="form-input" value="<?= e(setting('coming_soon_title', 'Coming Soon!')) ?>" placeholder="Coming Soon!">
                </div>
                <div>
                    <label class="form-label">Modal Icon</label>
                    <div class="flex gap-2">
                        <select name="modal_icon" class="form-input flex-1">
                            <option value="fa-clock" <?= setting('coming_soon_icon') === 'fa-clock' ? 'selected' : '' ?>>Clock</option>
                            <option value="fa-hammer" <?= setting('coming_soon_icon') === 'fa-hammer' ? 'selected' : '' ?>>Hammer</option>
                            <option value="fa-tools" <?= setting('coming_soon_icon') === 'fa-tools' ? 'selected' : '' ?>>Tools</option>
                            <option value="fa-construction" <?= setting('coming_soon_icon') === 'fa-construction' ? 'selected' : '' ?>>Construction</option>
                            <option value="fa-hourglass-half" <?= setting('coming_soon_icon') === 'fa-hourglass-half' ? 'selected' : '' ?>>Hourglass</option>
                            <option value="fa-calendar-days" <?= setting('coming_soon_icon') === 'fa-calendar-days' ? 'selected' : '' ?>>Calendar</option>
                            <option value="fa-sparkles" <?= setting('coming_soon_icon') === 'fa-sparkles' ? 'selected' : '' ?>>Sparkles</option>
                            <option value="fa-rocket" <?= setting('coming_soon_icon') === 'fa-rocket' ? 'selected' : '' ?>>Rocket</option>
                        </select>
                        <select name="modal_icon_color" class="form-input w-32">
                            <option value="text-yellow-500" <?= setting('coming_soon_icon_color') === 'text-yellow-500' ? 'selected' : '' ?>>Yellow</option>
                            <option value="text-blue-500" <?= setting('coming_soon_icon_color') === 'text-blue-500' ? 'selected' : '' ?>>Blue</option>
                            <option value="text-green-500" <?= setting('coming_soon_icon_color') === 'text-green-500' ? 'selected' : '' ?>>Green</option>
                            <option value="text-red-500" <?= setting('coming_soon_icon_color') === 'text-red-500' ? 'selected' : '' ?>>Red</option>
                            <option value="text-purple-500" <?= setting('coming_soon_icon_color') === 'text-purple-500' ? 'selected' : '' ?>>Purple</option>
                            <option value="text-pink-500" <?= setting('coming_soon_icon_color') === 'text-pink-500' ? 'selected' : '' ?>>Pink</option>
                            <option value="text-gray-500" <?= setting('coming_soon_icon_color') === 'text-gray-500' ? 'selected' : '' ?>>Gray</option>
                        </select>
                    </div>
                </div>
                <div class="md:col-span-2">
                    <label class="form-label">Modal Message</label>
                    <textarea name="modal_message" rows="3" class="form-input" placeholder="This page is currently under construction. We're working hard to bring you something amazing!"><?= e(setting('coming_soon_message', 'This page is currently under construction. We\'re working hard to bring you something amazing!')) ?></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="form-label">Home Button Text</label>
                    <input type="text" name="modal_button_home" class="form-input" value="<?= e(setting('coming_soon_button_home', 'Back to Home')) ?>" placeholder="Back to Home">
                </div>
                <div class="md:col-span-2">
                    <label class="form-label">Loaf Club Button Text</label>
                    <input type="text" name="modal_button_loaf_club" class="form-input" value="<?= e(setting('coming_soon_button_loaf_club', 'Join the Loaf Club')) ?>" placeholder="Join the Loaf Club">
                </div>
                <div class="md:col-span-2">
                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="modal_show_loaf_club_button" value="1" id="showLoafClubButton" class="w-4 h-4 accent-brown" <?= setting('coming_soon_show_loaf_club_button', '1') ? 'checked' : '' ?>>
                        <label for="showLoafClubButton" class="form-label mb-0 cursor-pointer">Show "Join the Loaf Club" button</label>
                    </div>
                    <p class="text-warm text-xs mt-1">When enabled, shows a button to join the subscription club in the modal</p>
                </div>
            </div>
            
            <div class="mt-6">
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-floppy-disk mr-2"></i>Update Modal
                </button>
            </div>
        </form>
        
        <!-- Live Preview -->
        <div class="mt-6 pt-6 border-t border-gray-200">
            <h4 class="font-semibold text-gray-700 mb-4">Live Preview</h4>
            <div class="bg-gray-100 rounded-lg p-4">
                <div class="bg-white rounded-xl p-6 max-w-sm mx-auto text-center shadow-lg">
                    <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fa-solid <?= setting('coming_soon_icon', 'fa-clock') ?> <?= setting('coming_soon_icon_color', 'text-yellow-500') ?> text-xl"></i>
                    </div>
                    <h5 class="text-lg font-bold text-gray-800 mb-2"><?= e(setting('coming_soon_title', 'Coming Soon!')) ?></h5>
                    <p class="text-gray-600 text-sm mb-4"><?= e(setting('coming_soon_message', 'This page is currently under construction. We\'re working hard to bring you something amazing!')) ?></p>
                    <div class="space-y-2">
                        <?php if (setting('coming_soon_show_loaf_club_button', '1')): ?>
                        <button class="btn-primary w-full text-sm py-2">
                            <i class="fa-solid fa-crown mr-2"></i><?= e(setting('coming_soon_button_loaf_club', 'Join the Loaf Club')) ?>
                        </button>
                        <?php endif; ?>
                        <button class="btn-outline w-full text-sm py-2">
                            <i class="fa-solid fa-home mr-2"></i><?= e(setting('coming_soon_button_home', 'Back to Home')) ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const pagesList = document.getElementById('pagesList');
    let draggedItem = null;
    
    // Drag and drop functionality
    pagesList.addEventListener('dragstart', function(e) {
        if (e.target.classList.contains('hover:bg-gray-50')) {
            draggedItem = e.target;
            e.target.style.opacity = '0.5';
        }
    });
    
    pagesList.addEventListener('dragend', function(e) {
        if (e.target.classList.contains('hover:bg-gray-50')) {
            e.target.style.opacity = '';
        }
    });
    
    pagesList.addEventListener('dragover', function(e) {
        e.preventDefault();
        const afterElement = getDragAfterElement(pagesList, e.clientY);
        if (afterElement == null) {
            pagesList.appendChild(draggedItem);
        } else {
            pagesList.insertBefore(draggedItem, afterElement);
        }
    });
    
    pagesList.addEventListener('drop', function(e) {
        e.preventDefault();
        updateOrder();
    });
    
    function getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('.hover:bg-gray-50:not(.dragging)')];
        
        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }
    
    function updateOrder() {
        const items = pagesList.querySelectorAll('[data-id]');
        const order = [];
        
        items.forEach(item => {
            order.push(item.dataset.id);
        });
        
        // Send order to server
        fetch('<?= pageUrl('admin.page_management') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                'csrf_token': '<?= csrfToken() ?>',
                'action': 'reorder',
                'order': order
            })
        }).then(response => {
            if (response.ok) {
                // Success feedback
                const badge = document.createElement('div');
                badge.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
                badge.textContent = 'Order updated successfully';
                document.body.appendChild(badge);
                setTimeout(() => badge.remove(), 2000);
            }
        });
    }
    
    // Make items draggable
    const items = pagesList.querySelectorAll('.hover:bg-gray-50');
    items.forEach(item => {
        item.draggable = true;
        item.classList.add('cursor-move');
    });
});
</script>
<?php
$content = ob_get_clean();
renderLayout('Page Management — Admin', $content, ['admin' => true]);
