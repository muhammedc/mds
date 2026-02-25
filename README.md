# 🕌 MDS — Musjid Display System

<div align="center">

```
███╗   ███╗██████╗ ███████╗
████╗ ████║██╔══██╗██╔════╝
██╔████╔██║██║  ██║███████╗
██║╚██╔╝██║██║  ██║╚════██║
██║ ╚═╝ ██║██████╔╝███████║
╚═╝     ╚═╝╚═════╝ ╚══════╝
   Musjid Display System
```

**A complete, open-source Islamic prayer time display and community information system for mosques.**  
Built with love, for the sake of Allāh ﷻ.

[![License: MDS-NC](https://img.shields.io/badge/License-MDS--NC%20(Free%20Fi%20Sabillalah)-green?style=for-the-badge)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue?style=for-the-badge&logo=php)](https://php.net)
[![SQLite](https://img.shields.io/badge/Database-SQLite-lightblue?style=for-the-badge&logo=sqlite)](https://sqlite.org)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5-purple?style=for-the-badge&logo=bootstrap)](https://getbootstrap.com)

> *"The best of people are those who are most beneficial to people."*  
> — Rasūlullāh ﷺ (At-Tabarani)

---

**© Copyright 2016–2026 Muhammed Cotwal · All Rights Reserved**  
*Original concept 2016 · Complete redesign 2026*

</div>

---

> ### ⚠️ Read Before You Deploy
> **Prayer times included with this software are a starting point only and are NOT verified for your location.**  
> You are solely responsible for verifying and entering correct prayer times, approved by your local Ulama or Islamic authority, before displaying anything to your congregation.  
> The author accepts **no liability** of any kind — religious, legal, financial, or otherwise. Full details: [Critical Warnings](#️-critical-warnings--disclaimers) · [Legal Disclaimer](#️-full-legal-disclaimer--limitation-of-liability)

---

## 📋 Table of Contents

1. [About MDS](#-about-mds)
2. [Screenshots & Layout](#-screenshots--layout)
3. [System Architecture](#-system-architecture)
4. [Feature Overview](#-feature-overview)
5. [Detailed Feature Walkthrough](#-detailed-feature-walkthrough)
    - [🖥️ Musjid Display Mode (TV Screen)](#️-musjid-display-mode-tv-screen)
    - [🌐 Public Website Mode](#-public-website-mode)
    - [📖 Daily Hadith Page](#-daily-hadith-page)
    - [📊 Admin Dashboard](#-admin-dashboard)
    - [🕐 Salaah Times Management](#-salaah-times-management)
    - [📢 Community Messages](#-community-messages)
    - [⚰️ Funeral Notices](#️-funeral-notices)
    - [📰 Ticker Messages](#-ticker-messages)
    - [🌙 Hijri Date](#-hijri-date)
    - [🕌 Jummah Times](#-jummah-times)
    - [☪️ Ramadan Override](#️-ramadan-override)
    - [🎨 Themes](#-themes)
    - [👥 User Management](#-user-management)
    - [⚙️ Site Settings](#️-site-settings)
    - [🛟 Backup & Restore](#-backup--restore)
    - [🧪 Simulator / Debug Tool](#-simulator--debug-tool)
6. [⚠️ Critical Warnings & Disclaimers](#️-critical-warnings--disclaimers)
7. [🖥️ Screen Burn-In & Hardware Considerations](#️-screen-burn-in--hardware-considerations)
8. [Installation Guide](#-installation-guide)
9. [Docker Deployment](#-docker-deployment)
10. [Database Schema](#-database-schema)
11. [Configuration Reference](#-configuration-reference)
12. [Prayer Time Logic](#-prayer-time-logic)
13. [Jamaat Change Notifications](#-jamaat-change-notifications)
14. [Frequently Asked Questions](#-frequently-asked-questions)
15. [Full Legal Disclaimer & Limitation of Liability](#-full-legal-disclaimer--limitation-of-liability)
16. [Licensing](#-licensing)
17. [Copyright Notice](#-copyright-notice)

---

## 🌟 About MDS

**MDS (Musjid Display System)** is a full-featured, web-based Islamic prayer time management and display platform, originally created in 2016 and completely redesigned in 2026 by Muhammed Cotwal for the **Newcastle Muslim Community (NMC)**.

It was built to solve a very real need: mosques need a dignified, reliable, always-on display system that shows prayer times, community announcements, funeral notices, and Islamic content — without the cost of proprietary solutions.

MDS runs on a simple PHP + SQLite stack, meaning it needs **no complex database server** and can run on even the most modest shared hosting or a Raspberry Pi. It is designed to be deployed once and run indefinitely on a TV screen inside the musjid without any manual intervention.

### ✅ What MDS Does

| Capability | Detail |
|---|---|
| 🖥️ TV Display | Full-screen musjid display, designed for large screens |
| 🌐 Public Website | Responsive prayer time website for the community |
| 📢 Announcements | Rich-text community message slideshows |
| ⚰️ Funeral Notices | Dignified in-memoriam notices with Islamic text |
| 📰 Ticker | Scrolling bottom ticker strip with custom messages |
| 📖 Hadith | Daily hadith sourced from a 365-entry database |
| 🌙 Hijri Calendar | Automatic Hijri date with adjustable offset |
| ☪️ Ramadan | Full Ramadan timetable override system |
| 🎨 Themes | 5 built-in colour themes + fully custom theme builder |
| 👥 Multi-user | Role-based admin access (admin + superadmin) |
| 🔔 Change Alerts | Automated 3-day advance notice when Jamaat times change |
| 🧪 Simulator | Time simulator to test displays without waiting |

---

## ⚠️ Critical Warnings & Disclaimers

> **Please read this section carefully before deploying MDS in any mosque or Islamic institution.**

---

### 🕐 Prayer Times — Your Responsibility

```
╔══════════════════════════════════════════════════════════════════════════════╗
║                                                                              ║
║   ⚠️  IMPORTANT: PRAYER TIMES ARE A STARTING POINT ONLY                     ║
║                                                                              ║
║   The prayer times and timetable data included with MDS (if any) are        ║
║   provided SOLELY as a reference example and starting point.                 ║
║                                                                              ║
║   They ARE NOT verified for your location, your madhab, your local          ║
║   Islamic authority, or your municipality's exact coordinates.               ║
║                                                                              ║
║   YOU ARE SOLELY RESPONSIBLE for verifying, correcting, and maintaining     ║
║   all prayer times displayed by your installation of MDS.                    ║
║                                                                              ║
╚══════════════════════════════════════════════════════════════════════════════╝
```

#### What This Means in Practice

Prayer times are not universal. They vary based on:

- 📍 **Geographic coordinates** — latitude, longitude, and elevation of your mosque
- 🏙️ **Local Islamic authority** — your regional Ulama council or Islamic institute may issue times that differ from purely astronomical calculations
- 🧎 **Madhab** — Hanafi and Shafi schools differ on the Asr validity window
- 🌍 **Country/region conventions** — moon sighting practices affect Hijri dates; horizon definitions affect Fajr and Esha times
- 🕌 **Your mosque's Jamaat decisions** — the congregation time is set by your mosque committee, not by software

#### What You Must Do Before Going Live

1. **Contact your local Ulama council or Islamic institute** and obtain the official prayer timetable for your location
2. **Enter all times manually** using the Salaah Times Management screen (day view or grid view)
3. **Cross-check every single day** — especially Fajr, Esha, and Sehri which have the most variation
4. **Have a knowledgeable person verify** the Jamaat times on-screen against your mosque's official schedule before displaying to the congregation
5. **Review times again for Ramadan** — enter separate Ramadan Jamaat times using the Ramadan Override module
6. **Check Jummah times** — configure your Friday Azaan, Khutbah, and Jamaat times explicitly

> **MDS is a display and management tool, not a prayer time calculator.** It does not calculate prayer times from coordinates. It only displays what you enter.

#### Recommended Sources for Prayer Times

- Your country's official Islamic council or Ulama board
- [IslamicFinder.org](https://www.islamicfinder.org) (for reference/verification only — always confirm with local authority)
- [Aladhan API](https://aladhan.com) (for reference/verification only)
- Your regional musjid federation or Islamic institute

---

### ⚖️ No Liability for Religious Errors

> The author of this software **bears no responsibility** for:
> - Incorrect prayer times being displayed due to unverified data
> - Any missed, delayed, or performed-at-wrong-time prayers resulting from use of this system
> - Any religious rulings, fatwas, or obligations arising from data shown by this software
> - Any discrepancies between what this system displays and what your local Ulama prescribe

**It is the sole responsibility of the mosque administration, IT administrator, and/or whoever deploys this system to ensure all displayed times are correct and approved by a qualified Islamic scholar or authority.**

---

### 🔒 Security Responsibility

> The author provides no guarantees regarding the security of this software. The mosque administrator is responsible for:
> - Keeping the server, PHP, and operating system patched and up to date
> - Configuring the web server to deny direct access to `data.sqlite`
> - Using strong admin passwords and changing all defaults immediately after installation
> - Securing the admin panel (e.g. behind VPN, IP restriction, or HTTPS)
> - Regular backups of the database

---

## 🖥️ Screen Burn-In & Hardware Considerations

Running a display system 24/7 on a TV or monitor comes with important hardware considerations. **Screen burn-in is a real risk** that can permanently damage your display if not properly managed.

### 🔥 What is Screen Burn-In?

Screen burn-in (also called image retention or ghosting) occurs when a **static image is displayed in the same position for extended periods**, causing the display panel to "remember" that image permanently. On modern screens this can happen as quickly as a few months of always-on use with a static layout.

Affected display technologies:

| Display Type | Burn-In Risk | Notes |
|---|---|---|
| **OLED / AMOLED** | 🔴 Very High | Most susceptible — pixels wear unevenly |
| **Plasma** | 🔴 Very High | Largely obsolete but still found in some mosques |
| **QLED / LCD / LED** | 🟡 Medium | Can suffer temporary image retention |
| **IPS LCD** | 🟡 Medium-Low | Generally more resistant but not immune |
| **Commercial Digital Signage** | 🟢 Low | Purpose-built panels have burn-in mitigation built-in |

> **Recommendation:** For always-on mosque display use, prefer **commercial-grade digital signage panels** (Samsung MagicInfo, LG SuperSign, etc.) over consumer TVs. They are engineered for 24/7 operation.

---

### 🛡️ How MDS Helps Prevent Burn-In

MDS is designed with burn-in awareness:

**1. Dynamic, Cycling Content**
The community messages slideshow, funeral notices, and ticker strip continuously change and cycle. This prevents any single piece of content from being "burned" in.

**2. Clock and Countdown Timers**
The live clock, countdown timers, and prayer status indicators update every second. Moving pixels do not burn in.

**3. Progress Bars**
The animated slide progress bar in the community notice area is in constant motion.

**4. Ticker Strip**
The scrolling ticker at the bottom ensures the lower portion of the screen is always changing.

**However**, the following elements are **relatively static** and present the highest burn-in risk in MDS's layout:

| Static Element | Risk | Mitigation |
|---|---|---|
| Prayer time column headers (Fajr, Zuhr, etc.) | Medium | Times themselves change daily |
| Prayer name labels | Medium | Content varies; status pills change |
| Card borders and dividers | Low-Medium | Thin lines; theme change helps |
| Header bar layout | Medium | Date and clock are dynamic |
| "Jamaat" / "Earliest" labels | Medium | Positionally static |

---

### ✅ Recommended Practices to Prevent Burn-In

#### 1. Enable Screen Sleep / Auto-Off During Closed Hours

Configure your TV or display computer to **turn off the screen** during hours when the mosque is closed. For example:

**Linux (Raspberry Pi / Ubuntu) — turn off screen at night:**
```bash
# Add to crontab (crontab -e):
# Turn screen OFF at 23:00 (11 PM)
0 23 * * * DISPLAY=:0 xset dpms force off

# Turn screen ON at 04:00 (4 AM, before Fajr)
0 4 * * * DISPLAY=:0 xset dpms force on
```

**Windows Task Scheduler:** Create tasks to run `nircmd.exe monitor off` / `monitor on` at scheduled times.

#### 2. Reduce Screen Brightness

Most displays do not need to run at 100% brightness indoors. Reducing brightness significantly extends panel life and reduces burn-in risk:

- Aim for **30–50% brightness** for indoor mosque lighting conditions
- Many commercial signage panels support scheduled brightness reduction for night hours

#### 3. Enable the TV's Built-in Pixel Shift (if available)

Most modern smart TVs and commercial displays include a **Pixel Shift** (also called Pixel Orbiter, Screen Shift, or Pixel Refresher) feature:

| Brand | Feature Name | Where to Find |
|---|---|---|
| Samsung | **Pixel Shift** | Settings → General → Pixel Shift |
| LG | **Screen Shift** | Settings → Support → OLED Care → Screen Shift |
| Sony | **Pixel Shift** | Settings → Display & Sound → Picture → Pixel Shift |
| Philips | **Pixel Orbiter** | Setup → TV Settings → Picture → Advanced |

> ✅ **Always enable pixel shift on your mosque display TV.**  
> Pixel shift moves the entire image by 1–2 pixels at regular intervals. This is invisible to viewers but distributes wear evenly across the panel.

#### 4. Enable the TV's Screen Saver / Panel Refresh

Many OLED TVs offer a **panel refresh / compensation** cycle. This should be run periodically (e.g., weekly, during mosque closure hours). Consult your TV's manual.

#### 5. Avoid Pure White or Pure Black Large Areas

Static large areas of pure white (#FFFFFF) or pure black (#000000) accelerate burn-in. MDS's default themes use deep greens, blues, and muted backgrounds precisely to reduce this risk. **Avoid using the "White" theme for always-on deployment unless your display is specifically LCD.**

#### 6. Consider a Screen Saver for Extended Closures

For extended periods (e.g., mosque closed for a week), configure the OS to activate a screen saver or turn off the display. Never leave a static image on an OLED/Plasma screen for hours when no one is present.

#### 7. Use Commercial Signage Hardware

For best results and peace of mind, consider:

- **Samsung QMB/QBB Series** — commercial-grade 24/7 panels
- **LG UM3/UM5 Series** — commercial signage displays
- **Philips D-Line** — designed for extended-hours use
- Any panel explicitly rated for **"24/7 operation"** in its specifications

---

### ⚠️ Hardware Disclaimer

> The author of MDS bears **no responsibility** for:
> - Screen burn-in, image retention, or any display hardware damage resulting from running MDS continuously
> - The selection of appropriate display hardware for your use case
> - Failure to implement the burn-in prevention practices described above
> - Any hardware costs, repair costs, or replacement costs related to display degradation

**The mosque administration is solely responsible for selecting appropriate hardware and implementing a screen protection strategy suitable for their specific display and usage pattern.**

---

## 🖼️ Screenshots & Layout

### Musjid Display (TV Screen) Layout

```
╔══════════════════════════════════════════════════════════════════════════════╗
║  🕌 NMC MASJID          Wed 25 Feb · 25 Sha'ban 1447 AH        ⏰ 14:32:07  ║
╠═══════════╦═══════════╦═══════════╦═══════════╦═══════════════════════════╣
║  [FAJR]   ║  [ZUHR]   ║   [ASR]   ║ [MAGHRIB] ║          [ESHA]          ║
║  ◉ NOW    ║           ║  ◉ NEXT   ║           ║                          ║
║  🌅       ║  ☀️        ║  🌤️       ║  🌇       ║  🌃                      ║
║  Fajr     ║  Zuhr     ║  Asr      ║  Maghrib  ║  Esha                    ║
║  Fajr Prayer║Midday Prayer║Afternoon Prayer║Sunset Prayer║  Night Prayer  ║
║  Jamaat   ║  Jamaat   ║  Jamaat   ║  Jamaat   ║  Jamaat                  ║
║  05:12    ║  13:15    ║  16:45    ║  19:52    ║  21:15                   ║
║  Earliest ║  Earliest ║  Earliest ║     —     ║  Earliest                ║
║  04:48    ║  13:05    ║  16:30    ║           ║  21:00                   ║
╠══════════════════════════════╦══════════════════════════════════════════════╣
║   📢 COMMUNITY NOTICE       ║  📖 Hadith of the Day                       ║
║                              ║                                              ║
║  [Slideshow — Rich HTML /   ║  "The best of you are those who learn the   ║
║   Images / Jamaat Changes]  ║  Qur'an and teach it." — (Al-Bukhari)       ║
║                              ║                                              ║
║  [Progress Bar]              ╠══════════════════════════════════════════════╣
║                              ║  ⚰️ Funeral Notice                          ║
║                              ║  Marhoom: Muhammad Abdullah (75)            ║
║                              ║  Janazah: 14:00 · Masjid                    ║
╠══════════════════╦═══════╦══╩════════════════════════════════════════════╣
║  🌙 Sehri 04:32 ║🌄 05:30║  🕛 Zawaal 12:58  ║  🌆 Sunset/Iftaar 19:52  ║
╠══════════════════════════════════════════════════════════════════════════════╣
║  📰 TICKER: The Prophet ﷺ said: "The first matter that the slave will be… ║
╚══════════════════════════════════════════════════════════════════════════════╝
```

### Public Website Layout

```
╔══════════════════════════════════════════════════════════╗
║  🕌 NMC  |  Salaah Times  |  📖 Daily Hadith  |  [Nav]  ║
╠══════════════════════════════════════════════════════════╣
║         ┌─────────────────────────────────────┐          ║
║         │  Wed, 25 February 2026              │          ║
║         │  25 Sha'ban 1447 AH                 │          ║
║         │  ⏰  14:32:07                        │          ║
║         └─────────────────────────────────────┘          ║
║  ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐ ║
║  │ FAJR  │ │ ZUHR  │ │  ASR  │ │MAGHRIB│ │ ESHA  │ ║
║  │ 05:12 │ │ 13:15 │ │ 16:45 │ │ 19:52 │ │ 21:15 │ ║
║  │ ◉NOW  │ │       │ │       │ │       │ │       │ ║
║  └────────┘ └────────┘ └────────┘ └────────┘ └────────┘ ║
║                                                          ║
║   🌙 Sehri  ☀️ Sunrise  🕛 Zawaal  🌆 Sunset/Iftaar       ║
║   [Jamaat Change Banner — if times changing in 3 days]   ║
║                                                          ║
║   © 2026 Muhammed Cotwal · MDS                          ║
╚══════════════════════════════════════════════════════════╝
```

---

## 🏗️ System Architecture

```
┌─────────────────────────────────────────────────┐
│                MDS Architecture                  │
│                                                  │
│  ┌──────────┐   ┌──────────┐   ┌──────────────┐ │
│  │index.php │   │admin.php │   │  hadith.php  │ │
│  │(Frontend)│   │(Backend) │   │ (Hadith Page)│ │
│  └────┬─────┘   └─────┬────┘   └──────┬───────┘ │
│       │               │               │          │
│       └───────────────┼───────────────┘          │
│                       │                          │
│              ┌────────▼────────┐                 │
│              │  data.sqlite    │                 │
│              │                 │                 │
│              │ • perpetual_    │                 │
│              │   salaah_times  │                 │
│              │ • community_    │                 │
│              │   messages      │                 │
│              │ • funeral_      │                 │
│              │   notices       │                 │
│              │ • ticker_       │                 │
│              │   messages      │                 │
│              │ • hadith_db     │                 │
│              │ • jummah_       │                 │
│              │   settings      │                 │
│              │ • ramadan_      │                 │
│              │   schedule      │                 │
│              │ • ramadan_      │                 │
│              │   override      │                 │
│              │ • admin_users   │                 │
│              │ • site_settings │                 │
│              │ • media         │                 │
│              └─────────────────┘                 │
│                                                  │
│  URL Modes:                                      │
│  /index.php              → Public website        │
│  /index.php?display=musjid → TV Display mode     │
│  /hadith.php             → Hadith page           │
│  /admin.php              → Admin panel           │
│  /index.php?poll=1&display=musjid → Poll endpoint│
└─────────────────────────────────────────────────┘
```

### Tech Stack

| Component | Technology |
|---|---|
| **Backend** | PHP 8.1+ |
| **Database** | SQLite 3 (via PDO) |
| **Frontend Framework** | Bootstrap 5 |
| **Rich Text Editor** | Summernote |
| **Fonts** | Cinzel Decorative, Nunito, Amiri (Google Fonts) |
| **Icons** | Emoji (no dependency) |
| **Session / Auth** | PHP native sessions with CSRF protection |
| **Timezone** | Africa/Johannesburg (configurable in code) |

---

## 🎯 Feature Overview

| Feature | Who Uses It | Description |
|---|---|---|
| 🖥️ TV Display | Musjid congregation | Full-screen display for wall-mounted TVs |
| 🌐 Public Site | Community members | Responsive prayer time website |
| 📖 Hadith Page | Community members | Daily hadith with share feature |
| 📊 Dashboard | Admins | System health, content overview, quick stats |
| 🕐 Prayer Times | Admins | Per-day or bulk-month jamaat time editing |
| 📢 Community Messages | Admins | Rich-text or image slides with scheduling |
| ⚰️ Funeral Notices | Admins | Structured janazah announcements |
| 📰 Ticker | Admins | Scrolling bottom-strip messages |
| 🌙 Hijri Date | Admins | Adjustable Hijri calendar offset |
| 🕌 Jummah | Admins | Azaan, Khutbah, Jamaat time management |
| ☪️ Ramadan | Admins | Full Ramadan timetable override |
| 🎨 Themes | Admins | 5 presets + custom colour builder |
| 👥 Users | Superadmin | User creation, roles, account locking |
| ⚙️ Settings | Superadmin | Site name, URL, Madhab, copyright toggle |
| 🛟 Backup/Restore | Superadmin | Original 2016 timetable restore |
| 🧪 Simulator | Superadmin | Time/day simulation for testing |
| 🔔 Change Alerts | Automatic | 3-day advance notice of Jamaat changes |
| 📊 Content Polling | Automatic | Real-time display refresh without page reload |

---

## 📚 Detailed Feature Walkthrough

---

### 🖥️ Musjid Display Mode (TV Screen)

**URL:** `https://yoursite.com/index.php?display=musjid`

This is the primary display designed to be opened on a television or monitor inside the mosque and left running indefinitely. It is a **fully automated, self-refreshing** display.

#### Layout Zones

The musjid display is divided into distinct visual zones:

**1. Header Bar**
- Mosque/site name (from settings)
- Current Gregorian date (day of week, date, month, year)
- Hijri date (calculated with your offset, in the format `DD Month YYYY AH`)
- Live digital clock (seconds-accurate, ticking in real-time via JavaScript)

**2. Prayer Time Grid (5 Columns)**

Each of the 5 daily prayers (Fajr, Zuhr, Asr, Maghrib, Esha) gets its own column, showing:

| Row | Content |
|---|---|
| Status Pill | `◉ NOW` (active window) / `NEXT` / `MISSED` / `UPCOMING` / `TOMORROW` |
| Icon | Prayer-specific emoji icon |
| Prayer Name | Arabic prayer name (e.g., Fajr) |
| Description | English description (e.g., "Dawn Prayer") |
| "Jamaat" Label | Label |
| **Jamaat Time** | **The congregational prayer time** (large, prominent) |
| Divider | Decorative separator |
| "Earliest" Label | Label |
| **Earliest Time** | **Earliest valid time to pray** (smaller, below Jamaat) |

> **Madhab Awareness:** Asr Earliest time respects the selected Madhab. Hanafi shows the later Asr earliest time; Shafi shows the earlier time. Maghrib has no separate "Earliest" as it begins immediately at sunset.

**Prayer Status Pills** — The system automatically determines the status of each prayer based on the current time:

- 🟡 `◉ NOW` — Current prayer window is open (it's valid to pray right now)
- 🔵 `NEXT` — This is the next prayer coming up
- 🔴 `MISSED` — Window has passed (time to make Qada)
- ⚪ `UPCOMING` — Future prayer today
- 🟤 `TOMORROW` — All prayers for today are done; this shows Fajr for tomorrow

**Jummah Override (Fridays):**  
On Fridays, the Zuhr column transforms to show the three Jummah times:
- 📣 Azaan time
- 📜 Khutbah start time
- 🕌 Jamaat (congregation) time

**3. Middle Zone — Two-Column Layout**

*Left (3/5 width):* **Community Notice Slideshow**
- Cycles through all active community messages
- Supports plain text, rich HTML (with images, formatting), or full-screen image slides
- Each slide can have a custom display duration (seconds)
- Progress bar at the top of the card shows how long until the next slide
- When Jamaat times are changing within 3 days: a special ⚠️ **Jamaat Change Notice** slide is automatically prepended as the first slide

*Right top (2/5 width, top half):* **Hadith of the Day**
- Shows a different hadith for every day of the year (365 entries in database)
- Text is automatically sized using a binary-search font-fitting algorithm to fill the available space
- Source attribution (hadith collection) displayed separately in a smaller font

*Right bottom (2/5 width, bottom half):* **Funeral Notice**
- Shows active funeral announcements with:
    - Deceased name, age (optional)
    - Date of passing (Gregorian + Hijri)
    - Janazah time and location
    - Proceeding to (cemetery/graveyard)
    - Arabic إِنَّا لِلَّٰهِ وَإِنَّا إِلَيْهِ رَاجِعُونَ (Innā lillāhi wa innā ilayhi rājiʿūn)
- If no active funeral notices exist, shows a dignified placeholder message with the same Arabic text
- Multiple funeral notices cycle automatically

**4. Time Markers Bar**

A row of 4 fixed event cards plus 1 dynamic countdown card:

| Marker | Meaning |
|---|---|
| 🌙 Sehri Ends | Last time to eat before the fast (same as Fajr Earliest) |
| 🌄 Sunrise | Sun rises — Salah not valid until Ishraq |
| 🕛 Zawaal | Solar noon — Makrooh time for prayer |
| 🌆 Sunset / Iftaar | Maghrib begins / fast breaking time |
| ⏱️ Next Marker | Dynamic countdown to the next time marker event |

**5. Ticker Strip (Bottom)**
- Smooth left-scrolling marquee ticker
- Cycles through all active ticker messages
- Each message scrolls at a consistent pixel-per-second speed
- After completing a scroll, the next ticker message begins
- Copyright notice is prepended automatically (can be removed in settings)

#### Real-Time Auto-Refresh (Polling)

The musjid display does **not** auto-reload the page. Instead, it uses a **lightweight polling system**:

Every 60 seconds, the display's JavaScript sends a request to:
```
/index.php?poll=1&display=musjid
```
This returns a tiny JSON `{"v": "123"}` — a content version number. When the number changes (because an admin saved or changed something), the page automatically **reloads itself** to pick up the latest content.

This means:
- ✅ Content updates appear within ~60 seconds of an admin saving
- ✅ No manual intervention required at the TV
- ✅ Minimal bandwidth (poll response is ~20 bytes)

---

### 🌐 Public Website Mode

**URL:** `https://yoursite.com/`  
**URL:** `https://yoursite.com/index.php`

A fully responsive public-facing prayer time website that the community can visit.

#### Features

- **Current Date & Clock** — Gregorian and Hijri dates with live clock
- **5 Prayer Time Cards** — Each prayer displayed in a card with:
    - Prayer icon and name
    - Jamaat (congregational) time
    - Earliest valid time
    - Status indicator (NOW / NEXT / UPCOMING / MISSED)
    - Prayer validity window colour-coding
- **Time Marker Row** — Sehri, Sunrise, Zawaal, Sunset
- **Jummah Section** — Shown on Fridays, displaying Azaan, Khutbah, Jamaat times
- **Jamaat Change Banner** — If Jamaat times are changing within 3 days, a prominent collapsible banner appears at the top showing exactly which prayers are changing, from what time to what time, and on what date(s). Users can dismiss this banner.
- **Daily Hadith FAB Button** — Floating action button linking to the Hadith page
- **Footer** — Copyright notice (can be toggled off in settings)

---

### 📖 Daily Hadith Page

**URL:** `https://yoursite.com/hadith.php`

A dedicated, beautifully styled page showing the hadith of the day.

- Large, readable display with the hadith text
- Source/collection attribution
- **Share button** — Generates a shareable text including the hadith, source, and your mosque's website URL
- Styled consistently with the main site theme
- Day number is used to cycle through the 365-entry hadith database

---

### 📊 Admin Dashboard

**URL:** `https://yoursite.com/admin.php` (after login)

The first page you see after logging in. A comprehensive at-a-glance system overview.

#### Dashboard Sections

**Row 1 — Today's Salaah Times**  
Shows all prayer times for today in a quick-reference grid. Pulls live from the database for today's month/date combination.

**Row 2 — Key System Metrics**

| Card | What it Shows |
|---|---|
| 📅 Hijri Date | Current calculated Hijri date (with offset applied) |
| 🧎 Madhab | Currently selected Madhab (Hanafi/Shafi) |
| 🌙 Ramadan | Whether Ramadan override is active + end date |
| 🎨 Theme | Currently active colour theme |

**Row 3 — Content Overview**

| Card | What it Shows |
|---|---|
| 📢 Community Messages | Count of active / total messages, ⚠️ expiry warning |
| ⚰️ Funeral Notices | Count of active / total, ⚠️ expiry warning |
| 📰 Ticker Messages | Count of active / total, ⚠️ expiry warning |

Any items expiring within 48 hours are flagged with ⚠️.

**Row 4 — System Info**

| Card | What it Shows |
|---|---|
| 🕐 Salaah Time Records | How many of the 365/366 days have times populated, last edit time |
| 🌙 Hijri Offset | Current offset, quick-edit link |
| 📊 Content Version | The internal counter bumped on every save (used for polling) |

---

### 🕐 Salaah Times Management

**URL:** `admin.php?action=times`

This is the core data entry area for the mosque's perpetual prayer timetable. MDS uses a **"perpetual" timetable** approach — you enter Jamaat times for every day of the year (month + day, without year), and these times repeat every year automatically.

> **Important:** You only ever edit **Jamaat times** (the congregational prayer times). The "Earliest" times (the theoretical start of the prayer window) should come pre-loaded in your database, as they are calculated from astronomical data and do not change year to year significantly for a given location.

#### Day View (Single Day)

- Select a month (1–12) and day (1–31) using the dropdowns
- Shows the current saved times for all 5 prayers
- Edit any or all times in HH:MM format
- Save to update just that day

#### Grid View (Full Month)

Switch to grid view to see and edit all days of a month at once.

- Displays a table with every day of the selected month
- Each row = one day; each column = one prayer
- Changed cells are highlighted in gold to show unsaved changes
- **Today's row is highlighted in green**
- Past days are slightly faded (but still editable)
- Click "Save Entire Month" to save all changes at once
- ⚠️ **Ramadan note:** If Ramadan Override is active, Maghrib is excluded from the Ramadan grid and always uses the standard timetable.

#### Ramadan Grid

When Ramadan Override is active and you navigate to `admin.php?action=ramadan`, a similar grid is shown but for **Ramadan only**, and only covers Fajr, Zuhr, Asr, and Esha (Maghrib uses sunset time from the standard table).

---

### 📢 Community Messages

**URL:** `admin.php?action=community_messages`

The most visually prominent content on the musjid display. Community messages are shown as a **cycling slideshow** in the main left panel of the TV display.

#### Creating/Editing a Community Message

| Field | Description |
|---|---|
| **Title** | Optional internal title (not shown on display) |
| **Content Type** | `HTML` (rich text) or `Image` (full-slide image) |
| **Content (HTML)** | Rich-text editor (Summernote) — supports formatting, colours, font sizes, bullet lists, etc. |
| **Image Upload** | For image type: upload a JPG, PNG, GIF, or WebP image |
| **Image Fit** | `Contain` (shows full image with padding) or `Cover` (fills the slide, may crop) |
| **Display Duration** | How many seconds this slide stays on screen before advancing (5–300 seconds) |
| **Sort Order** | Lower number = shown first. Controls the cycle order. |
| **Active** | Toggle on/off without deleting |
| **Schedule Start** | Optional: don't show before this date/time |
| **Schedule End** | Optional: automatically stop showing after this date/time |

#### How the Slideshow Works

1. All active, in-schedule community messages are loaded in sort_order ASC, then created_at DESC
2. If Jamaat times are changing in the next 3 days, a **Jamaat Change Notice** slide is automatically injected as slide #1 (60-second duration)
3. The JS engine shows each slide for its configured `display_secs`
4. A progress bar at the top of the card animates to show time remaining on the current slide
5. After all slides complete, it loops back to the beginning
6. If the browser tab is hidden (TV sleeps, browser in background), the engine resumes correctly when the tab becomes visible again

#### Content Type: HTML (Rich Text)

Uses **Summernote** editor (chosen over TinyMCE to avoid requiring an API key). You can:
- Set font size, colour, bold/italic/underline
- Create bullet lists and numbered lists
- Insert tables
- Write in Arabic (RTL text is supported)
- Paste formatted text from Word or other sources

#### Content Type: Image

- Upload an image directly from your computer
- The image is stored as a BLOB in the SQLite database (no external file storage needed)
- Choose `Contain` to show the full image | `Cover` to fill the slide (may crop edges)
- Images are served via `/index.php?action=img&id=XX` endpoint

---

### ⚰️ Funeral Notices

**URL:** `admin.php?action=funeral_notices`

Funeral notices appear in the **bottom-right panel** of the musjid display. They cycle automatically if multiple are active.

#### Creating/Editing a Funeral Notice

| Field | Description |
|---|---|
| **Deceased Name** | Full name of the marhoom/marhooma |
| **Age** | Optional age of the deceased |
| **Date of Passing** | Gregorian date (e.g., 25 February 2026) |
| **Date of Passing (Hijri)** | Hijri date (e.g., 25 Sha'ban 1447) — entered manually |
| **Janazah Time** | Time of the Janazah Salah (e.g., 14:00) |
| **Janazah Location** | Where the Janazah will be performed (e.g., Masjid / Graveyard) |
| **Proceeding To** | Where the body proceeds after Janazah (cemetery name) |
| **Display Duration** | How many seconds this notice shows if multiple notices cycle |
| **Active** | Toggle on/off |
| **Schedule Start/End** | Optional scheduling window |

#### What Appears on the Display

- Eyebrow: "◆ Funeral Notice"
- Deceased name (prominent, large)
- Age (if provided)
- Date of passing (Gregorian + Hijri)
- Janazah details (time + location + proceeding to)
- Arabic إِنَّا لِلَّٰهِ وَإِنَّا إِلَيْهِ رَاجِعُونَ displayed in Amiri font

If no funeral notices are active, a dignified placeholder is shown:
> *"No funeral announcements at this time. May Allāh grant all the deceased Jannatul Firdaus. Āmeen."*

---

### 📰 Ticker Messages

**URL:** `admin.php?action=ticker_messages`

The scrolling strip along the very bottom of the musjid display.

#### Creating/Editing a Ticker Message

| Field | Description |
|---|---|
| **Message Text** | Plain text that scrolls across the ticker |
| **Display Duration** | How long (seconds) this ticker message "owns" the strip — after it finishes scrolling, the next ticker message begins |
| **Sort Order** | Controls the order of ticker messages in the cycle |
| **Active** | Toggle on/off |
| **Schedule Start/End** | Optional scheduling window |

#### Ticker Behaviour

- Messages scroll at a fixed pixel-per-second speed (smooth, consistent)
- After one message finishes scrolling completely, the next begins
- If the copyright notice is not removed in settings, it is prepended as the first ticker item
- The default ticker (if no messages are configured) shows an Islamic hadith about prayer

---

### 🌙 Hijri Date

**URL:** `admin.php?action=hijri_date`

The Hijri (Islamic lunar calendar) date is **automatically calculated** from the Gregorian date using the standard astronomical conversion algorithm. However, the official Hijri date in many countries differs by 1–2 days from the calculated date due to moon sighting practices.

#### Hijri Offset Setting

The offset allows you to adjust the displayed Hijri date to match local moon sighting:

| Offset | Effect |
|---|---|
| `-2` | Show Hijri date 2 days earlier than calculated |
| `-1` | Show Hijri date 1 day earlier |
| `0` | Default calculated date |
| `+1` | Show Hijri date 1 day later |
| `+2` | Show Hijri date 2 days later |

This offset applies to:
- ✅ The musjid display header
- ✅ The public website
- ✅ The admin dashboard Hijri display

---

### 🕌 Jummah Times

**URL:** `admin.php?action=jummah`

Manage the three Jummah (Friday prayer) times.

| Time | Description |
|---|---|
| **Azaan Time** | When the Azaan for Jummah is called |
| **Khutbah Time** | When the Khutbah (sermon) begins |
| **Jamaat Time** | When the Jummah Salah congregation begins |

**Validation:** Times must be in ascending order (Azaan < Khutbah < Jamaat). All three must be in HH:MM format.

**Display:** On Fridays, the Zuhr column in the musjid display is completely replaced with the Jummah card showing all three times.

---

### ☪️ Ramadan Override

**URL:** `admin.php?action=ramadan`

The Ramadan Override is one of MDS's most powerful features. During Ramadan, Fajr, Zuhr, Asr, and Esha Jamaat times often change from the standard timetable. This module lets you create a separate timetable just for Ramadan without modifying the permanent timetable.

#### Step 1: Create the Schedule

1. Navigate to Ramadan Override
2. Enter the **Ramadan Start Date** and **Ramadan End Date** (must be 29–31 consecutive days)
3. Click **"Create Ramadan Schedule"**
4. MDS automatically pre-fills each day's times from the main timetable as a starting point

#### Step 2: Edit the Grid

- A grid is shown with one row per day of Ramadan
- Columns: Fajr, Zuhr, Asr, Esha (Maghrib always uses the standard timetable)
- Changed cells are highlighted in gold
- Today's row is highlighted in green
- Click **"Save Ramadan Grid"** to save all changes

#### Step 3: Activate/Deactivate

- Use the **"Activate Ramadan Override"** / **"Deactivate"** toggle button
- When active, a 🟢 `LIVE` badge appears in the sidebar navigation
- When active, the display and website use Ramadan times instead of standard times
- The system automatically deactivates when the end date passes
- You can manually deactivate at any time

#### Step 4: Reset

After Ramadan ends, you can reset the schedule entirely to create a new one for next year.

---

### 🎨 Themes

**URL:** `admin.php?action=themes`

MDS has a complete colour theming system. Choose from 5 built-in presets or create a fully custom theme.

#### Built-in Presets

| Theme | Description |
|---|---|
| 🟢 **Green** (Default) | Deep Islamic green with gold accents. Rich, traditional feel. |
| 🔵 **Blue** | Deep navy blue with light blue accents. Clean, modern. |
| 🟤 **Burgundy** | Deep burgundy/maroon with gold accents. Warm and regal. |
| ⬛ **Grey** | Dark charcoal with silver accents. Neutral and sophisticated. |
| ⬜ **White** | Light/cream background with dark text. High contrast, daylight-friendly. |

#### Custom Theme

Select "Custom" to reveal 8 individual colour pickers:

| Variable | Effect |
|---|---|
| `gold` | Primary accent colour (labels, headings) |
| `gold_light` | Brighter accent (highlighted text, times) |
| `gold_dim` | Subdued accent (secondary labels) |
| `bg_dark` | Deepest background colour |
| `bg_mid` | Mid background (cards, panels) |
| `bg_accent` | Accent background (active elements) |
| `cream` | Primary text colour |
| `cream_dim` | Secondary/subdued text colour |

MDS automatically derives all other needed CSS variables (card backgrounds, borders, overlays) from these 8 base colours, and intelligently adapts for light vs dark themes.

#### Theme Application

The theme applies consistently to:
- ✅ Musjid display mode
- ✅ Public website
- ✅ Hadith page
- ✅ Admin panel

---

### 👥 User Management

**URL:** `admin.php?action=users` *(SuperAdmin only)*

#### User Roles

| Role | Access |
|---|---|
| **Admin** | All content management (times, messages, notices, tickers, Jummah, Ramadan, themes, Hijri) |
| **SuperAdmin** | Everything + user management, site settings, restore, simulator, Madhab setting |

#### Creating Users

- Username: letters, numbers, underscores, hyphens only
- Password: minimum 8 characters
- Role: admin or superadmin

#### Account Security Features

- **Login Attempt Tracking** — After 5 failed login attempts, the account is automatically locked for 15 minutes
- **Account Locking** — SuperAdmin can manually unlock locked accounts
- **CSRF Protection** — All forms include CSRF tokens
- **Session Management** — Secure session-based authentication
- **Audit Logging** — Restore operations are logged with username, IP, and user agent

---

### ⚙️ Site Settings

**URL:** `admin.php?action=settings` *(SuperAdmin only)*

| Setting | Description |
|---|---|
| **Site Name** | The name displayed in the musjid display header and admin sidebar (e.g., "NMC Masjid") |
| **Site URL** | Your mosque's website URL, used in the Hadith share text |
| **Madhab** | **Hanafi** or **Shafi** — controls which Asr "Earliest" time is displayed. Hanafi uses the later Asr start time (4× shadow), Shafi uses the earlier (2× shadow). |
| **Remove Copyright** | Toggle to hide the "Musjid Display System © Muhammed Cotwal" footer from the public site and ticker |

---

### 🛟 Backup & Restore

**URL:** `admin.php?action=restore` *(SuperAdmin only)*

MDS ships with the original 2016 prayer timetable stored as a backup table (`perpetual_salaah_times_orig_2016`) in the database. This allows you to restore the original timetable if data is accidentally corrupted.

#### How to Restore

1. Navigate to **Restore Backup**
2. Read the warning carefully — this is irreversible
3. Type exactly `RESTORE FROM BACKUP` in the confirmation field
4. Click the restore button

**What happens:**
1. All current timetable data is deleted (`DELETE FROM perpetual_salaah_times`)
2. All rows from the backup table are copied into the live table
3. The operation runs in a transaction — if anything fails, nothing changes
4. The restore is logged with your username, IP address, and timestamp

---

### 🧪 Simulator / Debug Tool

**URL (Musjid):** `index.php?display=musjid&sim=1&sim_date=YYYY-MM-DD&sim_time=HH:MM:SS`  
**URL (Website):** `index.php?sim=1&sim_date=YYYY-MM-DD&sim_time=HH:MM:SS`  
*(SuperAdmin only — links appear in the sidebar)*

The simulator is an essential development and testing tool. It lets you set a **fake time and day** to test how the display looks at any point in the day without waiting.

#### Simulator Panel

When the simulator is active, a floating control panel appears on screen:

| Control | Function |
|---|---|
| **Time Slider** | Drag to scrub through the day (0–24 hours) |
| **Time Input** | Type a specific time (HH:MM:SS) |
| **Date Input** | Set the simulated date |
| **Day Override** | Force a specific day of the week (e.g., force Friday to test Jummah display) |
| **Speed** | 1×, 2×, 5×, 10×, 60× — fast-forward through the day |
| **Pause/Resume** | Pause the simulation at a specific moment |
| **Collapse** | Minimise the panel to a badge |

#### What the Simulator Affects

- ✅ Prayer status pills (NOW / NEXT / MISSED)
- ✅ Countdown timers
- ✅ Jummah display (force Friday)
- ✅ Prayer window open/close logic
- ✅ Event marker countdowns
- ✅ Jamaat change detection (tests 3-day look-ahead)

---

## 🚀 Installation Guide

### Prerequisites

- PHP 8.1 or higher with the following extensions:
    - `pdo_sqlite` (SQLite database)
    - `session`
    - `json`
    - `calendar` (for Hijri date calculation)
    - `mbstring` (recommended)
- A web server: **Apache**, **Nginx**, or **Caddy**
- Write access to the directory (for `data.sqlite`)

> 💡 MDS also runs perfectly on a **Raspberry Pi** with `php-cli` and any web server.

---

### Step-by-Step Installation

#### 1. Download or Clone the Repository

```bash
git clone https://github.com/muhammedc/mds.git /var/www/mds
cd /var/www/mds
```

#### 2. Set File Permissions

```bash
# Give the web server write access to the directory (for data.sqlite)
chown -R www-data:www-data /var/www/mds
chmod 755 /var/www/mds
chmod 664 /var/www/mds/data.sqlite   # if it already exists
```

#### 3. Configure Your Web Server

**Apache** (`/etc/apache2/sites-available/mds.conf`):

```apache
<VirtualHost *:80>
    ServerName mds.yourmasjid.org.za
    DocumentRoot /var/www/mds
    DirectoryIndex index.php

    <Directory /var/www/mds>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/mds_error.log
    CustomLog ${APACHE_LOG_DIR}/mds_access.log combined
</VirtualHost>
```

Enable and reload:
```bash
a2ensite mds.conf
a2enmod rewrite
systemctl reload apache2
```

**Nginx** (`/etc/nginx/sites-available/mds`):

```nginx
server {
    listen 80;
    server_name mds.yourmasjid.org.za;
    root /var/www/mds;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Protect the database file
    location ~ \.sqlite$ {
        deny all;
        return 404;
    }
}
```

Enable and reload:
```bash
ln -s /etc/nginx/sites-available/mds /etc/nginx/sites-enabled/
systemctl reload nginx
```

> 🔒 **Security:** Always configure your web server to deny direct access to `data.sqlite`!

#### 4. Database Initialisation

MDS uses a SQLite database. On first run, the application will create `data.sqlite` automatically if it doesn't exist (assuming write permissions are set correctly).

If you are supplied with a pre-seeded `data.sqlite` (containing the full 365-day timetable and hadith database), copy it into the project root:

```bash
cp /path/to/data.sqlite /var/www/mds/data.sqlite
chown www-data:www-data /var/www/mds/data.sqlite
chmod 664 /var/www/mds/data.sqlite
```

#### 5. First Login

Navigate to `http://yoursite.com/admin.php`.

Default credentials (change immediately after first login!):

```
Username: admin
Password: Admin@MDS2026!
```

> ⚠️ **Change the default password immediately!** Go to `admin.php?action=users`, click your user, and set a strong password.

#### 6. Basic Configuration

After logging in:

1. Go to ⚙️ **Site Settings** and set your mosque name and URL
2. Go to 🌙 **Hijri Date** and set the offset for your region
3. Go to 🕐 **Salaah Times** and verify today's times are correct
4. Go to 🕌 **Jummah Times** and set the correct Friday times
5. Go to 🎨 **Themes** and choose your preferred colour theme

#### 7. Set Up the TV Display

On the TV/display computer:

1. Open a web browser in **kiosk / fullscreen mode**
2. Navigate to: `http://yoursite.com/?display=musjid`
3. Set the browser to open this page on startup

**Chrome Kiosk Mode:**
```bash
chromium-browser --kiosk --noerrdialogs --disable-infobars "http://yoursite.com/?display=musjid"
```

**Firefox Kiosk Mode:**
```bash
firefox --kiosk "http://yoursite.com/?display=musjid"
```

---

## 🐳 Docker Deployment

Docker is ideal for running MDS with persistent data and easy updates.

### Project Structure for Docker

```
mds/
├── Dockerfile
├── docker-compose.yml
├── admin.php
├── index.php
├── hadith.php
├── css/
│   └── bootstrap.min.css
└── data/                    ← mounted volume (persistent)
    └── data.sqlite
```

### Dockerfile

```dockerfile
FROM php:8.1-apache

# Enable required PHP extensions
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite calendar \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Configure Apache to allow .htaccess
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Copy application files
COPY . /var/www/html/

# Create data directory and set permissions
RUN mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html \
    && chmod 755 /var/www/html \
    && chmod 775 /var/www/html/data

# Set working directory
WORKDIR /var/www/html

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

EXPOSE 80
```

> **Note:** The `data.sqlite` path in the PHP files must be updated to point to `/var/www/html/data/data.sqlite` for the Docker volume mount to work correctly. Alternatively, symlink the file.

### docker-compose.yml

```yaml
version: '3.8'

services:
  mds:
    build: .
    container_name: mds_app
    restart: unless-stopped
    ports:
      - "80:80"
      # Uncomment for HTTPS with a reverse proxy:
      # - "8080:80"
    volumes:
      # Persist the SQLite database across container restarts/updates
      - mds_data:/var/www/html/data
    environment:
      - TZ=Africa/Johannesburg
    labels:
      - "com.example.description=Musjid Display System"

volumes:
  mds_data:
    driver: local
    # Optional: specify host path for the volume (easier backup)
    # driver_opts:
    #   type: none
    #   o: bind
    #   device: /opt/mds/data

```

### With HTTPS via Traefik (Recommended for Production)

```yaml
version: '3.8'

services:
  mds:
    build: .
    container_name: mds_app
    restart: unless-stopped
    volumes:
      - mds_data:/var/www/html/data
    environment:
      - TZ=Africa/Johannesburg
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.mds.rule=Host(`mds.yourmasjid.org.za`)"
      - "traefik.http.routers.mds.entrypoints=websecure"
      - "traefik.http.routers.mds.tls.certresolver=letsencrypt"
    networks:
      - traefik_net

  traefik:
    image: traefik:v2.10
    container_name: traefik
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - traefik_certs:/etc/traefik/acme
    command:
      - "--providers.docker=true"
      - "--providers.docker.exposedbydefault=false"
      - "--entrypoints.web.address=:80"
      - "--entrypoints.websecure.address=:443"
      - "--certificatesresolvers.letsencrypt.acme.httpchallenge=true"
      - "--certificatesresolvers.letsencrypt.acme.httpchallenge.entrypoint=web"
      - "--certificatesresolvers.letsencrypt.acme.email=admin@yourmasjid.org.za"
      - "--certificatesresolvers.letsencrypt.acme.storage=/etc/traefik/acme/acme.json"
    networks:
      - traefik_net

volumes:
  mds_data:
  traefik_certs:

networks:
  traefik_net:
    external: true
```

### Build and Run

```bash
# Build and start
docker-compose up -d --build

# View logs
docker-compose logs -f mds

# Stop
docker-compose down

# Update (preserves data volume)
git pull
docker-compose up -d --build
```

### Backing Up the SQLite Database

```bash
# Backup
docker cp mds_app:/var/www/html/data/data.sqlite ./backup_$(date +%Y%m%d).sqlite

# Or if using a named volume:
docker run --rm \
  -v mds_data:/data \
  -v $(pwd):/backup \
  alpine tar czf /backup/mds_backup_$(date +%Y%m%d).tar.gz /data

# Restore
docker cp ./backup_20260225.sqlite mds_app:/var/www/html/data/data.sqlite
docker exec mds_app chown www-data:www-data /var/www/html/data/data.sqlite
```

### Raspberry Pi Deployment

MDS runs excellently on a Raspberry Pi for an all-in-one mosque display solution.

```bash
# Install on Raspberry Pi (Raspberry Pi OS / Debian)
sudo apt update
sudo apt install -y php8.1 php8.1-sqlite3 php8.1-calendar apache2 libapache2-mod-php8.1

# Clone MDS
sudo git clone https://github.com/muhammedc/mds.git /var/www/mds

# Permissions
sudo chown -R www-data:www-data /var/www/mds
sudo chmod 775 /var/www/mds

# Configure Apache (point DocumentRoot to /var/www/mds)
# Then enable the site and restart apache2

# Auto-start Chromium in kiosk mode on boot
# Add to /etc/xdg/lxsession/LXDE-pi/autostart:
@chromium-browser --kiosk --noerrdialogs --disable-infobars "http://localhost/?display=musjid"
```

---

## 🗄️ Database Schema

MDS uses SQLite with the following tables:

```sql
-- ══════════════════════════════════════════════
-- Perpetual prayer timetable (365/366 days)
-- month + date (not year) = repeats annually
-- ══════════════════════════════════════════════
CREATE TABLE perpetual_salaah_times (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    month     INTEGER NOT NULL,          -- 1–12
    date      INTEGER NOT NULL,          -- 1–31
    fajr      TEXT NOT NULL,             -- Jamaat time HH:MM:SS
    sehri     TEXT,                      -- Sehri end (= Fajr earliest)
    sunrise   TEXT,                      -- Sunrise time
    zawaal    TEXT,                      -- Solar noon
    zuhr      TEXT NOT NULL,             -- Zuhr Jamaat
    zuhr_e    TEXT,                      -- Zuhr earliest
    asr       TEXT NOT NULL,             -- Asr Jamaat
    asr_e     TEXT,                      -- Asr earliest (Shafi)
    asr_e_h   TEXT,                      -- Asr earliest (Hanafi)
    maghrib   TEXT NOT NULL,             -- Maghrib Jamaat (= Sunset)
    sunset    TEXT,                      -- Sunset / Iftaar time
    esha      TEXT NOT NULL,             -- Esha Jamaat
    esha_e    TEXT,                      -- Esha earliest
    UNIQUE(month, date)
);

-- Backup of original 2016 timetable (read-only)
CREATE TABLE perpetual_salaah_times_orig_2016 (
    -- Same structure as above
    ...
);

-- ══════════════════════════════════════════════
-- Jummah (Friday) times
-- ══════════════════════════════════════════════
CREATE TABLE jummah_settings (
    id           INTEGER PRIMARY KEY,  -- Always 1 (single row)
    azaan_time   TEXT NOT NULL,        -- HH:MM:SS
    khutbah_time TEXT NOT NULL,
    jamaat_time  TEXT NOT NULL,
    updated_at   TEXT DEFAULT (datetime('now'))
);

-- ══════════════════════════════════════════════
-- Community messages (slideshow content)
-- ══════════════════════════════════════════════
CREATE TABLE community_messages (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    title        TEXT,
    content_type TEXT DEFAULT 'html',   -- 'html' or 'image'
    content_html TEXT,                  -- Rich HTML content
    media_id     INTEGER,               -- FK to media table
    image_fit    TEXT DEFAULT 'contain',-- 'contain' or 'cover'
    display_secs INTEGER DEFAULT 30,
    sort_order   INTEGER DEFAULT 0,
    is_active    INTEGER DEFAULT 1,
    start_dt     TEXT,                  -- Optional schedule start
    end_dt       TEXT,                  -- Optional schedule end
    created_at   TEXT DEFAULT (datetime('now')),
    updated_at   TEXT DEFAULT (datetime('now'))
);

-- ══════════════════════════════════════════════
-- Media / image storage
-- ══════════════════════════════════════════════
CREATE TABLE media (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    mime_type  TEXT,
    data       BLOB,                    -- Raw image binary
    created_at TEXT DEFAULT (datetime('now'))
);

-- ══════════════════════════════════════════════
-- Funeral notices
-- ══════════════════════════════════════════════
CREATE TABLE funeral_notices (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    deceased_name     TEXT NOT NULL,
    age               INTEGER,
    date_of_passing   TEXT,             -- Gregorian (display string)
    date_of_passing_h TEXT,             -- Hijri (display string)
    janazah_time      TEXT,             -- HH:MM
    janazah_location  TEXT,
    proceeding_to     TEXT,
    funeral_date_en   TEXT,             -- Formatted English date
    funeral_date_hijri TEXT,
    display_secs      INTEGER DEFAULT 30,
    is_active         INTEGER DEFAULT 1,
    start_dt          TEXT,
    end_dt            TEXT,
    created_at        TEXT DEFAULT (datetime('now')),
    updated_at        TEXT DEFAULT (datetime('now'))
);

-- ══════════════════════════════════════════════
-- Ticker (bottom scrolling strip) messages
-- ══════════════════════════════════════════════
CREATE TABLE ticker_messages (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    message_text TEXT NOT NULL,
    display_secs INTEGER DEFAULT 30,
    sort_order   INTEGER DEFAULT 0,
    is_active    INTEGER DEFAULT 1,
    start_dt     TEXT,
    end_dt       TEXT,
    created_at   TEXT DEFAULT (datetime('now')),
    updated_at   TEXT DEFAULT (datetime('now'))
);

-- ══════════════════════════════════════════════
-- Hadith database (365 entries, one per day)
-- ══════════════════════════════════════════════
CREATE TABLE hadith_db (
    uid  INTEGER PRIMARY KEY,   -- Day number 1–365
    text TEXT NOT NULL          -- Full hadith text with source in parentheses
);

-- ══════════════════════════════════════════════
-- Ramadan schedule
-- ══════════════════════════════════════════════
CREATE TABLE ramadan_schedule (
    id         INTEGER PRIMARY KEY,  -- Always 1
    start_date TEXT NOT NULL,        -- YYYY-MM-DD
    end_date   TEXT NOT NULL,        -- YYYY-MM-DD
    is_active  INTEGER DEFAULT 0,
    created_at TEXT DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE ramadan_override (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    prayer_date TEXT NOT NULL UNIQUE, -- YYYY-MM-DD
    fajr        TEXT NOT NULL,        -- HH:MM:SS
    zuhr        TEXT NOT NULL,
    asr         TEXT NOT NULL,
    esha        TEXT NOT NULL
    -- Maghrib always uses standard table
);

-- ══════════════════════════════════════════════
-- Admin users
-- ══════════════════════════════════════════════
CREATE TABLE admin_users (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    username       TEXT NOT NULL UNIQUE,
    password_hash  TEXT NOT NULL,       -- password_hash() bcrypt
    role           TEXT DEFAULT 'admin',-- 'admin' or 'superadmin'
    login_attempts INTEGER DEFAULT 0,
    locked_until   TEXT,               -- datetime or NULL
    created_at     TEXT DEFAULT (datetime('now')),
    updated_at     TEXT DEFAULT (datetime('now'))
);

-- ══════════════════════════════════════════════
-- Site settings (key-value store)
-- ══════════════════════════════════════════════
CREATE TABLE site_settings (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    setting_key   TEXT NOT NULL,
    setting_value TEXT,
    updated_at    TEXT DEFAULT (datetime('now'))
);
-- Keys used: site_name, site_url, madhab, hijri_offset,
--            active_theme, custom_theme_json, remove_copyright,
--            content_version
```

---

## ⚙️ Configuration Reference

All configuration is managed through the admin panel — there are no `.env` or config files to edit. Key settings and where to find them:

| Setting | Admin Location | Default |
|---|---|---|
| Site Name | ⚙️ Settings | `Musjid Display System` |
| Site URL | ⚙️ Settings | *(empty)* |
| Madhab | ⚙️ Settings | `hanafi` |
| Hijri Offset | 🌙 Hijri Date | `0` |
| Active Theme | 🎨 Themes | `green` |
| Custom Theme JSON | 🎨 Themes (custom) | `{}` |
| Remove Copyright | ⚙️ Settings | `false` |
| Jummah Times | 🕌 Jummah | `12:20, 12:30, 13:00` |
| Ramadan Active | ☪️ Ramadan | `false` |

**Timezone** is set in code at the top of each PHP file:
```php
date_default_timezone_set('Africa/Johannesburg');
```
Change this to your mosque's timezone if needed (e.g., `Asia/Karachi`, `Europe/London`).

---

## 🧮 Prayer Time Logic

### Validity Windows

MDS calculates whether each prayer's window is currently open using the following rules:

| Prayer | Window Opens | Window Closes |
|---|---|---|
| Fajr | Fajr Earliest (`sehri` time) | Sunrise |
| Zuhr | Zuhr Earliest (`zuhr_e`) | Asr Boundary |
| Asr | Asr Boundary | Sunset |
| Maghrib | Maghrib time (sunset) | Esha Earliest |
| Esha | Esha Earliest (`esha_e`) | Fajr Earliest next day |

**Asr Boundary (Madhab-aware):**
- **Hanafi:** Asr starts at `asr_e_h` (later — shadow 4× object height)
- **Shafi:** Asr starts at `asr_e` (earlier — shadow 2× object height)

### Jamaat Change Detection

MDS continuously monitors whether Jamaat times are changing within the next **3 days** (configurable in code). The detection algorithm:

1. Loads today's Jamaat times
2. Loads the next 3 days' Jamaat times (or Ramadan override times if active)
3. Compares each prayer's time day-by-day
4. Groups consecutive same-direction changes together
5. Calculates the difference in minutes (earlier or later)
6. Returns a list of changes with: prayer name, from-time, to-time, date, day name, direction (earlier/later), minutes difference

This powers both:
- The **website banner** (dismissible, shown to community)
- The **musjid display slide** (injected as the first community notice slide)

---

## ❓ Frequently Asked Questions

**Q: Do I need to enter the "Earliest" times myself?**  
A: Earliest times (Sehri, Sunrise, Zawaal, Zuhr Earliest, Asr Earliest Hanafi/Shafi, Sunset, Esha Earliest) are astronomical times that should come pre-loaded in your database. You typically only edit the **Jamaat** times (the congregational prayer times set by your mosque committee). Contact your local Islamic institute or use an astronomical prayer time calculator for your coordinates to generate these. You will have to edit these in the database for now. Full editing on the admin section is for a future version.

**Q: The default timetable in the database — can I just use it as-is?**  
A: **No. Absolutely not without verification.** The sample timetable is provided as a technical starting point to demonstrate the system. It was created for a specific location (Newcastle, KwaZulu-Natal, South Africa) and will be incorrect for any other location. Even if you are in the same city, your mosque's Jamaat times are set by your mosque committee and must be entered manually. See the [⚠️ Critical Warnings](#️-critical-warnings--disclaimers) section above.

**Q: Does MDS calculate prayer times automatically from my GPS coordinates?**  
A: No. MDS is a **display and management tool only**. It does not calculate prayer times. You must enter all times manually. For a prayer time calculator, consult your local Ulama council or use a separate tool such as IslamicFinder, then enter the verified results into MDS. (Considering this feature for future versions)

**Q: Who is responsible for the prayer times shown on the display?**  
A: The **mosque administration** is solely responsible. The software author bears zero responsibility for the accuracy or correctness of any times displayed. See the [Legal Disclaimer](#️-full-legal-disclaimer--limitation-of-liability) section.

**Q: What happens if today's prayer times are missing from the database?**  
A: The display will show a message indicating prayer times are not available. Always ensure all 365 days are populated.

**Q: Can I run this without internet access?**  
A: Yes! Once installed and populated with data, MDS runs entirely offline. Google Fonts are the only external dependency — if offline, you can download them and serve locally.

**Q: The Hijri date is wrong. What do I do?**  
A: Go to 🌙 **Hijri Date** in the admin panel and adjust the offset by ±1 or ±2 days to match your local moon-sighting authority.

**Q: Can I have multiple mosques on one installation?**  
A: No — MDS is designed for a single mosque. For multiple mosques, run separate instances (Docker makes this easy).

**Q: Is the data.sqlite file safe from web access?**  
A: Only if your web server is configured to deny access to `.sqlite` files. See the web server configuration examples above — this is critical for security.

**Q: How do I update MDS?**  
A: `git pull` in the project directory, then restart your web server. The database schema is managed gracefully with `INSERT OR IGNORE` and `@` error suppression for new columns.

**Q: Can I use this on shared hosting?**  
A: Yes, as long as your host has PHP 8.1+ with `pdo_sqlite` and `calendar` extensions. Check with your hosting provider.

**Q: Will my TV get burn-in?**  
A: It depends on the display technology and how you manage it. OLED and Plasma screens are most at risk. See the [🖥️ Screen Burn-In & Hardware Considerations](#️-screen-burn-in--hardware-considerations) section for detailed guidance. The author accepts no liability for display damage.

**Q: What type of TV/screen should I buy for the mosque?**  
A: For always-on use, purchase a **commercial-grade digital signage display** rated for 24/7 operation (look for panels by Samsung, LG, or Philips in their commercial/signage range). Do not use a regular consumer TV for permanent display. The author makes no hardware recommendations and accepts no liability for hardware decisions.

**Q: How do I back up the database?**  
A: Simply copy the `data.sqlite` file to a safe location. See the Docker section for container backup commands. Back up regularly — there is no cloud backup built into MDS.

**Q: Something is wrong — a prayer is showing the wrong time. Who do I contact?**  
A: First, check and correct the time in `admin.php?action=times`. Times shown by MDS are exactly what is stored in the database — the software does not modify them. If the database has the wrong time, that is a data entry issue, not a software bug. The mosque administrator is responsible for all data.

---

---

## ⚖️ Full Legal Disclaimer & Limitation of Liability

> **This section constitutes a binding legal disclaimer. By downloading, installing, or using MDS in any form, you unconditionally agree to all terms stated herein.**

---

### 1. Software Provided "As Is"

MDS — Musjid Display System is provided **"as is"** and **"as available"**, without warranty of any kind, express or implied, including but not limited to:

- Warranties of **merchantability** or **fitness for a particular purpose**
- Warranties that the software will be **error-free**, **uninterrupted**, or **free of vulnerabilities**
- Warranties regarding the **accuracy, completeness, or correctness** of any data, times, or content displayed
- Warranties of **compatibility** with any specific hardware, browser, operating system, or hosting environment

---

### 2. Prayer Times — No Religious Warranty

**THE AUTHOR MAKES ABSOLUTELY NO WARRANTY, GUARANTEE, OR REPRESENTATION THAT:**

- The prayer times included in any sample or default database are correct for any location
- The prayer time calculation logic complies with any specific school of Islamic jurisprudence (madhab)
- The Hijri date calculation is accurate or matches moon-sighting authorities in any country
- The Jummah times, Ramadan times, or any other times configured in the system are Islamically correct

**THE SOLE RESPONSIBILITY FOR VERIFYING, ENTERING, MAINTAINING, AND APPROVING ALL PRAYER TIMES RESTS WITH THE MOSQUE ADMINISTRATION AND THEIR APPOINTED ISLAMIC SCHOLARS OR ULAMA.**

The author is not an Islamic scholar, astronomer, or prayer time authority. The software is a *display tool only* — it shows what you put in.

---

### 3. No Liability for Religious Consequences

The author shall not be liable for any:

- Prayers performed at incorrect times due to erroneous data in the system
- Religious obligations missed or invalidated due to displayed times being wrong
- Community harm, reputational damage, or religious controversy arising from incorrect times being displayed
- Any fatwa, religious ruling, or obligation arising from use of this system

---

### 4. No Liability for Data Loss

The author shall not be liable for:

- Loss of prayer time data, community messages, or any other data stored in `data.sqlite`
- Data corruption due to hardware failure, power loss, software bugs, or any other cause
- Failure to backup data, resulting in irrecoverable loss

**You are responsible for implementing and maintaining regular backups of your `data.sqlite` file.**

---

### 5. No Liability for Security Incidents

The author shall not be liable for:

- Unauthorised access to the admin panel resulting from weak passwords or misconfiguration
- Data breaches resulting from failure to protect the `data.sqlite` file from web access
- Security vulnerabilities in PHP, SQLite, Apache/Nginx, or any third-party dependency
- Any damage, loss, or harm resulting from the use of this software on an inadequately secured server

---

### 6. No Liability for Hardware Damage

The author shall not be liable for:

- Screen burn-in, image retention, or permanent display damage from running MDS on inappropriate or consumer-grade hardware
- Any costs associated with hardware repair or replacement
- Failure of any hardware component used to run or display MDS

---

### 7. No Liability for Indirect or Consequential Damages

**IN NO EVENT SHALL THE AUTHOR, MUHAMMED COTWAL, BE LIABLE FOR ANY:**

- Direct, indirect, incidental, special, exemplary, or consequential damages
- Loss of profits, revenue, data, goodwill, or other intangible losses
- Costs of procurement of substitute goods or services
- Damages arising from personal injury, property damage, or loss of life (in the highly unlikely event this software could contribute to such)

**REGARDLESS OF THE THEORY OF LIABILITY** (contract, tort, negligence, strict liability, or otherwise), **EVEN IF THE AUTHOR HAS BEEN ADVISED OF THE POSSIBILITY OF SUCH DAMAGES.**

---

### 8. Indemnification

By using MDS, you agree to **indemnify, defend, and hold harmless** Muhammed Cotwal and any contributors from and against any and all claims, liabilities, damages, losses, costs, and expenses (including reasonable legal fees) arising out of or in any way connected with:

- Your use or misuse of the software
- Your violation of these terms
- Your display of incorrect or unverified prayer times to your congregation
- Any third-party claim arising from your deployment of this software

---

### 9. Governing Law

This disclaimer and any disputes arising from the use of MDS shall be governed by and construed in accordance with the laws of the **Republic of South Africa**, without regard to its conflict of law provisions.

---

### 10. Entire Agreement

This disclaimer, together with the license terms below, constitutes the **entire agreement** between you and the author regarding the use of MDS. If any provision of this disclaimer is found to be unenforceable, the remaining provisions shall remain in full force and effect.

---

### 11. Acknowledgement

> **By installing or using MDS, you acknowledge that you have read, understood, and agree to be bound by all disclaimers and terms stated in this document. If you do not agree, do not install or use this software.**

---

## 📜 Licensing

### MDS Community License (Non-Commercial, Fi Sabīlillāh)

**Version 1.0, 2026**

This software is licensed under the following terms:

#### ✅ You MAY:
- Use, install, and run MDS at **no charge** for non-commercial purposes
- Use MDS in any mosque, Islamic school, Islamic centre, or community organisation **provided the service is offered free of charge** (fi sabīlillāh — for the sake of Allāh)
- Modify the source code for your own non-commercial use
- Share the software with other mosques and Islamic organisations under these same terms

#### ❌ You MAY NOT:
- Use, sell, license, or distribute MDS (or any derivative work) in **any commercial context**
- Charge money for access to a service powered by MDS
- Include MDS in any commercial product or SaaS platform
- Remove or alter copyright notices
- Sub-license the software under different terms
- Use the software in any application where users pay a fee

#### 📌 The Guiding Principle:

> *This software was created as an act of worship (ibādah) and community service. It may only be used where it serves the Muslim community free of charge — fi sabīlillāh (for the sake of Allāh). Commercial use of any kind is strictly prohibited.*

#### ⚖️ Legal Text

```
MDS — Musjid Display System
Copyright © 2016–2026 Muhammed Cotwal. All Rights Reserved.

Permission is hereby granted, free of charge, to any person or organisation 
obtaining a copy of this software and associated documentation files (the "Software"), 
to use, copy, modify, and distribute the Software, subject to the following conditions:

1. The Software, and any application or service built upon it, must be provided 
   free of charge to all end users. No fee, subscription, donation requirement, 
   or any other form of payment may be required in exchange for access to or 
   use of the Software or any service powered by it.

2. The Software may not be used, incorporated into, or distributed as part of 
   any commercial product, commercial service, or for-profit enterprise.

3. All copies or substantial portions of the Software must retain this copyright 
   notice, these conditions, and the following disclaimer.

4. The name "Muhammed Cotwal" and "MDS — Musjid Display System" may not be used 
   to endorse or promote products derived from this Software without specific prior 
   written permission.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, 
INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A 
PARTICULAR PURPOSE AND NONINFRINGEMENT. THE AUTHOR MAKES NO WARRANTY THAT THE 
SOFTWARE OR ANY DATA IT DISPLAYS IS ACCURATE, CORRECT, OR SUITABLE FOR ANY 
RELIGIOUS, LEGAL, OR OTHER PURPOSE.

IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, 
WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN 
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE, INCLUDING 
BUT NOT LIMITED TO: INCORRECT PRAYER TIMES, MISSED OR INVALIDATED PRAYERS, DATA LOSS, 
SECURITY INCIDENTS, HARDWARE DAMAGE (INCLUDING SCREEN BURN-IN), OR ANY INDIRECT, 
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES.

THE USER ASSUMES FULL AND SOLE RESPONSIBILITY FOR VERIFYING ALL PRAYER TIMES AND 
OTHER RELIGIOUS DATA DISPLAYED BY THIS SOFTWARE WITH A QUALIFIED ISLAMIC SCHOLAR 
OR AUTHORITY BEFORE DISPLAYING SUCH INFORMATION TO ANY CONGREGATION.
```

---

## © Copyright Notice

```
╔══════════════════════════════════════════════════════════════╗
║                                                              ║
║   MDS — Musjid Display System                               ║
║                                                              ║
║   Original Script  : © Muhammed Cotwal, 2016                ║
║   Complete Redesign: © Muhammed Cotwal, 2026                ║
║                                                              ║
║   All Rights Reserved.                                       ║
║                                                              ║
║   Unauthorised copying, reproduction, redistribution,        ║
║   or commercial use of this software, in whole or in part,  ║
║   via any medium, is strictly prohibited.                    ║
║                                                              ║
║   This software is provided as a free community service      ║
║   (fi sabīlillāh) for Islamic institutions.                  ║
║                                                              ║
║   GitHub: https://github.com/muhammedc/mds                  ║
║                                                              ║
╚══════════════════════════════════════════════════════════════╝
```

---

<div align="center">

**🕌 May Allāh ﷻ accept this effort and make it a source of benefit for the Ummah. Āmeen.**

*بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ*

---

Made with ❤️ for the Muslim Ummah · Originally designed for the Newcastle Muslim Community · South Africa  
© 2016–2026 Muhammed Cotwal · All Rights Reserved · E&OE

</div>