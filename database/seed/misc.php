<?php
return [
    'settings' => [
        'site_name' => 'SYSCOM',
        'site_tagline' => 'Domains, Hosting & Business Email',
        'site_description' => 'SYSCOM helps Indian businesses get online with domain registration, web hosting, VPS and dedicated servers, business email and SSL certificates.',
        'contact_email' => '',
        'contact_phone' => '',
        'contact_address' => '',
        'main_site_url' => 'https://syscom.co.in',
        'social_profiles' => '',
        'internal_links_max' => '5',
    ],

    // General FAQs for /faq (not attached to a page).
    'faqs' => [
        ['What does SYSCOM offer?', 'SYSCOM provides domain registration, Linux and Windows web hosting, WordPress hosting, VPS and dedicated servers, reseller hosting, business email and SSL certificates for businesses in India.'],
        ['I am new to websites. Where should I start?', 'Start by registering a domain name, then choose a web hosting plan and business email. If you plan to use WordPress, WordPress-optimised hosting is a good first choice.'],
        ['Can I transfer my existing domain and website to SYSCOM?', 'Yes. Domains can be transferred with the authorisation code from your current registrar, and websites can be migrated to a new hosting account before DNS is switched.'],
        ['Do I need an SSL certificate?', 'Yes. SSL enables HTTPS, which encrypts data between visitors and your site and prevents browsers from marking your pages as "Not secure".'],
        ['How do I get help choosing a plan?', 'Send us your requirements through the contact form and our team will recommend a suitable domain, hosting and email setup.'],
    ],

    // FAQs attached to services, keyed by service slug.
    'service_faqs' => [
        'web-hosting' => [
            ['How many websites can I host on one plan?', 'It depends on the plan. Entry plans usually host a single website, while higher plans allow multiple websites, databases and email accounts.'],
            ['Can I upgrade my hosting plan later?', 'Yes. You can move to a larger shared plan, WordPress hosting or a VPS as your website grows.'],
        ],
        'wordpress-hosting' => [
            ['Can I migrate my existing WordPress site?', 'Yes. Your files and database can be moved to WordPress hosting, and the site tested before DNS is switched.'],
        ],
        'vps-hosting' => [
            ['Do I get root access on a VPS?', 'Yes. Linux KVM VPS plans include full root access so you can install and configure the software you need.'],
            ['Is a VPS managed?', 'A VPS is self-managed by default. Managed services and add-ons are available if you do not have a system administrator.'],
        ],
        'dedicated-servers' => [
            ['Should I choose Linux or Windows for my dedicated server?', 'Choose Linux for PHP, MySQL, Node.js or Python applications and Windows Server for .NET or MS SQL Server workloads.'],
        ],
        'domain-registration' => [
            ['What do I need to transfer a domain?', 'The domain should be unlocked at the current registrar, you need its authorisation (EPP) code, and it usually must be more than 60 days old.'],
            ['Can I register a .in domain?', 'Yes. .in and .co.in domains are available alongside .com and many other extensions.'],
        ],
        'business-email' => [
            ['Will my email work on my phone?', 'Yes. Business email works with webmail, desktop email apps and mobile devices.'],
        ],
        'ssl-certificates' => [
            ['How long does it take to issue an SSL certificate?', 'Domain-validated certificates are usually issued quickly after validation. Organisation and extended validation certificates take longer because the company is verified.'],
        ],
        'reseller-hosting' => [
            ['Can my clients have their own control panel?', 'Yes. Each client account gets its own control panel login, isolated from other clients.'],
        ],
    ],

    // Keyword plan. No volumes are stored: they must be validated in a real research tool.
    'keywords' => [
        ['web hosting india', 'commercial', 'high', '/web-hosting-india', 'targeting'],
        ['best web hosting in india', 'commercial', 'high', '/web-hosting-india', 'targeting'],
        ['wordpress hosting india', 'commercial', 'high', '/wordpress-hosting-india', 'targeting'],
        ['business email hosting india', 'commercial', 'high', '/business-email-hosting-india', 'targeting'],
        ['buy domain name india', 'transactional', 'high', '/services/domain-registration', 'mapped'],
        ['vps hosting india', 'commercial', 'medium', '/services/vps-hosting', 'mapped'],
        ['dedicated server india', 'commercial', 'medium', '/services/dedicated-servers', 'mapped'],
        ['ssl certificate india', 'transactional', 'medium', '/services/ssl-certificates', 'mapped'],
        ['reseller hosting india', 'commercial', 'medium', '/services/reseller-hosting', 'mapped'],
        ['how to choose web hosting', 'informational', 'medium', '/blog/how-to-choose-web-hosting-india', 'mapped'],
        ['shared vs vps hosting', 'informational', 'medium', '/blog/shared-vs-vps-vs-dedicated-hosting', 'mapped'],
        ['how to choose a domain name', 'informational', 'medium', '/blog/how-to-choose-a-domain-name', 'mapped'],
        ['what is ssl certificate', 'informational', 'low', '/blog/what-is-an-ssl-certificate', 'mapped'],
        ['custom domain email', 'informational', 'low', '/blog/business-email-vs-free-email', 'mapped'],
        ['website backup', 'informational', 'low', '/blog/website-backup-strategy-small-business', 'mapped'],
        ['syscom hosting', 'navigational', 'medium', '/', 'mapped'],
        ['windows hosting india', 'commercial', 'low', null, 'researching'],
        ['google workspace reseller india', 'commercial', 'low', null, 'researching'],
    ],

    // Internal linking rules: phrase => target, priority 1-10.
    'internal_links' => [
        ['WordPress hosting', '/services/wordpress-hosting', 9],
        ['web hosting', '/services/web-hosting', 8],
        ['VPS hosting', '/services/vps-hosting', 8],
        ['dedicated server', '/services/dedicated-servers', 7],
        ['business email', '/services/business-email', 7],
        ['SSL certificate', '/services/ssl-certificates', 7],
        ['domain name', '/services/domain-registration', 6],
        ['reseller hosting', '/services/reseller-hosting', 5],
    ],

    // Off-page opportunities to research. All start as "opportunity" - nothing here is claimed as live.
    'backlinks' => [
        ['Google Business Profile', 'citation', null, 'https://syscom.co.in/', 'SYSCOM', 'Claim or verify the business listing with consistent name, address and phone (NAP).'],
        ['LinkedIn Company Page', 'social', null, 'https://syscom.co.in/', 'SYSCOM', 'Keep the company page updated and share new blog guides.'],
        ['Justdial', 'directory', null, 'https://syscom.co.in/', 'SYSCOM', 'Local business directory listing; keep NAP identical to other citations.'],
        ['IndiaMART', 'directory', null, 'https://syscom.co.in/web-hosting/index.php', 'web hosting services', 'B2B marketplace listing for hosting services.'],
        ['Quora – hosting questions', 'forum', null, 'https://syscom.co.in/', null, 'Answer genuine questions about choosing hosting; link only where it truly helps (links are usually nofollow).'],
        ['Indian startup/tech blog guest article', 'guest_post', null, 'https://syscom.co.in/optimized-wordpress-hosting.php', 'WordPress hosting', 'Pitch an original, useful article (e.g. WordPress speed checklist). No paid links.'],
        ['Web design agency partner page', 'partner', null, 'https://syscom.co.in/reseller-hosting.php', 'reseller hosting', 'Partner/reseller programme mention on agency sites.'],
    ],

    // Demo leads so the lead inbox is not empty; clearly marked as sample data.
    'leads' => [
        ['Demo Lead – Priya Sharma', 'priya.demo@example.com', '9800000001', 'Example Interiors (demo)', 'Web Hosting', 'Sample enquiry: we need hosting and 5 email accounts for our new company website.', '/web-hosting-india', 'new', 2],
        ['Demo Lead – Rahul Verma', 'rahul.demo@example.com', null, 'Example Agency (demo)', 'Reseller Hosting', 'Sample enquiry: looking for reseller hosting for around 20 client WordPress sites.', '/services/reseller-hosting', 'contacted', 5],
        ['Demo Lead – Anita Rao', 'anita.demo@example.com', '9800000003', null, 'Business Email', 'Sample enquiry: moving 12 staff from free email to our own domain.', '/business-email-hosting-india', 'qualified', 9],
    ],
];
