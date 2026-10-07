<script>
    document.addEventListener('DOMContentLoaded', () => {
        const initSidebarAccordion = () => {
            const store = window.Alpine?.store('sidebar');
            if (!store) {
                return;
            }

            const getAllGroupLabels = () => {
                return Array.from(document.querySelectorAll('.fi-sidebar-group[data-group-label]'))
                    .map(el => el.dataset.groupLabel)
                    .filter(Boolean);
            };

            const getActiveGroupLabel = () => {
                const activeGroup = document.querySelector('.fi-sidebar-group.fi-active[data-group-label]');
                return activeGroup ? activeGroup.dataset.groupLabel : null;
            };

            const allGroups = getAllGroupLabels();
            const activeGroup = getActiveGroupLabel();

            if (allGroups.length > 0) {
                // Ensure all groups start collapsed except the active one
                store.collapsedGroups = allGroups.filter(label => label !== activeGroup);
                try {
                    localStorage.setItem('collapsedGroups', JSON.stringify(store.collapsedGroups));
                } catch (e) {}
            }

            // Hook accordion behavior: opening one section auto-collapses all others
            if (!store._accordionHooked) {
                store._accordionHooked = true;

                store.toggleCollapsedGroup = function(clickedGroup) {
                    const isCurrentlyCollapsed = this.groupIsCollapsed(clickedGroup);
                    const currentAllGroups = getAllGroupLabels();

                    if (isCurrentlyCollapsed) {
                        // Open clicked section, collapse all other sections
                        this.collapsedGroups = currentAllGroups.filter(g => g !== clickedGroup);
                    } else {
                        // Close clicked section if already open
                        if (!this.collapsedGroups.includes(clickedGroup)) {
                            this.collapsedGroups = this.collapsedGroups.concat(clickedGroup);
                        }
                    }

                    try {
                        localStorage.setItem('collapsedGroups', JSON.stringify(this.collapsedGroups));
                    } catch (e) {}
                };
            }
        };

        if (window.Alpine) {
            initSidebarAccordion();
        } else {
            document.addEventListener('alpine:init', initSidebarAccordion);
        }

        document.addEventListener('livewire:navigated', initSidebarAccordion);
    });
</script>
