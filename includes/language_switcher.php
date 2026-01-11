<?php
// includes/language_switcher.php
// ✅ This file should NOT handle the actual language change (that's in header.php)
// It only displays the language switcher UI

// Get language object
$current_lang = 'en';
$available_languages = [
    'en' => 'English',
    'es' => 'Español',
    'hi' => 'हिन्दी'
];

if (isset($lang) && is_object($lang)) {
    if (method_exists($lang, 'getCurrentLanguage')) {
        $current_lang = $lang->getCurrentLanguage();
    }
    if (method_exists($lang, 'getAvailableLanguages')) {
        $available_languages = $lang->getAvailableLanguages();
    }
}
?>

<!-- Language Switcher Dropdown (for use in page content) -->
<div class="dropdown d-inline-block">
    <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="pageLangDropdown" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-globe me-2"></i>
        <?php echo htmlspecialchars($available_languages[$current_lang] ?? 'Language'); ?>
    </button>
    <ul class="dropdown-menu" aria-labelledby="pageLangDropdown">
        <?php foreach ($available_languages as $code => $name): ?>
            <li>
                <a class="dropdown-item <?php echo $code === $current_lang ? 'active' : ''; ?>" 
                   href="?change_language=<?php echo urlencode($code); ?>">
                    <?php if ($code === 'en'): ?>
                        <i class="fas fa-flag-usa me-2"></i>
                    <?php elseif ($code === 'es'): ?>
                        <i class="fas fa-flag me-2"></i>
                    <?php elseif ($code === 'hi'): ?>
                        <i class="fas fa-flag me-2"></i>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($name); ?>
                    <?php if ($code === $current_lang): ?>
                        <i class="fas fa-check ms-2 text-success"></i>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>