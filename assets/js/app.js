// Main Application JavaScript for Kentronics TechStar Solutions

// Global variables
let currentUser = null;
let userDevices = [];

// Currency formatting function for KSH
function formatCurrency(amount) {
    if (amount === null || amount === undefined || isNaN(amount)) {
        return 'KSH 0';
    }
    return 'KSH ' + parseFloat(amount).toLocaleString('en-KE', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
    });
}

// Date formatting function
function formatDate(dateString) {
    if (!dateString) return '--';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-KE', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// Date and time formatting function
function formatDateTime(dateString) {
    if (!dateString) return '--';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-KE', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Show alert function
function showAlert(containerId, type, message, autoHide = true) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
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

// Show loading state
function showLoading(elementId, show = true) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    if (show) {
        element.classList.remove('d-none');
    } else {
        element.classList.add('d-none');
    }
}

// Toggle password visibility
function setupPasswordToggle(passwordId, toggleId) {
    const passwordInput = document.getElementById(passwordId);
    const toggleButton = document.getElementById(toggleId);
    
    if (passwordInput && toggleButton) {
        toggleButton.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });
    }
}

// Load user info into navigation
function loadUserInfo() {
    const userData = getCurrentUser();
    if (!userData) return;
    
    const userNameElements = document.querySelectorAll('#userName');
    const userAccountElements = document.querySelectorAll('#userAccountInfo');
    
    userNameElements.forEach(element => {
        element.textContent = userData.name || 'User';
    });
    
    userAccountElements.forEach(element => {
        element.textContent = `Account: ${userData.accountNumber || 'N/A'}`;
    });
}

// Dashboard functions
async function loadDashboardData() {
    try {
        showLoading('devicesLoader', true);
        showLoading('devicesContainer', false);
        
        const userData = getCurrentUser();
        if (!userData) {
            throw new Error('User data not found');
        }
        
        // Get user devices
        const devices = await getUserDevices(userData.accountNumber);
        userDevices = devices;
        
        // Update summary cards
        updateDashboardSummary(devices);
        
        // Update devices table
        updateDevicesTable(devices);
        
        showLoading('devicesLoader', false);
        showLoading('devicesContainer', true);
        
        if (devices.length === 0) {
            showLoading('noDevices', true);
        }
        
    } catch (error) {
        console.error('Error loading dashboard data:', error);
        showLoading('devicesLoader', false);
        showAlert('devicesContainer', 'danger', 'Failed to load dashboard data: ' + error.message);
    }
}

function updateDashboardSummary(devices) {
    let totalBalance = 0;
    let dailyEarnings = 0;
    let totalGames = 0;
    
    devices.forEach(device => {
        totalBalance += parseFloat(device.balance || 0);
        dailyEarnings += parseFloat(device.dailyEarnings || 0);
        totalGames += parseInt(device.dailyGamesPlayed || 0);
    });
    
    document.getElementById('totalBalance').textContent = formatCurrency(totalBalance);
    document.getElementById('dailyEarnings').textContent = formatCurrency(dailyEarnings);
    document.getElementById('totalDevices').textContent = devices.length;
    document.getElementById('gamesPlayed').textContent = totalGames;
}

