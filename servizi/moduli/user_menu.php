<?php
// Questo file si aspetta che le variabili $path_prefix e $token siano definite nella pagina che lo include.
?>
<div class="relative">
    <button type="button" id="userMenuButton" class="flex text-sm bg-primary rounded-full focus:ring-4 focus:ring-red-300" style="width: 48px; height: 48px;">
        <span class="sr-only">Open user menu</span>
        <div class="w-full h-full flex items-center justify-center text-white"><i class="fas fa-user fa-lg"></i></div>
    </button>
    <!-- Dropdown menu -->
    <div id="userDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">
        <ul class="py-1" aria-labelledby="userMenuButton">
            <li>
                <a href="<?php echo htmlspecialchars($path_prefix . 'profilo.php?token=' . $token); ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Profilo</a>
            </li>
            <li><a href="<?php echo htmlspecialchars($path_prefix . 'logout.php'); ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Logout</a></li>
        </ul>
    </div>
</div>