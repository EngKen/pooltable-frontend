<?php
/**
 * Plugin Name: Enhanced Pool Table Management API
 * Description: Enhanced REST API endpoints for Kentronics TechStar Solutions Pool Table Management
 * Version: 2.0
 * Author: Kentronics TechStar Solutions
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Enhanced_PoolTable_Management_API {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        $this->create_required_tables();
        
        // Add CORS support
        add_action('rest_api_init', array($this, 'add_cors_support'));
    }

    /**
     * Add CORS support for API requests
     */
    public function add_cors_support() {
        $enable_cors = function() {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            
            if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
                wp_die('', '', array('response' => 200));
            }
        };
        
        add_action('wp_loaded', $enable_cors);
    }

    /**
     * Create all required tables
     */
    private function create_required_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Enhanced device transactions table with KSH amounts
        $transactions_table = $wpdb->prefix . 'device_transactions';
        $transactions_sql = "CREATE TABLE IF NOT EXISTS $transactions_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            account_no varchar(50) NOT NULL,
            device_id varchar(20) NOT NULL,
            transaction_id varchar(50) NOT NULL UNIQUE,
            amount decimal(12,2) NOT NULL COMMENT 'Amount in KSH',
            running_balance decimal(12,2) NOT NULL COMMENT 'Running balance in KSH',
            payer_name varchar(100) DEFAULT NULL,
            phone_number varchar(20) DEFAULT NULL,
            game_status enum('played', 'not_played') DEFAULT 'not_played',
            transaction_date timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY account_no (account_no),
            KEY device_id (device_id),
            KEY transaction_id (transaction_id),
            KEY transaction_date (transaction_date),
            KEY game_status (game_status),
            KEY phone_number (phone_number)
        ) $charset_collate;";

        // Withdrawal history table
        $withdrawals_table = $wpdb->prefix . 'device_withdrawals';
        $withdrawals_sql = "CREATE TABLE IF NOT EXISTS $withdrawals_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            account_no varchar(50) NOT NULL,
            transaction_code varchar(50) NOT NULL UNIQUE,
            amount decimal(10,2) NOT NULL,
            withdrawal_account varchar(100) NOT NULL,
            account_name varchar(100) NOT NULL,
            payment_method varchar(50) DEFAULT 'M-Pesa',
            status enum('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
            withdrawal_date timestamp DEFAULT CURRENT_TIMESTAMP,
            processed_date timestamp NULL,
            notes text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY account_no (account_no),
            KEY transaction_code (transaction_code),
            KEY status (status),
            KEY withdrawal_date (withdrawal_date)
        ) $charset_collate;";

        // Support tickets table
        $support_table = $wpdb->prefix . 'support_tickets';
        $support_sql = "CREATE TABLE IF NOT EXISTS $support_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ticket_number varchar(20) NOT NULL UNIQUE,
            account_no varchar(50) DEFAULT NULL,
            name varchar(100) NOT NULL,
            email varchar(100) DEFAULT NULL,
            phone varchar(20) NOT NULL,
            category enum('technical', 'billing', 'withdrawal', 'account', 'device', 'other') NOT NULL,
            subject varchar(200) NOT NULL,
            message text NOT NULL,
            priority enum('low', 'medium', 'high', 'critical') DEFAULT 'medium',
            status enum('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
            created_date timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_date timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            resolved_date timestamp NULL,
            admin_notes text DEFAULT NULL,
            PRIMARY KEY (id),
            KEY ticket_number (ticket_number),
            KEY account_no (account_no),
            KEY status (status),
            KEY priority (priority),
            KEY created_date (created_date)
        ) $charset_collate;";

        // Withdrawal details table (account information)
        $withdrawal_details_table = $wpdb->prefix . 'withdrawal_details';
        $withdrawal_details_sql = "CREATE TABLE IF NOT EXISTS $withdrawal_details_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            account_no varchar(50) NOT NULL UNIQUE,
            payment_method varchar(50) DEFAULT 'M-Pesa',
            withdrawal_account varchar(100) NOT NULL,
            account_name varchar(100) NOT NULL,
            bank_name varchar(100) DEFAULT NULL,
            branch varchar(100) DEFAULT NULL,
            is_active tinyint(1) DEFAULT 1,
            created_date timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_date timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY account_no (account_no)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($transactions_sql);
        dbDelta($withdrawals_sql);
        dbDelta($support_sql);
        dbDelta($withdrawal_details_sql);

        // Log any database errors
        if ($wpdb->last_error) {
            error_log('Enhanced Pool Table Plugin - Database Error: ' . $wpdb->last_error);
        }
    }

    /**
     * Register all API routes
     */
    public function register_routes() {
        // Authentication
        register_rest_route('pooltable/v1', '/login', array(
            'methods' => 'POST',
            'callback' => array($this, 'login_user'),
            'permission_callback' => '__return_true'
        ));

        // Health check
        register_rest_route('pooltable/v1', '/health', array(
            'methods' => 'GET',
            'callback' => array($this, 'health_check'),
            'permission_callback' => '__return_true'
        ));

        // User data
        register_rest_route('pooltable/v1', '/users/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_user_data'),
            'permission_callback' => array($this, 'check_api_auth')
        ));

        // Devices
        register_rest_route('pooltable/v1', '/devices', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_user_devices'),
            'permission_callback' => array($this, 'check_api_auth')
        ));

        register_rest_route('pooltable/v1', '/devices/(?P<id>\d+)/balance', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_device_balance'),
            'permission_callback' => array($this, 'check_api_auth')
        ));

        register_rest_route('pooltable/v1', '/devices/(?P<id>\d+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_device_info'),
            'permission_callback' => array($this, 'check_api_auth')
        ));

        // Transactions
        register_rest_route('pooltable/v1', '/transactions', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_all_transactions'),
            'permission_callback' => array($this, 'check_api_auth')
        ));

        register_rest_route('pooltable/v1', '/devices/(?P<id>\d+)/transactions', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_device_transactions'),
            'permission_callback' => array($this, 'check_api_auth')
        ));

        register_rest_route('pooltable/v1', '/transactions/search', array(
            'methods' => 'GET',
            'callback' => array($this, 'search_transactions'),
            'permission_callback' => array($this, 'check_api_auth')
        ));

        // Withdrawals
        register_rest_route('pooltable/v1', '/withdraw', array(
            'methods' => 'POST',
            'callback' => array($this, 'process_withdrawal'),
            'permission_callback' => array($this, 'check_api_auth')
        ));

        register_rest_route('pooltable/v1', '/withdrawals', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_withdrawal_history'),
            'permission_callback' => array($this, 'check_api_auth')
        ));

        register_rest_route('pooltable/v1', '/withdrawal-details', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_withdrawal_details'),
            'permission_callback' => array($this, 'check_api_auth')
        ));

        register_rest_route('pooltable/v1', '/withdrawal-summary', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_withdrawal_summary'),
            'permission_callback' => array($this, 'check_api_auth')
        ));

        // Support
        register_rest_route('pooltable/v1', '/support', array(
            'methods' => 'POST',
            'callback' => array($this, 'submit_support_ticket'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route('pooltable/v1', '/support', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_support_tickets'),
            'permission_callback' => array($this, 'check_api_auth')
        ));

        // Statistics
        register_rest_route('pooltable/v1', '/stats/dashboard', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_dashboard_stats'),
            'permission_callback' => array($this, 'check_api_auth')
        ));

        // Reports
        register_rest_route('pooltable/v1', '/reports/earnings', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_earnings_report'),
            'permission_callback' => array($this, 'check_api_auth')
        ));

        // Export
        register_rest_route('pooltable/v1', '/export/transactions', array(
            'methods' => 'GET',
            'callback' => array($this, 'export_transactions'),
            'permission_callback' => array($this, 'check_api_auth')
        ));

        register_rest_route('pooltable/v1', '/export/withdrawals', array(
            'methods' => 'GET',
            'callback' => array($this, 'export_withdrawals'),
            'permission_callback' => array($this, 'check_api_auth')
        ));
    }

    /**
     * Check API authorization
     */
    public function check_api_auth($request) {
        $auth_header = $request->get_header('Authorization');

        if (!$auth_header || strpos($auth_header, 'Bearer ') !== 0) {
            return new WP_Error('forbidden', 'Authorization required', array('status' => 401));
        }

        $token = str_replace('Bearer ', '', $auth_header);
        
        // Basic token validation - enhance this with proper JWT or database token validation
        if (strlen($token) < 32) {
            return new WP_Error('invalid_token', 'Invalid authorization token', array('status' => 401));
        }

        return true;
    }

    /**
     * Health check endpoint
     */
    public function health_check($request) {
        global $wpdb;
        
        // Test database connection
        $db_test = $wpdb->get_var("SELECT 1");
        
        return new WP_REST_Response(array(
            'status' => 'healthy',
            'timestamp' => current_time('mysql'),
            'database' => $db_test ? 'connected' : 'disconnected',
            'version' => '2.0'
        ), 200);
    }

    /**
     * Enhanced login user endpoint
     */
    public function login_user($request) {
        global $wpdb;
        
        $params = $request->get_json_params();
        $account_no = sanitize_text_field($params['accountId']);
        $password = sanitize_text_field($params['password']);
        
        if (empty($account_no) || empty($password)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Account ID and password are required'
            ), 400);
        }
        
        // Query the wp_device_registration table
        $query = $wpdb->prepare("SELECT * FROM `{$wpdb->prefix}device_registration` WHERE account_no = %s", $account_no);
        $user = $wpdb->get_row($query);
        
        if (!$user) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Account not found'
            ), 401);
        }
        
        // Verify password (implement proper password hashing in production)
        if ($password !== $user->password) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Invalid credentials'
            ), 401);
        }
        
        // Generate secure token
        $token = bin2hex(random_bytes(32));
        
        // Store token with expiration (implement token storage table in production)
        
        return new WP_REST_Response(array(
            'success' => true,
            'token' => $token,
            'user' => array(
                'id' => $user->device_id,
                'name' => $user->owner_name,
                'accountNumber' => $user->account_no,
                'phoneNumber' => $user->owner_number,
                'serialNumber' => $user->device_serial_number
            )
        ), 200);
    }

    /**
     * Get user data with enhanced information
     */
    public function get_user_data($request) {
        global $wpdb;
        
        $device_id = $request['id'];
        
        $user = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM `{$wpdb->prefix}device_registration` WHERE device_id = %d", $device_id)
        );
        
        if (!$user) {
            return new WP_Error('not_found', 'User not found', array('status' => 404));
        }
        
        $devices = $this->get_user_devices_with_balances($user->account_no);
        
        return new WP_REST_Response(array(
            'id' => $user->device_id,
            'name' => $user->owner_name,
            'accountNumber' => $user->account_no,
            'phoneNumber' => $user->owner_number,
            'serialNumber' => $user->device_serial_number,
            'devices' => $devices,
            'totalBalance' => array_sum(array_column($devices, 'balance')),
            'totalDevices' => count($devices)
        ), 200);
    }

    /**
     * Get user devices with enhanced information
     */
    public function get_user_devices($request) {
        $account_no = $request->get_param('account_no');
        
        if (!$account_no) {
            return new WP_Error('bad_request', 'Account number is required', array('status' => 400));
        }
        
        $devices = $this->get_user_devices_with_balances($account_no);
        
        return new WP_REST_Response($devices, 200);
    }

    /**
     * Enhanced helper function to get user devices with balances
     */
    private function get_user_devices_with_balances($account_no) {
        global $wpdb;
        
        $devices_query = $wpdb->prepare(
            "SELECT r.device_id, r.device_serial_number, r.owner_name, r.location, r.account_no, 
                    r.date_of_registration, r.status, 
                    COALESCE(b.balance, 0) as balance, COALESCE(b.games_paid, 0) as games_paid,
                    COALESCE(b.daily_earnings, 0) as daily_earnings, COALESCE(b.daily_gamespaid, 0) as daily_gamespaid,
                    b.last_updated
             FROM `{$wpdb->prefix}device_registration` r
             LEFT JOIN `{$wpdb->prefix}device_balances` b ON r.device_id = b.device_id
             WHERE r.account_no = %s
             ORDER BY r.device_id",
            $account_no
        );
        
        $devices = $wpdb->get_results($devices_query);
        $formatted_devices = array();
        
        foreach ($devices as $device) {
            $formatted_devices[] = array(
                'id' => $device->device_id,
                'name' => $device->owner_name,
                'serialNumber' => $device->device_serial_number,
                'location' => $device->location,
                'balance' => round((float) $device->balance, 2),
                'gamesPlayed' => (int) $device->games_paid,
                'dailyEarnings' => round((float) $device->daily_earnings, 2),
                'dailyGamesPlayed' => (int) $device->daily_gamespaid,
                'accountNumber' => $device->account_no,
                'registrationDate' => $device->date_of_registration,
                'lastActivity' => $device->last_updated,
                'status' => $device->status ?: 'unknown'
            );
        }
        
        return $formatted_devices;
    }

    /**
     * Get device balance
     */
    public function get_device_balance($request) {
        global $wpdb;
        
        $device_id = $request['id'];
        
        $balance = $wpdb->get_var(
            $wpdb->prepare("SELECT balance FROM `{$wpdb->prefix}device_balances` WHERE device_id = %d", $device_id)
        );
        
        return new WP_REST_Response(round((float) ($balance ?: 0), 2), 200);
    }

    /**
     * Get all transactions for user account
     */
    public function get_all_transactions($request) {
        global $wpdb;
        
        $account_no = $request->get_param('account_no');
        $page = max(1, (int) $request->get_param('page'));
        $limit = min(100, max(10, (int) $request->get_param('limit')));
        
        if (!$account_no) {
            return new WP_Error('bad_request', 'Account number is required', array('status' => 400));
        }
        
        $offset = ($page - 1) * $limit;
        
        $query = $wpdb->prepare(
            "SELECT t.*, r.location, r.device_serial_number 
             FROM `{$wpdb->prefix}device_transactions` t
             LEFT JOIN `{$wpdb->prefix}device_registration` r ON t.device_id = r.device_id
             WHERE t.account_no = %s
             ORDER BY t.transaction_date DESC
             LIMIT %d OFFSET %d",
            $account_no, $limit, $offset
        );
        
        $transactions = $wpdb->get_results($query);
        
        $formatted_transactions = array();
        foreach ($transactions as $transaction) {
            $formatted_transactions[] = array(
                'id' => $transaction->id,
                'transaction_id' => $transaction->transaction_id,
                'device_id' => $transaction->device_id,
                'device_location' => $transaction->location,
                'device_serial' => $transaction->device_serial_number,
                'amount' => round((float) $transaction->amount, 2),
                'running_balance' => round((float) $transaction->running_balance, 2),
                'payer_name' => $transaction->payer_name,
                'phone_number' => $transaction->phone_number,
                'game_status' => $transaction->game_status ?: 'not_played',
                'transaction_date' => $transaction->transaction_date,
                'updated_at' => $transaction->updated_at
            );
        }
        
        return new WP_REST_Response($formatted_transactions, 200);
    }

    /**
     * Get device transactions
     */
    public function get_device_transactions($request) {
        global $wpdb;
        
        $device_id = $request['id'];
        $page = max(1, (int) $request->get_param('page'));
        $limit = min(100, max(10, (int) $request->get_param('limit')));
        
        $offset = ($page - 1) * $limit;
        
        $query = $wpdb->prepare(
            "SELECT * FROM `{$wpdb->prefix}device_transactions` 
             WHERE device_id = %d
             ORDER BY transaction_date DESC
             LIMIT %d OFFSET %d",
            $device_id, $limit, $offset
        );
        
        $transactions = $wpdb->get_results($query);
        
        return new WP_REST_Response($transactions, 200);
    }

    /**
     * Search transactions
     */
    public function search_transactions($request) {
        global $wpdb;
        
        $query_param = $request->get_param('q');
        $account_no = $request->get_param('account_no');
        
        if (!$query_param || !$account_no) {
            return new WP_Error('bad_request', 'Search query and account number are required', array('status' => 400));
        }
        
        $query = $wpdb->prepare(
            "SELECT t.*, r.location, r.device_serial_number 
             FROM `{$wpdb->prefix}device_transactions` t
             LEFT JOIN `{$wpdb->prefix}device_registration` r ON t.device_id = r.device_id
             WHERE t.account_no = %s 
             AND (t.phone_number LIKE %s OR t.transaction_id LIKE %s OR t.payer_name LIKE %s)
             ORDER BY t.transaction_date DESC
             LIMIT 50",
            $account_no, 
            '%' . $wpdb->esc_like($query_param) . '%',
            '%' . $wpdb->esc_like($query_param) . '%',
            '%' . $wpdb->esc_like($query_param) . '%'
        );
        
        $transactions = $wpdb->get_results($query);
        
        return new WP_REST_Response($transactions, 200);
    }

    /**
     * Enhanced withdrawal processing
     */
    public function process_withdrawal($request) {
        global $wpdb;
        
        $params = $request->get_json_params();
        $account_no = sanitize_text_field($params['accountNo']);
        $amount = (float) $params['amount'];
        $password = sanitize_text_field($params['password']);
        
        // Validation
        if ($amount <= 0) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Invalid withdrawal amount'
            ), 400);
        }
        
        if ($amount < 100) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Minimum withdrawal amount is KSH 100'
            ), 400);
        }
        
        // Verify password
        $user = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM `{$wpdb->prefix}device_registration` WHERE account_no = %s", $account_no)
        );
        
        if (!$user || $password !== $user->password) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Invalid password'
            ), 401);
        }
        
        // Check total balance
        $total_balance = $wpdb->get_var(
            $wpdb->prepare("SELECT COALESCE(SUM(b.balance), 0) FROM `{$wpdb->prefix}device_balances` b
                              JOIN `{$wpdb->prefix}device_registration` r ON b.device_id = r.device_id
                              WHERE r.account_no = %s", $account_no)
        );
        
        if ($total_balance < $amount) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Insufficient funds. Available balance: KSH ' . number_format($total_balance, 2)
            ), 400);
        }
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Generate transaction code
            $transaction_code = 'WD' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Get withdrawal details
            $withdrawal_details = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM `{$wpdb->prefix}withdrawal_details` WHERE account_no = %s", $account_no)
            );
            
            if (!$withdrawal_details) {
                // Create default withdrawal details
                $wpdb->insert(
                    "{$wpdb->prefix}withdrawal_details",
                    array(
                        'account_no' => $account_no,
                        'payment_method' => 'M-Pesa',
                        'withdrawal_account' => $user->owner_number,
                        'account_name' => $user->owner_name
                    )
                );
                
                $withdrawal_details = $wpdb->get_row(
                    $wpdb->prepare("SELECT * FROM `{$wpdb->prefix}withdrawal_details` WHERE account_no = %s", $account_no)
                );
            }
            
            // Deduct from device balances
            $devices = $wpdb->get_results(
                $wpdb->prepare("SELECT b.device_id, b.balance 
                                 FROM `{$wpdb->prefix}device_balances` b
                                 JOIN `{$wpdb->prefix}device_registration` r ON b.device_id = r.device_id
                                 WHERE r.account_no = %s AND b.balance > 0
                                 ORDER BY b.balance DESC", $account_no)
            );
            
            $remaining_amount = $amount;
            
            foreach ($devices as $device) {
                $device_balance = (float) $device->balance;
                $withdrawal_from_device = min($device_balance, $remaining_amount);
                
                if ($withdrawal_from_device > 0) {
                    $new_balance = $device_balance - $withdrawal_from_device;
                    
                    $wpdb->update(
                        "{$wpdb->prefix}device_balances",
                        array('balance' => $new_balance),
                        array('device_id' => $device->device_id),
                        array('%f'),
                        array('%d')
                    );
                    
                    $remaining_amount -= $withdrawal_from_device;
                    
                    if ($remaining_amount <= 0) {
                        break;
                    }
                }
            }
            
            // Record withdrawal
            $wpdb->insert(
                "{$wpdb->prefix}device_withdrawals",
                array(
                    'account_no' => $account_no,
                    'transaction_code' => $transaction_code,
                    'amount' => $amount,
                    'withdrawal_account' => $withdrawal_details->withdrawal_account,
                    'account_name' => $withdrawal_details->account_name,
                    'payment_method' => $withdrawal_details->payment_method,
                    'status' => 'pending'
                ),
                array('%s', '%s', '%f', '%s', '%s', '%s', '%s')
            );
            
            $wpdb->query('COMMIT');
            
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Withdrawal request submitted successfully',
                'transaction_code' => $transaction_code,
                'amount' => $amount,
                'status' => 'pending'
            ), 200);
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Withdrawal processing failed: ' . $e->getMessage()
            ), 500);
        }
    }

    /**
     * Get withdrawal history
     */
    public function get_withdrawal_history($request) {
        global $wpdb;
        
        $account_no = $request->get_param('account_no');
        $page = max(1, (int) $request->get_param('page'));
        $limit = min(100, max(10, (int) $request->get_param('limit')));
        
        if (!$account_no) {
            return new WP_Error('bad_request', 'Account number is required', array('status' => 400));
        }
        
        $offset = ($page - 1) * $limit;
        
        $query = $wpdb->prepare(
            "SELECT * FROM `{$wpdb->prefix}device_withdrawals` 
             WHERE account_no = %s
             ORDER BY withdrawal_date DESC
             LIMIT %d OFFSET %d",
            $account_no, $limit, $offset
        );
        
        $withdrawals = $wpdb->get_results($query);
        
        $formatted_withdrawals = array();
        foreach ($withdrawals as $withdrawal) {
            $formatted_withdrawals[] = array(
                'id' => $withdrawal->id,
                'transaction_code' => $withdrawal->transaction_code,
                'amount' => round((float) $withdrawal->amount, 2),
                'withdrawal_account' => $withdrawal->withdrawal_account,
                'account_name' => $withdrawal->account_name,
                'payment_method' => $withdrawal->payment_method,
                'status' => $withdrawal->status,
                'withdrawal_date' => $withdrawal->withdrawal_date,
                'processed_date' => $withdrawal->processed_date,
                'notes' => $withdrawal->notes
            );
        }
        
        return new WP_REST_Response($formatted_withdrawals, 200);
    }

    /**
     * Get withdrawal details for account
     */
    public function get_withdrawal_details($request) {
        global $wpdb;
        
        $account_no = $request->get_param('account_no');
        
        if (!$account_no) {
            return new WP_Error('bad_request', 'Account number is required', array('status' => 400));
        }
        
        $details = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM `{$wpdb->prefix}withdrawal_details` WHERE account_no = %s", $account_no)
        );
        
        if (!$details) {
            // Return default details if not found
            $user = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM `{$wpdb->prefix}device_registration` WHERE account_no = %s", $account_no)
            );
            
            if ($user) {
                return new WP_REST_Response(array(
                    'payment_method' => 'M-Pesa',
                    'withdrawal_account' => $user->owner_number,
                    'account_name' => $user->owner_name,
                    'bank_name' => null,
                    'branch' => null
                ), 200);
            }
        }
        
        return new WP_REST_Response(array(
            'payment_method' => $details->payment_method,
            'withdrawal_account' => $details->withdrawal_account,
            'account_name' => $details->account_name,
            'bank_name' => $details->bank_name,
            'branch' => $details->branch
        ), 200);
    }

    /**
     * Get withdrawal summary statistics
     */
    public function get_withdrawal_summary($request) {
        global $wpdb;
        
        $account_no = $request->get_param('account_no');
        
        if (!$account_no) {
            return new WP_Error('bad_request', 'Account number is required', array('status' => 400));
        }
        
        // Total withdrawn
        $total_withdrawn = $wpdb->get_var(
            $wpdb->prepare("SELECT COALESCE(SUM(amount), 0) FROM `{$wpdb->prefix}device_withdrawals` 
                           WHERE account_no = %s AND status = 'completed'", $account_no)
        );
        
        // This month
        $monthly_withdrawn = $wpdb->get_var(
            $wpdb->prepare("SELECT COALESCE(SUM(amount), 0) FROM `{$wpdb->prefix}device_withdrawals` 
                           WHERE account_no = %s AND status = 'completed' 
                           AND MONTH(withdrawal_date) = MONTH(CURRENT_DATE()) 
                           AND YEAR(withdrawal_date) = YEAR(CURRENT_DATE())", $account_no)
        );
        
        // Total withdrawals count
        $total_withdrawals = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM `{$wpdb->prefix}device_withdrawals` 
                           WHERE account_no = %s", $account_no)
        );
        
        // Last withdrawal date
        $last_withdrawal = $wpdb->get_var(
            $wpdb->prepare("SELECT withdrawal_date FROM `{$wpdb->prefix}device_withdrawals` 
                           WHERE account_no = %s ORDER BY withdrawal_date DESC LIMIT 1", $account_no)
        );
        
        return new WP_REST_Response(array(
            'total_withdrawn' => round((float) $total_withdrawn, 2),
            'monthly_withdrawn' => round((float) $monthly_withdrawn, 2),
            'total_withdrawals' => (int) $total_withdrawals,
            'last_withdrawal' => $last_withdrawal
        ), 200);
    }

    /**
     * Submit support ticket
     */
    public function submit_support_ticket($request) {
        global $wpdb;
        
        $params = $request->get_json_params();
        
        // Sanitize input
        $ticket_data = array(
            'ticket_number' => 'TK' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
            'account_no' => sanitize_text_field($params['accountNumber']) ?: null,
            'name' => sanitize_text_field($params['name']),
            'email' => sanitize_email($params['email']) ?: null,
            'phone' => sanitize_text_field($params['phone']),
            'category' => sanitize_text_field($params['category']),
            'subject' => sanitize_text_field($params['subject']),
            'message' => sanitize_textarea_field($params['message']),
            'priority' => sanitize_text_field($params['priority']) ?: 'medium'
        );
        
        // Validation
        $required_fields = array('name', 'phone', 'category', 'subject', 'message');
        foreach ($required_fields as $field) {
            if (empty($ticket_data[$field])) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => "Field '{$field}' is required"
                ), 400);
            }
        }
        
        if (strlen($ticket_data['message']) > 1000) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Message cannot exceed 1000 characters'
            ), 400);
        }
        
        // Insert support ticket
        $result = $wpdb->insert(
            "{$wpdb->prefix}support_tickets",
            $ticket_data,
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to submit support ticket: ' . $wpdb->last_error
            ), 500);
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Support ticket submitted successfully',
            'ticket_number' => $ticket_data['ticket_number']
        ), 200);
    }

    /**
     * Get support tickets for account
     */
    public function get_support_tickets($request) {
        global $wpdb;
        
        $account_no = $request->get_param('account_no');
        
        if (!$account_no) {
            return new WP_Error('bad_request', 'Account number is required', array('status' => 400));
        }
        
        $tickets = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM `{$wpdb->prefix}support_tickets` 
                           WHERE account_no = %s 
                           ORDER BY created_date DESC", $account_no)
        );
        
        return new WP_REST_Response($tickets, 200);
    }

    /**
     * Get dashboard statistics
     */
    public function get_dashboard_stats($request) {
        global $wpdb;
        
        $account_no = $request->get_param('account_no');
        
        if (!$account_no) {
            return new WP_Error('bad_request', 'Account number is required', array('status' => 400));
        }
        
        // Total balance
        $total_balance = $wpdb->get_var(
            $wpdb->prepare("SELECT COALESCE(SUM(b.balance), 0) FROM `{$wpdb->prefix}device_balances` b
                              JOIN `{$wpdb->prefix}device_registration` r ON b.device_id = r.device_id
                              WHERE r.account_no = %s", $account_no)
        );
        
        // Daily earnings
        $daily_earnings = $wpdb->get_var(
            $wpdb->prepare("SELECT COALESCE(SUM(b.daily_earnings), 0) FROM `{$wpdb->prefix}device_balances` b
                              JOIN `{$wpdb->prefix}device_registration` r ON b.device_id = r.device_id
                              WHERE r.account_no = %s", $account_no)
        );
        
        // Total devices
        $total_devices = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM `{$wpdb->prefix}device_registration` WHERE account_no = %s", $account_no)
        );
        
        // Games today
        $games_today = $wpdb->get_var(
            $wpdb->prepare("SELECT COALESCE(SUM(b.daily_gamespaid), 0) FROM `{$wpdb->prefix}device_balances` b
                              JOIN `{$wpdb->prefix}device_registration` r ON b.device_id = r.device_id
                              WHERE r.account_no = %s", $account_no)
        );
        
        return new WP_REST_Response(array(
            'total_balance' => round((float) $total_balance, 2),
            'daily_earnings' => round((float) $daily_earnings, 2),
            'total_devices' => (int) $total_devices,
            'games_today' => (int) $games_today
        ), 200);
    }

    /**
     * Get earnings report
     */
    public function get_earnings_report($request) {
        global $wpdb;
        
        $account_no = $request->get_param('account_no');
        $start_date = $request->get_param('start_date');
        $end_date = $request->get_param('end_date');
        
        if (!$account_no) {
            return new WP_Error('bad_request', 'Account number is required', array('status' => 400));
        }
        
        $where_clause = "WHERE t.account_no = %s";
        $params = array($account_no);
        
        if ($start_date && $end_date) {
            $where_clause .= " AND DATE(t.transaction_date) BETWEEN %s AND %s";
            $params[] = $start_date;
            $params[] = $end_date;
        }
        
        $query = $wpdb->prepare(
            "SELECT 
                DATE(t.transaction_date) as date,
                COUNT(*) as total_transactions,
                SUM(t.amount) as total_earnings,
                COUNT(CASE WHEN t.game_status = 'played' THEN 1 END) as games_played
             FROM `{$wpdb->prefix}device_transactions` t
             {$where_clause}
             GROUP BY DATE(t.transaction_date)
             ORDER BY date DESC
             LIMIT 30",
            ...$params
        );
        
        $report = $wpdb->get_results($query);
        
        return new WP_REST_Response($report, 200);
    }

    /**
     * Export transactions as CSV
     */
    public function export_transactions($request) {
        global $wpdb;
        
        $account_no = $request->get_param('account_no');
        
        if (!$account_no) {
            return new WP_Error('bad_request', 'Account number is required', array('status' => 400));
        }
        
        $transactions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.*, r.location, r.device_serial_number 
                 FROM `{$wpdb->prefix}device_transactions` t
                 LEFT JOIN `{$wpdb->prefix}device_registration` r ON t.device_id = r.device_id
                 WHERE t.account_no = %s
                 ORDER BY t.transaction_date DESC",
                $account_no
            )
        );
        
        // Generate CSV content
        $csv_header = array(
            'Date & Time', 'Transaction Code', 'Device ID', 'Device Location', 
            'Payer Name', 'Phone Number', 'Amount (KSH)', 'Game Status', 'Running Balance'
        );
        
        $csv_content = implode(',', $csv_header) . "\n";
        
        foreach ($transactions as $transaction) {
            $row = array(
                $transaction->transaction_date,
                $transaction->transaction_id,
                $transaction->device_id,
                $transaction->location ?: 'N/A',
                $transaction->payer_name ?: 'N/A',
                $transaction->phone_number ?: 'N/A',
                number_format($transaction->amount, 2),
                $transaction->game_status === 'played' ? 'Played' : 'Not Played',
                number_format($transaction->running_balance, 2)
            );
            
            $csv_content .= '"' . implode('","', $row) . '"' . "\n";
        }
        
        return new WP_REST_Response($csv_content, 200, array(
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="transactions_' . date('Y-m-d') . '.csv"'
        ));
    }

    /**
     * Export withdrawals as CSV
     */
    public function export_withdrawals($request) {
        global $wpdb;
        
        $account_no = $request->get_param('account_no');
        
        if (!$account_no) {
            return new WP_Error('bad_request', 'Account number is required', array('status' => 400));
        }
        
        $withdrawals = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$wpdb->prefix}device_withdrawals` 
                 WHERE account_no = %s
                 ORDER BY withdrawal_date DESC",
                $account_no
            )
        );
        
        // Generate CSV content
        $csv_header = array(
            'Date & Time', 'Transaction Code', 'Amount (KSH)', 'Account Number', 
            'Account Name', 'Payment Method', 'Status', 'Processed Date'
        );
        
        $csv_content = implode(',', $csv_header) . "\n";
        
        foreach ($withdrawals as $withdrawal) {
            $row = array(
                $withdrawal->withdrawal_date,
                $withdrawal->transaction_code,
                number_format($withdrawal->amount, 2),
                $withdrawal->withdrawal_account,
                $withdrawal->account_name,
                $withdrawal->payment_method,
                ucfirst($withdrawal->status),
                $withdrawal->processed_date ?: 'N/A'
            );
            
            $csv_content .= '"' . implode('","', $row) . '"' . "\n";
        }
        
        return new WP_REST_Response($csv_content, 200, array(
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="withdrawals_' . date('Y-m-d') . '.csv"'
        ));
    }

    /**
     * Update device information
     */
    public function update_device_info($request) {
        global $wpdb;
        
        $device_id = $request['id'];
        $params = $request->get_json_params();
        
        // Only allow certain fields to be updated
        $allowed_fields = array('location', 'status');
        $update_data = array();
        $update_format = array();
        
        foreach ($allowed_fields as $field) {
            if (isset($params[$field])) {
                $update_data[$field] = sanitize_text_field($params[$field]);
                $update_format[] = '%s';
            }
        }
        
        if (empty($update_data)) {
            return new WP_Error('bad_request', 'No valid fields to update', array('status' => 400));
        }
        
        $result = $wpdb->update(
            "{$wpdb->prefix}device_registration",
            $update_data,
            array('device_id' => $device_id),
            $update_format,
            array('%d')
        );
        
        if ($result === false) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Failed to update device information'
            ), 500);
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Device information updated successfully'
        ), 200);
    }
}

// Initialize the enhanced API
new Enhanced_PoolTable_Management_API();

// Add activation hook to create tables
register_activation_hook(__FILE__, function() {
    $api = new Enhanced_PoolTable_Management_API();
});
?>
