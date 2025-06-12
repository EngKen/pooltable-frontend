// API JavaScript for Kentronics TechStar Solutions Pool Table Management

// API Configuration
const API_CONFIG = {
    baseUrl: 'https://www.kentronicssolutions.com/wp-json/pooltable/v1',
    timeout: 30000, // 30 seconds
    retryAttempts: 3,
    retryDelay: 1000 // 1 second
};

// API Error handling
class APIError extends Error {
    constructor(message, status = null, response = null) {
        super(message);
        this.name = 'APIError';
        this.status = status;
        this.response = response;
    }
}

// Generic API request function with retry logic
async function makeAPIRequest(endpoint, options = {}) {
    const url = `${API_CONFIG.baseUrl}${endpoint}`;
    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json',
        },
        timeout: API_CONFIG.timeout,
        ...options
    };

    // Add authentication header if token exists
    const token = getAuthToken();
    if (token) {
        defaultOptions.headers['Authorization'] = `Bearer ${token}`;
    }

    let lastError;
    
    for (let attempt = 1; attempt <= API_CONFIG.retryAttempts; attempt++) {
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), API_CONFIG.timeout);
            
            const response = await fetch(url, {
                ...defaultOptions,
                signal: controller.signal
            });
            
            clearTimeout(timeoutId);
            
            // Handle different response types
            let responseData;
            const contentType = response.headers.get('content-type');
            
            if (contentType && contentType.includes('application/json')) {
                responseData = await response.json();
            } else {
                responseData = await response.text();
            }
            
            // Handle HTTP errors
            if (!response.ok) {
                const errorMessage = responseData?.message || responseData || `HTTP ${response.status}: ${response.statusText}`;
                
                // Handle authentication errors
                if (response.status === 401) {
                    clearAuthData();
                    window.location.href = 'login.html';
                    throw new APIError('Session expired. Please login again.', response.status, responseData);
                }
                
                throw new APIError(errorMessage, response.status, responseData);
            }
            
            return responseData;
            
        } catch (error) {
            lastError = error;
            
            // Don't retry on authentication errors or client errors (4xx)
            if (error.status >= 400 && error.status < 500) {
                throw error;
            }
            
            // Don't retry on the last attempt
            if (attempt === API_CONFIG.retryAttempts) {
                throw error;
            }
            
            // Wait before retrying
            await new Promise(resolve => setTimeout(resolve, API_CONFIG.retryDelay * attempt));
        }
    }
    
    throw lastError;
}

// User Authentication APIs
async function loginUser(credentials) {
    try {
        const response = await makeAPIRequest('/login', {
            method: 'POST',
            body: JSON.stringify(credentials)
        });
        
        return response;
    } catch (error) {
        console.error('Login API error:', error);
        throw new APIError(`Login failed: ${error.message}`);
    }
}

// User Data APIs
async function getUserData(userId) {
    try {
        const response = await makeAPIRequest(`/users/${userId}`);
        return response;
    } catch (error) {
        console.error('Get user data error:', error);
        throw new APIError(`Failed to get user data: ${error.message}`);
    }
}

async function getUserDevices(accountNumber) {
    try {
        const response = await makeAPIRequest(`/devices?account_no=${encodeURIComponent(accountNumber)}`);
        return Array.isArray(response) ? response : [];
    } catch (error) {
        console.error('Get user devices error:', error);
        throw new APIError(`Failed to get user devices: ${error.message}`);
    }
}

async function getDeviceBalance(deviceId) {
    try {
        const response = await makeAPIRequest(`/devices/${deviceId}/balance`);
        return parseFloat(response) || 0;
    } catch (error) {
        console.error('Get device balance error:', error);
        throw new APIError(`Failed to get device balance: ${error.message}`);
    }
}

// Transaction APIs
async function getAllUserTransactions(accountNumber, page = 1, limit = 100) {
    try {
        const response = await makeAPIRequest(`/transactions?account_no=${encodeURIComponent(accountNumber)}&page=${page}&limit=${limit}`);
        return Array.isArray(response) ? response : [];
    } catch (error) {
        console.error('Get transactions error:', error);
        throw new APIError(`Failed to get transactions: ${error.message}`);
    }
}

async function getDeviceTransactions(deviceId, page = 1, limit = 50) {
    try {
        const response = await makeAPIRequest(`/devices/${deviceId}/transactions?page=${page}&limit=${limit}`);
        return Array.isArray(response) ? response : [];
    } catch (error) {
        console.error('Get device transactions error:', error);
        throw new APIError(`Failed to get device transactions: ${error.message}`);
    }
}

