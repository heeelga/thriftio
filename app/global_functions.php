<script>
    function toggleMenu() {
        const menuLinks = document.getElementById('menu-links');
        if (menuLinks.style.display === "block") {
            menuLinks.style.display = "none";
        } else {
            menuLinks.style.display = "block";
        }
    }

    // Schließt das Menü, wenn der Benutzer außerhalb des Menüs klickt
    window.onclick = function(event) {
        const menuLinks = document.getElementById('menu-links');
        if (event.target === menuLinks) {
            menuLinks.style.display = "none";
        }
    }
</script>