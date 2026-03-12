-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 05, 2025 at 03:16 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `faculty_workload`
--

-- --------------------------------------------------------

--
-- Table structure for table `consultation_hours`
--

CREATE TABLE `consultation_hours` (
  `id` int(11) NOT NULL,
  `workload_id` int(11) NOT NULL,
  `day` varchar(20) NOT NULL,
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
  `room` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `consultation_hours`
--

INSERT INTO `consultation_hours` (`id`, `workload_id`, `day`, `time_start`, `time_end`, `room`, `created_at`) VALUES
(1, 1, 'MWF', '08:00:00', '09:00:00', 'Faculty Rm.', '2025-08-28 04:09:04');

-- --------------------------------------------------------

--
-- Table structure for table `functions`
--

CREATE TABLE `functions` (
  `id` int(11) NOT NULL,
  `workload_id` int(11) NOT NULL,
  `type` enum('research','admin') NOT NULL,
  `description` text NOT NULL,
  `hours` decimal(4,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `functions`
--

INSERT INTO `functions` (`id`, `workload_id`, `type`, `description`, `hours`, `created_at`) VALUES
(1, 1, 'admin', 'Infrastructure Development Officer', 9.00, '2025-08-28 04:09:04'),
(2, 1, 'research', 'Research and Extension', 3.00, '2025-08-28 04:09:04');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `room_name` varchar(50) NOT NULL,
  `building` varchar(50) DEFAULT NULL,
  `capacity` int(11) DEFAULT 0,
  `room_type` enum('classroom','laboratory','auditorium','conference') DEFAULT 'classroom',
  `equipment` text DEFAULT NULL,
  `status` enum('active','maintenance','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `room_name`, `building`, `capacity`, `room_type`, `equipment`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Room 101', 'Main Building', 40, 'classroom', 'Whiteboard, Projector', 'active', '2025-08-28 04:09:04', '2025-08-28 04:09:04'),
(2, 'Room 102', 'Main Building', 35, 'classroom', 'Whiteboard, TV', 'active', '2025-08-28 04:09:04', '2025-08-28 04:09:04'),
(3, 'Room 103', 'Main Building', 45, 'classroom', 'Whiteboard, Projector, Sound System', 'active', '2025-08-28 04:09:04', '2025-08-28 04:09:04'),
(4, 'Computer Lab 1', 'IT Building', 30, 'laboratory', 'Computers, Projector, Air Conditioning', 'active', '2025-08-28 04:09:04', '2025-08-28 04:09:04'),
(5, 'Computer Lab 2', 'IT Building', 25, 'laboratory', 'Computers, Projector', 'active', '2025-08-28 04:09:04', '2025-08-28 04:09:04'),
(6, 'Physics Lab', 'Science Building', 20, 'laboratory', 'Lab Equipment, Whiteboard', 'active', '2025-08-28 04:09:04', '2025-08-28 04:09:04'),
(7, 'Chemistry Lab', 'Science Building', 20, 'laboratory', 'Lab Equipment, Fume Hood', 'active', '2025-08-28 04:09:04', '2025-08-28 04:09:04'),
(8, 'Auditorium', 'Main Building', 200, 'auditorium', 'Sound System, Projector, Microphones', 'active', '2025-08-28 04:09:04', '2025-08-28 04:09:04'),
(9, 'Conference Room', 'Admin Building', 15, 'conference', 'Conference Table, Projector, Air Conditioning', 'active', '2025-08-28 04:09:04', '2025-08-28 04:09:04'),
(10, 'Room 201', 'Main Building', 40, 'classroom', 'Whiteboard, Projector', 'active', '2025-08-28 04:09:04', '2025-08-28 04:09:04'),
(11, 'Room 202', 'Main Building', 35, 'classroom', 'Whiteboard', 'active', '2025-08-28 04:09:04', '2025-08-28 04:09:04'),
(12, 'Room 203', 'Main Building', 45, 'classroom', 'Whiteboard, TV', 'active', '2025-08-28 04:09:04', '2025-08-28 04:09:04'),
(13, 'Nursing Lab', 'Health Building', 25, 'laboratory', 'Medical Equipment, Hospital Beds', 'active', '2025-08-28 04:09:04', '2025-08-28 04:09:04'),
(14, 'TBA', 'Various', 0, 'classroom', 'To Be Announced', 'active', '2025-08-28 04:09:04', '2025-08-28 04:09:04');

-- --------------------------------------------------------

--
-- Table structure for table `teaching_loads`
--

CREATE TABLE `teaching_loads` (
  `id` int(11) NOT NULL,
  `workload_id` int(11) NOT NULL,
  `course_code` varchar(20) NOT NULL,
  `course_title` varchar(200) NOT NULL,
  `section` varchar(20) NOT NULL,
  `room` varchar(50) NOT NULL,
  `day` varchar(20) NOT NULL,
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
  `units` int(11) NOT NULL,
  `students` int(11) NOT NULL,
  `class_type` enum('Lec','Lab') DEFAULT 'Lec',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teaching_loads`
--

INSERT INTO `teaching_loads` (`id`, `workload_id`, `course_code`, `course_title`, `section`, `room`, `day`, `time_start`, `time_end`, `units`, `students`, `class_type`, `created_at`) VALUES
(1, 1, 'NSTP 11', 'National Service Training Program', '1A', 'TBA', 'MWF', '11:00:00', '12:00:00', 3, 45, 'Lec', '2025-08-28 04:09:04'),
(2, 1, 'NSTP 11', 'National Service Training Program', '1C', 'TBA', 'MWF', '09:00:00', '10:00:00', 3, 45, 'Lec', '2025-08-28 04:09:04'),
(3, 1, 'NSTP 11', 'National Service Training Program', '1D', 'TBA', 'TTH', '14:30:00', '16:00:00', 3, 45, 'Lec', '2025-08-28 04:09:04');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','faculty') NOT NULL,
  `faculty_rank` varchar(50) DEFAULT NULL,
  `eligibility` varchar(100) DEFAULT NULL,
  `bachelor_degree` varchar(100) DEFAULT NULL,
  `master_degree` varchar(100) DEFAULT NULL,
  `doctorate_degree` varchar(100) DEFAULT NULL,
  `scholarship` varchar(100) DEFAULT NULL,
  `length_of_service` varchar(20) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `faculty_rank`, `eligibility`, `bachelor_degree`, `master_degree`, `doctorate_degree`, `scholarship`, `length_of_service`, `photo`, `status`, `created_at`, `updated_at`) VALUES
(1, 'System Administrator', 'admin@gmail.com', '$2y$10$uGfREicAB18gMjHLkZjZMeHd.rooIMz9NWhqkoq5HjFDLt1jV.ZwC', 'admin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-08-28 04:09:04', '2025-09-03 07:54:01'),
(2, 'Valentino M. Balubag', 'vbalubag@apayao.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'faculty', 'Instructor I', 'Licensure Examination for Architecture', 'Bachelor of Science in Architecture', 'None', NULL, NULL, '21 Years', NULL, 'active', '2025-08-28 04:09:04', '2025-08-28 04:09:04'),
(3, 'Lloyd Mark C. Razalan', 'lrazalan@apayao.edu.ph', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'faculty', 'MIT', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-08-28 04:09:04', '2025-08-28 04:09:04');

-- --------------------------------------------------------

--
-- Table structure for table `workloads`
--

CREATE TABLE `workloads` (
  `id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `semester` varchar(50) NOT NULL,
  `school_year` varchar(20) NOT NULL,
  `program` varchar(100) DEFAULT NULL,
  `prepared_by` varchar(100) DEFAULT NULL,
  `prepared_by_title` varchar(100) DEFAULT NULL,
  `reviewed_by` varchar(100) DEFAULT NULL,
  `reviewed_by_title` varchar(100) DEFAULT NULL,
  `approved_by` varchar(100) DEFAULT NULL,
  `approved_by_title` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `workloads`
--

INSERT INTO `workloads` (`id`, `faculty_id`, `semester`, `school_year`, `program`, `prepared_by`, `prepared_by_title`, `reviewed_by`, `reviewed_by_title`, `approved_by`, `approved_by_title`, `created_at`, `updated_at`) VALUES
(1, 2, 'First Semester', 'AY 2025-2026', 'Bachelor of Science in Information Technology', 'Lloyd Mark C. Razalan, MIT', 'BSIT Program Chair', 'Rema Bascos - Ocampo, PhD', 'Campus Dean', 'Ronald O. Ocampo, PhD', 'Vice-President for Academics, Research & Development and Extension Services', '2025-08-28 04:09:04', '2025-08-28 04:09:04');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `consultation_hours`
--
ALTER TABLE `consultation_hours`
  ADD PRIMARY KEY (`id`),
  ADD KEY `workload_id` (`workload_id`);

--
-- Indexes for table `functions`
--
ALTER TABLE `functions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `workload_id` (`workload_id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `room_name` (`room_name`);

--
-- Indexes for table `teaching_loads`
--
ALTER TABLE `teaching_loads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `workload_id` (`workload_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `workloads`
--
ALTER TABLE `workloads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `faculty_id` (`faculty_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `consultation_hours`
--
ALTER TABLE `consultation_hours`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `functions`
--
ALTER TABLE `functions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `teaching_loads`
--
ALTER TABLE `teaching_loads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `workloads`
--
ALTER TABLE `workloads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `consultation_hours`
--
ALTER TABLE `consultation_hours`
  ADD CONSTRAINT `consultation_hours_ibfk_1` FOREIGN KEY (`workload_id`) REFERENCES `workloads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `functions`
--
ALTER TABLE `functions`
  ADD CONSTRAINT `functions_ibfk_1` FOREIGN KEY (`workload_id`) REFERENCES `workloads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teaching_loads`
--
ALTER TABLE `teaching_loads`
  ADD CONSTRAINT `teaching_loads_ibfk_1` FOREIGN KEY (`workload_id`) REFERENCES `workloads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `workloads`
--
ALTER TABLE `workloads`
  ADD CONSTRAINT `workloads_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