async function searchTransactions(searchQuery, accountNumber) {
    try {
        const response = await makeAPIRequest(`/transactions/search?q=${encodeURIComponent(searchQuery)}&account_no=${encodeURIComponent(accountNumber)}`);
        return Array.isArray(response) ? response : [];
    } catch (error) {
        console.error('Search transactions error:', error);
        throw new APIError(`Failed to search transactions: ${error.message}`);
    }
}

// Withdrawal APIs
async function processWithdrawal(withdrawalData) {
    try {
        const response = await makeAPIRequest('/withdraw', {
            method: 'POST',
            body: JSON.stringify(withdrawalData)
        });
        
        return response;
    } catch (error) {
        console.error('Process withdrawal error:', error);
        throw new APIError(`Withdrawal failed: ${error.message}`);
    }
}

async function getWithdrawalHistory(accountNumber, page = 1, limit = 50) {
    try {
        const response = await makeAPIRequest(`/withdrawals?account_no=${encodeURIComponent(accountNumber)}&page=${page}&limit=${limit}`);
        return Array.isArray(response) ? response : [];
    } catch (error) {
        console.error('Get withdrawal history error:', error);
        throw new APIError(`Failed to get withdrawal history: ${error.message}`);
    }
}

async function getWithdrawalDetails(accountNumber) {
    try {
        const response = await makeAPIRequest(`/withdrawal-details?account_no=${encodeURIComponent(accountNumber)}`);
        return response || {};
    } catch (error) {
        console.error('Get withdrawal details error:', error);
        throw new APIError(`Failed to get withdrawal details: ${error.message}`);
    }
}

async function getWithdrawalSummary(accountNumber) {
    try {
        const response = await makeAPIRequest(`/withdrawal-summary?account_no=${encodeURIComponent(accountNumber)}`);
        return response || {};
    } catch (error) {
        console.error('Get withdrawal summary error:', error);
        throw new APIError(`Failed to get withdrawal summary: ${error.message}`);
    }
}

// Support APIs
async function submitSupportTicket(supportData) {
    try {
        const response = await makeAPIRequest('/support', {
            method: 'POST',
            body: JSON.stringify(supportData)
        });
        
        return response;
    } catch (error) {
        console.error('Submit support ticket error:', error);
        throw new APIError(`Failed to submit support ticket: ${error.message}`);
    }
}

async function getSupportTickets(accountNumber) {
    try {
        const response = await makeAPIRequest(`/support?account_no=${encodeURIComponent(accountNumber)}`);
        return Array.isArray(response) ? response : [];
    } catch (error) {
        console.error('Get support tickets error:', error);
        throw new APIError(`Failed to get support tickets: ${error.message}`);
    }
}

// Device Management APIs
async function updateDeviceInfo(deviceId, deviceData) {
    try {
        const response = await makeAPIRequest(`/devices/${deviceId}`, {
            method: 'POST',
            body: JSON.stringify(deviceData)
        });
        
        return response;
    } catch (error) {
        console.error('Update device info error:', error);
        throw new APIError(`Failed to update device info: ${error.message}`);
    }
}

// Statistics APIs
async function getDashboardStats(accountNumber) {
    try {
        const response = await makeAPIRequest(`/stats/dashboard?account_no=${encodeURIComponent(accountNumber)}`);
        return response || {};
    } catch (error) {
        console.error('Get dashboard stats error:', error);
        throw new APIError(`Failed to get dashboard statistics: ${error.message}`);
    }
}

async function getEarningsReport(accountNumber, startDate, endDate) {
    try {
        const params = new URLSearchParams({
            account_no: accountNumber,
            start_date: startDate,
            end_date: endDate
        });
        
        const response = await makeAPIRequest(`/reports/earnings?${params.toString()}`);
        return response || {};
    } catch (error) {
        console.error('Get earnings report error:', error);
        throw new APIError(`Failed to get earnings report: ${error.message}`);
    }
}

// Data Export APIs
async function exportTransactionsCSV(accountNumber, filters = {}) {
    try {
        const params = new URLSearchParams({
            account_no: accountNumber,
            format: 'csv',
            ...filters
        });
        
        const response = await makeAPIRequest(`/export/transactions?${params.toString()}`, {
            headers: {
                'Accept': 'text/csv'
            }
        });
        
        return response;
    } catch (error) {
        console.error('Export transactions error:', error);
        throw new APIError(`Failed to export transactions: ${error.message}`);
    }
}

