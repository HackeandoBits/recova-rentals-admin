// Navbar Hover Behavior for Filament Admin (Global Delegation)
// Version 7: Dropdown Handoff Strategy

console.log('Recova Navbar Hover V7 Loaded');

document.addEventListener('mouseover', (e) => {

    // Find the closest topbar active item
    const item = e.target.closest('.fi-topbar-item') || e.target.closest('.fi-nav-group');
    if (!item) return;

    if (item.dataset.hoverAttached === 'true') return;
    item.dataset.hoverAttached = 'true';

    const button = item.querySelector('button');
    if (!button) return;

    let closeTimeout;

    const openMenu = () => {
        if (closeTimeout) clearTimeout(closeTimeout);

        const isExpanded = button.getAttribute('aria-expanded') === 'true';
        if (!isExpanded) {
            button.click();
        }
    };

    const forceClose = () => {
        // console.log('Hover: Force closing...');
        document.body.dispatchEvent(new MouseEvent('mousedown', { bubbles: true, cancelable: true, view: window }));
        document.body.dispatchEvent(new MouseEvent('mouseup', { bubbles: true, cancelable: true, view: window }));
        document.body.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
    }

    const closeMenu = () => {
        closeTimeout = setTimeout(() => {
            // Check if mouse is over a dropdown panel (Filament uses .fi-dropdown-panel)
            // We use the :hover pseudo-class to find currently hovered elements
            const hoveredElements = document.querySelectorAll(':hover');
            const isHoveringDropdown = Array.from(hoveredElements).some(el =>
                el.classList.contains('fi-dropdown-panel') ||
                el.classList.contains('fi-dropdown-content') // Fallback class
            );

            if (isHoveringDropdown) {
                // User moved to the dropdown. Do NOT close yet.
                // console.log('Hover: Saved by the dropdown!');

                // Find the dropdown being hovered to attach a leave listener
                const dropdown = Array.from(hoveredElements).find(el =>
                    el.classList.contains('fi-dropdown-panel') ||
                    el.classList.contains('fi-dropdown-content')
                );

                if (dropdown && !dropdown.dataset.hoverCloseAttached) {
                    dropdown.dataset.hoverCloseAttached = 'true';
                    dropdown.addEventListener('mouseleave', () => {
                        // When leaving the dropdown, user might go back to trigger OR leave completely.
                        // We recycle the closeMenu logic with its delay to handle "back to trigger" case.
                        closeMenu();
                        delete dropdown.dataset.hoverCloseAttached;
                    }, { once: true });
                }
                return;
            }

            // If we are not on the trigger (mouseleave fired) AND not on the dropdown:
            forceClose();

        }, 500);
    };

    item.addEventListener('mouseenter', openMenu);
    item.addEventListener('mouseleave', closeMenu);
});