function updateDevicesTable(devices) {
    const tbody = document.getElementById('devicesTableBody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    devices.forEach(device => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><strong>${device.id}</strong></td>
            <td>${device.serialNumber || 'N/A'}</td>
            <td>${device.location || 'N/A'}</td>
            <td class="text-ksh">${formatCurrency(device.balance)}</td>
            <td class="text-ksh">${formatCurrency(device.dailyEarnings)}</td>
            <td>${device.dailyGamesPlayed || 0}</td>
            <td>
                <span class="badge ${device.status === 'active' ? 'status-active' : 'status-inactive'}">
                    ${device.status || 'Unknown'}
                </span>
            </td>
            <td>${formatDateTime(device.lastActivity)}</td>
        `;
        tbody.appendChild(row);
    });
}

function refreshDevices() {
    const btn = document.getElementById('refreshDevicesBtn');
    if (btn) {
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Refreshing...';
        btn.disabled = true;
        
        setTimeout(() => {
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        }, 2000);
    }
    
    showLoading('devicesLoader', true);
    showLoading('devicesContainer', false);
    loadDashboardData();
}

// Enhanced refresh function with visual feedback
function refreshPage() {
    const currentPage = window.location.pathname;
    
    if (currentPage.includes('dashboard')) {
        refreshDevices();
    } else if (currentPage.includes('transactions')) {
        refreshTransactions();
    } else if (currentPage.includes('withdraw')) {
        loadWithdrawPageData();
    }
    
    // Show refresh indicator
    const refreshButtons = document.querySelectorAll('[onclick*="refresh"]');
    refreshButtons.forEach(btn => {
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Refreshing...';
        btn.disabled = true;
        
        setTimeout(() => {
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        }, 2000);
    });
}

// Transaction functions
async function loadTransactions() {
    try {
        showLoading('transactionsLoader', true);
        showLoading('transactionsContainer', false);
        
        const userData = getCurrentUser();
        if (!userData) {
            throw new Error('User data not found');
        }
        
        // Get all transactions for user devices
        const transactions = await getAllUserTransactions(userData.accountNumber);
        allTransactions = transactions;
        filteredTransactions = transactions;
        
        updateTransactionsTable(transactions);
        updateTransactionsPagination();
        
        showLoading('transactionsLoader', false);
        showLoading('transactionsContainer', true);
        
        if (transactions.length === 0) {
            showLoading('noTransactions', true);
        }
        
    } catch (error) {
        console.error('Error loading transactions:', error);
        showLoading('transactionsLoader', false);
        showAlert('transactionsContainer', 'danger', 'Failed to load transaction data: ' + error.message);
    }
}

async function loadDeviceFilter() {
    try {
        const userData = getCurrentUser();
        if (!userData) return;
        
        const devices = await getUserDevices(userData.accountNumber);
        const deviceFilter = document.getElementById('deviceFilter');
        
        if (deviceFilter) {
            deviceFilter.innerHTML = '<option value="">All Devices</option>';
            devices.forEach(device => {
                const option = document.createElement('option');
                option.value = device.id;
                option.textContent = `Device ${device.id} - ${device.location || 'Unknown Location'}`;
                deviceFilter.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Error loading device filter:', error);
    }
}

function updateTransactionsTable(transactions) {
    const tbody = document.getElementById('transactionsTableBody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    const startIndex = (currentPage - 1) * 10;
    const endIndex = startIndex + 10;
    const paginatedTransactions = transactions.slice(startIndex, endIndex);
    
    paginatedTransactions.forEach(transaction => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${formatDateTime(transaction.transaction_date)}</td>
            <td><code>${transaction.transaction_id}</code></td>
            <td>${transaction.payer_name || 'N/A'}</td>
            <td>${transaction.phone_number || 'N/A'}</td>
            <td class="text-ksh">${formatCurrency(transaction.amount)}</td>
            <td>Device ${transaction.device_id}</td>
            <td>
                <span class="badge ${transaction.game_status === 'played' ? 'status-completed' : 'status-pending'}">
                    ${transaction.game_status === 'played' ? 'Played' : 'Not Played'}
                </span>
            </td>
            <td class="text-ksh">${formatCurrency(transaction.running_balance)}</td>
        `;
        tbody.appendChild(row);
    });
}

function updateTransactionsPagination() {
    const pagination = document.getElementById('transactionsPagination');
    if (!pagination) return;
    
    const totalTransactions = filteredTransactions.length;
    totalPages = Math.ceil(totalTransactions / 10);
    
    pagination.innerHTML = '';
    
    if (totalPages <= 1) return;
    
    // Previous button
    const prevLi = document.createElement('li');
    prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
    prevLi.innerHTML = `<a class="page-link" href="#" onclick="changePage(${currentPage - 1})">Previous</a>`;
    pagination.appendChild(prevLi);
    
    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
        const li = document.createElement('li');
        li.className = `page-item ${i === currentPage ? 'active' : ''}`;
        li.innerHTML = `<a class="page-link" href="#" onclick="changePage(${i})">${i}</a>`;
        pagination.appendChild(li);
    }
    
    // Next button
    const nextLi = document.createElement('li');
    nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
    nextLi.innerHTML = `<a class="page-link" href="#" onclick="changePage(${currentPage + 1})">Next</a>`;
    pagination.appendChild(nextLi);
}

function changePage(page) {
    if (page < 1 || page > totalPages) return;
    currentPage = page;
    updateTransactionsTable(filteredTransactions);
    updateTransactionsPagination();
}

function applyFilters() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const deviceFilter = document.getElementById('deviceFilter').value;
    const statusFilter = document.getElementById('statusFilter').value;
    
    filteredTransactions = allTransactions.filter(transaction => {
        const matchesSearch = !searchTerm || 
            transaction.phone_number?.toLowerCase().includes(searchTerm) ||
            transaction.transaction_id?.toLowerCase().includes(searchTerm);
        
        const matchesDevice = !deviceFilter || transaction.device_id == deviceFilter;
        
        const matchesStatus = !statusFilter || 
            (statusFilter === 'played' && transaction.game_status === 'played') ||
            (statusFilter === 'not_played' && transaction.game_status !== 'played');
        
        return matchesSearch && matchesDevice && matchesStatus;
    });
    
    currentPage = 1;
    updateTransactionsTable(filteredTransactions);
    updateTransactionsPagination();
    
    showLoading('noTransactions', filteredTransactions.length === 0);
    showLoading('transactionsContainer', filteredTransactions.length > 0);
}

