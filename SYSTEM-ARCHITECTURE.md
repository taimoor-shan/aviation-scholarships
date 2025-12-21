# Email Reminder System - Architecture Diagram

## System Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                    SCHOLARSHIP REMINDER SYSTEM                   │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                         DATA SOURCES                             │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────────┐      ┌──────────────────────────────┐   │
│  │  Scholarships    │      │  User Favorites              │   │
│  │  (CPT)           │      │  (User Meta)                 │   │
│  ├──────────────────┤      ├──────────────────────────────┤   │
│  │ - ID             │      │ meta_key: simplefavorites    │   │
│  │ - Title          │      │ meta_value: {                │   │
│  │ - sch_deadline   │      │   1: {                       │   │
│  │ - sch_max_amount │      │     site_id: 1,              │   │
│  │ - sch_status     │      │     posts: [123, 456, 789]   │   │
│  │ - etc.           │      │   }                          │   │
│  └──────────────────┘      │ }                            │   │
│                            └──────────────────────────────┘   │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘

                              ↓

┌─────────────────────────────────────────────────────────────────┐
│                      DAILY CRON JOB                              │
│                   (9:00 AM Server Time)                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Hook: avs_daily_reminder_check                                 │
│  Runs: Reminder_Manager::process_daily_reminders()              │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘

                              ↓

┌─────────────────────────────────────────────────────────────────┐
│                   REMINDER MANAGER LOGIC                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  FOR EACH INTERVAL (30, 15, 5 days):                            │
│                                                                  │
│  1. Calculate target deadline date                              │
│     ┌──────────────────────────────────────┐                   │
│     │ Today + Interval = Target Date       │                   │
│     │ Example: 2025-12-21 + 30 = 2025-01-20│                   │
│     └──────────────────────────────────────┘                   │
│                      ↓                                           │
│  2. Find scholarships with deadline = target date               │
│     ┌──────────────────────────────────────┐                   │
│     │ WP_Query:                            │                   │
│     │ - post_type: scholarship             │                   │
│     │ - meta: sch_deadline = 2025-01-20    │                   │
│     │ - status: active                     │                   │
│     └──────────────────────────────────────┘                   │
│                      ↓                                           │
│  3. For each scholarship found:                                 │
│     ┌──────────────────────────────────────┐                   │
│     │ Find users who favorited it          │                   │
│     │ Query: usermeta table                │                   │
│     │ WHERE meta_value LIKE '%scholarship_id%'│                │
│     └──────────────────────────────────────┘                   │
│                      ↓                                           │
│  4. For each user:                                              │
│     ┌──────────────────────────────────────┐                   │
│     │ Check: reminder_exists()?            │                   │
│     │ If NO:                               │                   │
│     │   - Send email                       │                   │
│     │   - Record in database               │                   │
│     │ If YES:                              │                   │
│     │   - Skip (already sent)              │                   │
│     └──────────────────────────────────────┘                   │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘

                              ↓

┌─────────────────────────────────────────────────────────────────┐
│                    DUPLICATE CHECK                               │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌────────────────────────────────────────────────────┐        │
│  │ SELECT COUNT(*) FROM wp_avs_scholarship_reminders  │        │
│  │ WHERE user_id = ?                                  │        │
│  │   AND scholarship_id = ?                           │        │
│  │   AND reminder_type = ?                            │        │
│  └────────────────────────────────────────────────────┘        │
│                                                                  │
│  Result: 0 = Send Email | > 0 = Skip                            │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘

                              ↓

┌─────────────────────────────────────────────────────────────────┐
│                      EMAIL GENERATION                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Reminder_Email::send_reminder()                                │
│                                                                  │
│  1. Get user data (name, email)                                 │
│  2. Prepare scholarship data                                    │
│     - Title, deadline, amount, eligibility                      │
│  3. Generate HTML email template                                │
│     - Personalized greeting                                     │
│     - Scholarship details                                       │
│     - Call-to-action links                                      │
│  4. Send via wp_mail()                                          │
│     - Works with WP Mail SMTP automatically                     │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘

                              ↓

┌─────────────────────────────────────────────────────────────────┐
│                    EMAIL DELIVERY                                │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  wp_mail($to, $subject, $message, $headers)                     │
│                  ↓                                               │
│  WP Mail SMTP Plugin (if installed)                             │
│                  ↓                                               │
│  SMTP Server → User's Inbox                                     │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘

                              ↓

┌─────────────────────────────────────────────────────────────────┐
│                    RECORD SENT REMINDER                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  INSERT INTO wp_avs_scholarship_reminders                       │
│  (user_id, scholarship_id, reminder_type, deadline_date,        │
│   sent_date)                                                    │
│  VALUES (?, ?, ?, ?, NOW())                                     │
│                                                                  │
│  Purpose: Prevent duplicate emails in future runs               │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Data Flow Example

