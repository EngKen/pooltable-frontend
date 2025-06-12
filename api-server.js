// Simple Express.js server to simulate the WordPress API
const express = require('express');
const cors = require('cors');
const path = require('path');

const app = express();
const PORT = 3001;

// Middleware
app.use(cors());
app.use(express.json());
app.use(express.static('.'));

// Mock database - replace with real database in production
const mockUsers = {
    'ACC001': {
        password: 'password123',
        device_id: 1,
        owner_name: 'John Doe',
        account_no: 'ACC001',
        owner_number: '+254701234567',
        device_serial_number: 'PT001'
    },
    'ACC002': {
        password: 'test123',
        device_id: 2,
        owner_name: 'Jane Smith',
        account_no: 'ACC002',
        owner_number: '+254701234568',
        device_serial_number: 'PT002'
    }
};

const mockDevices = {
    'ACC001': [
        {
            id: 1,
            name: 'John Doe',
            serialNumber: 'PT001',
            location: 'Nairobi CBD',
            balance: 15500.75,
            dailyEarnings: 2300.50,
            dailyGamesPlayed: 45,
            gamesPlayed: 1250,
            accountNumber: 'ACC001',
            registrationDate: '2024-01-15',
            lastActivity: '2025-06-12T10:30:00',
            status: 'active'
        },
        {
            id: 2,
            name: 'John Doe',
            serialNumber: 'PT002',
            location: 'Westlands',
            balance: 8750.25,
            dailyEarnings: 1200.00,
            dailyGamesPlayed: 28,
            gamesPlayed: 890,
            accountNumber: 'ACC001',
            registrationDate: '2024-02-20',
            lastActivity: '2025-06-12T09:45:00',
            status: 'active'
        }
    ],
    'ACC002': [
        {
            id: 3,
            name: 'Jane Smith',
            serialNumber: 'PT003',
            location: 'Kiambu',
            balance: 12300.00,
            dailyEarnings: 1800.75,
            dailyGamesPlayed: 35,
            gamesPlayed: 980,
            accountNumber: 'ACC002',
            registrationDate: '2024-03-10',
            lastActivity: '2025-06-12T11:15:00',
            status: 'active'
        }
    ]
};

const mockTransactions = {
    'ACC001': [
        {
            id: 1,
            account_no: 'ACC001',
            device_id: 1,
            transaction_id: 'TXN202506120001',
            amount: 50.00,
            running_balance: 15500.75,
            payer_name: 'Michael Kamau',
            phone_number: '+254712345678',
            game_status: 'played',
            transaction_date: '2025-06-12T10:30:00'
        },
        {
            id: 2,
            account_no: 'ACC001',
            device_id: 1,
            transaction_id: 'TXN202506120002',
            amount: 100.00,
            running_balance: 15450.75,
            payer_name: 'Grace Wanjiku',
            phone_number: '+254723456789',
            game_status: 'played',
            transaction_date: '2025-06-12T09:15:00'
        },
        {
            id: 3,
            account_no: 'ACC001',
            device_id: 2,
            transaction_id: 'TXN202506120003',
            amount: 75.00,
            running_balance: 8750.25,
            payer_name: 'David Muthomi',
            phone_number: '+254734567890',
            game_status: 'not_played',
            transaction_date: '2025-06-12T08:45:00'
        }
    ],
    'ACC002': [
        {
            id: 4,
            account_no: 'ACC002',
            device_id: 3,
            transaction_id: 'TXN202506120004',
            amount: 150.00,
            running_balance: 12300.00,
            payer_name: 'Sarah Njeri',
            phone_number: '+254745678901',
            game_status: 'played',
            transaction_date: '2025-06-12T11:15:00'
        }
    ]
};

const mockWithdrawals = {
    'ACC001': [
        {
            id: 1,
            account_no: 'ACC001',
            transaction_code: 'WD202506110001',
            amount: 5000.00,
            withdrawal_account: '+254701234567',
            account_name: 'John Doe',
            payment_method: 'M-Pesa',
            status: 'completed',
            withdrawal_date: '2025-06-11T14:30:00',
            processed_date: '2025-06-11T14:35:00'
        }
    ],
    'ACC002': []
};

