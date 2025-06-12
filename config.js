// Configuration for Kentronics TechStar Solutions Pool Table Management
// Update these settings to match your WordPress installation

const CONFIG = {
    // Your WordPress site URL (replace with your actual domain)
    WORDPRESS_URL: 'https://your-site.com',
    
    // API endpoints
    API_BASE_URL: 'https://your-site.com/wp-json/pooltable/v1',
    
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
    
    // Demo mode (set to false for production)
    DEMO_MODE: true
};

// Demo credentials for testing (remove in production)
const DEMO_CREDENTIALS = {
    accounts: [
        {
            accountId: 'ACC001',
            password: 'password123',
            name: 'John Doe'
        },
        {
            accountId: 'ACC002', 
            password: 'test123',
            name: 'Jane Smith'
        }
    ]
};

// Export configuration
window.APP_CONFIG = CONFIG;
window.DEMO_CREDENTIALS = DEMO_CREDENTIALS;