/**
 * Standardized Notification System JS
 * Include this file on all pages with notifications
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize notification dropdown toggle
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationDropdown = document.getElementById('notificationDropdown');
    
    if (notificationBtn && notificationDropdown) {
        // Toggle dropdown visibility
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationDropdown.classList.toggle('active');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!notificationBtn.contains(e.target) && !notificationDropdown.contains(e.target)) {
                notificationDropdown.classList.remove('active');
            }
        });

        // Mark notification as read when clicked
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', function() {
                const notificationId = this.dataset.id;
                if (this.classList.contains('unread')) {
                    fetch('mark_notification_read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ id: notificationId }),
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.classList.remove('unread');
                            updateBadgeCount();
                        }
                    })
                    .catch(error => console.error('Error:', error));
                }
            });
        });

        // Mark all as read functionality
        const markAllRead = document.getElementById('markAllRead');
        if (markAllRead) {
            markAllRead.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                fetch('mark_all_notifications_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ user_id: document.body.dataset.userId }),
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.querySelectorAll('.notification-item.unread').forEach(item => {
                            item.classList.remove('unread');
                        });
                        updateBadgeCount(true);
                    }
                })
                .catch(error => console.error('Error:', error));
            });
        }

        // Helper function to update badge count
        function updateBadgeCount(removeAll = false) {
            const badge = document.querySelector('.notification-badge');
            if (badge) {
                if (removeAll) {
                    badge.remove();
                } else {
                    const unreadCount = document.querySelectorAll('.notification-item.unread').length;
                    if (unreadCount > 0) {
                        badge.textContent = unreadCount;
                    } else {
                        badge.remove();
                    }
                }
            }
        }
    }
});