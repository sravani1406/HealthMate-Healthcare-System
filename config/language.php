<?php
// config/language.php
class Language {
    private $lang;
    private $translations = [];
    private $fallback = 'en';
    
    public function __construct($lang = 'en') {
        $this->lang = $lang;
        $this->loadTranslations();
    }
    
    private function loadTranslations() {
        $langFile = __DIR__ . "/../languages/{$this->lang}.php";
        
        if (file_exists($langFile)) {
            $this->translations = require $langFile;
        } else {
            // Load fallback language
            $fallbackFile = __DIR__ . "/../languages/{$this->fallback}.php";
            if (file_exists($fallbackFile)) {
                $this->translations = require $fallbackFile;
            }
        }
    }
    
    public function get($key, $default = null) {
        $keys = explode('.', $key);
        $value = $this->translations;
        
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default ?? $key;
            }
        }
        
        return $value;
    }
    
    public function setLanguage($lang) {
        $this->lang = $lang;
        $this->loadTranslations();
    }
    
    public function getCurrentLanguage() {
        return $this->lang;
    }
    
    public function getAvailableLanguages() {
        return [
            'en' => 'English',
            'te' => 'తెలుగు',
            'hi' => 'हिन्दी'
        ];
    }
}

// Initialize language system
function initLanguage() {
    if (!isset($_SESSION['language'])) {
        // Check user preference from database
        if (isset($_SESSION['user_id'])) {
            global $db, $pdo;
            $database = $db ?? $pdo;
            $stmt = $database->prepare("SELECT language_preference FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $_SESSION['language'] = $user['language_preference'] ?? 'en';
        } else {
            // Default to browser language or English
            $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'en', 0, 2);
            $_SESSION['language'] = in_array($browserLang, ['en', 'te', 'hi']) ? $browserLang : 'en';
        }
    }
    
    return new Language($_SESSION['language']);
}

// Helper function for translations
function __($key, $default = null) {
    global $lang;
    if (!isset($lang)) {
        $lang = new Language($_SESSION['language'] ?? 'en');
    }
    return $lang->get($key, $default);
}

// Helper function for translations with parameters
function __t($key, $params = [], $default = null) {
    global $lang;
    if (!isset($lang)) {
        $lang = new Language($_SESSION['language'] ?? 'en');
    }
    
    $translation = $lang->get($key, $default);
    
    // Replace parameters in translation
    foreach ($params as $param => $value) {
        $translation = str_replace(':' . $param, $value, $translation);
    }
    
    return $translation;
}
?>