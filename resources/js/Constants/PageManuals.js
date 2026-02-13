export const PageManuals = {
    'dashboard': {
        title: 'Business Overview',
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
        title: 'Live Connections',
        description: 'This page shows you every single person currently using your internet. It is the best place to go if a customer says "I can\'t browse" even though they have a active plan.',
        workflow: [
            {
                step: 'Find a Specific Customer',
                explanation: 'Use the search box to type in a customer\'s name or phone number.',
                why: 'To see if their device is actually communicating with your network right now.'
            },
            {
                step: 'The "Disconnect" Action',
                explanation: 'Forcing a logout will disconnect the current session.',
                why: 'Sometimes a device gets "stuck". Forcing a logout refreshes the connection, which often fixes speed or login issues.'
            }
        ],
        impacts: 'Disconnecting a customer will briefly stop their internet. They will typically reconnect automatically within a few seconds.'
    },
    'users.index': {
        title: 'Customer Directory',
        description: 'This is your digital filing cabinet. Every person who has ever used your service is listed here, along with their history and contact details.',
        workflow: [
            {
                step: 'Check Subscription Status',
                explanation: 'Green means they are paid up, Red means they have expired.',
                why: 'This is your first check when a customer says their internet isn\'t working.'
            },
            {
                step: 'Update a Customer Profile',
                explanation: 'Click "Edit" to change a customer\'s name, phone number, or their internet plan.',
                why: 'Use this when a customer wants to upgrade to a faster service.'
            }
        ],
        impacts: 'Directly editing a user status can grant or revoke internet access immediately.'
    },
    'leads.index': {
        title: 'Potential Customers (Leads)',
        description: 'These are people who have shown interest in your service but haven\'t signed up yet.',
        workflow: [
            {
                step: 'Track Inquiries',
                explanation: 'Record names and locations of people asking about your internet.',
                why: 'To follow up with them later when you expand your coverage to their area.'
            }
        ],
        impacts: 'Managing leads helps you plan where to build your next network branch.'
    },
    'packages.index': {
        title: 'Your Internet Plans',
        description: 'This is where you decide what you are selling—the speeds and the prices.',
        workflow: [
            {
                step: 'Set Speeds & Price',
                explanation: 'Define how fast the internet is and how much it costs.',
                why: 'To provide different options for home users versus high-demand businesses.'
            }
        ],
        impacts: 'Changes here apply to all future purchases. Increasing speed on an existing plan will immediately benefit all customers currently using it.'
    },
    'vouchers.index': {
        title: 'Internet Scratch-Cards',
        description: 'Vouchers are one-time-use codes that you can sell in shops or at your reception.',
        workflow: [
            {
                step: 'Generate Codes',
                explanation: 'Create a batch of secret codes for a specific plan.',
                why: 'Allows customers to pay with cash at a local shop and get online instantly.'
            }
        ],
        impacts: 'Unused vouchers are like cash; keep them secure. Once used, they cannot be used again.'
    },
    'settings.staff.index': {
        title: 'Team Management',
        description: 'Manage who can log into this system and what they are allowed to see or do.',
        workflow: [
            {
                step: 'Add Staff Member',
                explanation: 'Create a login for your employees (e.g., Technicians or Managers).',
                why: 'To track who is doing what and ensure security.'
            },
            {
                step: 'Set Permissions',
                explanation: 'Decide if a staff member can see financial data or just help customers.',
                why: 'Protects your sensitive business information.'
            }
        ],
        impacts: 'Adding or removing staff changes who can access your business data. Always disable accounts for former employees immediately.'
    },
    'payments.index': {
        title: 'Payments & Money',
        description: 'This is your digital cash register, tracking every payment made via M-Pesa or Cash.',
        workflow: [
            {
                step: 'Verify a Payment',
                explanation: 'Look up a customer\'s phone number to see if their payment arrived.',
                why: 'To resolve "I have paid but am not online" questions.'
            },
            {
                step: 'Record Cash',
                explanation: 'If someone pays you in person, you must record it here.',
                why: 'This automatically switches on their internet.'
            }
        ],
        impacts: 'Recorded payments provide a permanent audit trail for your business accounting.'
    },
    'analytics.reports.index': {
        title: 'Financial Intelligence Guide',
        description: 'Analyzing the money flowing through your ISP network.',
        workflow: [
            {
                step: 'Monitor MRR (Monthly Recurring Revenue)',
                explanation: 'This is your "Subscription Fuel"—the predictable money you expect every month.',
                why: 'To see if your business is growing or shrinking over time.'
            },
            {
                step: 'Track ARPU (Average Revenue Per User)',
                explanation: 'This is the "Average Value per Tap"—the average amount one customer pays you.',
                why: 'Higher ARPU means your customers are buying more expensive plans.'
            },
            {
                step: 'Analyze Revenue by Zone',
                explanation: 'See which neighborhoods or estates are bringing in the most money.',
                why: 'To decide where to expand your fiber or wireless network next.'
            }
        ],
        impacts: 'Using these numbers helps you make smart decisions about where to invest your capital.'
    },
    'invoices.index': {
        title: 'Invoices & Billing',
        description: 'A record of all bills sent to your customers.',
        workflow: [
            {
                step: 'Audit Billings',
                explanation: 'Review monthly bills for home or business users.',
                why: 'To ensure everyone is being billed correctly for their plan.'
            }
        ],
        impacts: 'Invoices serve as the formal request for payment from your customers.'
    },
    'analytics.traffic': {
        title: 'Traffic Analytics',
        description: 'A technical view of how much total data your entire network is using.',
        workflow: [
            {
                step: 'Monitor Peak Times',
                explanation: 'Watch the graph to see when your network is most busy.',
                why: 'To know if you need to buy more total bandwidth from your provider.'
            }
        ],
        impacts: 'Helps you understand your network capacity and avoid "slow internet" during peak hours.'
    },
    'analytics.topology': {
        title: 'Network Map (Topology)',
        description: 'A visual diagram of how your routers and equipment are connected.',
        workflow: [
            {
                step: 'Visual Inspection',
                explanation: 'Look at the lines connecting your equipment.',
                why: 'To see exactly where a break in the connection has occurred.'
            }
        ],
        impacts: 'Makes it easier for technicians to find physical network faults.'
    },
    'mikrotiks.index': {
        title: 'Internet Gateways (Routers)',
        description: 'This handles the physical boxes that provide the connection to your customers.',
        workflow: [
            {
                step: 'Check Status',
                explanation: 'Ensure the "Connected" light is on.',
                why: 'If the router is red, no one on that branch can browse.'
            }
        ],
        impacts: 'CRITICAL: Do not change settings here unless you are a network professional. Incorrect settings will shut down your entire network.'
    },
    'sms.index': {
        title: 'SMS Messaging',
        description: 'Monitor the text messages sent to your customers.',
        workflow: [
            {
                step: 'View Message History',
                explanation: 'Check if expiry warnings were sent successfully.',
                why: 'To confirm customers were notified before their internet stopped.'
            }
        ],
        impacts: 'Helps maintain good customer communication and transparency.'
    },
    'tickets.index': {
        title: 'Customer Support Tickets',
        description: 'Your to-do list for fixing customer problems.',
        workflow: [
            {
                step: 'Reply to Ticket',
                explanation: 'Update the customer on the status of their issue.',
                why: 'So the customer knows you are working on it.'
            }
        ],
        impacts: 'Closing a ticket confirms the issue is resolved and notifies the customer.'
    },
    'settings.content-filter.index': {
        title: 'Internet Safety (Content Filter)',
        description: 'Block harmful or unwanted websites across your network.',
        workflow: [
            {
                step: 'Enable Protection',
                explanation: 'Select categories of websites to block.',
                why: 'To provide a safer browsing experience for families or schools.'
            }
        ],
        impacts: 'Changes here take effect immediately for everyone on your network.'
    },
    'equipment.index': {
        title: 'Router & Hardware Inventory',
        description: 'A list of all the physical boxes and devices that make up your internet network.',
        workflow: [
            {
                step: 'Track Device Location',
                explanation: 'Record exactly where each piece of equipment is installed.',
                why: 'So you know exactly where to send a technician when something breaks.'
            }
        ],
        impacts: 'Knowing your inventory helps you plan for future upgrades and prevents equipment loss.'
    },
    'tenant.installations.index': {
        title: 'New Service Installations',
        description: 'Manage the process of connecting new customers to your network.',
        workflow: [
            {
                step: 'Assign Technician',
                explanation: 'Choose which staff member will go to the customer\'s house.',
                why: 'To ensure someone is responsible for the job.'
            },
            {
                step: 'Complete Installation',
                explanation: 'Mark the job as "Finished" once the customer is browsing.',
                why: 'This automatically converts the installation request into an active customer account.'
            }
        ],
        impacts: 'Finishing an installation starts the customer\'s billing cycle.'
    }
};

export const GlobalHelp = {
    title: 'ISP Operational Dictionary',
    description: 'General rules and "Plain English" explanations for running your business smoothly.',
    tips: [
        'Green always means Good/Active. Red always means Stop/Inactive/Error.',
        'When in doubt, check "Payments" first—90% of customer calls are just forgotten payments.',
        'Mbps = "Megabits per second". Think of it like a water pipe: higher Mbps means a wider pipe that lets more "data" through at once.',
        'Router = The box that provides the WiFi signal.'
    ]
};