function refreshTransactions() {
    loadTransactions();
}

function exportTransactions() {
    // Simple CSV export
    const csvContent = "data:text/csv;charset=utf-8," + 
        "Date,Transaction Code,Payer Name,Phone Number,Amount (KSH),Device,Game Status,Balance\n" +
        filteredTransactions.map(t => 
            `"${formatDateTime(t.transaction_date)}","${t.transaction_id}","${t.payer_name || 'N/A'}","${t.phone_number || 'N/A'}","${t.amount}","Device ${t.device_id}","${t.game_status === 'played' ? 'Played' : 'Not Played'}","${t.running_balance}"`
        ).join("\n");
    
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `transactions_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Withdrawal functions
async function loadWithdrawPageData() {
    try {
        const userData = getCurrentUser();
        if (!userData) return;
        
        // Load balance
        const devices = await getUserDevices(userData.accountNumber);
        const totalBalance = devices.reduce((sum, device) => sum + parseFloat(device.balance || 0), 0);
        currentBalance = totalBalance;
        
        document.getElementById('availableBalance').textContent = formatCurrency(totalBalance);
        document.getElementById('totalDevicesCount').textContent = devices.length;
        
        if (devices.length > 0) {
            const lastUpdate = devices.reduce((latest, device) => {
                const deviceDate = new Date(device.lastActivity);
                return deviceDate > latest ? deviceDate : latest;
            }, new Date(0));
            
            document.getElementById('lastBalanceUpdate').textContent = formatDateTime(lastUpdate);
        }
        
        // Load withdrawal details (mock data - replace with actual API call)
        loadWithdrawalDetails();
        
        // Load recent withdrawals
        loadRecentWithdrawals();
        
    } catch (error) {
        console.error('Error loading withdrawal page data:', error);
        showAlert('withdrawAlert', 'danger', 'Failed to load withdrawal data: ' + error.message);
    }
}

function loadWithdrawalDetails() {
    // Mock withdrawal details - replace with actual API call
    document.getElementById('paymentMethod').textContent = 'M-Pesa';
    document.getElementById('withdrawAccount').textContent = '+254 XXX XXX XXX';
    document.getElementById('withdrawAccountName').textContent = 'Account Holder Name';
}

async function loadRecentWithdrawals() {
    try {
        showLoading('recentWithdrawalsLoader', true);
        
        // Mock recent withdrawals - replace with actual API call
        const recentWithdrawals = []; // await getRecentWithdrawals();
        
        const tbody = document.getElementById('recentWithdrawalsBody');
        if (tbody) {
            tbody.innerHTML = '';
            
            if (recentWithdrawals.length === 0) {
                showLoading('noRecentWithdrawals', true);
            } else {
                recentWithdrawals.forEach(withdrawal => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${formatDate(withdrawal.date)}</td>
                        <td class="text-ksh">${formatCurrency(withdrawal.amount)}</td>
                        <td><code>${withdrawal.transaction_code}</code></td>
                        <td>
                            <span class="badge status-${withdrawal.status}">${withdrawal.status}</span>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
                showLoading('recentWithdrawalsContainer', true);
            }
        }
        
        showLoading('recentWithdrawalsLoader', false);
        
    } catch (error) {
        console.error('Error loading recent withdrawals:', error);
        showLoading('recentWithdrawalsLoader', false);
        showLoading('noRecentWithdrawals', true);
    }
}

function setupWithdrawForm() {
    const form = document.getElementById('withdrawForm');
    const amountInput = document.getElementById('withdrawAmount');
    const confirmAmount = document.getElementById('confirmAmount');
    
    if (amountInput && confirmAmount) {
        amountInput.addEventListener('input', function() {
            const amount = parseFloat(this.value) || 0;
            confirmAmount.textContent = formatCurrency(amount);
        });
    }
    
    if (form) {
        form.addEventListener('submit', handleWithdrawal);
    }
    
    setupPasswordToggle('withdrawPassword', 'toggleWithdrawPassword');
}

async function handleWithdrawal(event) {
    event.preventDefault();
    
    const btn = document.getElementById('withdrawBtn');
    const btnText = btn.querySelector('.btn-text');
    const spinner = btn.querySelector('.spinner-border');
    
    try {
        // Show loading state
        btn.disabled = true;
        btnText.textContent = 'Processing...';
        spinner.classList.remove('d-none');
        
        const formData = new FormData(event.target);
        const amount = parseFloat(formData.get('amount'));
        const password = formData.get('password');
        
        // Validation
        if (amount < 100) {
            throw new Error('Minimum withdrawal amount is KSH 100');
        }
        
        if (amount > currentBalance) {
            throw new Error('Insufficient balance');
        }
        
        if (!password) {
            throw new Error('Password is required');
        }
        
        // Process withdrawal
        const userData = getCurrentUser();
        const result = await processWithdrawal({
            accountNo: userData.accountNumber,
            amount: amount,
            password: password
        });
        
        if (result.success) {
            showAlert('withdrawAlert', 'success', 'Withdrawal request submitted successfully! Your funds will be processed within 24 hours.');
            event.target.reset();
            document.getElementById('confirmAmount').textContent = 'KSH 0';
            loadWithdrawPageData(); // Refresh data
        } else {
            throw new Error(result.message || 'Withdrawal failed');
        }
        
    } catch (error) {
        console.error('Withdrawal error:', error);
        showAlert('withdrawAlert', 'danger', error.message);
    } finally {
        // Reset button state
        btn.disabled = false;
        btnText.textContent = 'Process Withdrawal';
        spinner.classList.add('d-none');
    }
}

function setWithdrawAmount(percentage) {
    const amountInput = document.getElementById('withdrawAmount');
    const confirmAmount = document.getElementById('confirmAmount');
    
    if (amountInput && confirmAmount) {
        const amount = Math.floor(currentBalance * percentage);
        amountInput.value = amount;
        confirmAmount.textContent = formatCurrency(amount);
    }
}

// Support functions
function setupSupportForm() {
    const form = document.getElementById('supportForm');
    const messageTextarea = document.getElementById('supportMessage');
    const messageCount = document.getElementById('messageCount');
    
    if (messageTextarea && messageCount) {
        messageTextarea.addEventListener('input', function() {
            const count = this.value.length;
            messageCount.textContent = count;
            
            if (count > 1000) {
                messageCount.style.color = 'var(--danger-red)';
                this.value = this.value.substring(0, 1000);
                messageCount.textContent = '1000';
            } else {
                messageCount.style.color = '';
            }
        });
    }
    
    if (form) {
        form.addEventListener('submit', handleSupportSubmission);
    }
}

function prefillUserData() {
    const userData = getCurrentUser();
    if (!userData) return;
    
    const nameInput = document.getElementById('supportName');
    const phoneInput = document.getElementById('supportPhone');
    const accountInput = document.getElementById('supportAccount');
    
    if (nameInput) nameInput.value = userData.name || '';
    if (phoneInput) phoneInput.value = userData.phoneNumber || '';
    if (accountInput) accountInput.value = userData.accountNumber || '';
}

async function handleSupportSubmission(event) {
    event.preventDefault();
    
    const btn = document.getElementById('supportBtn');
    const btnText = btn.querySelector('.btn-text');
    const spinner = btn.querySelector('.spinner-border');
    
    try {
        // Show loading state
        btn.disabled = true;
        btnText.textContent = 'Sending...';
        spinner.classList.remove('d-none');
        
        const formData = new FormData(event.target);
        const supportData = {
            name: formData.get('name'),
            email: formData.get('email'),
            phone: formData.get('phone'),
            accountNumber: formData.get('accountNumber'),
            category: formData.get('category'),
            subject: formData.get('subject'),
            message: formData.get('message'),
            priority: formData.get('priority')
        };
        
        // Submit support ticket
        const result = await submitSupportTicket(supportData);
        
        if (result.success) {
            showAlert('supportAlert', 'success', 'Your message has been sent successfully! We will respond within 4 hours during business hours.');
            event.target.reset();
            document.getElementById('messageCount').textContent = '0';
        } else {
            throw new Error(result.message || 'Failed to send message');
        }
        
    } catch (error) {
        console.error('Support submission error:', error);
        showAlert('supportAlert', 'danger', 'Failed to send message: ' + error.message);
    } finally {
        // Reset button state
        btn.disabled = false;
        btnText.textContent = 'Send Message';
        spinner.classList.add('d-none');
    }
}

// Utility functions
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Initialize common functionality
document.addEventListener('DOMContentLoaded', function() {
    // Setup password toggles
    setupPasswordToggle('password', 'togglePassword');
    setupPasswordToggle('withdrawPassword', 'toggleWithdrawPassword');
    
    // Setup responsive navigation
    const navbarToggler = document.querySelector('.navbar-toggler');
    const navbarCollapse = document.querySelector('.navbar-collapse');
    
    if (navbarToggler && navbarCollapse) {
        document.addEventListener('click', function(event) {
            if (!navbarToggler.contains(event.target) && !navbarCollapse.contains(event.target)) {
                const bsCollapse = bootstrap.Collapse.getInstance(navbarCollapse);
                if (bsCollapse && navbarCollapse.classList.contains('show')) {
                    bsCollapse.hide();
                }
            }
        });
    }
});
