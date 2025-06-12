<?php
/**
 * Plugin Name: Pool Table Management API
 * Description: Custom REST API endpoints for the Pool Table Management Android app
 * Version: 1.0
 * Author: Kenedy_Kentronics
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class PoolTable_Management_API {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        $this->create_transactions_table();
    }

    /**
     * Create transactions table if it doesn't exist
     */
    private function create_transactions_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'device_transactions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            account_no varchar(50) NOT NULL,
            device_id varchar(20) NOT NULL,
            transaction_id varchar(50) NOT NULL,
            amount decimal(10,2) NOT NULL,
            running_balance decimal(10,2) NOT NULL,
            payer_name varchar(100) NOT NULL,
            phone_number varchar(20) NOT NULL,
            transaction_date timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY account_no (account_no),
            KEY device_id (device_id),
            KEY transaction_id (transaction_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        if ($wpdb->last_error) {
            error_log('Pool Table Plugin - Database Error: ' . $wpdb->last_error);
        }
    }

    public function register_routes() {
        register_rest_route('pooltable/v1', '/login', array(
            'methods' => 'POST',
            'callback' => array($this, 'login_user'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route('pooltable/v1', '/users/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_user_data'),
            'permission_callback' => array($this, 'check_api_auth')
        ));

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

        register_rest_route('pooltable/v1', '/withdraw', array(
            'methods' => 'POST',
            'callback' => array($this, 'withdraw_funds'),
            'permission_callback' => array($this, 'check_api_auth')
        ));

        register_rest_route('pooltable/v1', '/devices/(?P<id>\d+)/transactions', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_device_transactions'),
            'permission_callback' => array($this, 'check_api_auth')
        ));

        register_rest_route('pooltable/v1', '/devices/(?P<id>\d+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'update_device_info'),
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
        // Verify token logic here
        return true;
    }
    
    /**
     * Login user endpoint
     */
    public function login_user($request) {
        global $wpdb;
        
        $params = $request->get_json_params();
        $account_no = sanitize_text_field($params['accountId']);
        $password = sanitize_text_field($params['password']);
        
        // Query the wp_device_registration table for the user
        $query = $wpdb->prepare("SELECT * FROM `{$wpdb->prefix}device_registration` WHERE account_no = %s", $account_no);
        $user = $wpdb->get_row($query);
        if (!$user) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'User not found. Debug: ' . $wpdb->last_error
            ), 401);
        }
        
        // Verify password - in a real implementation you would use password_verify()
        // This is just a placeholder
        if ($password !== $user->password) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Invalid credentials'
            ), 401);
        }
        
        // Generate a token
        $token = bin2hex(random_bytes(32));
        
        // In a real implementation, you would store this token
        
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
     * Get user data with devices
     */
    public function get_user_data($request) {
        global $wpdb;
        
        $device_id = $request['id'];
        
        // Get user from database
        $user = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM `{$wpdb->prefix}device_registration` WHERE device_id = %d", $device_id)
        );
        
        if (!$user) {
            return new WP_Error('not_found', 'User not found', array('status' => 404));
        }
        
        // Get user's devices (for this account number)
        $account_no = $user->account_no;
        $devices = $this->get_user_devices_with_balances($account_no);
        
        return new WP_REST_Response(array(
            'id' => $user->device_id,
            'name' => $user->owner_name,
            'accountNumber' => $user->account_no,
            'phoneNumber' => $user->owner_number,
            'serialNumber' => $user->device_serial_number,
            'devices' => $devices
        ), 200);
    }
    
    /**
     * Get user devices
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
     * Helper function to get user devices with balances
     */
    private function get_user_devices_with_balances($account_no) {
        global $wpdb;
        
        // Get devices registered to the account
        $devices_query = $wpdb->prepare(
            "SELECT r.device_id, r.device_serial_number, r.owner_name, r.location, r.account_no, 
                    r.date_of_registration, r.status, 
                    COALESCE(b.balance, 0) as balance, COALESCE(b.games_paid, 0) as games_paid,
                    COALESCE(b.daily_earnings, 0) as daily_earnings, COALESCE(b.daily_gamespaid, 0) as daily_gamespaid,
                    b.last_updated
             FROM `{$wpdb->prefix}device_registration` r
             LEFT JOIN `{$wpdb->prefix}device_balances` b ON r.device_id = b.device_id
             WHERE r.account_no = %s",
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
                'balance' => (float) $device->balance,
                'gamesPlayed' => (int) $device->games_paid,
                'dailyEarnings' => (float) $device->daily_earnings,
                'dailyGamesPlayed' => (int) $device->daily_gamespaid,
                'accountNumber' => $device->account_no,
                'registrationDate' => $device->date_of_registration,
                'lastActivity' => $device->last_updated,
                'status' => $device->status
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
        
        if ($balance === null) {
            $balance = 0;
        }
        
        return new WP_REST_Response((float) $balance, 200);
    }
    
    /**
     * Withdraw funds
     */
    public function withdraw_funds($request) {
        global $wpdb;
        
        $params = $request->get_json_params();
        $account_no = sanitize_text_field($params['accountNo']);
        $amount = (float) $params['amount'];
        
        if ($amount <= 0) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Invalid withdrawal amount'
            ), 400);
        }
        
        // Get total balance for the account
        $total_balance = $wpdb->get_var(
            $wpdb->prepare("SELECT SUM(b.balance) FROM `{$wpdb->prefix}device_balances` b
                             JOIN `{$wpdb->prefix}device_registration` r ON b.device_id = r.device_id
                             WHERE r.account_no = %s", $account_no)
        );
        
        if ($total_balance < $amount) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Insufficient funds'
            ), 400);
        }
        
        // Start a transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Get devices with balances
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
                    
                    // Update device balance
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
            
            // Record the withdrawal transaction
            $transaction_id = 'WD' . time() . rand(1000, 9999);
            $device_owner = $wpdb->get_row(
                $wpdb->prepare("SELECT owner_name, owner_number FROM `{$wpdb->prefix}device_registration` 
                               WHERE account_no = %s LIMIT 1", $account_no)
            );
            
            $wpdb->insert(
                "{$wpdb->prefix}device_transactions",
                array(
                    'account_no' => $account_no,
                    'device_id' => $device->device_id,
                    'transaction_id' => $transaction_id,
                    'amount' => -$withdrawal_from_device, // Negative for withdrawals
                    'running_balance' => $new_balance,
                    'payer_name' => $device_owner->owner_name,
                    'phone_number' => $device_owner->owner_number
                ),
                array('%s', '%d', '%s', '%f', '%f', '%s', '%s')
            );
            
            $wpdb->query('COMMIT');
            
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Withdrawal successful',
                'amount' => $amount
            ), 200);
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Withdrawal failed: ' . $e->getMessage()
            ), 500);
        }
    }
    
    /**
     * Update device info
     */
    public function update_device_info($request) {
        global $wpdb;
        
        $device_id = $request['id'];
        $params = $request->get_json_params();
        
        // Check if device exists
        $device_exists = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM `{$wpdb->prefix}device_registration` WHERE device_id = %d", $device_id)
        );
        
        if (!$device_exists) {
            return new WP_Error('not_found', 'Device not found', array('status' => 404));
        }
        
        // Update device data
        $device_data = array();
        
        if (isset($params['name'])) {
            $device_data['owner_name'] = sanitize_text_field($params['name']);
        }
        
        if (isset($params['location'])) {
            $device_data['location'] = sanitize_text_field($params['location']);
        }
        
        if (isset($params['status'])) {
            $device_data['status'] = sanitize_text_field($params['status']);
        }
        
        if (isset($params['phoneNumber'])) {
            $device_data['owner_number'] = sanitize_text_field($params['phoneNumber']);
        }
        
        // Only update if there's data to update
        if (!empty($device_data)) {
            $wpdb->update(
                "{$wpdb->prefix}device_registration",
                $device_data,
                array('device_id' => $device_id)
            );
        }
        
        // If balance is provided, update the balance
        if (isset($params['balance']) || isset($params['gamesPlayed']) || isset($params['dailyEarnings']) || isset($params['dailyGamesPlayed'])) {
            
            $balance_data = array();
            
            if (isset($params['balance'])) {
                $balance_data['balance'] = (float) $params['balance'];
            }
            
            if (isset($params['gamesPlayed'])) {
                $balance_data['games_paid'] = (int) $params['gamesPlayed'];
            }
            
            if (isset($params['dailyEarnings'])) {
                $balance_data['daily_earnings'] = (float) $params['dailyEarnings'];
            }
            
            if (isset($params['dailyGamesPlayed'])) {
                $balance_data['daily_gamespaid'] = (int) $params['dailyGamesPlayed'];
            }
            
            // Check if balance record exists
            $balance_exists = $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM `{$wpdb->prefix}device_balances` WHERE device_id = %d", $device_id)
            );
            
            if ($balance_exists) {
                // Update existing balance
                $wpdb->update(
                    "{$wpdb->prefix}device_balances",
                    $balance_data,
                    array('device_id' => $device_id)
                );
            } else if (!empty($balance_data)) {
                // Insert new balance record
                $balance_data['device_id'] = $device_id;
                $wpdb->insert("{$wpdb->prefix}device_balances", $balance_data);
            }
        }
        
        // Get updated device data
        $device = $this->get_device_by_id($device_id);
        
        return new WP_REST_Response($device, 200);
    }
    
    /**
     * Helper function to get device by ID
     */
    /**
     * Get device transactions
     */
    public function get_device_transactions($request) {
        global $wpdb;
        
        $device_id = $request['id'];
        $search = $request->get_param('search');
        
        $query = "SELECT t.*, DATE(t.transaction_date) as date
                 FROM `{$wpdb->prefix}device_transactions` t
                 WHERE t.device_id = %d";
        $params = array($device_id);
        
        if ($search) {
            $query .= " AND (t.phone_number LIKE %s OR t.transaction_id LIKE %s)";
            $search_param = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        $query .= " ORDER BY t.transaction_date DESC";
        
        $transactions = $wpdb->get_results($wpdb->prepare($query, $params));
        
        // Group transactions by date
        $grouped_transactions = array();
        foreach ($transactions as $transaction) {
            $date = $transaction->date;
            if (!isset($grouped_transactions[$date])) {
                $grouped_transactions[$date] = array();
            }
            
            $grouped_transactions[$date][] = array(
                'transactionId' => $transaction->transaction_id,
                'amount' => (float) $transaction->amount,
                'runningBalance' => (float) $transaction->running_balance,
                'payerName' => $transaction->payer_name,
                'phoneNumber' => $transaction->phone_number,
                'timestamp' => $transaction->transaction_date
            );
        }
        
        return new WP_REST_Response($grouped_transactions, 200);
    }
    
    private function get_device_by_id($device_id) {
        global $wpdb;
        
        $device_query = $wpdb->prepare(
            "SELECT r.device_id, r.device_serial_number, r.owner_name, r.location, r.account_no, 
                   r.date_of_registration, r.status, r.owner_number,
                   COALESCE(b.balance, 0) as balance, COALESCE(b.games_paid, 0) as games_paid,
                   COALESCE(b.daily_earnings, 0) as daily_earnings, COALESCE(b.daily_gamespaid, 0) as daily_gamespaid,
                   b.last_updated
            FROM `{$wpdb->prefix}device_registration` r
            LEFT JOIN `{$wpdb->prefix}device_balances` b ON r.device_id = b.device_id
            WHERE r.device_id = %d",
            $device_id
        );
        
        $device = $wpdb->get_row($device_query);
        
        if (!$device) {
            return null;
        }
        
        return array(
            'id' => $device->device_id,
            'name' => $device->owner_name,
            'serialNumber' => $device->device_serial_number,
            'location' => $device->location,
            'balance' => (float) $device->balance,
            'gamesPlayed' => (int) $device->games_paid,
            'dailyEarnings' => (float) $device->daily_earnings,
            'dailyGamesPlayed' => (int) $device->daily_gamespaid,
            'accountNumber' => $device->account_no,
            'phoneNumber' => $device->owner_number,
            'registrationDate' => $device->date_of_registration,
            'lastActivity' => $device->last_updated,
            'status' => $device->status
        );
    }
}

// Initialize the plugin
new PoolTable_Management_API();
?>