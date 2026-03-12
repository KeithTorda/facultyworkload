# Faculty Workload System

A comprehensive web-based platform designed to manage faculty teaching loads, course scheduling, and room assignments efficiently. This system focuses on user-friendly data entry and foolproof conflict prevention.

## 🎯 Key Features

- **Centralized Management**: Dedicated pages for managing Courses, Sections, and Rooms.
- **Dynamic Workflow**: Intelligent dropdowns and auto-fill features minimize manual typing and prevent errors.
- **Conflict Prevention System**: Real-time AJAX-based room conflict detection that blocks overlapping schedules with detailed modal feedback.
- **Responsive Design**: Fully mobile-friendly interface that works across desktops, tablets, and phones.
- **Reporting & Statistics**: Dashboard metrics for courses, sections, and faculty workloads.

## 🛠️ Technologies Used

- **Backend**: PHP
- **Database**: MySQL
- **Frontend**: HTML5, CSS3, JavaScript
- **Communication**: AJAX for real-time validation

## 🚀 Getting Started

1. **Database Setup**: Import `faculty_workload.sql` into your MySQL database.
2. **Configuration**: Update database connection details in `includes/db_connect.php` (if applicable).
3. **Admin Setup**: 
    - Go to **Manage Courses** and add your subject list.
    - Go to **Manage Sections** to define your class blocks.
    - Go to **Manage Rooms** to list available classrooms.
4. **Encoding**: Use the **Encode Workload** page to start assigning subjects to faculty.

## 🛡️ Safety Features

- **Deletion Protection**: Prevents deleting items already in use by the system.
- **Schedule Integrity**: Guaranteed no-double-booking policy via the conflict detection engine.

---
*Developed for efficient academic workload management.*
