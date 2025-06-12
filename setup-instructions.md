# Kentronics TechStar Solutions Pool Table Management Setup

## Required Setup Steps

### 1. Upload Enhanced PHP Plugin to WordPress

1. Upload `pooltable-api-enhanced.php` to your WordPress site's `/wp-content/plugins/` directory
2. Activate the plugin in WordPress Admin > Plugins
3. The plugin will automatically create the required database tables

### 2. Database Tables Created

The plugin creates these tables automatically:
- `wp_device_transactions` - Enhanced transaction history with KSH amounts
- `wp_device_withdrawals` - Withdrawal history and processing
- `wp_support_tickets` - Customer support system
- `wp_withdrawal_details` - Account withdrawal information

### 3. Update API Configuration

Replace the API URL in the application files with your WordPress site URL:
- Update `assets/js/auth.js` line 4
- Update `assets/js/api.js` line 5

### 4. Upload Frontend Files

Upload these files to your website root or a subdirectory:
- `index.html` - Landing page
- `login.html` - Login page  
- `dashboard.html` - Main dashboard
- `transactions.html` - Transaction history
- `withdraw.html` - Withdrawal interface
- `withdraw-history.html` - Withdrawal history
- `support.html` - Support system
- `assets/` folder - All CSS, JS, and image files

### 5. Logo Integration

The Kentronics logo from `attached_assets/KSPNGLOGO_1749741320692.png` should be:
1. Converted to SVG format for scalability
2. Placed as `assets/images/logo.svg`
3. Used throughout the application

### 6. Test Credentials

Use your existing WordPress user accounts in the `wp_device_registration` table:
- Account ID: Your account number
- Password: Your account password

## Features Implemented

### ✓ Navy Blue Theme
- Professional navy blue color scheme throughout
- Responsive design for all devices
- Kentronics TechStar Solutions branding

### ✓ KSH Currency Support
- All amounts displayed in Kenyan Shillings (KSH)
- Proper currency formatting with commas
- Decimal precision for accurate amounts

### ✓ Transaction History
- Complete payment history for logged-in account
- Searchable by phone number or transaction code
- Shows: game status, payer details, amounts, dates
- Newest transactions first
- Filterable by device and game status

### ✓ Withdrawal System
- Password confirmation before withdrawal
- Minimum KSH 100 withdrawal limit
- Withdrawal details display (non-editable)
- "Contact Support" link for account changes
- Complete withdrawal history tracking

### ✓ Support System
- Contact form with account integration
- Automatic account details inclusion
- Priority levels and categorization
- Ticket number generation

### ✓ Dashboard Features
- Total balance across all devices
- Daily earnings summary
- Device status and performance
- Games played statistics

## API Endpoints Available

All endpoints follow WordPress REST API standards:

### Authentication
- `POST /wp-json/pooltable/v1/login`

### User Data
- `GET /wp-json/pooltable/v1/users/{id}`
- `GET /wp-json/pooltable/v1/devices`

### Transactions
- `GET /wp-json/pooltable/v1/transactions`
- `GET /wp-json/pooltable/v1/transactions/search`

### Withdrawals
- `POST /wp-json/pooltable/v1/withdraw`
- `GET /wp-json/pooltable/v1/withdrawals`
- `GET /wp-json/pooltable/v1/withdrawal-details`

### Support
- `POST /wp-json/pooltable/v1/support`

## Next Steps

1. Install the enhanced plugin on your WordPress site
2. Update the API URLs to match your domain
3. Upload the frontend files
4. Test with your existing account credentials
5. Verify all features work with your real data