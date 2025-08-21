<?php

namespace Lilac\SubscriptionTracker;

/**
 * Main Plugin Class
 * 
 * @package Lilac\SubscriptionTracker
 * @since 1.0.0
 */
class Plugin {
    
    /**
     * Plugin instance
     * 
     * @var Plugin|null
     */
    private static $instance = null;
    
    /**
     * Plugin version
     */
    const VERSION = '1.0.0';
    
    /**
     * Get plugin instance
     * 
     * @return Plugin
     */
    public static function getInstance(): Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }
    
    /**
     * Initialize plugin
     */
    private function init(): void {
        // Hook into WordPress
        add_action('init', [$this, 'onInit']);
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
        
        // Initialize components
        new SubscriptionManager();
        new TestingInterface();
        new DatabaseManager();
        
        // Log initialization
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Subscription Tracker Plugin v' . self::VERSION . ' initialized');
        }
    }
    
    /**
     * WordPress init hook
     */
    public function onInit(): void {
        // Create database tables if needed
        DatabaseManager::createTables();
    }
    
    /**
     * Add admin menu
     */
    public function addAdminMenu(): void {
        add_management_page(
            'Subscription Tracker',
            'Subscription Tracker',
            'manage_options',
            'subscription-tracker',
            [$this, 'renderAdminPage']
        );
    }
    
    /**
     * Render admin page
     */
    public function renderAdminPage(): void {
        $testingInterface = new TestingInterface();
        $testingInterface->renderAdminInterface();
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueueScripts(): void {
        // Only enqueue on relevant pages
        if (is_user_logged_in()) {
            wp_enqueue_style(
                'subscription-tracker-frontend',
                $this->getAssetUrl('css/frontend.css'),
                [],
                self::VERSION
            );
        }
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueueAdminScripts($hook): void {
        if ($hook === 'tools_page_subscription-tracker') {
            wp_enqueue_style(
                'subscription-tracker-admin',
                $this->getAssetUrl('css/admin.css'),
                [],
                self::VERSION
            );
            
            wp_enqueue_script(
                'subscription-tracker-admin',
                $this->getAssetUrl('js/admin.js'),
                ['jquery'],
                self::VERSION,
                true
            );
            
            wp_localize_script('subscription-tracker-admin', 'subscriptionTracker', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('subscription_tracker_nonce'),
                'version' => self::VERSION
            ]);
        }
    }
    
    /**
     * Get asset URL
     * 
     * @param string $path
     * @return string
     */
    private function getAssetUrl(string $path): string {
        return plugin_dir_url(__FILE__) . '../assets/' . $path;
    }
    
    /**
     * Get plugin path
     * 
     * @return string
     */
    public static function getPluginPath(): string {
        return dirname(__DIR__);
    }
    
    /**
     * Get plugin URL
     * 
     * @return string
     */
    public static function getPluginUrl(): string {
        return plugin_dir_url(__DIR__);
    }
}
