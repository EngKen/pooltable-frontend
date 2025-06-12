// Authentication JavaScript for Kentronics TechStar Solutions

// API Base URL - Update this to match your WordPress site URL
const API_BASE_URL = window.location.protocol + '//' + window.location.hostname + '/wp-json/pooltable/v1';

// Authentication state
let authToken = null;
let authCurrentUser = null;

// Initialize authentication on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeAuth();
});

// Initialize authentication
function initializeAuth() {
    // Load stored authentication data
    authToken = localStorage.getItem('pooltable_auth_token');
    const userData = localStorage.getItem('pooltable_user_data');
    
    if (userData) {
        try {
            authCurrentUser = JSON.parse(userData);
        } catch (error) {
            console.error('Error parsing user data:', error);
            clearAuthData();
        }
    }
    
    // Setup login form if it exists
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    }
}

// Handle login form submission
async function handleLogin(event) {
    event.preventDefault();
    
    const btn = document.getElementById('loginBtn');
    const btnText = btn.querySelector('.btn-text');
    const spinner = btn.querySelector('.spinner-border');
    
    try {
        // Show loading state
        btn.disabled = true;
        btnText.textContent = 'Signing in...';
        spinner.classList.remove('d-none');
        
        // Clear previous alerts
        document.getElementById('loginAlert').innerHTML = '';
        
        // Get form data
        const formData = new FormData(event.target);
        const credentials = {
            accountId: formData.get('accountId').trim(),
            password: formData.get('password')
        };
        
        // Validate input
        if (!credentials.accountId || !credentials.password) {
            throw new Error('Please enter both account ID and password');
        }
        
        // Attempt login
        const response = await fetch(`${API_BASE_URL}/login`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(credentials)
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.message || `Login failed: ${response.status}`);
        }
        
        if (data.success && data.token && data.user) {
            // Store authentication data
            authToken = data.token;
            authCurrentUser = data.user;
            
            localStorage.setItem('pooltable_auth_token', authToken);
            localStorage.setItem('pooltable_user_data', JSON.stringify(authCurrentUser));
            
            // Show success message
            showAlert('loginAlert', 'success', 'Login successful! Redirecting to dashboard...', false);
            
            // Redirect to dashboard
            setTimeout(() => {
                window.location.href = 'dashboard.html';
            }, 1500);
            
        } else {
            throw new Error(data.message || 'Login failed - Invalid response format');
        }
        
    } catch (error) {
        console.error('Login error:', error);
        showAlert('loginAlert', 'danger', error.message);
    } finally {
        // Reset button state
        btn.disabled = false;
        btnText.textContent = 'Sign In';
        spinner.classList.add('d-none');
    }
}

// Check if user is authenticated
function isAuthenticated() {
    return authToken && authCurrentUser;
}

// Get current user data
function getCurrentUser() {
    return authCurrentUser;
}

// Get auth token
function getAuthToken() {
    return authToken;
}

// Clear authentication data
function clearAuthData() {
    authToken = null;
    authCurrentUser = null;
    localStorage.removeItem('pooltable_auth_token');
    localStorage.removeItem('pooltable_user_data');
}

// Logout function
function logout() {
    clearAuthData();
    
    // Show logout message
    if (typeof showAlert === 'function') {
        showAlert('mainAlert', 'info', 'You have been logged out successfully.');
    }
    
    // Redirect to login page
    setTimeout(() => {
        window.location.href = 'login.html';
    }, 1000);
}

// Make authenticated API request
async function makeAuthenticatedRequest(url, options = {}) {
    if (!authToken) {
        throw new Error('No authentication token available');
    }
    
    const defaultOptions = {
        headers: {
            'Authorization': `Bearer ${authToken}`,
            'Content-Type': 'application/json',
            ...options.headers
        }
    };
    
    const requestOptions = {
        ...options,
        headers: defaultOptions.headers
    };
    
    try {
        const response = await fetch(url, requestOptions);
        
        // Check if token is still valid
        if (response.status === 401) {
            // Token expired or invalid
            clearAuthData();
            window.location.href = 'login.html';
            throw new Error('Session expired. Please login again.');
        }
        
        return response;
        
    } catch (error) {
        // Handle network errors
        if (error.name === 'TypeError' && error.message.includes('fetch')) {
            throw new Error('Network error. Please check your internet connection.');
        }
        throw error;
    }
}

