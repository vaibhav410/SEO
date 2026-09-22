<?php
// Services mirror product lines listed on syscom.co.in. Copy is educational and avoids prices,
// uptime figures or customer counts that would need verification.
return [
    [
        'name' => 'Web Hosting',
        'slug' => 'web-hosting',
        'icon' => 'server',
        'primary_keyword' => 'shared web hosting',
        'external_url' => 'https://syscom.co.in/web-hosting/index.php',
        'description' => 'Linux and Windows shared hosting for business websites, with cPanel/Plesk control, free SSL options and email on your own domain.',
        'meta_title' => 'Web Hosting in India for Business Websites',
        'meta_description' => 'Linux and Windows shared web hosting for Indian businesses: control panel, SSL, email accounts and support to get your website online quickly.',
        'features' => "Linux (cPanel) and Windows (Plesk) plans\nEmail accounts on your own domain\nOne-click installs for WordPress and popular apps\nSSL certificate support for HTTPS\nIndian and US data-centre options\nUpgrade path to VPS or cloud hosting",
        'content' => <<<'MD'
## What shared web hosting is

Shared hosting places your website on a professionally managed server alongside other sites. The server, network and security patching are handled for you, which keeps costs low and removes the need for in-house server skills. For most company websites, portfolios, brochure sites and small online stores, shared hosting is the right starting point.

## Who it suits

- Small and medium businesses launching or moving a company website
- Professionals such as doctors, CAs and consultants who need a reliable online presence
- Agencies and freelancers hosting client brochure sites
- Anyone moving from a free website builder to their own domain

## Linux or Windows hosting?

Choose **Linux hosting** for PHP, MySQL, WordPress, Joomla or static HTML sites. It is the most common choice and is managed through cPanel. Choose **Windows hosting** when your site is built with ASP.NET or needs MS SQL, and manage it through Plesk.

## What to check before you buy

1. Storage and bandwidth that match your expected traffic
2. How many websites, databases and email accounts are included
3. Whether SSL certificates can be installed easily
4. Backup options and how restores work
5. How to upgrade when you outgrow shared resources

## Growing beyond shared hosting

If your site starts receiving heavy traffic or needs custom server software, you can move to [VPS hosting](/services/vps-hosting) or a [dedicated server](/services/dedicated-servers) without changing your domain or email setup.
MD,
    ],
    [
        'name' => 'WordPress Hosting',
        'slug' => 'wordpress-hosting',
        'icon' => 'wordpress',
        'primary_keyword' => 'managed wordpress hosting',
        'external_url' => 'https://syscom.co.in/optimized-wordpress-hosting.php',
        'description' => 'Hosting tuned for WordPress with one-click installation, server-level caching and an environment configured for common plugins and themes.',
        'meta_title' => 'Optimized WordPress Hosting in India',
        'meta_description' => 'WordPress hosting tuned for speed and stability: one-click install, caching, SSL support and help from a team that understands WordPress sites.',
        'features' => "One-click WordPress installation\nServer configuration tuned for WordPress\nCaching for faster page loads\nSSL support for secure logins\nCompatible with popular themes and plugins\nOptional malware scanning and backups",
        'content' => <<<'MD'
## Why WordPress-specific hosting helps

WordPress powers a large share of business websites, but a default server setup is not always a good fit. Optimized WordPress hosting configures PHP, the database and caching around how WordPress actually works, so pages load faster and the admin dashboard stays responsive.

## Good fit for

- Company websites and blogs built on WordPress
- WooCommerce stores with a moderate product catalogue
- Agencies managing several WordPress client sites
- Content teams publishing articles regularly

## Performance basics that still matter

Even on tuned hosting, site speed depends on your choices:

- Use a lightweight, well-maintained theme
- Keep plugins to the ones you actually use
- Compress images before uploading them
- Keep WordPress core, themes and plugins updated

## Security for WordPress sites

Most WordPress compromises come from outdated plugins and weak passwords. Combine updates and strong passwords with an [SSL certificate](/services/ssl-certificates) and regular off-site backups. Our guide to [website backups](/blog/website-backup-strategy-small-business) explains a simple plan you can follow.
MD,
    ],
    [
        'name' => 'VPS Hosting',
        'slug' => 'vps-hosting',
        'icon' => 'layers',
        'primary_keyword' => 'vps hosting india',
        'external_url' => 'https://syscom.co.in/virtualserverlinux-hosting.php',
        'description' => 'Linux KVM virtual private servers with dedicated resources and root access for growing websites, applications and development teams.',
        'meta_title' => 'Linux KVM VPS Hosting in India',
        'meta_description' => 'KVM-based Linux VPS hosting with guaranteed resources and full root access for applications, busy websites and development environments.',
        'features' => "KVM virtualisation with dedicated resources\nFull root access\nChoice of Linux distributions\nScale CPU, RAM and storage as you grow\nOptional managed services and add-ons\nIsolated from other customers' workloads",
        'content' => <<<'MD'
## What a VPS gives you

A Virtual Private Server (VPS) splits a physical server into isolated virtual machines. Each VPS has its own allocated CPU, memory and storage, its own operating system and full root access. You get much of the control of a dedicated server at a lower cost.

## When to move to a VPS

- Your shared hosting account regularly hits resource limits
- You need to install custom software, services or a specific PHP/Node/Python version
- You run several sites or applications and want them on one server you control
- You need a staging or development environment that mirrors production

## KVM virtualisation

KVM (Kernel-based Virtual Machine) provides full virtualisation: your VPS runs its own kernel and is not affected by other tenants' configuration. This makes resource allocation predictable.

## Managed or unmanaged?

With an unmanaged VPS your team handles the operating system, updates and security. If you do not have a system administrator, consider managed add-ons or stay on [web hosting](/services/web-hosting) until you do. For the heaviest workloads, compare with [dedicated servers](/services/dedicated-servers).
MD,
    ],
    [
        'name' => 'Dedicated Servers',
        'slug' => 'dedicated-servers',
        'icon' => 'cpu',
        'primary_keyword' => 'dedicated server india',
        'external_url' => 'https://syscom.co.in/dedicated-servers.php',
        'description' => 'Linux and Windows dedicated servers when an application needs the full power, isolation and configuration control of physical hardware.',
        'meta_title' => 'Linux & Windows Dedicated Servers',
        'meta_description' => 'Dedicated Linux and Windows servers for high-traffic sites, databases and business applications that need full hardware resources and control.',
        'features' => "Entire physical server for your workloads\nLinux or Windows operating systems\nFull administrative access\nOptional managed server services\nSuitable for databases and ERP workloads\nPredictable performance with no neighbours",
        'content' => <<<'MD'
## Full hardware, no neighbours

A dedicated server is a physical machine used only by your organisation. All CPU cores, memory, disks and network capacity are available to your applications, which makes performance predictable under load.

## Typical workloads

- High-traffic websites and online stores
- Large databases and reporting systems
- ERP, CRM and other line-of-business applications
- Hosting many client sites for an agency or reseller

## Linux or Windows

Pick Linux for open-source stacks (PHP, MySQL, Node.js, Python) and Windows Server when your applications depend on .NET, MS SQL Server or other Microsoft technologies.

## Planning checklist

1. Estimate peak CPU, RAM and storage needs with headroom
2. Decide who will manage patching, monitoring and backups
3. Plan a backup and disaster-recovery process before going live
4. Secure access with SSH keys or strong passwords and a firewall

If your needs are smaller, a [VPS](/services/vps-hosting) often gives enough control at a lower cost.
MD,
    ],
    [
        'name' => 'Domain Registration',
        'slug' => 'domain-registration',
        'icon' => 'globe',
        'primary_keyword' => 'domain registration india',
        'external_url' => 'https://syscom.co.in/domain-registration/index.php',
        'description' => 'Register, transfer and manage domain names across popular extensions including .com, .in and .co.in, with DNS management included.',
        'meta_title' => 'Domain Name Registration & Transfer',
        'meta_description' => 'Search, register and transfer domain names including .com, .in and .co.in. Manage DNS, renewals and privacy from one control panel.',
        'features' => "Popular extensions including .com, .in and .co.in\nNew gTLDs, premium and IDN domains\nDomain transfer to SYSCOM\nDNS management\nBulk registration for portfolios\nRenewal reminders",
        'content' => <<<'MD'
## Your domain is your online identity

A domain name is the address customers type to find you and the part after the @ in your business email. A short, memorable domain builds trust and is an asset you keep even if you change hosting provider.

## Choosing an extension

- **.in / .co.in** signal an Indian business and suit companies serving Indian customers
- **.com** is widely recognised internationally
- **New extensions** such as .online or .store can work when the name you want is taken elsewhere

Our guide on [how to choose a domain name](/blog/how-to-choose-a-domain-name) walks through naming rules in detail.

## Transfers

You can move an existing domain to SYSCOM. The domain must usually be unlocked at the current registrar, you need its authorisation (EPP) code, and it should be more than 60 days past registration or a previous transfer.

## Protect the domain

- Turn on auto-renew so the domain never lapses
- Keep the registrant email address current
- Keep registrar login details secure

Pair your domain with [business email](/services/business-email) and [web hosting](/services/web-hosting) to get fully online.
MD,
    ],
    [
        'name' => 'Business Email',
        'slug' => 'business-email',
        'icon' => 'mail',
        'primary_keyword' => 'professional email address',
        'external_url' => 'https://syscom.co.in/business-email',
        'description' => 'Professional email on your own domain, with options including Business Email, Titan, Google Workspace and enterprise email hosting.',
        'meta_title' => 'Business Email Hosting on Your Domain',
        'meta_description' => 'Professional email addresses on your own domain with Business Email, Titan and Google Workspace options, webmail, mobile access and spam filtering.',
        'features' => "Email addresses on your own domain\nWebmail, desktop and mobile access\nSpam and virus filtering\nTitan Business Email option\nGoogle Workspace option\nEnterprise email for larger teams",
        'content' => <<<'MD'
## Why a domain email matters

An address like name@yourcompany.in tells customers they are dealing with an established business. It also keeps company communication under company control: when an employee leaves, the mailbox stays with the business.

## Choosing the right plan

- **Business Email** – cost-effective mailboxes for small teams
- **Titan Business Email** – a modern mail app with calendar and productivity features
- **Google Workspace** – Gmail on your domain plus Docs, Drive and Meet
- **Enterprise Email** – larger mailboxes and admin controls for bigger teams

## Deliverability basics

Set up SPF, DKIM and DMARC DNS records so your messages are trusted by receiving servers and less likely to land in spam. These records live in your [domain's DNS](/services/domain-registration).

Read [business email vs free email](/blog/business-email-vs-free-email) for a fuller comparison.
MD,
    ],
    [
        'name' => 'SSL Certificates',
        'slug' => 'ssl-certificates',
        'icon' => 'lock',
        'primary_keyword' => 'ssl certificate india',
        'external_url' => 'https://syscom.co.in/digital-certificate',
        'description' => 'SSL/TLS certificates that enable HTTPS, encrypt data between visitors and your site, and remove "Not secure" browser warnings.',
        'meta_title' => 'SSL Certificates for HTTPS Websites',
        'meta_description' => 'Secure your website with an SSL certificate: HTTPS encryption, browser trust and protection for logins, forms and payments on your site.',
        'features' => "Domain, organisation and extended validation options\nSingle-domain, multi-domain and wildcard certificates\nEncrypts data in transit with HTTPS\nRemoves browser \"Not secure\" warnings\nInstallation help with your hosting\nRenewal reminders",
        'content' => <<<'MD'
## What an SSL certificate does

An SSL (TLS) certificate lets your website use HTTPS. It encrypts information travelling between the visitor's browser and your server, such as passwords, contact form entries and payment details, and proves the site belongs to your domain.

## Certificate types

| Type | Validates | Good for |
| --- | --- | --- |
| Domain Validation (DV) | Domain control | Blogs, brochure sites |
| Organisation Validation (OV) | Domain and company | Business websites |
| Extended Validation (EV) | Strict company checks | Finance, large e-commerce |
| Wildcard | One domain and all its subdomains | Sites using many subdomains |

## HTTPS and search

Google has confirmed HTTPS as a lightweight ranking signal, and browsers label HTTP pages that collect data as "Not secure". Moving to HTTPS is a basic step for trust and for search. See [what an SSL certificate is](/blog/what-is-an-ssl-certificate) for a step-by-step migration checklist.
MD,
    ],
    [
        'name' => 'Reseller Hosting',
        'slug' => 'reseller-hosting',
        'icon' => 'users',
        'primary_keyword' => 'reseller hosting india',
        'external_url' => 'https://syscom.co.in/reseller-hosting.php',
        'description' => 'Linux and Windows reseller hosting for agencies and freelancers who want to host client websites under their own brand.',
        'meta_title' => 'Linux & Windows Reseller Hosting',
        'meta_description' => 'Reseller hosting for web designers and agencies: create separate client accounts, set your own packages and grow a hosting business.',
        'features' => "Separate control panel accounts per client\nCreate your own hosting packages\nLinux (WHM/cPanel) and Windows options\nSell domains and SSL alongside hosting\nOne bill for all client accounts\nUpgrade as your client base grows",
        'content' => <<<'MD'
## Build recurring revenue from hosting

Reseller hosting lets web designers, developers and agencies host client websites under their own brand. You buy a pool of resources and divide it into separate accounts, each with its own control panel login.

## Why agencies use it

- Keep client websites isolated from each other
- Offer hosting, [domains](/services/domain-registration) and [SSL](/services/ssl-certificates) as one package
- Earn recurring income instead of one-off project fees
- Control the support relationship with your clients

## Running it well

1. Define clear packages with storage and email limits
2. Put backups and renewals in writing in your client agreements
3. Monitor resource use so one client does not affect others
4. Move demanding clients to a [VPS](/services/vps-hosting) when they outgrow shared resources
MD,
    ],
];