```
SCENARIO: User has favorited 3 scholarships with deadlines in 30 days

Step 1: Cron runs at 9:00 AM on 2025-12-21
Step 2: Calculate target date: 2025-12-21 + 30 days = 2025-01-20
Step 3: Find scholarships with deadline = 2025-01-20

┌──────────────────────────────────────────┐
│ Found 3 Scholarships:                    │
│ - ID: 123 "Aviation Excellence Award"    │
│ - ID: 456 "Pilot Training Grant"         │
│ - ID: 789 "Flight School Scholarship"    │
└──────────────────────────────────────────┘

Step 4: For scholarship 123, find users who favorited it

┌──────────────────────────────────────────┐
│ Query usermeta:                          │
│ WHERE meta_key = 'simplefavorites'       │
│   AND meta_value LIKE '%"123"%'          │
│                                          │
│ Found users: 5, 12, 18, 24               │
└──────────────────────────────────────────┘

Step 5: Check if reminders already sent

┌──────────────────────────────────────────┐
│ User 5:  reminder_exists() = NO  → SEND  │
│ User 12: reminder_exists() = YES → SKIP  │
│ User 18: reminder_exists() = NO  → SEND  │
│ User 24: reminder_exists() = NO  → SEND  │
└──────────────────────────────────────────┘

Step 6: Send emails and record

┌──────────────────────────────────────────┐
│ Sent to User 5  → Record in DB           │
│ Sent to User 18 → Record in DB           │
│ Sent to User 24 → Record in DB           │
│                                          │
│ Total: 3 emails sent for scholarship 123│
└──────────────────────────────────────────┘

Step 7: Repeat for scholarships 456 and 789
Step 8: Complete. Log statistics.
```

---

## Database Schema Visual

```
Table: wp_avs_scholarship_reminders
┌──────────────┬─────────────────┬─────────────┬─────────────┐
│ id           │ user_id         │ scholar_id  │ reminder_   │
│ (PK)         │ (FK → users)    │ (FK → posts)│ type        │
├──────────────┼─────────────────┼─────────────┼─────────────┤
│ 1            │ 5               │ 123         │ 30_days     │
│ 2            │ 5               │ 456         │ 30_days     │
│ 3            │ 18              │ 123         │ 30_days     │
│ 4            │ 24              │ 123         │ 30_days     │
└──────────────┴─────────────────┴─────────────┴─────────────┘
┌─────────────────┬────────────────────────────────────┐
│ deadline_date   │ sent_date                          │
├─────────────────┼────────────────────────────────────┤
│ 2025-01-20      │ 2025-12-21 09:05:23                │
│ 2025-01-20      │ 2025-12-21 09:05:24                │
│ 2025-01-20      │ 2025-12-21 09:05:25                │
│ 2025-01-20      │ 2025-12-21 09:05:26                │
└─────────────────┴────────────────────────────────────┘

Indexes:
- PRIMARY KEY (id)
- INDEX (user_id, scholarship_id, reminder_type)  ← Fast duplicate check
- INDEX (deadline_date)                           ← Fast date queries
- INDEX (sent_date)                               ← Statistics queries
```

---

## Class Relationships

```
aviation-scholarships.php (Main Plugin File)
    │
    ├─→ Loads → Reminder_Database
    ├─→ Loads → Reminder_Email
    ├─→ Loads → Reminder_Manager
    │
    └─→ Hooks:
        ├─→ Plugin Activation → Create DB Table
        ├─→ init → Initialize Reminder_Manager
        ├─→ before_delete_post → Cleanup reminders
        └─→ acf/save_post → Reset on deadline change

Reminder_Manager
    │
    ├─→ Uses → Reminder_Database (for duplicate checks)
    ├─→ Uses → Reminder_Email (for sending)
    │
    └─→ Methods:
        ├─→ schedule_cron() - Set up daily cron
        ├─→ process_daily_reminders() - Main logic
        ├─→ get_scholarships_by_deadline() - WP_Query
        ├─→ get_users_who_favorited() - Database query
        └─→ send_user_reminder() - Orchestrate sending

Reminder_Email
    │
    ├─→ Methods:
    │   ├─→ send_reminder() - Main sending function
    │   ├─→ get_email_subject() - Generate subject
    │   ├─→ get_email_message() - Generate HTML
    │   ├─→ get_email_headers() - Set headers
    │   └─→ prepare_scholarship_data() - Format data
    │
    └─→ Uses → wp_mail() (WordPress core)
              └─→ Hooked by → WP Mail SMTP Plugin

Reminder_Database
    │
    └─→ Methods:
        ├─→ create_table() - Initialize schema
        ├─→ reminder_exists() - Check for duplicates
        ├─→ add_reminder() - Record sent email
        ├─→ cleanup_old_reminders() - Maintenance
        └─→ get_statistics() - Admin dashboard
```