// Redirect to login if not authenticated (for protected pages)
function requireAuth() {
    if (!isAuthenticated()) {
        window.location.href = 'login.html';
        return false;
    }
    return true;
}

// Auto-refresh token (if needed)
function scheduleTokenRefresh() {
    // Implementation for token refresh if your API supports it
    // This is a placeholder for future implementation
    setInterval(() => {
        if (isAuthenticated()) {
            // Attempt to refresh token
            refreshAuthToken().catch(error => {
                console.warn('Token refresh failed:', error);
            });
        }
    }, 30 * 60 * 1000); // Every 30 minutes
}

// Refresh authentication token
async function refreshAuthToken() {
    // This is a placeholder - implement if your API supports token refresh
    try {
        const response = await makeAuthenticatedRequest(`${API_BASE_URL}/refresh-token`, {
            method: 'POST'
        });
        
        if (response.ok) {
            const data = await response.json();
            if (data.token) {
                authToken = data.token;
                localStorage.setItem('pooltable_auth_token', authToken);
            }
        }
    } catch (error) {
        console.error('Token refresh error:', error);
        // Don't throw error to avoid disrupting user experience
    }
}

// Handle session expiry
function handleSessionExpiry() {
    showAlert('mainAlert', 'warning', 'Your session has expired. Please login again.');
    setTimeout(() => {
        logout();
    }, 3000);
}

// Session timeout warning
let sessionWarningTimer = null;
let sessionTimeoutTimer = null;

function startSessionTimers() {
    if (!isAuthenticated()) return;
    
    // Clear existing timers
    if (sessionWarningTimer) clearTimeout(sessionWarningTimer);
    if (sessionTimeoutTimer) clearTimeout(sessionTimeoutTimer);
    
    // Warning at 25 minutes
    sessionWarningTimer = setTimeout(() => {
        if (confirm('Your session will expire in 5 minutes. Do you want to continue?')) {
            // User wants to continue, restart timers
            startSessionTimers();
        }
    }, 25 * 60 * 1000);
    
    // Timeout at 30 minutes
    sessionTimeoutTimer = setTimeout(() => {
        handleSessionExpiry();
    }, 30 * 60 * 1000);
}

// Reset session timers on user activity
function resetSessionTimers() {
    if (isAuthenticated()) {
        startSessionTimers();
    }
}

// Listen for user activity to reset session timers
document.addEventListener('click', resetSessionTimers);
document.addEventListener('keypress', resetSessionTimers);
document.addEventListener('scroll', resetSessionTimers);

// Start session management
if (isAuthenticated()) {
    startSessionTimers();
    scheduleTokenRefresh();
}

// Show alert function (if not already defined in app.js)
if (typeof showAlert === 'undefined') {
    function showAlert(containerId, type, message, autoHide = true) {
        const container = document.getElementById(containerId);
        if (!container) {
            console.warn(`Alert container '${containerId}' not found`);
            return;
        }
        
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        container.innerHTML = alertHtml;
        
        if (autoHide) {
            setTimeout(() => {
                const alert = container.querySelector('.alert');
                if (alert) {
                    alert.classList.remove('show');
                    setTimeout(() => {
                        container.innerHTML = '';
                    }, 150);
                }
            }, 5000);
        }
    }
}

// Export functions for use in other scripts
window.authAPI = {
    isAuthenticated,
    getCurrentUser,
    getAuthToken,
    logout,
    makeAuthenticatedRequest,
    requireAuth,
    clearAuthData
};
