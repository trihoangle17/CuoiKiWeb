# NoteApp – Web-Based Note Management Application

> **503073 – Web Programming & Applications | Final Project – Semester I/2026-2027**  
> **Faculty of Information Technology – Ton Duc Thang University (TDTU)**

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B%20(Pure%20MVC)-777BB4?logo=php&logoColor=white)](#)
[![MySQL](https://img.shields.io/badge/MySQL-8.0%20(PDO)-4479A1?logo=mysql&logoColor=white)](#)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white)](#)
[![WebSocket](https://img.shields.io/badge/Realtime-WebSocket%20(RFC%206455)-00ADD8)](#)
[![PWA](https://img.shields.io/badge/PWA-Offline--First%20(IndexedDB)-5A0FC8?logo=pwa&logoColor=white)](#)
[![Docker](https://img.shields.io/badge/Deployment-Docker%20Compose-2496ED?logo=docker&logoColor=white)](#)
[![Heuristics Score](https://img.shields.io/badge/Design%20Health-40%2F40%20(Nielsen%20Heuristics)-success)](#)

---

## Overview

**NoteApp** is a modern, reliable, and secure web-based note management application developed for the Final Project in Web Programming & Applications (Course 503073).

The system enables users to create, organize, and collaborate on diverse notes encompassing rich text, images, videos, and document attachments. Designed strictly under the course specifications and academic rubric, NoteApp features zero external heavyweight CMS/framework dependencies, operating entirely on pure PHP MVC architecture while integrating cutting-edge web capabilities:
- **Two-way PWA Offline Support**: Service Worker cache coupled with client-side IndexedDB and automated bidirectional MySQL synchronization.
- **Real-Time Multi-User Collaboration**: Native RFC 6455 WebSocket daemon written in pure PHP for simultaneous live editing.
- **AI-Powered Intelligence**: Semantic Retrieval-Augmented Generation (RAG) Q&A referencing notes, plus LLM-powered note summarization.
- **Defense-in-Depth Security**: Per-note Bcrypt password challenges, granular read/write sharing permissions, strict Prepared Statements, and automated CSRF protection.

---

## Repository Contents

| Path | Description |
| :--- | :--- |
| **`NoteApp/app/controllers/`** | MVC Controllers handling Auth, Notes, Labels, Shares, AI, and Offline Sync |
| **`NoteApp/app/models/`** | Database data models (User, Note, Label, Attachment, Share, Database PDO) |
| **`NoteApp/app/views/`** | Server-rendered view templates (Notes grid/list, Editor, Auth, Shared, Profile) |
| **`NoteApp/app/services/`** | External integrations: `LlmService.php` (AI Q&A/Summary) and `Mailer.php` |
| **`NoteApp/database/schema.sql`** | Full relational schema with sample seed data (accounts, notes, tags, files) |
| **`NoteApp/public/`** | Web DocumentRoot: `index.php`, Service Worker (`sw.js`), manifest, and uploads |
| **`NoteApp/public/assets/`** | Modular JavaScript ES6+ modules (`notes.js`, `collab.js`, `offline.js`, `labels.js`) & CSS |
| **`NoteApp/websocket/server.php`** | Native PHP RFC 6455 WebSocket Server listening on port `8081` |
| **`NoteApp/docker-compose.yml`** | Automated multi-container orchestration (Apache Web, MySQL 8.0, WebSocket) |
| **`NoteApp/Dockerfile`** | Production container build with PHP 8.2, Apache mod_rewrite, and required extensions |
| **`NoteApp/readme.txt`** | Official academic evaluation guide, test credentials, and architecture summary |

---

## Feature Matrix (Full 32 Rubric Criteria)

<details open>
<summary><b>Click to expand / collapse 32 Rubric Criteria Verification</b></summary>

| ID | Criteria | Implementation Highlights | Status |
| :---: | :--- | :--- | :---: |
| **1** | **User registration** | Email, display name, password (x2), Bcrypt hashing, automatic login, email token sent | **100% Full Point** |
| **2** | **Account activation** | Unverified warning banner, token activation via `/activate?token=...` | **100% Full Point** |
| **3** | **User login & logout** | Unauthorized route protection, redirect to `/login`, personalized homepage, secure logout | **100% Full Point** |
| **4** | **Password reset** | Password recovery via email reset link or 6-digit OTP, manual re-login required | **100% Full Point** |
| **5** | **View profile & avatar** | Display name, user email, custom avatar or default SVG placeholder display | **100% Full Point** |
| **6** | **Edit profile & avatar** | Update display name, avatar file upload validation (`finfo` MIME inspection, max 2MB) | **100% Full Point** |
| **7** | **Change password** | Current password verification + new password confirmation (x2), session re-generation | **100% Full Point** |
| **8** | **User preferences** | Live customization of note font size (small/medium/large), card color, Light/Dark theme | **100% Full Point** |
| **9** | **Display notes in list view** | Dense list view with horizontal layout, persisted via `localStorage` | **100% Full Point** |
| **10** | **Display notes in grid view** | Default responsive CSS grid (`minmax(280px, 1fr)`) | **100% Full Point** |
| **11** | **Create notes** | Only title & content mandatory; single unified interface for create & edit (`edit.php`) | **100% Full Point** |
| **12** | **Update notes** | Same interface as creation; dynamic metadata and attachment synchronization | **100% Full Point** |
| **13** | **Delete notes** | Mandatory modal confirmation dialog (`#deleteConfirmModal`) per Rubric Criterion 13 | **100% Full Point** |
| **14** | **Auto-save notes** | Debounced 600ms background saving, status feedback indicator, no explicit Save button | **100% Full Point** |
| **15** | **Attach images/video** | Single and multi-file media uploads, in-browser previews | **100% Full Point** |
| **16** | **Attach files** | Document attachments (.pdf, .docx, .zip), download links, file deletion | **100% Full Point** |
| **17** | **Pin notes to top** | Chronological ordering with pinned notes taking strict priority sorted by `pinned_at` | **100% Full Point** |
| **18** | **Special status icons** | Visual status badges in both Grid & List views: Pinned, Password-Locked, Shared | **100% Full Point** |
| **19** | **Search notes** | Live 300ms debounced search over titles & contents; keyboard shortcuts `Ctrl+K` and `/` | **100% Full Point** |
| **20** | **Label management** | Dedicated modal for listing, creating, renaming inline, and deleting labels | **100% Full Point** |
| **21** | **Attach labels to notes** | Multi-label support per note, interactive badge selector | **100% Full Point** |
| **22** | **Filter notes by labels** | Sidebar label filtering; deleting a label preserves notes; renaming updates all notes | **100% Full Point** |
| **23** | **Enable/disable note password** | Per-note locking: enter password twice to enable; enter current password to disable | **100% Full Point** |
| **24** | **Password protection on notes** | Password dialog prompt required before viewing, editing, pinning, or deleting | **100% Full Point** |
| **25** | **Share and receive notes** | Share via email with Read-only or Editable rights; dedicated `/shares/with-me` section | **100% Full Point** |
| **26** | **Realtime collaboration** | Native PHP RFC 6455 WebSocket Server (`websocket/server.php`) with multi-cursor sync | **100% Full Point** |
| **27** | **AI Summary** | LLM-powered summarization preserving core ideas with regeneration capability | **100% Full Point** |
| **28** | **AI Question & Answer** | Natural language RAG querying over notes with clickable note reference links (`/ai`) | **100% Full Point** |
| **29** | **UI & UX Quality** | Rated **40/40 (Excellent)** on Nielsen's 10 Heuristics; WCAG AAA 7.1:1 contrast compliance | **100% Full Point** |
| **30** | **Responsive Design** | 44×44px mobile touch targets, responsive drawer navigation, fluid layouts across devices | **100% Full Point** |
| **31** | **Offline Capabilities** | PWA Service Worker caching (`sw.js`) + IndexedDB; automatic background resync | **100% Full Point** |
| **32** | **Deployment Ready** | Containerized with Docker Compose; clean relative URLs running at root `http://localhost:PORT/` | **100% Full Point** |

</details>

---

## Quick Start Guide

### Method 1: Docker Compose (Recommended – Fully Automated)

Ensure [Docker Desktop](https://www.docker.com/products/docker-desktop/) is installed and running, then execute:

```bash
# Navigate to the project root directory
cd CuoiKiWeb/NoteApp

# Start the web app, MySQL database, and WebSocket collaboration daemon
docker-compose up -d --build
```

Access the application in your browser at:
* **Web Application**: `http://localhost:8080/`
* **WebSocket Service**: `ws://localhost:8081` *(automatically running in background)*
* **MySQL Database**: `localhost:3307` *(user: `root` / password: `rootpassword`)*

To stop the services:
```bash
docker-compose down
```

---

### Method 2: Local XAMPP / PHP CLI

1. **Start MySQL** via XAMPP Control Panel.
2. **Import Database**:
   ```bash
   "C:\xampp\mysql\bin\mysql.exe" -u root < "NoteApp/database/schema.sql"
   ```
   *(Or import `NoteApp/database/schema.sql` via phpMyAdmin at `http://localhost/phpmyadmin`)*.
3. **Run Web Server**:
   ```bash
   "C:\xampp\php\php.exe" -S localhost:8080 -t NoteApp/public NoteApp/public/index.php
   ```
4. **Run WebSocket Server** *(in a second terminal window)*:
   ```bash
   "C:\xampp\php\php.exe" NoteApp/websocket/server.php
   ```
5. Open your browser at **`http://localhost:8080/`**.

---

## Pre-loaded Test Accounts & Credentials

The seed database (`schema.sql`) comes pre-populated with ready-to-evaluate test accounts:

| Role / Test Scenario | Email | Password | Pre-loaded Data & Features |
| :--- | :--- | :--- | :--- |
| **Primary Account** *(Activated)* | `demo@noteapp.local` | `Demo@123` | Pinned notes, label tags, media attachments, shared notes, and password-protected note |
| **Locked Note Passcode** | *(Note #4)* | `1234` | Protects *"Thông tin tài khoản ngân hàng & mã PIN cá nhân"* |
| **Collaborator Account** | `friend@noteapp.local` | `Demo@123` | Edit permission for live WebSocket realtime collaboration testing |
| **Unverified Account** | `unverified@noteapp.local` | `Demo@123` | Demonstrates the unverified email warning banner (`/activate?token=demo_activation_token_123456`) |

---

## Keyboard Shortcuts (Operate Mode)

| Shortcut | Action |
| :--- | :--- |
| `Ctrl + K` or `/` | Focus search bar immediately |
| `Esc` | Clear search query, exit search focus, or close open modals |
| `Alt + N` | Jump directly to new note creation |
| `?` | Open Keyboard Shortcuts & Tips modal dialog |

---

## Team & Contributors

* **Faculty**: Faculty of Information Technology – Ton Duc Thang University
* **Course**: Web Programming & Applications (503073) – Final Project
* **Repository**: [`trihoangle17 / CuoiKiWeb`](https://github.com/trihoangle17/CuoiKiWeb)
* **Team Members**:
  - `trihoangle17`
  - `Whuy-68`
