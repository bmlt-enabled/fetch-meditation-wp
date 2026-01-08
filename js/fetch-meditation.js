jQuery(document).ready(function($) {
    const bookSelect = $('#fetch_meditation_book');
    const jftLanguageSelect = $('#fetch_meditation_jft_language');
    const spadLanguageSelect = $('#fetch_meditation_spad_language');
    const jftLanguageContainer = $('#jft-language-container');
    const spadLanguageContainer = $('#spad-language-container');
    const timezoneContainer = $('#timezone-container');
    const tabsLayoutContainer = $('#tabs-layout-container');

    if (!bookSelect.length) return;

    function updateLanguageVisibility() {
        const selectedBook = bookSelect.val();
        
        // Hide both containers first
        jftLanguageContainer.hide();
        spadLanguageContainer.hide();
        tabsLayoutContainer.hide();
        
        // Show the appropriate container
        if (selectedBook === 'jft') {
            jftLanguageContainer.show();
        } else if (selectedBook === 'spad') {
            spadLanguageContainer.show();
        } else if (selectedBook === 'both') {
            // For 'both', show both language containers and tabs layout
            jftLanguageContainer.show();
            spadLanguageContainer.show();
            tabsLayoutContainer.show();
        }
        
        // Update timezone visibility based on current selections
        updateTimezoneVisibility();
    }
    
    function updateTimezoneVisibility() {
        const selectedBook = bookSelect.val();
        let showTimezone = false;
        
        if (selectedBook === 'jft' && jftLanguageSelect.val() === 'english') {
            showTimezone = true;
        } else if (selectedBook === 'spad' && spadLanguageSelect.val() === 'english') {
            showTimezone = true;
        }
        
        if (showTimezone) {
            timezoneContainer.show();
        } else {
            timezoneContainer.hide();
        }
    }

    // Initial update
    updateLanguageVisibility();
    
    // Listen for changes
    bookSelect.on('change', updateLanguageVisibility);
    jftLanguageSelect.on('change', updateTimezoneVisibility);
    spadLanguageSelect.on('change', updateTimezoneVisibility);
});