---

## Admin Interface Flow

```
┌─────────────────────────────────────────────────────────┐
│  WordPress Admin                                        │
│  ┌───────────────────────────────────────────────┐     │
│  │ Scholarships → Import Settings                │     │
│  └───────────────────────────────────────────────┘     │
│                       ↓                                 │
│  ┌───────────────────────────────────────────────┐     │
│  │ Email Reminder Settings                       │     │
│  │                                               │     │
│  │ [√] Enable Email Reminders                    │     │
│  │                                               │     │
│  │ ┌─────────────────────────────────────────┐  │     │
│  │ │ Statistics:                             │  │     │
│  │ │ - Total Sent: 1,234                     │  │     │
│  │ │ - Last 30 Days: 456                     │  │     │
│  │ │ - Last Run: 2025-12-21 09:00:00         │  │     │
│  │ │ - Next Scheduled: 2025-12-22 09:00:00   │  │     │
│  │ └─────────────────────────────────────────┘  │     │
│  │                                               │     │
│  │ [Run Reminder Check Now]                     │     │
│  │ [Send Test Email]                            │     │
│  │                                               │     │
│  └───────────────────────────────────────────────┘     │
└─────────────────────────────────────────────────────────┘
```

---

## Customization Points

```
┌─────────────────────────────────────────────────────────┐
│  Filter: avs_reminder_email_subject                     │
│  ┌───────────────────────────────────────────────┐     │
│  │ Input:  Default subject line                  │     │
│  │ Output: Custom subject line                   │     │
│  └───────────────────────────────────────────────┘     │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│  Filter: avs_reminder_email_message                     │
│  ┌───────────────────────────────────────────────┐     │
│  │ Input:  Default HTML template                 │     │
│  │ Output: Custom HTML template                  │     │
│  └───────────────────────────────────────────────┘     │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│  Action: avs_reminder_sent                              │
│  ┌───────────────────────────────────────────────┐     │
│  │ Triggered: After email successfully sent      │     │
│  │ Use for: Webhooks, custom logging, etc.       │     │
│  └───────────────────────────────────────────────┘     │
└─────────────────────────────────────────────────────────┘
```

---

## Performance Optimization Strategy

```
┌──────────────────────────────────────────────────────────┐
│                    BATCH PROCESSING                       │
├──────────────────────────────────────────────────────────┤
│                                                           │
│  Total Users with Favorites: 10,000                      │
│  Batch Limit per Cron Run: 50 emails                     │
│                                                           │
│  Day 1: Process first 50 users                           │
│  Day 2: Process next 50 users                            │
│  Day 3: Process next 50 users                            │
│  ...                                                      │
│                                                           │
│  Result: System never overloads                          │
│                                                           │
└──────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────┐
│                  DATABASE INDEXES                         │
├──────────────────────────────────────────────────────────┤
│                                                           │
│  Composite Index: (user_id, scholarship_id, type)        │
│  Query Time: < 0.001 seconds                             │
│                                                           │
│  Without Index: 2+ seconds for large tables              │
│  With Index: Sub-millisecond lookup                      │
│                                                           │
└──────────────────────────────────────────────────────────┘
```

---

## Error Handling Flow

```
┌─────────────────────────────────────────────────────────┐
│  process_daily_reminders()                              │
│  ┌───────────────────────────────────────────────┐     │
│  │ Feature disabled?                             │     │
│  │ → Log and return                              │     │
│  └───────────────────────────────────────────────┘     │
│                    ↓                                    │
│  ┌───────────────────────────────────────────────┐     │
│  │ No scholarships found?                        │     │
│  │ → Log "No scholarships" and continue          │     │
│  └───────────────────────────────────────────────┘     │
│                    ↓                                    │
│  ┌───────────────────────────────────────────────┐     │
│  │ User not found?                               │     │
│  │ → Log error and skip                          │     │
│  └───────────────────────────────────────────────┘     │
│                    ↓                                    │
│  ┌───────────────────────────────────────────────┐     │
│  │ Email send failed?                            │     │
│  │ → Log error but continue                      │     │
│  └───────────────────────────────────────────────┘     │
│                    ↓                                    │
│  ┌───────────────────────────────────────────────┐     │
│  │ Batch limit reached?                          │     │
│  │ → Log and stop for today                      │     │
│  └───────────────────────────────────────────────┘     │
└─────────────────────────────────────────────────────────┘
```

---

**End of Diagram**
