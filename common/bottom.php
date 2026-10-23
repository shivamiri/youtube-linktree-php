    </div> 
    <footer class="w-full max-w-2xl mt-12 pb-6 text-center text-gray-500 text-sm">
        <p>&copy; <?= date('Y') ?> <?= isset($settings['channel_name']) ? htmlspecialchars($settings['channel_name']) : 'SM YouTube Link Manager' ?>. All rights reserved.</p>
    </footer>

    <script>
        // 1. Disable Right Click
        document.addEventListener('contextmenu', event => event.preventDefault());

        // 2. Disable Keyboard Shortcuts
        document.addEventListener('keydown', function(e) {
            // Disable F12, Ctrl+Shift+I (Inspect), Ctrl+Shift+J (Console), Ctrl+Shift+C, Ctrl+U (View Source)
            if(e.keyCode === 123 || 
              (e.ctrlKey && e.shiftKey && (e.keyCode === 73 || e.keyCode === 74 || e.keyCode === 67)) || 
              (e.ctrlKey && e.keyCode === 85)) {
                e.preventDefault();
                return false;
            }
            // Disable Zoom: Ctrl+ / Ctrl- / Ctrl0
            if (e.ctrlKey && (e.key === '+' || e.key === '-' || e.key === '0' || e.key === '=')) {
                e.preventDefault();
            }
        });

        // 3. Disable Ctrl + Mouse Wheel Zoom
        document.addEventListener('wheel', function(e) {
            if (e.ctrlKey) {
                e.preventDefault();
            }
        }, { passive: false });
        
        // Touchmove handler removed to keep scrolling smooth
    </script>
</body>
</html>