async function exportWithdrawalsCSV(accountNumber, filters = {}) {
    try {
        const params = new URLSearchParams({
            account_no: accountNumber,
            format: 'csv',
            ...filters
        });
        
        const response = await makeAPIRequest(`/export/withdrawals?${params.toString()}`, {
            headers: {
                'Accept': 'text/csv'
            }
        });
        
        return response;
    } catch (error) {
        console.error('Export withdrawals error:', error);
        throw new APIError(`Failed to export withdrawals: ${error.message}`);
    }
}

// Utility functions for API responses
function handleAPIError(error) {
    console.error('API Error:', error);
    
    if (error instanceof APIError) {
        return {
            success: false,
            message: error.message,
            status: error.status
        };
    }
    
    // Handle network errors
    if (error.name === 'TypeError' && error.message.includes('fetch')) {
        return {
            success: false,
            message: 'Network error. Please check your internet connection and try again.'
        };
    }
    
    // Handle timeout errors
    if (error.name === 'AbortError') {
        return {
            success: false,
            message: 'Request timed out. Please try again.'
        };
    }
    
    return {
        success: false,
        message: 'An unexpected error occurred. Please try again.'
    };
}

// Connection test function
async function testAPIConnection() {
    try {
        const response = await fetch(`${API_CONFIG.baseUrl}/health`, {
            method: 'GET',
            timeout: 5000
        });
        
        return response.ok;
    } catch (error) {
        console.error('API connection test failed:', error);
        return false;
    }
}

// Data validation utilities
function validateTransactionData(transaction) {
    const required = ['transaction_id', 'amount', 'device_id'];
    const missing = required.filter(field => !transaction[field]);
    
    if (missing.length > 0) {
        throw new Error(`Missing required transaction fields: ${missing.join(', ')}`);
    }
    
    if (isNaN(parseFloat(transaction.amount))) {
        throw new Error('Invalid transaction amount');
    }
    
    return true;
}

function validateWithdrawalData(withdrawal) {
    const required = ['accountNo', 'amount', 'password'];
    const missing = required.filter(field => !withdrawal[field]);
    
    if (missing.length > 0) {
        throw new Error(`Missing required withdrawal fields: ${missing.join(', ')}`);
    }
    
    const amount = parseFloat(withdrawal.amount);
    if (isNaN(amount) || amount <= 0) {
        throw new Error('Invalid withdrawal amount');
    }
    
    if (amount < 100) {
        throw new Error('Minimum withdrawal amount is KSH 100');
    }
    
    return true;
}

function validateSupportData(support) {
    const required = ['name', 'phone', 'category', 'subject', 'message'];
    const missing = required.filter(field => !support[field]);
    
    if (missing.length > 0) {
        throw new Error(`Missing required support fields: ${missing.join(', ')}`);
    }
    
    if (support.message.length > 1000) {
        throw new Error('Message cannot exceed 1000 characters');
    }
    
    return true;
}

// Currency formatting for API responses
function formatAPIAmount(amount) {
    const numAmount = parseFloat(amount);
    if (isNaN(numAmount)) return 0;
    return Math.round(numAmount * 100) / 100; // Round to 2 decimal places
}

// Date formatting for API requests
function formatAPIDate(date) {
    if (date instanceof Date) {
        return date.toISOString().split('T')[0];
    }
    return date;
}

// Export API functions for global use
window.poolTableAPI = {
    // Authentication
    loginUser,
    
    // User data
    getUserData,
    getUserDevices,
    getDeviceBalance,
    
    // Transactions
    getAllUserTransactions,
    getDeviceTransactions,
    searchTransactions,
    
    // Withdrawals
    processWithdrawal,
    getWithdrawalHistory,
    getWithdrawalDetails,
    getWithdrawalSummary,
    
    // Support
    submitSupportTicket,
    getSupportTickets,
    
    // Device management
    updateDeviceInfo,
    
    // Statistics
    getDashboardStats,
    getEarningsReport,
    
    // Export
    exportTransactionsCSV,
    exportWithdrawalsCSV,
    
    // Utilities
    handleAPIError,
    testAPIConnection,
    validateTransactionData,
    validateWithdrawalData,
    validateSupportData,
    formatAPIAmount,
    formatAPIDate
};

// Auto-test API connection on load
document.addEventListener('DOMContentLoaded', function() {
    testAPIConnection().then(isConnected => {
        if (!isConnected) {
            console.warn('API connection test failed. Some features may not work properly.');
        }
    });
});
