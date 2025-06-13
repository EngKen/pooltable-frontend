// Configuration for Kentronics TechStar Solutions Pool Table Management
// Production configuration

const CONFIG = {
    // WordPress site URL
    WORDPRESS_URL: 'https://www.kentronicssolutions.com',
    
    // API endpoints
    API_BASE_URL: 'https://www.kentronicssolutions.com/wp-json/pooltable/v1',
    
    // Application settings
    CURRENCY: 'KSH',
    COMPANY_NAME: 'Kentronics TechStar Solutions',
    
    // Pagination settings
    TRANSACTIONS_PER_PAGE: 10,
    WITHDRAWALS_PER_PAGE: 10,
    
    // Withdrawal settings
    MIN_WITHDRAWAL_AMOUNT: 100,
    
    // API timeout settings
    API_TIMEOUT: 30000, // 30 seconds
    
    // Production mode
    DEMO_MODE: false
};

// Export configuration
window.APP_CONFIG = CONFIG;
// Remove demo credentials in production
window.DEMO_CREDENTIALS = null;