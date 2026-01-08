(function() {
    'use strict';
    
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTabs);
    } else {
        initTabs();
    }
    
    function initTabs() {
        // Initialize tab containers
        const tabContainers = document.querySelectorAll('.meditation-tabs-container');
        tabContainers.forEach(function(container) {
            initTabContainer(container);
        });
        
        // Initialize accordion containers
        const accordionContainers = document.querySelectorAll('.meditation-accordion-container');
        accordionContainers.forEach(function(container) {
            initAccordionContainer(container);
        });
    }
    
    function initTabContainer(container) {
        const tabs = container.querySelectorAll('.meditation-tab-button');
        const panels = container.querySelectorAll('.meditation-tab-panel');
        const storageKey = 'meditation-active-tab-' + container.getAttribute('data-instance-id');
        
        if (tabs.length === 0 || panels.length === 0) return;
        
        // Try to restore last selected tab from localStorage
        let activeTabId = localStorage.getItem(storageKey);
        if (!activeTabId || !container.querySelector('[data-tab-id="' + activeTabId + '"]')) {
            activeTabId = tabs[0].getAttribute('data-tab-id');
        }
        
        // Set up click handlers
        tabs.forEach(function(tab) {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                activateTab(container, tab.getAttribute('data-tab-id'), storageKey);
            });
            
            // Keyboard accessibility
            tab.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    activateTab(container, tab.getAttribute('data-tab-id'), storageKey);
                } else if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
                    e.preventDefault();
                    const currentIndex = Array.from(tabs).indexOf(tab);
                    let newIndex;
                    
                    if (e.key === 'ArrowLeft') {
                        newIndex = currentIndex > 0 ? currentIndex - 1 : tabs.length - 1;
                    } else {
                        newIndex = currentIndex < tabs.length - 1 ? currentIndex + 1 : 0;
                    }
                    
                    tabs[newIndex].focus();
                    activateTab(container, tabs[newIndex].getAttribute('data-tab-id'), storageKey);
                }
            });
        });
        
        // Activate initial tab
        activateTab(container, activeTabId, storageKey);
    }
    
    function initAccordionContainer(container) {
        const buttons = container.querySelectorAll('.meditation-accordion-button');
        
        buttons.forEach(function(button) {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                toggleAccordion(button);
            });
            
            // Keyboard accessibility
            button.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggleAccordion(button);
                }
            });
        });
    }
    
    function activateTab(container, tabId, storageKey) {
        const tabs = container.querySelectorAll('.meditation-tab-button');
        const panels = container.querySelectorAll('.meditation-tab-panel');
        
        // Update tabs
        tabs.forEach(function(tab) {
            if (tab.getAttribute('data-tab-id') === tabId) {
                tab.classList.add('active');
                tab.setAttribute('aria-selected', 'true');
                tab.setAttribute('tabindex', '0');
            } else {
                tab.classList.remove('active');
                tab.setAttribute('aria-selected', 'false');
                tab.setAttribute('tabindex', '-1');
            }
        });
        
        // Update panels
        panels.forEach(function(panel) {
            if (panel.getAttribute('data-tab-id') === tabId) {
                panel.classList.add('active');
                panel.removeAttribute('hidden');
            } else {
                panel.classList.remove('active');
                panel.setAttribute('hidden', 'hidden');
            }
        });
        
        // Save to localStorage
        try {
            localStorage.setItem(storageKey, tabId);
        } catch (e) {
            // Ignore localStorage errors (e.g., private browsing)
        }
    }
    
    function toggleAccordion(button) {
        const isActive = button.classList.contains('active');
        const panel = button.nextElementSibling;
        
        if (isActive) {
            // Collapse
            button.classList.remove('active');
            button.setAttribute('aria-expanded', 'false');
            panel.classList.remove('active');
            panel.setAttribute('hidden', 'hidden');
        } else {
            // Expand
            button.classList.add('active');
            button.setAttribute('aria-expanded', 'true');
            panel.classList.add('active');
            panel.removeAttribute('hidden');
        }
    }
})();
