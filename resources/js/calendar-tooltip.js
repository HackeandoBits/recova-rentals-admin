 // Global Calendar Tooltip for FullCalendar Events
document.addEventListener('DOMContentLoaded', () => {
    const tooltip = document.createElement('div');
    tooltip.id = 'rr-global-tooltip';
    Object.assign(tooltip.style, {
        position: 'fixed',
        background: '#1e293b', // Slate 800
        color: '#ffffff',
        padding: '6px 10px',
        borderRadius: '4px',
        fontSize: '12px',
        zIndex: '99999',
        pointerEvents: 'none',
        display: 'none',
        boxShadow: '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
        border: '1px solid #334155'
    });
    document.body.appendChild(tooltip);

    const showTooltip = (e, text) => {
        tooltip.innerText = text;
        tooltip.style.display = 'block'; 
        moveTooltip(e);
    };

    const moveTooltip = (e) => {
        const x = e.clientX + 15;
        const y = e.clientY + 15;
        
        // Prevent overflow (basic logic)
        tooltip.style.left = `${Math.min(x, window.innerWidth - tooltip.offsetWidth - 10)}px`;
        tooltip.style.top = `${Math.min(y, window.innerHeight - tooltip.offsetHeight - 10)}px`;
    };

    const hideTooltip = () => {
        tooltip.style.display = 'none';
    };

    document.addEventListener('mouseover', (e) => {
        const eventEl = e.target.closest('.fc-event');
        if (eventEl) {
            // Try to find title from various sources
            let title = eventEl.getAttribute('title') || 
                        eventEl.querySelector('.fc-event-title')?.innerText ||
                        eventEl.innerText;
            
            if (title) {
                // Strip emoji if needed or keep it
                // title = title.replace(/^[\u{1F000}-\u{1F9FF}] /u, ''); 
                
                showTooltip(e, title);
                
                // Remove native title to prevent double tooltip
                if (eventEl.hasAttribute('title')) {
                    eventEl.setAttribute('data-original-title', title);
                    eventEl.removeAttribute('title');
                }
            }
        }
    });

    document.addEventListener('mousemove', (e) => {
        if (tooltip.style.display === 'block') {
            moveTooltip(e);
        }
    });

    document.addEventListener('mouseout', (e) => {
        const eventEl = e.target.closest('.fc-event');
        if (eventEl) {
            hideTooltip();
            // Restore native title if we removed it
            const originalTitle = eventEl.getAttribute('data-original-title');
            if (originalTitle) {
                eventEl.setAttribute('title', originalTitle);
                eventEl.removeAttribute('data-original-title');
            }
        }
    });
});
