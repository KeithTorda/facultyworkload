-- Faculty Workload and Scheduling System Database Schema
-- Drop database if exists and create new one
DROP DATABASE IF EXISTS faculty_work;
CREATE DATABASE faculty_work;
USE faculty_work;

-- Users table (Admin and Faculty)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'faculty') NOT NULL,
    faculty_rank VARCHAR(50) NULL,
    eligibility VARCHAR(100) NULL,
    bachelor_degree VARCHAR(100) NULL,
    master_degree VARCHAR(100) NULL,
    doctorate_degree VARCHAR(100) NULL,
    scholarship VARCHAR(100) NULL,
    length_of_service VARCHAR(20) NULL,
    photo VARCHAR(255) NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Workloads table (main workload record per faculty per semester)
CREATE TABLE workloads (
    id INT PRIMARY KEY AUTO_INCREMENT,
    faculty_id INT NOT NULL,
    semester VARCHAR(50) NOT NULL,
    school_year VARCHAR(20) NOT NULL,
    program VARCHAR(100) NULL,
    prepared_by VARCHAR(100) NULL,
    prepared_by_title VARCHAR(100) NULL,
    reviewed_by VARCHAR(100) NULL,
    reviewed_by_title VARCHAR(100) NULL,
    approved_by VARCHAR(100) NULL,
    approved_by_title VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (faculty_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Teaching loads table
CREATE TABLE teaching_loads (
    id INT PRIMARY KEY AUTO_INCREMENT,
    workload_id INT NOT NULL,
    course_code VARCHAR(20) NOT NULL,
    course_title VARCHAR(200) NOT NULL,
    section VARCHAR(20) NOT NULL,
    room VARCHAR(50) NOT NULL,
    day VARCHAR(20) NOT NULL,
    time_start TIME NOT NULL,
    time_end TIME NOT NULL,
    units INT NOT NULL,
    students INT NOT NULL,
    class_type ENUM('Lec', 'Lab') DEFAULT 'Lec',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (workload_id) REFERENCES workloads(id) ON DELETE CASCADE
);

-- Consultation hours table
CREATE TABLE consultation_hours (
    id INT PRIMARY KEY AUTO_INCREMENT,
    workload_id INT NOT NULL,
    day VARCHAR(20) NOT NULL,
    time_start TIME NOT NULL,
    time_end TIME NOT NULL,
    room VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (workload_id) REFERENCES workloads(id) ON DELETE CASCADE
);

-- Functions table (research or admin)
CREATE TABLE functions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    workload_id INT NOT NULL,
    type ENUM('research', 'admin') NOT NULL,
    description TEXT NOT NULL,
    hours DECIMAL(4,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (workload_id) REFERENCES workloads(id) ON DELETE CASCADE
);

-- Rooms table
CREATE TABLE rooms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_name VARCHAR(50) NOT NULL UNIQUE,
    building VARCHAR(50) NULL,
    capacity INT DEFAULT 0,
    room_type ENUM('classroom', 'laboratory', 'auditorium', 'conference') DEFAULT 'classroom',
    equipment TEXT NULL,
    status ENUM('active', 'maintenance', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default admin user
INSERT INTO users (name, email, password, role) VALUES 
('System Administrator', 'admin@apayao.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
-- Default password is 'password'

-- Insert sample faculty users based on the document
INSERT INTO users (name, email, password, role, faculty_rank, eligibility, bachelor_degree, master_degree, doctorate_degree, length_of_service) VALUES 
('Valentino M. Balubag', 'vbalubag@apayao.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'faculty', 'Instructor I', 'Licensure Examination for Architecture', 'Bachelor of Science in Architecture', 'None', NULL, '21 Years'),
('Lloyd Mark C. Razalan', 'lrazalan@apayao.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'faculty', 'MIT', NULL, NULL, NULL, NULL, NULL);

-- Insert sample workload for Valentino M. Balubag
INSERT INTO workloads (faculty_id, semester, school_year, program, prepared_by, prepared_by_title, reviewed_by, reviewed_by_title, approved_by, approved_by_title) VALUES 
(2, 'First Semester', 'AY 2025-2026', 'Bachelor of Science in Information Technology', 'Lloyd Mark C. Razalan, MIT', 'BSIT Program Chair', 'Rema Bascos - Ocampo, PhD', 'Campus Dean', 'Ronald O. Ocampo, PhD', 'Vice-President for Academics, Research & Development and Extension Services');

-- Insert sample teaching loads based on the document
INSERT INTO teaching_loads (workload_id, course_code, course_title, section, room, day, time_start, time_end, units, students, class_type) VALUES 
(1, 'NSTP 11', 'National Service Training Program', '1A', 'TBA', 'MWF', '11:00:00', '12:00:00', 3, 45, 'Lec'),
(1, 'NSTP 11', 'National Service Training Program', '1C', 'TBA', 'MWF', '09:00:00', '10:00:00', 3, 45, 'Lec'),
(1, 'NSTP 11', 'National Service Training Program', '1D', 'TBA', 'TTH', '14:30:00', '16:00:00', 3, 45, 'Lec');

-- Insert sample consultation hours
INSERT INTO consultation_hours (workload_id, day, time_start, time_end, room) VALUES 
(1, 'MWF', '08:00:00', '09:00:00', 'Faculty Rm.');

-- Insert sample functions
INSERT INTO functions (workload_id, type, description, hours) VALUES 
(1, 'admin', 'Infrastructure Development Officer', 9.00),
(1, 'research', 'Research and Extension', 3.00);

-- Insert sample rooms
INSERT INTO rooms (room_name, building, capacity, room_type, equipment) VALUES 
('Room 101', 'Main Building', 40, 'classroom', 'Whiteboard, Projector'),
('Room 102', 'Main Building', 35, 'classroom', 'Whiteboard, TV'),
('Room 103', 'Main Building', 45, 'classroom', 'Whiteboard, Projector, Sound System'),
('Computer Lab 1', 'IT Building', 30, 'laboratory', 'Computers, Projector, Air Conditioning'),
('Computer Lab 2', 'IT Building', 25, 'laboratory', 'Computers, Projector'),
('Physics Lab', 'Science Building', 20, 'laboratory', 'Lab Equipment, Whiteboard'),
('Chemistry Lab', 'Science Building', 20, 'laboratory', 'Lab Equipment, Fume Hood'),
('Auditorium', 'Main Building', 200, 'auditorium', 'Sound System, Projector, Microphones'),
('Conference Room', 'Admin Building', 15, 'conference', 'Conference Table, Projector, Air Conditioning'),
('Room 201', 'Main Building', 40, 'classroom', 'Whiteboard, Projector'),
('Room 202', 'Main Building', 35, 'classroom', 'Whiteboard'),
('Room 203', 'Main Building', 45, 'classroom', 'Whiteboard, TV'),
('Nursing Lab', 'Health Building', 25, 'laboratory', 'Medical Equipment, Hospital Beds'),
('TBA', 'Various', 0, 'classroom', 'To Be Announced'); 