// API Routes

// Health check
app.get('/wp-json/pooltable/v1/health', (req, res) => {
    res.json({
        status: 'healthy',
        timestamp: new Date().toISOString(),
        database: 'connected',
        version: '2.0'
    });
});

// Login endpoint
app.post('/wp-json/pooltable/v1/login', (req, res) => {
    const { accountId, password } = req.body;
    
    if (!accountId || !password) {
        return res.status(400).json({
            success: false,
            message: 'Account ID and password are required'
        });
    }
    
    const user = mockUsers[accountId];
    if (!user || user.password !== password) {
        return res.status(401).json({
            success: false,
            message: 'Invalid credentials'
        });
    }
    
    const token = 'mock_token_' + Date.now();
    
    res.json({
        success: true,
        token: token,
        user: {
            id: user.device_id,
            name: user.owner_name,
            accountNumber: user.account_no,
            phoneNumber: user.owner_number,
            serialNumber: user.device_serial_number
        }
    });
});

// Get user devices
app.get('/wp-json/pooltable/v1/devices', (req, res) => {
    const accountNo = req.query.account_no;
    
    if (!accountNo) {
        return res.status(400).json({ error: 'Account number is required' });
    }
    
    const devices = mockDevices[accountNo] || [];
    res.json(devices);
});

// Get transactions
app.get('/wp-json/pooltable/v1/transactions', (req, res) => {
    const accountNo = req.query.account_no;
    
    if (!accountNo) {
        return res.status(400).json({ error: 'Account number is required' });
    }
    
    const transactions = mockTransactions[accountNo] || [];
    // Sort by date descending (newest first)
    transactions.sort((a, b) => new Date(b.transaction_date) - new Date(a.transaction_date));
    
    res.json(transactions);
});

// Get withdrawal history
app.get('/wp-json/pooltable/v1/withdrawals', (req, res) => {
    const accountNo = req.query.account_no;
    
    if (!accountNo) {
        return res.status(400).json({ error: 'Account number is required' });
    }
    
    const withdrawals = mockWithdrawals[accountNo] || [];
    res.json(withdrawals);
});

// Get withdrawal details
app.get('/wp-json/pooltable/v1/withdrawal-details', (req, res) => {
    const accountNo = req.query.account_no;
    
    if (!accountNo) {
        return res.status(400).json({ error: 'Account number is required' });
    }
    
    res.json({
        payment_method: 'M-Pesa',
        withdrawal_account: '+254701234567',
        account_name: 'John Doe',
        bank_name: null,
        branch: null
    });
});

// Process withdrawal
app.post('/wp-json/pooltable/v1/withdraw', (req, res) => {
    const { accountNo, amount, password } = req.body;
    
    if (!accountNo || !amount || !password) {
        return res.status(400).json({
            success: false,
            message: 'Account number, amount, and password are required'
        });
    }
    
    if (amount < 100) {
        return res.status(400).json({
            success: false,
            message: 'Minimum withdrawal amount is KSH 100'
        });
    }
    
    // Mock successful withdrawal
    const transactionCode = 'WD' + Date.now();
    
    res.json({
        success: true,
        message: 'Withdrawal request submitted successfully',
        transaction_code: transactionCode,
        amount: amount,
        estimated_processing_time: '24 hours'
    });
});

// Submit support ticket
app.post('/wp-json/pooltable/v1/support', (req, res) => {
    const { name, phone, category, subject, message } = req.body;
    
    if (!name || !phone || !category || !subject || !message) {
        return res.status(400).json({
            success: false,
            message: 'All required fields must be filled'
        });
    }
    
    const ticketNumber = 'TKT' + Date.now();
    
    res.json({
        success: true,
        message: 'Support ticket submitted successfully',
        ticket_number: ticketNumber,
        estimated_response_time: '4 hours'
    });
});

// Serve static files (HTML, CSS, JS)
app.get('/', (req, res) => {
    res.sendFile(path.join(__dirname, 'index.html'));
});

app.listen(PORT, '0.0.0.0', () => {
    console.log(`Pool Table API Server running on port ${PORT}`);
});