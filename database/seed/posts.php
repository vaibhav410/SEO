<?php
// Seed articles. published_at offsets are days before install so the blog has a realistic timeline.
return [
    [
        'title' => 'How to Choose the Right Web Hosting Plan for Your Business in India',
        'slug' => 'how-to-choose-web-hosting-india',
        'service' => 'web-hosting',
        'primary_keyword' => 'how to choose web hosting',
        'meta_title' => 'How to Choose Web Hosting for an Indian Business',
        'meta_description' => 'A practical checklist for choosing web hosting in India: hosting types, server location, resources, security, support and upgrade paths.',
        'excerpt' => 'Hosting decides how fast, secure and reliable your website is. Use this checklist to compare plans on the things that actually matter.',
        'days_ago' => 42,
        'content' => <<<'MD'
Your hosting plan is the foundation of your website. It affects page speed, security, email reliability and how easily you can grow. Yet many businesses pick a plan on price alone and only discover the limits later. This guide gives you a clear way to compare options.

## Start with what your website needs to do

Write down, in plain language, what the site is for:

- A brochure site that explains your services and collects enquiries
- A blog or resource centre you will publish to every week
- An online store with payments and customer accounts
- A web application with custom software

A five-page company website has very different needs from a store with thousands of products. Being specific stops you from overpaying or under-buying.

## Understand the hosting types

**Shared web hosting** puts your site on a managed server with other websites. It is affordable and needs no server skills, which makes it right for most small business sites.

**WordPress hosting** is shared hosting configured specifically for WordPress, with caching and settings tuned for it.

**VPS hosting** gives you a private slice of a server with guaranteed resources and root access.

**Dedicated servers** give you an entire physical machine for heavy or sensitive workloads.

If you are unsure, read our comparison of [shared, VPS and dedicated hosting](/blog/shared-vs-vps-vs-dedicated-hosting).

## Check server location

For a business whose customers are mostly in India, a server in or near India usually means lower latency and faster first loads. If your audience is global, a content delivery network (CDN) can help regardless of where the server is.

## Compare resources honestly

Look past the headline numbers:

1. **Storage type** – SSD or NVMe storage is noticeably faster than older disks.
2. **Bandwidth** – "unmetered" plans still have fair-use limits; read them.
3. **CPU and memory limits** – these matter more than storage for busy sites.
4. **Websites, databases and email accounts** – count how many you need today and next year.

## Security is not optional

Make sure the plan supports an SSL certificate so your site runs on HTTPS. Ask how the server is patched, whether malware scanning is available and how backups work. A backup you cannot restore quickly is not much help.

## Look at support and upgrade paths

Find out how you contact support and what is covered. Also check how you move to a bigger plan: a smooth path from shared hosting to a VPS saves a painful migration later.

## A simple decision checklist

| Question | If yes, consider |
| --- | --- |
| Is it a small business or brochure site? | Shared web hosting |
| Is it built on WordPress? | WordPress hosting |
| Do you need custom software or root access? | VPS hosting |
| Do you have heavy traffic or large databases? | Dedicated server |

Choosing well now means fewer surprises later. Start with the plan that fits today, and make sure it can grow with you.
MD,
        'faqs' => [
            ['Is cheap web hosting good enough for a business website?', 'For a small brochure site, an entry-level shared plan is often enough. Check the resource limits, SSL support and backup options rather than price alone, and make sure you can upgrade easily.'],
            ['Does server location affect SEO?', 'Server location mainly affects speed for your visitors. Faster pages give a better experience, which supports SEO indirectly. For local targeting, content, language and your domain matter more than server location.'],
        ],
    ],
    [
        'title' => 'Shared vs VPS vs Dedicated Hosting: Which One Do You Need?',
        'slug' => 'shared-vs-vps-vs-dedicated-hosting',
        'service' => 'vps-hosting',
        'primary_keyword' => 'shared vs vps hosting',
        'meta_title' => 'Shared vs VPS vs Dedicated Hosting Compared',
        'meta_description' => 'Compare shared, VPS and dedicated hosting on performance, control, security and management effort to pick the right option for your site.',
        'excerpt' => 'The three main hosting types differ in isolation, control and the skills they need. Here is how to decide without overspending.',
        'days_ago' => 35,
        'content' => <<<'MD'
The biggest hosting decision is not which company to use but which *type* of hosting fits your workload. Shared, VPS and dedicated hosting trade off cost, control and responsibility in different ways.

## Shared hosting

Many websites share one server's CPU, memory and disk. The provider manages everything at server level.

- **Best for:** company websites, blogs, small stores, portfolios
- **You manage:** your website files, databases and email accounts
- **Limitation:** resource limits apply, and you cannot install custom server software

## VPS hosting

A physical server is divided into isolated virtual machines. Your VPS has allocated resources and its own operating system.

- **Best for:** growing sites, several applications, development environments
- **You manage:** the operating system, security updates and software (unless you choose managed services)
- **Limitation:** needs Linux administration skills

## Dedicated servers

You rent an entire physical server.

- **Best for:** high-traffic platforms, large databases, ERP and compliance-sensitive workloads
- **You manage:** everything from the operating system upward
- **Limitation:** highest cost, and capacity planning is your responsibility

## Side-by-side comparison

| | Shared | VPS | Dedicated |
| --- | --- | --- | --- |
| Isolation | Low | High | Complete |
| Root access | No | Yes | Yes |
| Skills needed | Minimal | Moderate | Advanced |
| Relative cost | Lowest | Medium | Highest |
| Scaling | Upgrade plan | Resize VPS | New hardware |

## Signs you have outgrown shared hosting

1. Pages slow down at busy times even after optimising images and plugins
2. You regularly hit CPU or process limits
3. You need software the shared server does not allow
4. You want to separate several important sites

## A practical path

Most businesses start on shared or WordPress hosting, move to a VPS when they need more control, and only then consider dedicated hardware. Moving step by step keeps cost aligned with real needs. Whatever you choose, run your site on HTTPS and keep off-site backups.
MD,
        'faqs' => [
            ['Is a VPS faster than shared hosting?', 'Usually, because resources are allocated to you rather than shared. Real-world speed still depends on how well your site and server are configured.'],
            ['Can I move from shared hosting to a VPS later?', 'Yes. Your domain stays the same; the website files, databases and email are migrated to the new server and DNS is updated.'],
        ],
    ],
    [
        'title' => 'How to Choose a Domain Name for Your Business: A Practical Checklist',
        'slug' => 'how-to-choose-a-domain-name',
        'service' => 'domain-registration',
        'primary_keyword' => 'how to choose a domain name',
        'meta_title' => 'How to Choose a Domain Name: Business Checklist',
        'meta_description' => 'Pick a domain name customers remember: length, spelling, .in vs .com, trademarks, and how to protect the domain once it is registered.',
        'excerpt' => 'A good domain is short, easy to say and hard to misspell. Follow these rules before you register one.',
        'days_ago' => 28,
        'content' => <<<'MD'
Your domain name appears on your website, email addresses, visiting cards and invoices. Changing it later is expensive, so it is worth a little thought up front.

## Keep it short and easy to say

Say the name aloud to someone and ask them to type it. If they hesitate or misspell it, simplify. Shorter names are easier to remember and less likely to be mistyped.

## Avoid hyphens and numbers

Hyphens and digits are hard to communicate by voice ("is that the number 4 or the word four?"). Use them only when there is no alternative.

## Choose the right extension

- **.in** or **.co.in** – clearly Indian, good for businesses serving Indian customers
- **.com** – the most familiar global extension
- **New extensions** – such as .online or .tech, useful when your preferred name is taken

Many businesses register both the .in and .com versions and point one to the other so customers reach them either way.

## Use your brand, not a string of keywords

Exact-match keyword domains no longer carry special weight in Google. A brandable name that people remember builds more value over time than a long keyword phrase.

## Check trademarks and social handles

Before you register, search for existing trademarks and check whether matching social media usernames are available. It is far easier to adjust a name now than after a dispute.

## Protect the domain after registration

1. Turn on auto-renew so the domain never expires by accident
2. Use a registrant email address you will keep for years
3. Enable domain lock to prevent unauthorised transfers
4. Store registrar login details securely and limit who can access them

## Put the domain to work

A domain on its own is just an address. Connect it to web hosting for your website and to business email so every message you send reinforces your brand.
MD,
        'faqs' => [
            ['Should I buy .in or .com for my business?', 'If most customers are in India, .in or .co.in is a strong choice. Registering both and redirecting one to the other protects your brand.'],
        ],
    ],
    [
        'title' => 'What Is an SSL Certificate and Why Does Your Website Need One?',
        'slug' => 'what-is-an-ssl-certificate',
        'service' => 'ssl-certificates',
        'primary_keyword' => 'what is ssl certificate',
        'meta_title' => 'What Is an SSL Certificate? A Plain-English Guide',
        'meta_description' => 'Learn what an SSL certificate does, the types available, how HTTPS affects trust and search, and a checklist for moving your site to HTTPS.',
        'excerpt' => 'SSL certificates turn HTTP into HTTPS. Here is what they protect, which type to choose and how to switch without losing traffic.',
        'days_ago' => 21,
        'content' => <<<'MD'
When a browser shows a padlock next to a web address, the site is using HTTPS, made possible by an SSL (more accurately TLS) certificate. Without one, browsers show a "Not secure" warning on pages that collect information.

## What SSL protects

An SSL certificate encrypts data moving between the visitor and your server. That includes:

- Login usernames and passwords
- Contact and enquiry form submissions
- Payment and personal details

It also confirms the visitor is connected to the real site for your domain, not an impostor.

## Types of certificate

**Domain Validation (DV)** confirms you control the domain. It is issued quickly and suits blogs and brochure sites.

**Organisation Validation (OV)** also verifies your company, adding a layer of trust for business websites.

**Extended Validation (EV)** involves the strictest company checks and is often used by financial and large e-commerce sites.

**Wildcard** certificates cover a domain and all its first-level subdomains.

## HTTPS, trust and search

Google has said HTTPS is a lightweight ranking signal. More importantly, visitors are less likely to fill in a form on a page marked "Not secure". HTTPS is now a baseline expectation.

## Checklist for moving to HTTPS

1. Install the certificate on your hosting account
2. Update your site's base URL to https://
3. Redirect every HTTP URL to its HTTPS version with a 301 redirect
4. Update internal links, images and scripts to use HTTPS to avoid mixed-content warnings
5. Update canonical tags and your XML sitemap
6. Add the HTTPS property in Google Search Console and submit the sitemap

## Remember renewals

Certificates expire. Keep renewal reminders active, or your visitors will see a full-page security warning instead of your website.
MD,
        'faqs' => [
            ['Is a free SSL certificate enough?', 'For many brochure sites a domain-validated certificate is enough to enable HTTPS. Businesses that want company details verified choose OV or EV certificates.'],
            ['Will moving to HTTPS hurt my rankings?', 'Not if you use permanent 301 redirects from every HTTP URL, update internal links and canonicals, and submit the HTTPS sitemap.'],
        ],
    ],
    [
        'title' => 'Business Email vs Free Email: Why a Custom Domain Email Matters',
        'slug' => 'business-email-vs-free-email',
        'service' => 'business-email',
        'primary_keyword' => 'custom domain email',
        'meta_title' => 'Business Email vs Free Email for Companies',
        'meta_description' => 'Why companies use email on their own domain instead of free accounts: trust, control, deliverability and security, plus how to set it up.',
        'excerpt' => 'Free email accounts are fine for personal use. For a business they cost you trust, control and deliverability.',
        'days_ago' => 14,
        'content' => <<<'MD'
Many small businesses start with a free email account. It works, but as the business grows the limits show: customers question the address, staff leave with mailboxes, and messages land in spam.

## Trust starts with the address

An email from accounts@yourcompany.in looks established. An email from yourcompany123@ a free provider looks temporary, and it is also easier for scammers to imitate.

## The business owns the mailbox

With business email, the company controls every mailbox. When someone leaves, you can reset the password, forward the mail or keep the history. With free personal accounts, the mailbox belongs to the individual.

## Better deliverability

Business email on your domain lets you publish SPF, DKIM and DMARC records. These tell receiving servers which systems may send email for your domain, which helps legitimate messages reach inboxes and makes spoofing harder.

## Security and administration

Business plans give an administrator control over:

- Creating and removing users
- Password policies and, on many plans, two-step verification
- Aliases such as sales@ and support@ that route to the right people

## Choosing a plan

| Need | Suitable option |
| --- | --- |
| A few mailboxes at low cost | Business Email |
| Modern mail app with calendar | Titan Business Email |
| Documents, video calls and shared drives | Google Workspace |
| Large teams with compliance needs | Enterprise Email |

## Setting it up

1. Register or choose your domain
2. Pick an email plan and create mailboxes
3. Update the domain's MX records to point to the email service
4. Add SPF, DKIM and DMARC records
5. Configure email apps on desktops and phones

Business email is one of the lowest-cost upgrades that makes a company look and operate more professionally.
MD,
        'faqs' => [
            ['Can I keep my website hosting and email with different providers?', 'Yes. Email is controlled by your domain\'s MX records, so it can point to a different service from your website hosting.'],
        ],
    ],
    [
        'title' => 'Website Backups: A Practical 3-2-1 Plan for Small Businesses',
        'slug' => 'website-backup-strategy-small-business',
        'service' => 'web-hosting',
        'primary_keyword' => 'website backup',
        'meta_title' => 'Website Backup Plan for Small Businesses (3-2-1)',
        'meta_description' => 'A simple 3-2-1 website backup plan: what to back up, how often, where to keep copies, and how to test restores before you need them.',
        'excerpt' => 'Hacks, bad updates and accidental deletions happen. A tested backup plan turns a disaster into a short interruption.',
        'days_ago' => 7,
        'content' => <<<'MD'
Websites break for ordinary reasons: a plugin update goes wrong, someone deletes the wrong folder, or malware gets in through an outdated component. The businesses that recover quickly are the ones with a backup plan they have actually tested.

## What needs backing up

- **Website files** – code, themes, plugins and uploaded images
- **Databases** – your content, orders, users and settings
- **Email** – if your mailboxes are on the same hosting account
- **Configuration** – DNS records, SSL details and important settings

## The 3-2-1 rule

Keep **3** copies of your data, on **2** different types of storage, with **1** copy off-site. For a website that usually means the live site, a backup on the hosting server and another copy stored somewhere else entirely.

## How often to back up

Match frequency to how often the site changes:

| Site type | Suggested frequency |
| --- | --- |
| Brochure site that rarely changes | Weekly, plus before every update |
| Blog publishing several times a week | Daily |
| Online store taking orders | Daily or more often, including the database |

## Automate it

Manual backups get forgotten. Use automated tools that run on a schedule, keep several versions, and store copies away from the server. Services such as CodeGuard for websites or Acronis for wider infrastructure are designed for this.

## Test your restores

A backup is only useful if you can restore it. Every few months:

1. Restore a recent backup to a staging location
2. Check that pages, images and forms work
3. Note how long it took and any missing pieces
4. Update your plan based on what you learn

## Combine backups with prevention

Backups are your safety net. Keep software updated, use strong passwords, run your site on HTTPS and scan for malware to reduce how often you need them.
MD,
        'faqs' => [
            ['Are my hosting provider\'s backups enough?', 'They help, but keep at least one copy that you control and that is stored off the hosting server. That protects you if the account itself is compromised.'],
        ],
    ],
];
