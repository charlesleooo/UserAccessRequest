<!-- Footer -->
<footer class="fixed bottom-0 left-0 w-full bg-blue-600 text-white py-4 text-center z-40" style="margin-left: 0; transition: margin-left 0.3s;">
    <div class="container mx-auto">
        <p>© <?php echo date('Y'); ?> Alsons Agribusiness Unit. All Rights Reserved.</p>
    </div>
</footer>

<script>
    // Update footer position based on sidebar state
    document.addEventListener('alpine:init', () => {
        Alpine.store('app', {
            sidebarOpen: true,
            updateFooterPosition() {
                const footer = document.querySelector('footer');
                if (window.innerWidth >= 768) { // Only for desktop
                    footer.style.marginLeft = this.sidebarOpen ? '18rem' : '0';
                } else {
                    footer.style.marginLeft = '0';
                }
            }
        });
    });

    // Listen for sidebar state changes
    document.addEventListener('alpine:initialized', () => {
        const app = Alpine.store('app');
        app.updateFooterPosition();
        
        // Update on window resize
        window.addEventListener('resize', () => {
            app.updateFooterPosition();
        });
    });
</script>