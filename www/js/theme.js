(function () {
  // Retrieve saved theme preference or default to light
  const savedTheme = localStorage.getItem('theme') || 'light';
  
  // Apply theme immediately to html element to prevent FOUC
  document.documentElement.setAttribute('data-theme', savedTheme);

  // Helper function to update toggle button appearance
  function updateToggleButtons(theme) {
    const toggleBtns = document.querySelectorAll('#themeToggleBtn, .theme-toggle-btn');
    toggleBtns.forEach(btn => {
      if (theme === 'dark') {
        btn.classList.add('dark-active');
        btn.setAttribute('aria-label', 'Switch to Light Mode');
        btn.setAttribute('title', 'Switch to Light Mode');
      } else {
        btn.classList.remove('dark-active');
        btn.setAttribute('aria-label', 'Switch to Dark Mode');
        btn.setAttribute('title', 'Switch to Dark Mode');
      }
    });
  }

  // Function to toggle theme
  window.toggleDarkMode = function () {
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    updateToggleButtons(newTheme);

    // Dispatch custom event for dynamic components (e.g. Chart.js)
    window.dispatchEvent(new CustomEvent('themeChange', { detail: { theme: newTheme } }));
  };

  // Attach event listener once DOM is ready
  document.addEventListener('DOMContentLoaded', function () {
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
    updateToggleButtons(currentTheme);

    document.querySelectorAll('#themeToggleBtn, .theme-toggle-btn').forEach(btn => {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        window.toggleDarkMode();
      });
    });
  });
})();
