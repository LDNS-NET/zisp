export const PageManuals = {
    'dashboard': {
        title: 'Your Business Health at a Glance',
        description: 'Think of this as your business cockpit. It shows you exactly what is happening right now across your entire network without needing to look at complex logs or code.',
        workflow: [
            {
                step: 'Monitor Live Connections',
                explanation: 'Look at the "Active Users" count. This tells you how many people are currently using your internet service.',
                why: 'If this number is zero or much lower than usual, there might be a general power outage or a major cable break.'
            },
            {
                step: 'Check Financial Performance',
                explanation: 'The revenue charts show how much money has come in today versus yesterday.',
                why: 'This helps you see if your sales are growing or if there is a sudden drop in customer renewals.'
            },
            {
                step: 'Alerts & Notifications',
                explanation: 'Check for any red status lights near your equipment names.',
                why: 'Red means a part of your network is offline. Finding this here allows you to send a technician before customers start calling to complain.'
            }
        ],
        impacts: 'Looking at the dashboard does not change anything for your customers. It is purely for your information so you can make better business decisions.'
    },
    'activeusers.index': {
        title: 'Managing People Currently Online',
        description: 'This page shows you Every single person currently using your internet. It is the best place to go if a customer says "I can\'t browse" even though they have a valid subscription.',
        workflow: [
            {
                step: 'Find a Specific Customer',
                explanation: 'Use the search box to type in a customer\'s name or phone number.',
                why: 'To see if their device is actually communicating with your network right now.'
            },
            {
                step: 'The "Disconnect" Action',
                explanation: 'Clicking the "Disconnect" icon (usually a logout or trash icon) will force that customer\'s device to log out.',
                why: 'Sometimes a customer\'s phone gets "stuck" in your system. Disconnecting them forces their phone to refresh the connection, which often fixes speed or login issues.'
            }
        ],
        impacts: 'Warning: If you disconnect a customer who is in the middle of a Zoom call or a movie, their stream will stop for a few seconds while their device reconnects. Only use this for troubleshooting.'
    },
    'users.index': {
        title: 'Customer Directory & Accounts',
        description: 'This is your digital filing cabinet. Every person who has ever used your service is listed here, along with their history and contact details.',
        workflow: [
            {
                step: 'Check Subscription Status',
                explanation: 'Look at the color-coded labels. Green means they are paid up, Red means they have expired.',
                why: 'If a customer calls saying "The internet isn\'t working," your first check should be here to see if they simply forgot to pay.'
            },
            {
                step: 'Update a Customer Profile',
                explanation: 'Click "Edit" to change a customer\'s name, phone number, or the internet package they use.',
                why: 'If a customer wants to move from a "Cheap" package to a "Fast" package, you change it here.'
            },
            {
                step: 'Disable an Account',
                explanation: 'The "Disable" switch stops a customer from being able to log in even if they try to pay.',
                why: 'Use this for customers who have moved away or those who are violating your terms of service.'
            }
        ],
        impacts: 'Changing an "Expired" user to "Active" manually will give them free internet. Be careful with who has permission to edit these settings!'
    },
    'packages.index': {
        title: 'Setting Up Your Products & Prices',
        description: 'This is where you decide what you are selling. You define the speeds and the prices that your customers see on their phones.',
        workflow: [
            {
                step: 'Setting Speeds',
                explanation: 'Upload and Download speeds are measured in "Mbps". 5Mbps is good for basic browsing; 20Mbps is great for 4K video.',
                why: 'Providing different speeds allows you to have different price points for different types of customers (e.g., Home vs. Business).'
            },
            {
                step: 'Validity & Time Limits',
                explanation: 'Decide if a package lasts for 24 hours, 7 days, or 30 days.',
                why: 'This automates your billing. The system will automatically stop the customer\'s internet the second their time runs out.'
            }
        ],
        impacts: 'If you lower the price of a package here, it applies immediately to the next person who buys it. If you increase the speed, everyone currently on that package will suddenly get faster internet.'
    },
    'analytics.reports.index': {
        title: 'Business Growth & Reporting',
        description: 'This tool turns your raw data into simple charts and documents. It helps you understand if you are making a profit and where your biggest costs are.',
        workflow: [
            {
                step: 'Entering Manual Data',
                explanation: 'Use the "Data Entry" tab to record things that the system doesn\'t know about—like fuel for the generator or marketing flyers.',
                why: 'To get a true picture of your profit, you need to subtract your manual expenses from your digital revenue.'
            },
            {
                step: 'Building a Custom Report',
                explanation: 'Choose what you want to see (e.g., "Revenue per Office") and click "Generate".',
                why: 'This creates a PDF or Excel sheet that you can print or email to your business partners.'
            }
        ],
        impacts: 'Generating a massive report (e.g., 5 years of data) might make the system feel slow for a minute while it calculates thousands of payments.'
    },
    'mikrotiks.index': {
        title: 'Your Internet Gateways (Routers)',
        description: 'This is the most technical part of the system. It manages the physical boxes that actually provide the WiFi/Cables to your customers.',
        workflow: [
            {
                step: 'Check Connection Status',
                explanation: 'Look for the "Connected" status light. This ensures your server can talk to the router.',
                why: 'If the server can\'t talk to the router, customers won\'t be able to log in, even if they have paid.'
            },
            {
                step: 'Avoid "The Danger Zone"',
                explanation: 'Do not change the "Secret" or "IP Address" unless you are a trained network engineer.',
                why: 'These are the "passwords" that allow the system to work. Changing them will lock every single customer out of the network instantly.'
            }
        ],
        impacts: 'Critical Impact: Any change here can potentially shut down your entire internet business for all customers. If you are unsure, contact technical support before clicking save.'
    },
    'payments.index': {
        title: 'Money & Receipts Cabinet',
        description: 'This is your digital cash register. It records every payment made via M-Pesa or Cash.',
        workflow: [
            {
                step: 'Search for a Payment',
                explanation: 'If a customer says "I paid via M-Pesa but I am not online," search for their phone number here.',
                why: 'Sometimes M-Pesa takes a few minutes to notify our system. You can see the exact second the money arrived here.'
            },
            {
                step: 'Manual Payment Entry',
                explanation: 'If a customer hands you physical cash, you must record it here manually.',
                why: 'Recording the cash here will automatically "Switch On" the customer\'s internet so you don\'t have to do it manually.'
            }
        ],
        impacts: 'Once a payment is recorded, it cannot be easily "un-recorded". This ensures your staff cannot hide or steal cash payments.'
    },
    'vouchers.index': {
        title: 'Creating Internet Scratch-Cards',
        description: 'Vouchers are one-time-use "Secret Codes" that you can print and sell in shops or at your reception.',
        workflow: [
            {
                step: 'Generating Codes',
                explanation: 'Choose how many codes you want (e.g., 100 pieces) and which package they are for.',
                why: 'This allows customers who don\'t have M-Pesa to buy a physical card from you and get online instantly.'
            },
            {
                step: 'Checking if a Code is Used',
                explanation: 'If a customer says "This code is not working," search for the code here.',
                why: 'The system will tell you if the code was already used by someone else or if it has expired.'
            }
        ],
        impacts: 'vouchers are like cash. If someone steals your list of un-used codes, they can use your internet for free.'
    },
    'tickets.index': {
        title: 'Customer Complaints & Support',
        description: 'Think of this as your "To-Do" list for customer problems. It ensures no customer is ignored.',
        workflow: [
            {
                step: 'Opening a Ticket',
                explanation: 'When a customer calls with a problem, record it here as a "New Ticket".',
                why: 'So your team knows exactly who is waiting for help and how long they have been waiting.'
            },
            {
                step: 'Replying to Customers',
                explanation: 'Type your updates into the ticket conversation box.',
                why: 'This keeps all your staff informed. If one staff member goes home, the next person can see exactly what was promised to the customer.'
            }
        ],
        impacts: 'Closing a ticket lets everyone know the problem is solved. It also helps you track which technicians are the fastest at fixing issues.'
    }
};

export const GlobalHelp = {
    title: 'The ISP Operational Bible',
    description: 'General rules and "Plain English" explanations for running your business smoothly.',
    tips: [
        'Green always means Good/Active. Red always means Stop/Inactive/Error.',
        'When in doubt, check "Payments" first—90% of customer calls are just forgotten payments.',
        'Never share your Admin password. Anyone with your password can see your total revenue and delete your customers.',
        'Mbps = "Megabits per second". Think of it like a water pipe: higher Mbps means a wider pipe that lets more "data water" through at once.'
    ]
};
