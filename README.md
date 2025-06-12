# Kentronics TechStar Solutions - Pool Table Management System

## Overview
Complete pool table management application with navy blue theme, KSH currency support, and comprehensive transaction management for Kentronics TechStar Solutions.

## Installation Instructions

### 1. WordPress Plugin Setup
1. Upload `pooltable-api-enhanced.php` to `/wp-content/plugins/` directory
2. Activate the plugin in WordPress Admin
3. Plugin automatically creates required database tables

### 2. Frontend Application Setup
1. Upload all files to your web server
2. Update API URL in `assets/js/auth.js` line 5:
   ```javascript
   const API_BASE_URL = 'https://YOUR-DOMAIN.com/wp-json/pooltable/v1';
   ```

### 3. Test with Your Credentials
Use your existing account credentials from the `wp_device_registration` table:
- Account ID: Your account number
- Password: Your account password

## Features Implemented

### Navy Blue Theme
- Professional navy blue color scheme (#1e3a5f)
- Responsive design for all devices
- Kentronics TechStar Solutions branding integration

### KSH Currency Support
- All amounts displayed in Kenyan Shillings
- Proper formatting with decimal precision
- Currency calculations for withdrawals and balances

### Complete Dashboard
- Total balance across all devices
- Daily earnings summary
- Device performance metrics
- Games played statistics
- Account information display

### Transaction History
- Complete payment history for logged-in accounts
- Searchable by phone number or transaction code
- Filterable by device and game status
- Shows: game status (played/not played), payer details, amounts, timestamps
- Sorted newest to oldest

### Withdrawal Management
- Password confirmation before withdrawal
- Minimum KSH 100 withdrawal limit
- Withdrawal account details (non-editable)
- Complete withdrawal history tracking
- "Contact Support" integration for account changes

### Support System
- Comprehensive contact form
- Automatic account details inclusion
- Priority levels and issue categorization
- Ticket number generation and tracking

## File Structure
```
├── index.html              # Landing page
├── login.html              # Authentication page
├── dashboard.html          # Main dashboard
├── transactions.html       # Transaction history
├── withdraw.html           # Withdrawal interface
├── withdraw-history.html   # Withdrawal records
├── support.html           # Support system
├── assets/
│   ├── css/style.css      # Navy blue theme styles
│   ├── js/
│   │   ├── auth.js        # Authentication logic
│   │   ├── api.js         # API communication
│   │   └── app.js         # Application functionality
│   └── images/logo.svg    # Kentronics logo
└── pooltable-api-enhanced.php  # WordPress plugin
```

## Database Tables
The plugin creates and manages:
- `wp_device_transactions` - Enhanced transaction records with KSH amounts
- `wp_device_withdrawals` - Withdrawal history and processing
- `wp_support_tickets` - Customer support system
- `wp_withdrawal_details` - Account withdrawal information

## API Endpoints
All endpoints follow WordPress REST API standards:
- `POST /login` - User authentication
- `GET /devices` - Device information and balances
- `GET /transactions` - Transaction history with search
- `POST /withdraw` - Process withdrawals
- `GET /withdrawals` - Withdrawal history
- `POST /support` - Submit support tickets

## Configuration
Update the API URL in `assets/js/auth.js` to match your WordPress installation:
```javascript
const API_BASE_URL = 'https://your-domain.com/wp-json/pooltable/v1';
```

## Testing
1. Install the WordPress plugin
2. Update the API URL configuration
3. Test login with your existing account credentials
4. Verify all features work with your real database data

## Security Features
- JWT-based authentication
- Password confirmation for withdrawals
- Secure API communication
- Input validation and sanitization

## Responsive Design
- Mobile-friendly interface
- Tablet and desktop optimization
- Touch-friendly navigation
- Consistent experience across devices