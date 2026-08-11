(() => {
    if (window.__workdeskNotificationAutoCloseInstalled) {
        return;
    }

    window.__workdeskNotificationAutoCloseInstalled = true;

    document.addEventListener(
        'click',
        (event) => {
            const action = event.target.closest?.(
                '#database-notifications .fi-no-notification-actions a[href]',
            );

            if (!action) {
                return;
            }

            window.dispatchEvent(
                new CustomEvent('close-modal', {
                    detail: {
                        id: 'database-notifications',
                    },
                }),
            );
        },
        true,
    );
})();
