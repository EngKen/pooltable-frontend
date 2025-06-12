// WordPress Connection Configuration for Kentronics TechStar Solutions
// This file helps you connect the application to your actual WordPress site

window.WordPressConfig = {
    // Step 1: Replace this with your actual WordPress site URL
    siteUrl: 'https://your-wordpress-site.com',
    
    // Step 2: API endpoint paths
    apiPaths: {
        login: '/wp-json/pooltable/v1/login',
        devices: '/wp-json/pooltable/v1/devices',
        transactions: '/wp-json/pooltable/v1/transactions',
        withdraw: '/wp-json/pooltable/v1/withdraw',
        withdrawals: '/wp-json/pooltable/v1/withdrawals',
        support: '/wp-json/pooltable/v1/support',
        health: '/wp-json/pooltable/v1/health'
    },
    
    // Step 3: Test connection function
    async testConnection() {
        try {
            const response = await fetch(this.siteUrl + this.apiPaths.health);
            const data = await response.json();
            console.log('WordPress API Connection:', data);
            return response.ok;
        } catch (error) {
            console.error('WordPress API Connection Failed:', error);
            return false;
        }
    },
    
    // Step 4: Get full API URL
    getApiUrl(endpoint) {
        return this.siteUrl + this.apiPaths[endpoint];
    }
};

// Connection instructions for the user
console.log(`
=== WORDPRESS CONNECTION SETUP ===

1. Upload 'pooltable-api-enhanced.php' to your WordPress plugins folder
2. Activate the plugin in WordPress Admin
3. Replace 'https://your-wordpress-site.com' with your actual site URL
4. Test the connection by running: WordPressConfig.testConnection()

Your existing database tables will be used:
- wp_device_registration (user accounts)
- wp_device_balances (device balances) 
- wp_device_transactions (transaction history)

The plugin will create additional tables for enhanced features:
- wp_device_withdrawals (withdrawal history)
- wp_support_tickets (support system)
- wp_withdrawal_details (account information)
`);