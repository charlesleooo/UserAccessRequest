-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 18, 2025 at 07:04 AM
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
-- Database: `uar_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `access_requests`
--

CREATE TABLE `access_requests` (
  `id` int(11) NOT NULL,
  `requestor_name` varchar(255) NOT NULL,
  `business_unit` varchar(50) NOT NULL,
  `access_request_number` varchar(20) NOT NULL,
  `department` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `request_date` date NOT NULL,
  `access_type` varchar(50) NOT NULL,
  `justification` text NOT NULL,
  `system_type` varchar(255) DEFAULT NULL,
  `other_system_type` varchar(255) DEFAULT NULL,
  `role_access_type` varchar(50) DEFAULT NULL,
  `duration_type` varchar(20) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('pending_superior','pending_technical','pending_process_owner','pending_admin','approved','rejected','pending_testing') NOT NULL DEFAULT 'pending_superior',
  `testing_status` enum('not_required','pending','success','failed') DEFAULT 'not_required',
  `testing_notes` text DEFAULT NULL,
  `submission_date` datetime NOT NULL,
  `superior_id` int(11) DEFAULT NULL,
  `superior_review_date` datetime DEFAULT NULL,
  `superior_notes` text DEFAULT NULL,
  `technical_id` int(11) DEFAULT NULL,
  `technical_review_date` datetime DEFAULT NULL,
  `technical_notes` text DEFAULT NULL,
  `process_owner_id` int(11) DEFAULT NULL,
  `process_owner_review_date` datetime DEFAULT NULL,
  `process_owner_notes` text DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `admin_review_date` datetime DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `review_date` datetime DEFAULT NULL,
  `review_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `access_requests`
--

INSERT INTO `access_requests` (`id`, `requestor_name`, `business_unit`, `access_request_number`, `department`, `email`, `employee_id`, `request_date`, `access_type`, `justification`, `system_type`, `other_system_type`, `role_access_type`, `duration_type`, `start_date`, `end_date`, `status`, `testing_status`, `testing_notes`, `submission_date`, `superior_id`, `superior_review_date`, `superior_notes`, `technical_id`, `technical_review_date`, `technical_notes`, `process_owner_id`, `process_owner_review_date`, `process_owner_notes`, `admin_id`, `admin_review_date`, `admin_notes`, `reviewed_by`, `review_date`, `review_notes`) VALUES
(34, 'PALOMARES, CHARLES LEO H.', 'AAC', 'UAR-REQ2025-004', 'INFORMATION TECHNOLOGY (IT)', 'charlesleohermano@gmail.com', 'AAC052003', '0000-00-00', 'PC Access - Network', 'charles palomares', NULL, NULL, '', 'permanent', NULL, NULL, '', 'not_required', NULL, '2025-05-14 09:41:30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(37, 'PALOMARES, CHARLES LEO H.', 'AAC', 'UAR-REQ2025-007', 'INFORMATION TECHNOLOGY (IT)', 'charlesleohermano@gmail.com', 'AAC052003', '0000-00-00', 'System Application', 'im newly hired i need access', 'Legacy Vouchering', NULL, '', 'permanent', NULL, NULL, 'pending_testing', 'pending', 'bruh nigga', '2025-05-15 10:17:03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2025-05-15 10:17:31', 'hahaahaha'),
(39, 'PALOMARES, CHARLES LEO H.', 'AAC', 'UAR-REQ2025-009', 'INFORMATION TECHNOLOGY (IT)', 'charlesleohermano@gmail.com', 'AAC052003', '0000-00-00', 'Wi-Fi/Access Point Access', 'qweqwe', NULL, NULL, '', 'permanent', NULL, NULL, '', 'not_required', NULL, '2025-05-18 12:39:53', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(40, 'PALOMARES, CHARLES LEO H.', 'AAC', 'UAR-REQ2025-010', 'INFORMATION TECHNOLOGY (IT)', 'charlesleohermano@gmail.com', 'AAC052003', '0000-00-00', 'Internet Access', 'qweqwe', NULL, NULL, '', 'permanent', NULL, NULL, '', 'not_required', NULL, '2025-05-18 12:45:57', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(41, 'PALOMARES, CHARLES LEO H.', 'AAC', 'UAR-REQ2025-011', 'INFORMATION TECHNOLOGY (IT)', 'charlesleohermano@gmail.com', 'AAC052003', '0000-00-00', 'PC Access - Network', 'qweqwe', NULL, NULL, '', 'permanent', NULL, NULL, 'pending_superior', 'not_required', NULL, '2025-05-18 12:55:38', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

--
-- Triggers `access_requests`
--
DELIMITER $$
CREATE TRIGGER `after_request_status_change` AFTER UPDATE ON `access_requests` FOR EACH ROW BEGIN
    IF NEW.status IN ('approved', 'rejected') THEN
        INSERT INTO approval_history (
            request_id, 
            admin_id, 
            action, 
            comments, 
            requestor_name, 
            business_unit, 
            department, 
            access_type, 
            system_type, 
            duration_type, 
            start_date, 
            end_date, 
            justification, 
            email, 
            employee_id,
            request_date,
            access_request_number,
            testing_status
        )
        VALUES (
            NEW.id,
            NEW.reviewed_by,
            NEW.status,
            NEW.review_notes,
            NEW.requestor_name,
            NEW.business_unit,
            NEW.department,
            NEW.access_type,
            NEW.system_type,
            NEW.duration_type,
            NEW.start_date,
            NEW.end_date,
            NEW.justification,
            NEW.email,
            NEW.employee_id,
            NEW.request_date,
            NEW.access_request_number,
            NEW.testing_status
        );
        
        DELETE FROM access_requests WHERE id = NEW.id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `role` varchar(255) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `role`, `username`, `password`, `created_at`) VALUES
(3, 'admin', 'admin', '$2y$10$SA4aRMZAhyKQPxzFdI1w/uwT1Xf2VKciIzpraAAxQcaRu2DTDYyHG', '2025-03-26 01:56:34'),
(6, 'superior', 'superior', '$2y$10$Pu46k5pVVhkX2oKpg4ZFy.BssUanZdGykoqRPPe6fuPSkfOA0ZuNe', '2025-05-05 05:31:07'),
(9, 'technical_support', 'technical_support', '$2y$10$iNe0w0/K7F6rIgRsFHTlge7auKyZMW/fbCHmN2/CnGPdhbD7vsTcG', '2025-05-18 04:25:26'),
(10, 'process_owner', 'process_owner', '$2y$10$IJrAULu0Y6ecrA6TcB7IsOJPkneQOkxL0/HVF1VGkNpvThwdiRiXS', '2025-05-18 04:25:34');

-- --------------------------------------------------------

--
-- Table structure for table `approval_history`
--

CREATE TABLE `approval_history` (
  `history_id` int(11) NOT NULL,
  `access_request_number` varchar(20) NOT NULL,
  `action` enum('approved','rejected') NOT NULL,
  `requestor_name` varchar(255) NOT NULL,
  `business_unit` varchar(50) NOT NULL,
  `department` varchar(100) NOT NULL,
  `access_type` varchar(50) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `system_type` varchar(255) DEFAULT NULL,
  `duration_type` varchar(20) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `justification` text NOT NULL,
  `email` varchar(255) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `testing_status` enum('not_required','success','failed') DEFAULT 'not_required',
  `superior_id` int(11) DEFAULT NULL,
  `superior_notes` text DEFAULT NULL,
  `technical_id` int(11) DEFAULT NULL,
  `technical_notes` text DEFAULT NULL,
  `process_owner_id` int(11) DEFAULT NULL,
  `process_owner_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `approval_history`
--

INSERT INTO `approval_history` (`history_id`, `access_request_number`, `action`, `requestor_name`, `business_unit`, `department`, `access_type`, `admin_id`, `comments`, `system_type`, `duration_type`, `start_date`, `end_date`, `justification`, `email`, `contact_number`, `testing_status`, `superior_id`, `superior_notes`, `technical_id`, `technical_notes`, `process_owner_id`, `process_owner_notes`, `created_at`) VALUES
(10, 'UAR-REQ2025-001', 'approved', 'Charles Leo Palomares', 'AAC', 'INFORMATION TECHNOLOGY (IT)', 'Server Access', 3, '', NULL, 'permanent', NULL, NULL, 'qwe', 'charlesleohermano@gmail.com', '09606072661', 'not_required', NULL, NULL, NULL, NULL, NULL, NULL, '2025-05-13 06:02:15'),
(11, 'UAR-REQ2025-003', 'rejected', 'Charles Leo Palomares', 'AAC', 'INFORMATION TECHNOLOGY (IT)', 'PC Access - Network', 3, '', NULL, 'permanent', NULL, NULL, 'qwe', 'charlesleohermano@gmail.com', '09606072661', 'not_required', NULL, NULL, NULL, NULL, NULL, NULL, '2025-05-13 06:06:26'),
(12, 'UAR-REQ2025-002', 'approved', 'Charles Leo Palomares', 'AAC', 'INFORMATION TECHNOLOGY (IT)', 'PC Access - Network', 3, 'Email: clpalomares@saranganibay.com.ph\r\n\r\nPassword: clpalomares', NULL, 'permanent', NULL, NULL, 'qwe', 'charlesleohermano@gmail.com', '09606072661', 'not_required', NULL, NULL, NULL, NULL, NULL, NULL, '2025-05-14 01:29:07'),
(13, 'UAR-REQ2025-005', 'approved', 'PALOMARES, CHARLES LEO H.', 'AAC', 'INFORMATION TECHNOLOGY (IT)', 'PC Access - Network', 3, 'qwe', NULL, 'permanent', NULL, NULL, 'qwe', 'charlesleohermano@gmail.com', 'Not provided', 'not_required', NULL, NULL, NULL, NULL, NULL, NULL, '2025-05-15 01:48:59'),
(14, 'UAR-REQ2025-006', 'approved', 'PALOMARES, CHARLES LEO H.', 'AAC', 'INFORMATION TECHNOLOGY (IT)', 'System Application', 3, 'haha', 'Legacy Vouchering', 'permanent', NULL, NULL, 'im newly hired and i want to have access to legacy vouchering', 'charlesleohermano@gmail.com', 'Not provided', 'success', NULL, NULL, NULL, NULL, NULL, NULL, '2025-05-15 02:09:44'),
(15, 'UAR-REQ2025-008', 'approved', 'PALOMARES, CHARLES LEO H.', 'AAC', 'INFORMATION TECHNOLOGY (IT)', 'System Application', 3, 'wow nice!', 'Quickbooks', 'permanent', NULL, NULL, 'qwe', 'charlesleohermano@gmail.com', 'Not provided', 'success', NULL, NULL, NULL, NULL, NULL, NULL, '2025-05-15 04:02:19');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `employee_id` varchar(20) NOT NULL,
  `company` varchar(10) NOT NULL,
  `employee_name` varchar(255) NOT NULL,
  `department` varchar(255) NOT NULL,
  `employee_email` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`employee_id`, `company`, `employee_name`, `department`, `employee_email`) VALUES
('AAC000001', 'AAC', 'ALCANTARA, ALEJANDRO I.', 'G & A', ''),
('AAC000003', 'AAC', 'TICAO, REX J.', 'SPECIAL PROJECT', ''),
('AAC000007', 'AAC', 'MINGO, NOEL R.', 'GROW OUT', ''),
('AAC000008', 'AAC', 'REYES SR., ROBERT B.', 'ENGINEERING', ''),
('AAC000009', 'AAC', 'DAVILA, NICOLAS G.', 'GROW OUT', ''),
('AAC000010', 'AAC', 'DESCARTIN, NESTOR B.', 'GROW OUT', ''),
('AAC000011', 'AAC', 'FATADILLO, RAFAEL V.', 'GROW OUT', ''),
('AAC000013', 'AAC', 'GALO, SALVADOR C.', 'ENGINEERING', ''),
('AAC000014', 'AAC', 'ABREA, DANILO S.', 'ENGINEERING', ''),
('AAC000016', 'AAC', 'DAVILA, JOSE EVAN G.', 'GROW OUT', ''),
('AAC000017', 'AAC', 'FALLER, ALAN B.', 'GROW OUT', ''),
('AAC000018', 'AAC', 'ARISGADO, MELCHOR V.', 'ENGINEERING', ''),
('AAC000022', 'AAC', 'AMLON, ERENEO P.', 'GROW OUT', ''),
('AAC000023', 'AAC', 'ANDRINO, RONILO P.', 'GROW OUT', ''),
('AAC000026', 'AAC', 'POLISTICO SR, JERRY O.', 'GROW OUT', ''),
('AAC000027', 'AAC', 'SALARDA, TEODY C.', 'GROW OUT', ''),
('AAC000029', 'AAC', 'VILLAMORA, GARRY M.', 'GROW OUT', ''),
('AAC000030', 'AAC', 'FALLER, EDWIN B.', 'ENGINEERING', ''),
('AAC000031', 'AAC', 'GAMAO, ARIEL I.', 'ENGINEERING', ''),
('AAC000033', 'AAC', 'ECHAVEZ, ROLANDO B.', 'GROW OUT', ''),
('AAC000036', 'AAC', 'CONATO, RONIE P.', 'GROW OUT', ''),
('AAC000037', 'AAC', 'SACEDOR SR, RENANTE P.', 'GROW OUT', ''),
('AAC000038', 'AAC', 'CAMINOY, PROCESO T.', 'GROW OUT', ''),
('AAC000039', 'AAC', 'TANGUILIG, TRUMAN F.', 'GROW OUT', ''),
('AAC000041', 'AAC', 'ALIMAJEN, LEONARDO A.', 'GROW OUT', ''),
('AAC000043', 'AAC', 'DEAROS, RUBEN A.', 'GROW OUT', ''),
('AAC000045', 'AAC', 'BACTOL, EDGAR G.', 'GROW OUT', ''),
('AAC000047', 'AAC', 'CARANDANG, LAWRENCE J.', 'HUMAN RESOURCE & ADMIN', ''),
('AAC000048', 'AAC', 'BARZA, NANCY L.', 'APP', ''),
('AAC000050', 'AAC', 'CAWIT, OLIVA L.', 'APP', ''),
('AAC000054', 'AAC', 'BENITEZ, CELSO M.', 'GROW OUT', ''),
('AAC000056', 'AAC', 'LAPASA, NORBERTO A.', 'SUPPLY CHAIN', ''),
('AAC000059', 'AAC', 'ANCHETA SR, CRISANTO G.', 'GROW OUT', ''),
('AAC000060', 'AAC', 'BAHINTING, FELIPE B.', 'GROW OUT', ''),
('AAC000062', 'AAC', 'JULIA, LANELISA V.', 'FINANCE', ''),
('AAC000063', 'AAC', 'OSIAS, ERLINDA D.', 'TECHNICAL SERVICES', ''),
('AAC000064', 'AAC', 'PERANDOS, PASCUAL C.', 'SUPPLY CHAIN', ''),
('AAC000065', 'AAC', 'CAAT JR, ANTONIO S.', 'RPP', ''),
('AAC000067', 'AAC', 'COMAHIG, FEDERICO B.', 'GROW OUT', ''),
('AAC000068', 'AAC', 'DAPOSALA, RENELYN L.', 'APP', ''),
('AAC000069', 'AAC', 'LAPASA, MARY ANN P.', 'APP', ''),
('AAC000070', 'AAC', 'SENIT, DIONECE N.', 'SUPPLY CHAIN', ''),
('AAC000072', 'AAC', 'GERALDO, RAINIER A.', 'SEACAGE', ''),
('AAC000074', 'AAC', 'QUILLAZA JR, ERNESTO P.', 'GROW OUT', ''),
('AAC000075', 'AAC', 'NAWA, MHEER M.', 'SEACAGE', ''),
('AAC000076', 'AAC', 'BRACERO, FELICISIMO C.', 'SEACAGE', ''),
('AAC000077', 'AAC', 'TEJADA, RICHARD T.', 'GROW OUT', ''),
('AAC000078', 'AAC', 'BUALA, MARIA LUZ J.', 'G & A', ''),
('AAC000084', 'AAC', 'VICENCIO, MICHAEL B.', 'GROW OUT', ''),
('AAC000085', 'AAC', 'REQUIRON, MARIGEN M.', 'GROW OUT', ''),
('AAC000087', 'AAC', 'DIONIO, GERRY A.', 'HUMAN RESOURCE & ADMIN', ''),
('AAC000090', 'AAC', 'BLANCO, RENE I.', 'GROW OUT', ''),
('AAC000093', 'AAC', 'GERALDO, HELEN C.', 'HUMAN RESOURCE & ADMIN', ''),
('AAC000094', 'AAC', 'CALIAO JR, DOMINGO P.', 'ENGINEERING', ''),
('AAC000095', 'AAC', 'ROMANA, HERMINIGILDA L.', 'INFORMATION TECHNOLOGY (IT)', ''),
('AAC000097', 'AAC', 'ARISGADO, ELMO V.', 'GROW OUT', ''),
('AAC000098', 'AAC', 'PANZA, RANDEL A.', 'GROW OUT', ''),
('AAC000099', 'AAC', 'WAHAB, RONIE D.', 'SEACAGE', ''),
('AAC000100', 'AAC', 'FORTALEZA, YOICHIE C.', 'FINANCE', ''),
('AAC000103', 'AAC', 'SACRO, HYRAM A.', 'GROW OUT', ''),
('AAC000107', 'AAC', 'ALESNA, ROLANDO V.', 'GROW OUT', ''),
('AAC000108', 'AAC', 'AMODIA, REYNALDO P.', 'INFORMATION TECHNOLOGY (IT)', ''),
('AAC000109', 'AAC', 'SALAAN, ANA SHEILA M.', 'APP', ''),
('AAC000110', 'AAC', 'DE LA CRUZ, FAO G.', 'MATERIALS MANAGEMENT', ''),
('AAC000113', 'AAC', 'AUSTERO, DALEA L.', 'HUMAN RESOURCE & ADMIN', ''),
('AAC000116', 'AAC', 'BACASTIGUE, VICENTE M.', 'ENGINEERING', ''),
('AAC000119', 'AAC', 'LAPE, RENATO M.', 'GROW OUT', ''),
('AAC000120', 'AAC', 'TOMON, MICHAEL M.', 'GROW OUT', ''),
('AAC000122', 'AAC', 'MACASI, SAMSON S.', 'ENGINEERING', ''),
('AAC000123', 'AAC', 'NADAL, NONITO M.', 'SALES & MARKETING', ''),
('AAC000124', 'AAC', 'ALCANTARA, GABRIEL H.', 'SALES & MARKETING', ''),
('AAC000125', 'AAC', 'SATUR, JOHNNY T.', 'ENGINEERING', ''),
('AAC000126', 'AAC', 'SOBREMISANA, MARY JOY U.', 'HUMAN RESOURCE & ADMIN', ''),
('AAC000128', 'AAC', 'VILLEGAS, EDENE PEARL C.', 'HUMAN RESOURCE & ADMIN', ''),
('AAC000129', 'AAC', 'OSITA, GERLIN B.', 'MATERIALS MANAGEMENT', ''),
('AAC000131', 'AAC', 'TOMON, DONNA JEAN B.', 'GROW OUT', ''),
('AAC000135', 'AAC', 'ANGUAY, ROGEL G.', 'GROW OUT', ''),
('AAC000140', 'AAC', 'POBLACION, JOEANN A.', 'GROW OUT', ''),
('AAC000141', 'AAC', 'BAYADONG, GEMMA R.', 'APP', ''),
('AAC000143', 'AAC', 'PACA, DENNIS C.', 'GPP', ''),
('AAC000146', 'AAC', 'CARPENTERO, ARNEL P.', 'APP', ''),
('AAC000147', 'AAC', 'ELICAN, LIZAMAE T.', 'GROW OUT', ''),
('AAC000148', 'AAC', 'CAMPOSO, NENITA S.', 'QA & C', ''),
('AAC000151', 'AAC', 'NIERRE JR, NONI D.', 'SUPPLY CHAIN', ''),
('AAC000152', 'AAC', 'SORONGON, CRISTIN A.', 'TECHNICAL SERVICES', ''),
('AAC000154', 'AAC', 'QUIETA, RUTCHIE H.', 'APP', ''),
('AAC000155', 'AAC', 'BRAÑAIROS Sr., FELIX B.', 'SPECIAL PROJECT', ''),
('AAC000156', 'AAC', 'RICANOR, DONDEE S.', 'APP', ''),
('AAC000157', 'AAC', 'FORMENTO, AREL P.', 'GROW OUT', ''),
('AAC000159', 'AAC', 'OROSIO, JO ANN P.', 'FINANCE', ''),
('AAC000162', 'AAC', 'RAFAL, MARICEL D.', 'OUTSOURCING & GROWERSHIP', ''),
('AAC000164', 'AAC', 'NECOR, FERDIE N.', 'GROW OUT', ''),
('AAC000165', 'AAC', 'ANGELES, LEO G.', 'ENGINEERING', ''),
('AAC000166', 'AAC', 'RUFINO, VANESSA C.', 'FINANCE', ''),
('AAC000168', 'AAC', 'PURGATORIO, RALPH L.', 'SALES & MARKETING', ''),
('AAC000171', 'AAC', 'JULIA, ARNEL R.', 'ENGINEERING', ''),
('AAC000172', 'AAC', 'NIERRE, SHEILA L.', 'SALES & MARKETING', ''),
('AAC000174', 'AAC', 'AMPAC, MERY JEAN J.', 'MATERIALS MANAGEMENT', ''),
('AAC000180', 'AAC', 'ESTIGOY, VIOLETA R.', 'SALES & MARKETING', ''),
('AAC000190', 'AAC', 'GUADALQUIVER, RAZEL P.', 'SPECIAL PROJECT', ''),
('AAC000196', 'AAC', 'MANDANI, ANAMARIE C.', 'QA & C', ''),
('AAC000199', 'AAC', 'OREDA, JESER D.', 'SUPPLY CHAIN', ''),
('AAC000200', 'AAC', 'GALBO, ENRELEN B.', 'FINANCE', ''),
('AAC000204', 'AAC', 'FERMATO, JENNELYN A.', 'TECHNICAL SERVICES', ''),
('AAC000205', 'AAC', 'SASTRELLAS, RICHARD B.', 'GROW OUT', ''),
('AAC000207', 'AAC', 'MELENDRES, JUNREY C.', 'GROW OUT', ''),
('AAC000208', 'AAC', 'MEDIANA, WILMA A.', 'TECHNICAL SERVICES', ''),
('AAC000212', 'AAC', 'LEGUIP, JOSEPHINE R.', 'SUPPLY CHAIN', ''),
('AAC000213', 'AAC', 'DE ASIS, CHERRYL GRACE S.', 'FINANCE', ''),
('AAC000218', 'AAC', 'MALUBAY, ROSELYN M.', 'APP', ''),
('AAC000220', 'AAC', 'PAUNILLAN, AIDA A.', 'SUPPLY CHAIN', ''),
('AAC000222', 'AAC', 'LOMOCSO JR., ARTURO S.', 'SALES & MARKETING', ''),
('AAC000226', 'AAC', 'RETOBADO, ALICE A.', 'SALES & MARKETING', ''),
('AAC000230', 'AAC', 'BERNARDO, ERIKA MARI G.', 'SALES & MARKETING', ''),
('AAC000231', 'AAC', 'PALATI, IVAN A.', 'GROW OUT', ''),
('AAC000232', 'AAC', 'GUTIERREZ, MARY JOY R.', 'RESEARCH & DEVELOPMENT', ''),
('AAC000235', 'AAC', 'DELCANO, JEONYLEN C.', 'QA & C', ''),
('AAC000237', 'AAC', 'MACEDA, JOSIE MAR R.', 'QA & C', ''),
('AAC000238', 'AAC', 'MARTINEZ JR., HILARIO L.', 'SALES & MARKETING', ''),
('AAC000240', 'AAC', 'PANTILGAN, ALLYN P.', 'FINANCE', ''),
('AAC000242', 'AAC', 'CASCABEL, RISSYLD R.', 'FINANCE', ''),
('AAC000245', 'AAC', 'LUMANAO, ERWIN B.', 'MATERIALS MANAGEMENT', ''),
('AAC000247', 'AAC', 'ABLANIDA, JAYMAR R.', 'FINANCE', ''),
('AAC000251', 'AAC', 'ENDINO, BENJIE N.', 'APP', ''),
('AAC000254', 'AAC', 'CAMEROS, MARIBEL A.', 'FINANCE', ''),
('AAC000256', 'AAC', 'MELICOR, MISHELYN R.', 'SALES & MARKETING', ''),
('AAC000277', 'AAC', 'BAHINTING, ARGIE A.', 'SUPPLY CHAIN', ''),
('AAC000278', 'AAC', 'CARBONERA, REDINA B.', 'HUMAN RESOURCE & ADMIN', ''),
('AAC000279', 'AAC', 'DOMINGUEZ, MIGUEL RENE A.', 'G & A', ''),
('AAC000280', 'AAC', 'JORE, MARICYL T.', 'FINANCE', ''),
('AAC000281', 'AAC', 'GONZAGA, JEROME A.', 'FINANCE', ''),
('AAC000286', 'AAC', 'DIZON JR, FEDERICO B.', 'MATERIALS MANAGEMENT', ''),
('AAC000291', 'AAC', 'OROÑGAN, ELAINE JANE D.', 'INFORMATION TECHNOLOGY (IT)', ''),
('AAC000296', 'AAC', 'POLANCOS JR, ARTURO P.', 'MATERIALS MANAGEMENT', ''),
('AAC000298', 'AAC', 'DAQUIZ, RUEL T.', 'FINANCE', ''),
('AAC000302', 'AAC', 'NIERRE, PAUL D.', 'GROW OUT', ''),
('AAC000303', 'AAC', 'MALINAO, CARLOW C.', 'SPECIAL PROJECT', ''),
('AAC000305', 'AAC', 'GARCIANO, JANCRIS E.', 'HUMAN RESOURCE & ADMIN', ''),
('AAC000307', 'AAC', 'DEAROS, RANDY A.', 'GROW OUT', ''),
('AAC000310', 'AAC', 'ENRIQUEZ, ALDWIN R.', 'INFORMATION TECHNOLOGY (IT)', ''),
('AAC000316', 'AAC', 'CASINTO, LOIS L.', 'INFORMATION TECHNOLOGY (IT)', ''),
('AAC000317', 'AAC', 'CASIDSID, SALVADOR R.', 'ENGINEERING', ''),
('AAC000318', 'AAC', 'HONGOY, ESTELITO B.', 'ENGINEERING', ''),
('AAC000322', 'AAC', 'MANGUILIMUTAN, MARVIN A.', 'FINANCE', ''),
('AAC000328', 'AAC', 'RONULO, LESTER S.', 'SALES & MARKETING', ''),
('AAC000330', 'AAC', 'FERMATO, AL SHERVIN D.', 'GROW OUT', ''),
('AAC000333', 'AAC', 'DELA TORRE, RICKY E.', 'TECHNICAL SERVICES', ''),
('AAC000336', 'AAC', 'AYOP, LADY MARIE C.', 'SALES & MARKETING', ''),
('AAC000337', 'AAC', 'GERONAGA, JHOER T.', 'APP', ''),
('AAC000339', 'AAC', 'SORIANO, JOEL G.', 'GROW OUT', ''),
('AAC000347', 'AAC', 'CADORNA, RAYMUNDO C.', 'HUMAN RESOURCE & ADMIN', ''),
('AAC000350', 'AAC', 'GERONAGA, JENNIFER L.', 'MATERIALS MANAGEMENT', ''),
('AAC000351', 'AAC', 'DAJAO, LIGAYA G.', 'FINANCE', ''),
('AAC000352', 'AAC', 'PARADIANG, RALPH JAY F.', 'FINANCE', ''),
('AAC000354', 'AAC', 'GERALDO, RIZA A.', 'FINANCE', ''),
('AAC000363', 'AAC', 'MAKALWA, ALDRIN V.', 'HUMAN RESOURCE & ADMIN', ''),
('AAC000365', 'AAC', 'SUMANTING, MICHAEL JADE U.', 'FINANCE', ''),
('AAC000388', 'AAC', 'CABILLA, MAY A.', 'SALES & MARKETING', ''),
('AAC000390', 'AAC', 'CADUNGOG, DIOSCORO O.', 'GROW OUT', ''),
('AAC000391', 'AAC', 'MILLAN, FRANCIS A.', 'SPECIAL PROJECT', ''),
('AAC000393', 'AAC', 'MOSQUERA, ISRAEL M.', 'GROW OUT', ''),
('AAC000394', 'AAC', 'MIANA, ARLENE E.', 'APP', ''),
('AAC000395', 'AAC', 'MANATAD, VINCENT ANGELO M.', 'FINANCE', ''),
('AAC000400', 'AAC', 'BACARISAS, QUINT ANTHONY L.', 'GROW OUT', ''),
('AAC000402', 'AAC', 'IGNACIO, ROGEL B.', 'FINANCE', ''),
('AAC000403', 'AAC', 'PEDRANO, CRISTINE MARIZ M.', 'GROW OUT', ''),
('AAC000404', 'AAC', 'BELLEZA, RIA G.', 'FINANCE', ''),
('AAC000405', 'AAC', 'CHUA, CLAIRE B.', 'FINANCE', ''),
('AAC000416', 'AAC', 'ESTRELLA, DENBERT C.', 'TECHNICAL SERVICES', ''),
('AAC000417', 'AAC', 'PAGAY, JIGS BRYAN V.', 'GROW OUT', ''),
('AAC000418', 'AAC', 'MORAL, JINTCHELLE E.', 'FINANCE', ''),
('AAC000426', 'AAC', 'ERIGBUAGAS, KAREN C.', 'FINANCE', ''),
('AAC000427', 'AAC', 'NIQUIT, MARY JOY C.', 'FINANCE', ''),
('AAC000428', 'AAC', 'CLAPANO, EMELIE L.', 'FINANCE', ''),
('AAC000432', 'AAC', 'ALOWA, ALFONSO A.', 'TECHNICAL SERVICES', ''),
('AAC000434', 'AAC', 'BUENAFE, CYNDREX M.', 'GROW OUT', ''),
('AAC000436', 'AAC', 'BAYLOSIS, ROSALIE V.', 'HUMAN RESOURCE & ADMIN', ''),
('AAC000444', 'AAC', 'CELIS, JERAN MAI T.', 'MATERIALS MANAGEMENT', ''),
('AAC000457', 'AAC', 'ALCANTARA, ANGINETTE T.', 'OUTSOURCING & GROWERSHIP', ''),
('AAC000459', 'AAC', 'TEVES, MARY JOY T.', 'HUMAN RESOURCE & ADMIN', ''),
('AAC000461', 'AAC', 'APELINGA, REZEL JOY A.', 'SPECIAL PROJECT', ''),
('AAC000463', 'AAC', 'BOLO, DHEGI M.', 'SPECIAL PROJECT', ''),
('AAC000464', 'AAC', 'GARGANTIEL, LORENZO S.', 'ENGINEERING', ''),
('AAC000465', 'AAC', 'ABDULRADZAK, ANGELICA L.', 'TECHNICAL SERVICES', ''),
('AAC000469', 'AAC', 'MALIGON, JESAH A.', 'SPECIAL PROJECT', ''),
('AAC000473', 'AAC', 'CATIPAY, FRANCISCO V.', 'ENGINEERING', ''),
('AAC000475', 'AAC', 'PAGAY, CHARLIE S.', 'ENGINEERING', ''),
('AAC000476', 'AAC', 'PLANIA, WILLIE C.', 'ENGINEERING', ''),
('AAC000477', 'AAC', 'CALLO, DIONESIO E.', 'ENGINEERING', ''),
('AAC000478', 'AAC', 'JOPSON, DOMINADOR P.', 'ENGINEERING', ''),
('AAC000479', 'AAC', 'ALMARIO, MARJUN S.', 'OUTSOURCING & GROWERSHIP', ''),
('AAC000483', 'AAC', 'TORREVERDE, CHARLES T.', 'FINANCE', ''),
('AAC000485', 'AAC', 'NAVARRO, HARRYBREN K.', 'INFORMATION TECHNOLOGY (IT)', ''),
('AAC000489', 'AAC', 'TIBAY, DONALD PAUL E.', 'ENGINEERING', ''),
('AAC000490', 'AAC', 'ALINSUB, RYAN F.', 'ENGINEERING', ''),
('AAC000492', 'AAC', 'VARGAS, FRANCIS JOHN D.', 'INFORMATION TECHNOLOGY (IT)', ''),
('AAC000495', 'AAC', 'MANRIQUE, REXAN JAY S.', 'TECHNICAL SERVICES', ''),
('AAC000497', 'AAC', 'GALAN, ASTRID KAYE T.', 'GROW OUT', ''),
('AAC000499', 'AAC', 'DALIGDIG, MARGIELYN T.', 'FINANCE', ''),
('AAC000502', 'AAC', 'HIBUNE, WARREN R.', 'HUMAN RESOURCE & ADMIN', ''),
('AAC000506', 'AAC', 'QUIMSON, SHAIRA JAY A.', 'HUMAN RESOURCE & ADMIN', ''),
('AAC000507', 'AAC', 'QUIMA, GENEVIEVE ROCEL D.', 'HUMAN RESOURCE & ADMIN', ''),
('AAC000508', 'AAC', 'EUSALINA, GLADYS C.', 'SALES & MARKETING', ''),
('AAC000510', 'AAC', 'BORBON, ANDREW P.', 'SALES & MARKETING', ''),
('AAC000512', 'AAC', 'ALBELAR, RONNIE T.', 'GROW OUT', ''),
('AAC000514', 'AAC', 'CALIBAYAN, ROLLY V.', 'GROW OUT', ''),
('AAC000515', 'AAC', 'DAVID, RONNIE M.', 'GROW OUT', ''),
('AAC000517', 'AAC', 'MORANO, ANTONIO D.', 'GROW OUT', ''),
('AAC000518', 'AAC', 'PANTOJAN, CESARIO A.', 'GROW OUT', ''),
('AAC000519', 'AAC', 'MAH, MYCA S.', 'GROW OUT', ''),
('AAC000534', 'AAC', 'DY, ELVIS J.', 'OUTSOURCING & GROWERSHIP', ''),
('AAC000538', 'AAC', 'PACARAT, EDGARDO G.', 'ENGINEERING', ''),
('AAC000541', 'AAC', 'GERODIAS, RONALDO V.', 'ENGINEERING', ''),
('AAC000542', 'AAC', 'FORROSUELO, RENANTE C.', 'ENGINEERING', ''),
('AAC000544', 'AAC', 'BACUS, FRANCISCO L.', 'ENGINEERING', ''),
('AAC000545', 'AAC', 'BACUS, JOEL M.', 'ENGINEERING', ''),
('AAC000546', 'AAC', 'MANONGSONG, JESABEL C.', 'SUPPLY CHAIN', ''),
('AAC000550', 'AAC', 'JESIM, FREDDIE C.', 'ENGINEERING', ''),
('AAC000554', 'AAC', 'PAGAY, RUTCHEL G.', 'MATERIALS MANAGEMENT', ''),
('AAC000555', 'AAC', 'BALALA, VIA NICA C.', 'MATERIALS MANAGEMENT', ''),
('AAC000559', 'AAC', 'PALAJE, ANA O.', 'SALES & MARKETING', ''),
('AAC000563', 'AAC', 'ALCAZARIN, JOHNDRIL H.', 'GROW OUT', ''),
('AAC000613', 'AAC', 'DELANTES, CHRISTOPHER A.', 'GROW OUT', ''),
('AAC000642', 'AAC', 'VILLAMERO, ALLAN H.', 'SUPPLY CHAIN', ''),
('AAC000659', 'AAC', 'SENDO, KENN P.', 'GROW OUT', ''),
('AAC000691', 'AAC', 'MARIGON, SHIELA MAY E.', 'QA & C', ''),
('AAC000715', 'AAC', 'POLINAR, MARTIN T.', 'TECHNICAL SERVICES', ''),
('AAC000780', 'AAC', 'GAJUSTA, MILKY E.', 'GROW OUT', ''),
('AAC000791', 'AAC', 'INGKONG, MARLON G.', 'ENGINEERING', ''),
('AAC000804', 'AAC', 'CANTIL, EDISON B.', 'SEACAGE', ''),
('AAC000805', 'AAC', 'CORDERO, LEO PAOLO D.', 'ENGINEERING', ''),
('AAC000806', 'AAC', 'CABANTE, CUERMY L.', 'FINANCE', ''),
('AAC000810', 'AAC', 'ENOPIA, KRISTINE DAWN M.', 'FINANCE', ''),
('AAC000811', 'AAC', 'REGINO, KIMBERLIE MARIE L.', 'SALES & MARKETING', ''),
('AAC000812', 'AAC', 'TABANAO, VANGIELYN A.', 'FINANCE', ''),
('AAC000813', 'AAC', 'AMOR, KAREN MAE L.', 'FINANCE', ''),
('AAC000814', 'AAC', 'JUAREZ, ROMMELINA C.', 'SALES & MARKETING', ''),
('AAC000815', 'AAC', 'CABANTE, DONNY L.', 'FINANCE', ''),
('AAC000821', 'AAC', 'ARAÑA, SHIENA C.', 'FINANCE', ''),
('AAC000822', 'AAC', 'RICHARDS, MICHAEL O.', 'RPP', ''),
('AAC000830', 'AAC', 'ARTILLERO, RUTH A.', 'RPP', ''),
('AAC000831', 'AAC', 'CAMEROS, CHRISTIAN A.', 'FINANCE', ''),
('AAC000841', 'AAC', 'CAITUM, RYAN JOHN S.', 'ENGINEERING', ''),
('AAC000845', 'AAC', 'TINAYA, GLENN NIERRA J.', 'SALES & MARKETING', ''),
('AAC000847', 'AAC', 'PALAPAS, LORENA T.', 'SPECIAL PROJECT', ''),
('AAC000853', 'AAC', 'GARDOSE, CARTHER D.', 'HUMAN RESOURCE & ADMIN', ''),
('AAC000855', 'AAC', 'BARBERO, JEREMY ARNOLD B.', 'FINANCE', ''),
('AAC000856', 'AAC', 'ARIZ, IZA GRACE F.', 'RPP', ''),
('AAC000857', 'AAC', 'DIAMANTE, MARY ANN Y.', 'SUPPLY CHAIN', ''),
('AAC000858', 'AAC', 'TUYAC, JANMARIE L.', 'APP', ''),
('AAC000859', 'AAC', 'SECOYA, DARYL O.', 'FINANCE', ''),
('AAC000863', 'AAC', 'SOLEDAD, JUNISA O.', 'TECHNICAL SERVICES', ''),
('AAC000870', 'AAC', 'ABELLA, JEFFREY L.', 'APP', ''),
('AAC000871', 'AAC', 'AGOO, RANDY O.', 'APP', ''),
('AAC000872', 'AAC', 'ALBARANDO, JOEY REY T.', 'APP', ''),
('AAC000873', 'AAC', 'ALBARANDO, ROWENA T.', 'APP', ''),
('AAC000874', 'AAC', 'AZURO, NILA F.', 'RPP', ''),
('AAC000875', 'AAC', 'BALONGO, FAITH B.', 'QA & C', ''),
('AAC000876', 'AAC', 'BERGADO, FANNY V.', 'APP', ''),
('AAC000877', 'AAC', 'CUBERO, ELMARIE JANE B.', 'APP', ''),
('AAC000878', 'AAC', 'CALO, RICHARD T.', 'APP', ''),
('AAC000879', 'AAC', 'CASTILLO, JO AYESSA S.', 'APP', ''),
('AAC000880', 'AAC', 'CATUBAY, LETECIA M.', 'APP', ''),
('AAC000881', 'AAC', 'CORDOVA, DARLYN B.', 'APP', ''),
('AAC000882', 'AAC', 'DAVID, LEONORA C.', 'APP', ''),
('AAC000883', 'AAC', 'DELA CRUZ, RONALD S.', 'RPP', ''),
('AAC000885', 'AAC', 'DIALDE, ISIDRO J.', 'APP', ''),
('AAC000886', 'AAC', 'ELEVADO, NARCISO S.', 'APP', ''),
('AAC000887', 'AAC', 'ELEVADO, OLIVER S.', 'APP', ''),
('AAC000888', 'AAC', 'GAOIRAN, LYN MAR M.', 'QA & C', ''),
('AAC000889', 'AAC', 'GASCO, JAKE STEPHEN R.', 'APP', ''),
('AAC000891', 'AAC', 'JABILLES, RUSSEL GLENN Q.', 'QA & C', ''),
('AAC000892', 'AAC', 'LALISAN, RODEL P.', 'APP', ''),
('AAC000893', 'AAC', 'LARAÑO, MARY JEAN F.', 'APP', ''),
('AAC000894', 'AAC', 'MONTES, ANALYN R.', 'QA & C', ''),
('AAC000897', 'AAC', 'PRESBITERO, FLORA MAE T.', 'SUPPLY CHAIN', ''),
('AAC000898', 'AAC', 'RELABO, BOMBIE A.', 'APP', ''),
('AAC000899', 'AAC', 'TE, JESSA R.', 'QA & C', ''),
('AAC000900', 'AAC', 'RIVERA, JESIE M.', 'PPP', ''),
('AAC000901', 'AAC', 'ROMERO, MARIA CORAZON M.', 'GPP', ''),
('AAC000902', 'AAC', 'SENIT, ANIE B.', 'APP', ''),
('AAC000906', 'AAC', 'TORION, IAN MARK C.', 'SUPPLY CHAIN', ''),
('AAC000907', 'AAC', 'MOISES, JOHN MARK N.', 'SUPPLY CHAIN', ''),
('AAC000908', 'AAC', 'DELES, RONNY G.', 'ENGINEERING', ''),
('AAC000911', 'AAC', 'PADILLO, JIMMY C.', 'ENGINEERING', ''),
('AAC000919', 'AAC', 'BUCAYAN, MARGIE LOU U.', 'QA & C', ''),
('AAC000923', 'AAC', 'CUARESMA, JONREL L.', 'SEACAGE', ''),
('AAC000925', 'AAC', 'SALES, JERRY P.', 'FINANCE', ''),
('AAC000927', 'AAC', 'ROYO, IVY GRACE M.', 'MATERIALS MANAGEMENT', ''),
('AAC000929', 'AAC', 'QUINQUITO, GENALYN U.', 'MATERIALS MANAGEMENT', ''),
('AAC000931', 'AAC', 'ASILO, NERWIN S.', 'SEACAGE', ''),
('AAC000932', 'AAC', 'BATUA, DALTON S.', 'SEACAGE', ''),
('AAC000934', 'AAC', 'BUNGHANOY, APRIL CARRY M.', 'SPECIAL PROJECT', ''),
('AAC000935', 'AAC', 'BAYNOSA, CAMELO M.', 'SPECIAL PROJECT', ''),
('AAC000936', 'AAC', 'DERUCA, DIANNE SHANE R.', 'ENGINEERING', ''),
('AAC000937', 'AAC', 'AGBAYANI, GELLIE R.', 'RESEARCH & DEVELOPMENT', ''),
('AAC000938', 'AAC', 'JACO, MARY ROSE G.', 'QA & C', ''),
('AAC000940', 'AAC', 'GENEROSO, JURIM S.', 'SEACAGE', ''),
('AAC000949', 'AAC', 'DELANTES, JERYL M.', 'FINANCE', ''),
('AAC000955', 'AAC', 'FACIOL, FABIANO D.', 'SPECIAL PROJECT', ''),
('AAC000956', 'AAC', 'BAJA, DANNY G.', 'SPECIAL PROJECT', ''),
('AAC000958', 'AAC', 'AGUDO, SARAH MAE C.', 'QA & C', ''),
('AAC000961', 'AAC', 'ALINSUB, CHARLES JUSTIN A.', 'SPECIAL PROJECT', ''),
('AAC000990', 'AAC', 'ABEQUIBEL, SARAH JANE L.', 'QA & C', ''),
('AAC001012', 'AAC', 'ALESNA, MARIEL P.', 'QA & C', ''),
('AAC001013', 'AAC', 'ALESNA, JOLINA P.', 'APP', ''),
('AAC001041', 'AAC', 'ANCHETA, ANALIE O.', 'APP', ''),
('AAC001059', 'AAC', 'ARAQUE, JESON A.', 'RPP', ''),
('AAC001063', 'AAC', 'TRINIDAD, PINKY A.', 'RPP', ''),
('AAC001066', 'AAC', 'ARIZ, SARAH JANE F.', 'RPP', ''),
('AAC001091', 'AAC', 'BALASABAS, MELVIN Q.', 'QA & C', ''),
('AAC001102', 'AAC', 'BANAY, MICHAEL G.', 'APP', ''),
('AAC001120', 'AAC', 'BAYRON, DENISE MARC L.', 'QA & C', ''),
('AAC001127', 'AAC', 'BENDIRO, ROCELYN B.', 'RPP', ''),
('AAC001190', 'AAC', 'CALO, LOIDA G.', 'APP', ''),
('AAC001195', 'AAC', 'CAMINGAW, LINDA MAE L.', 'GROW OUT', ''),
('AAC001280', 'AAC', 'DELES, GERALDINE B.', 'PPP', ''),
('AAC001301', 'AAC', 'DUASO, RICHARD C.', 'APP', ''),
('AAC001320', 'AAC', 'ENOT, DONDON L.', 'APP', ''),
('AAC001322', 'AAC', 'ERIBAL, DONNA C.', 'RPP', ''),
('AAC001336', 'AAC', 'FALLER, EDWARD B.', 'SPECIAL PROJECT', ''),
('AAC001351', 'AAC', 'FUENTES, JERALD M.', 'APP', ''),
('AAC001373', 'AAC', 'GICA, JESSIE V.', 'RESEARCH & DEVELOPMENT', ''),
('AAC001377', 'AAC', 'GINOSOLANGO, ARNIEL G.', 'SALES & MARKETING', ''),
('AAC001382', 'AAC', 'GRANADA, IAN L.', 'GPP', ''),
('AAC001391', 'AAC', 'GUERRA, RAYAN A.', 'RPP', ''),
('AAC001394', 'AAC', 'GUTIERREZ, REGIE B.', 'RPP', ''),
('AAC001425', 'AAC', 'LACERA SR, ROLLY S.', 'GROW OUT', ''),
('AAC001454', 'AAC', 'LECITA, JEROME DOMINIC G.', 'APP', ''),
('AAC001460', 'AAC', 'LEQUINA JR., NUMERIANO R.', 'APP', ''),
('AAC001484', 'AAC', 'MAGNO, REY A.', 'APP', ''),
('AAC001524', 'AAC', 'MATURA, EFRENIEL L.', 'RESEARCH & DEVELOPMENT', ''),
('AAC001578', 'AAC', 'OCTAVIO, MERRY ROSE E.', 'RESEARCH & DEVELOPMENT', ''),
('AAC001594', 'AAC', 'OROLA, FRANZYN GAIL U.', 'OUTSOURCING & GROWERSHIP', ''),
('AAC001625', 'AAC', 'PARAISO, LUCITA L.', 'GPP', ''),
('AAC001712', 'AAC', 'SACEDOR, ROSELA C.', 'APP', ''),
('AAC001716', 'AAC', 'SALAAN, KRISTOFFER SON R.', 'APP', ''),
('AAC001722', 'AAC', 'SALILI, ELMER C.', 'APP', ''),
('AAC001732', 'AAC', 'SANTES JR, CLEMENTE B.', 'APP', ''),
('AAC001837', 'AAC', 'GRANADA, KIARA JEAN M.', 'QA & C', ''),
('AAC001839', 'AAC', 'OPALLA, GIVEL M.', 'OUTSOURCING & GROWERSHIP', ''),
('AAC001841', 'AAC', 'ONG, JOHN LORENZO A.', 'GROW OUT', ''),
('AAC001854', 'AAC', 'VILBAR, ROBERT T.', 'SPECIAL PROJECT', ''),
('AAC001855', 'AAC', 'RESANE, JONIL P.', 'ENGINEERING', ''),
('AAC001856', 'AAC', 'BAYNOSA, ANDRO J.', 'ENGINEERING', ''),
('AAC001859', 'AAC', 'BELLINGAN, ROSE MARIE E.', 'QA & C', ''),
('AAC001866', 'AAC', 'ROA, AINA JEAN E.', 'TECHNICAL SERVICES', ''),
('AAC001867', 'AAC', 'APOLINAR, REY D.', 'OUTSOURCING & GROWERSHIP', ''),
('AAC001881', 'AAC', 'PELAEZ, DENNIS J.', 'GROW OUT', ''),
('AAC001882', 'AAC', 'GARBO, PAQUITO NINO P.', 'SALES & MARKETING', ''),
('AAC001886', 'AAC', 'BUENO, AL JANE S.', 'QA & C', ''),
('AAC001894', 'AAC', 'MUSICO, MELANIE D.', 'QA & C', ''),
('AAC001901', 'AAC', 'NIEVES, NILCARJUN B.', 'SEACAGE', ''),
('AAC001902', 'AAC', 'CANDELOSA, PONCIANO S.', 'SEACAGE', ''),
('AAC001904', 'AAC', 'PATUNOG, ELGINE JOHN .', 'ENGINEERING', ''),
('AAC001905', 'AAC', 'TRAJE, LEMUEL J.', 'OUTSOURCING & GROWERSHIP', ''),
('AAC001906', 'AAC', 'SALIK, DEVINE GRACE P.', 'QA & C', ''),
('AAC001923', 'AAC', 'CALAYON, CRIEZAVELLA U.', 'SPECIAL PROJECT', ''),
('AAC001939', 'AAC', 'TANZA, JEANELYN A.', 'QA & C', ''),
('AAC001949', 'AAC', 'MANGGARON, MARK JAY T.', 'ENGINEERING', ''),
('AAC001955', 'AAC', 'PADLA, JANJAN F.', 'SEACAGE', ''),
('AAC001957', 'AAC', 'CORVERA, MARVIN BLESS P.', 'SEACAGE', ''),
('AAC001959', 'AAC', 'AQUINO, LORIE JOHN T.', 'SEACAGE', ''),
('AAC001965', 'AAC', 'PIODOS, MIKEE R.', 'FINANCE', ''),
('AAC001966', 'AAC', 'PANES, JASMINE O.', 'MATERIALS MANAGEMENT', ''),
('AAC001967', 'AAC', 'SALIGAN, ABDULRAHMAN B.', 'SEACAGE', ''),
('AAC001968', 'AAC', 'SUMALINOG, ARCHIE G.', 'SEACAGE', ''),
('AAC001971', 'AAC', 'YANGAN, ROBERT P.', 'SPECIAL PROJECT', ''),
('AAC001972', 'AAC', 'MANRIAL, RENE M.', 'SPECIAL PROJECT', ''),
('AAC001974', 'AAC', 'BARO, FELIMON A.', 'SPECIAL PROJECT', ''),
('AAC001975', 'AAC', 'MANALILI, JAMES M.', 'SPECIAL PROJECT', ''),
('AAC001976', 'AAC', 'PARDILLO, REYDENE E.', 'ENGINEERING', ''),
('AAC001979', 'AAC', 'GRAMATICA, CRESITO B.', 'ENGINEERING', ''),
('AAC001980', 'AAC', 'ELTAGONDE, PAULINE P.', 'GROW OUT', ''),
('AAC001983', 'AAC', 'TALAUGON, JOVELYN A.', 'GPP', ''),
('AAC001984', 'AAC', 'MAGALLANES, NOE B.', 'OUTSOURCING & GROWERSHIP', ''),
('AAC001985', 'AAC', 'CULANAG, JAMES E.', 'OUTSOURCING & GROWERSHIP', ''),
('AAC001986', 'AAC', 'OLINO, ASHLEY C.', 'HUMAN RESOURCE & ADMIN', ''),
('AAC001993', 'AAC', 'ANTIPUESTO, ANGEL C.', 'QA & C', ''),
('AAC001995', 'AAC', 'VILLARIZA, VERONICKSON S.', 'SPECIAL PROJECT', ''),
('AAC001996', 'AAC', 'MACEREN, GABRIEL R.', 'HUMAN RESOURCE & ADMIN', ''),
('AAC001998', 'AAC', 'ERIBAL, GARRY L.', 'GROW OUT', ''),
('AAC001999', 'AAC', 'EJORANGO, ERWIN B.', 'INFORMATION TECHNOLOGY (IT)', ''),
('AAC002000', 'AAC', 'AGAS, TUESDAY JANE A.', 'SALES & MARKETING', ''),
('AAC002002', 'AAC', 'ARTAJO, DANIEL I.', 'ENGINEERING', ''),
('AAC002003', 'AAC', 'AMLON, JESSALVE REGINA T.', 'SPECIAL PROJECT', ''),
('AAC002004', 'AAC', 'OLANDRIA, ELMER A.', 'OUTSOURCING & GROWERSHIP', ''),
('AAC002006', 'AAC', 'GUARDE, KIMBERLY S.', 'SUPPLY CHAIN', ''),
('AAC002009', 'AAC', 'LARA, HARVEY L.', 'GROW OUT', ''),
('AAC002010', 'AAC', 'CARIGAY, MARIO S.', 'SPECIAL PROJECT', ''),
('AAC002011', 'AAC', 'ROJAS, LIMUEL A.', 'SPECIAL PROJECT', ''),
('AAC002012', 'AAC', 'GUTIERREZ, BERNIE A.', 'SPECIAL PROJECT', ''),
('AAC002013', 'AAC', 'CABANLIT, MARTA ANZUARA M.', 'HUMAN RESOURCE & ADMIN', ''),
('AAC002017', 'AAC', 'DUQUEZA, LESTER ACE D.', 'ENGINEERING', ''),
('AAC002018', 'AAC', 'LASALITA, KENCH P.', 'ENGINEERING', ''),
('AAC002019', 'AAC', 'CANTALEJO, MARY JANE B.', 'OUTSOURCING & GROWERSHIP', ''),
('AAC002020', 'AAC', 'CAMACHO, MARLON A.', 'OUTSOURCING & GROWERSHIP', ''),
('AAC002022', 'AAC', 'GAOIRAN, LHEMAR L.', 'SALES & MARKETING', ''),
('AAC002023', 'AAC', 'GABILAGON, JERRY F.', 'OUTSOURCING & GROWERSHIP', ''),
('AAC002026', 'AAC', 'DECREPITO, ROLLIE C.', 'APP', ''),
('AAC002027', 'AAC', 'BALANDAN, KENNITH G.', 'QA & C', ''),
('AAC002030', 'AAC', 'CABUSLAY, RONEL N.', 'PPP', ''),
('AAC002039', 'AAC', 'FLORES, JOYCE ANN G.', 'MATERIALS MANAGEMENT', ''),
('AAC002055', 'AAC', 'PARAS, OLIVER JOHN B.', 'ENGINEERING', ''),
('AAC002057', 'AAC', 'ALVIOR, JAMELA B.', 'APP', ''),
('AAC002061', 'AAC', 'TSANG, YIN ROGER M.', 'SALES & MARKETING', ''),
('AAC002064', 'AAC', 'OMONGOS, DEANEM JEFF B.', 'TECHNICAL SERVICES', ''),
('AAC002079', 'AAC', 'BARANDA, RICHELLE E.', 'MATERIALS MANAGEMENT', ''),
('AAC002081', 'AAC', 'MARQUEZ, MARAH A.', 'ENGINEERING', ''),
('AAC002082', 'AAC', 'BALOLONG, JOLEMIE E.', 'ENGINEERING', ''),
('AAC002084', 'AAC', 'DIONIO, JANET F.', 'APP', ''),
('AAC002086', 'AAC', 'BAGUIO, CHELSEA BELLE D.', 'FINANCE', ''),
('AAC002088', 'AAC', 'ENOT, JUDYANN B.', 'SALES & MARKETING', ''),
('AAC002094', 'AAC', 'VALDEZ, MANNY A.', 'RPP', ''),
('AAC002104', 'AAC', 'VALDEPEÑAS, MARY QUEEN P.', 'PPP', ''),
('AAC002107', 'AAC', 'DORADO, FRANCIS C.', 'RPP', ''),
('AAC002123', 'AAC', 'HOBAYAN, BILLYMAR C.', 'SALES & MARKETING', ''),
('AAC002124', 'AAC', 'WONG, FEBBIE ANN D.', 'QA & C', ''),
('AAC002125', 'AAC', 'MELENDRES, CRISTINE JOY V.', 'MATERIALS MANAGEMENT', ''),
('AAC002128', 'AAC', 'ORBIGOSO, CATTEE L.', 'SALES & MARKETING', ''),
('AAC002160', 'AAC', 'SON, DIVINA .', 'APP', ''),
('AAC002163', 'AAC', 'SALAZAR, NAZARIO M.', 'HUMAN RESOURCE & ADMIN', ''),
('AAC002166', 'AAC', 'ADOLFO, KERLENE ARIANNE J.', 'TECHNICAL SERVICES', ''),
('AAC002167', 'AAC', 'JOAQUIN, LEOVELYN S.', 'MATERIALS MANAGEMENT', ''),
('AAC002168', 'AAC', 'MACADO, BONALYN G.', 'MATERIALS MANAGEMENT', ''),
('AAC002169', 'AAC', 'ALGER, CHRISTOHER RHETT A.', 'SALES & MARKETING', ''),
('AAC002171', 'AAC', 'TABLADA, PRINCES KEATH .', 'SALES & MARKETING', ''),
('AAC002172', 'AAC', 'MANTO, JEZA M.', 'FINANCE', ''),
('AAC002173', 'AAC', 'DELARA, JORDAN DAVE A.', 'MATERIALS MANAGEMENT', ''),
('AAC002174', 'AAC', 'DEL ROSARIO, KWIN CYBIL P.', 'RESEARCH & DEVELOPMENT', ''),
('AAC002176', 'AAC', 'PARREÑAS, RAYMOND C.', 'ENGINEERING', ''),
('AAC002182', 'AAC', 'PELEGRO, REMROSE D.', 'FINANCE', ''),
('AAC002193', 'AAC', 'ANCHETA, ROSEMARIE T.', 'QA & C', ''),
('AAC002194', 'AAC', 'BAGUIO, GIAN CARLO J.', 'FINANCE', ''),
('AAC002196', 'AAC', 'PALMA, BRIDGET GWYN B.', 'APP', ''),
('AAC002215', 'AAC', 'OBEÑITA, APRIL BOY E.', 'ENGINEERING', ''),
('AAC002216', 'AAC', 'MENDEZ, ALLAN E.', 'ENGINEERING', ''),
('AAC002218', 'AAC', 'REYES, KEVEN CLENN B.', 'QA & C', ''),
('AAC002228', 'AAC', 'GASATAN, CYNTHIA M.', 'GPP', ''),
('AAC002229', 'AAC', 'BARSALOTE, AUDIE T.', 'SPECIAL PROJECT', ''),
('AAC002230', 'AAC', 'SABILLO, IRISH O.', 'QA & C', ''),
('AAC002235', 'AAC', 'YUSAL, MICHEL M.', 'ENGINEERING', ''),
('AAC002236', 'AAC', 'FUENTES, BEATRIZ OLGA B.', 'QA & C', ''),
('AAC002238', 'AAC', 'TULO, RACKY C.', 'QA & C', ''),
('AAC002240', 'AAC', 'LIBAWAN, RONEL B.', 'SPECIAL PROJECT', ''),
('AAC002241', 'AAC', 'VEQUISO, RENE V.', 'SPECIAL PROJECT', ''),
('AAC002242', 'AAC', 'DIAMA, MICHAEL B.', 'SPECIAL PROJECT', ''),
('AAC002243', 'AAC', 'CADIZ, CHRISTOPHER F.', 'SALES & MARKETING', ''),
('AAC002244', 'AAC', 'EJARES, RYAN R.', 'SPECIAL PROJECT', ''),
('AAC002249', 'AAC', 'SUICO, JOHN CARLO M.', 'FINANCE', ''),
('AAC002250', 'AAC', 'DIMCO, PRECIOUS MAY M.', 'FINANCE', ''),
('AAC002257', 'AAC', 'PRADO, JOHN MARCEL P.', 'FINANCE', ''),
('AAC002258', 'AAC', 'BALAGTAS, MARLON J.', 'FINANCE', ''),
('AAC002262', 'AAC', 'REVILLA, HARRY JOHN M.', 'MATERIALS MANAGEMENT', ''),
('AAC002265', 'AAC', 'ERES, BRANDON JAY .', 'RESEARCH & DEVELOPMENT', ''),
('AAC002267', 'AAC', 'RAMOS, ARVIN JAN A.', 'RPP', ''),
('AAC002268', 'AAC', 'BELARMINO, SEAN MICHAEL B.', 'RPP', ''),
('AAC002269', 'AAC', 'DINOPOL, GIEZEL S.', 'MATERIALS MANAGEMENT', ''),
('AAC002271', 'AAC', 'BRAO, LEIZLY A.', 'QA & C', ''),
('AAC002278', 'AAC', 'ABALLE, ELLAINE L.', 'FINANCE', ''),
('AAC002280', 'AAC', 'PANUGAS, JAYNE MARYELL L.', 'ENGINEERING', ''),
('AAC002300', 'AAC', 'INTEGRO, MARY JOY F.', 'MATERIALS MANAGEMENT', ''),
('AAC002312', 'AAC', 'CLARION, CINDY A.', 'HUMAN RESOURCE & ADMIN', ''),
('AAC002316', 'AAC', 'NAVARRO, GODJAVEN K.', 'FINANCE', ''),
('AAC002319', 'AAC', 'ALESNA, MARIECAR P.', 'MATERIALS MANAGEMENT', ''),
('AAC002321', 'AAC', 'SABORNIDO, NORIMAE S.', 'MATERIALS MANAGEMENT', ''),
('AAC002322', 'AAC', 'SERVAN, REGEN V.', 'FINANCE', ''),
('AAC002334', 'AAC', 'NARCISO, RISSAH S.', 'FINANCE', ''),
('AAC002335', 'AAC', 'LARAÑO, ESEM JANE C.', 'FINANCE', ''),
('AAC002347', 'AAC', 'DELGADO, AGNES I.', 'QA & C', ''),
('AAC002353', 'AAC', 'SIMTIM, DAXNELLE P.', 'RESEARCH & DEVELOPMENT', ''),
('AAC002355', 'AAC', 'RALLA, JAYVEE F.', 'QA & C', ''),
('AAC002356', 'AAC', 'RAFAEL, CREIGHTON H.', 'APP', ''),
('AAC002357', 'AAC', 'BATAY, RICA G.', 'RPP', ''),
('AAC002358', 'AAC', 'NOBLE, MARY DALE .', 'FINANCE', ''),
('AAC002360', 'AAC', 'TAN, KATE ALEXIS P.', 'FINANCE', ''),
('AAC002361', 'AAC', 'MAMALIAS, REYMARK N.', 'TECHNICAL SERVICES', ''),
('AAC002362', 'AAC', 'MICO, PRINCE ROEL G.', 'RESEARCH & DEVELOPMENT', ''),
('AAC002364', 'AAC', 'SAMONTE, TEDDY A.', 'FINANCE', ''),
('AAC002365', 'AAC', 'CAHUCOM, ARLENE C.', 'MATERIALS MANAGEMENT', ''),
('AAC002366', 'AAC', 'MANATAD, MARIAN KATE M.', 'FINANCE', ''),
('AAC002367', 'AAC', 'SAYLOON, JOEMARIE A.', 'MATERIALS MANAGEMENT', ''),
('AAC002368', 'AAC', 'TALISAY, ANGELYN T.', 'MATERIALS MANAGEMENT', ''),
('AAC002380', 'AAC', 'OLOG, APPLE JANE B.', 'HUMAN RESOURCE & ADMIN', ''),
('AAC002381', 'AAC', 'DANTE, RAZEL FAITH R.', 'MATERIALS MANAGEMENT', ''),
('AAC002382', 'AAC', 'SOLEDAD, JANICE O.', 'TECHNICAL SERVICES', ''),
('AAC002384', 'AAC', 'MAGALLANES, LOUIE T.', 'APP', ''),
('AAC002390', 'AAC', 'TIBAY, JERRY E.', 'ENGINEERING', ''),
('AAC002391', 'AAC', 'BALANTAY, RYAN GEORGE T.', 'APP', ''),
('AAC002392', 'AAC', 'ROCACORBA, CLEVEN M.', 'FINANCE', ''),
('AAC002393', 'AAC', 'FRONTERAS, DAISY JANE A.', 'APP', ''),
('AAC002394', 'AAC', 'DELICANO, ARIAN S.', 'HUMAN RESOURCE & ADMIN', ''),
('AAC002395', 'AAC', 'DIAMA, MERILYN M.', 'SPECIAL PROJECT', ''),
('AAC002402', 'AAC', 'VILLANUEVA, JOEL D.', 'ENGINEERING', ''),
('AAC002405', 'AAC', 'CABANDA, PAUL DREXLER B.', 'INFORMATION TECHNOLOGY (IT)', ''),
('AAC002407', 'AAC', 'ALIGATO, LUDGADE D.', 'SPECIAL PROJECT', ''),
('AAC002409', 'AAC', 'OLMEDO, IAN B.', 'SPECIAL PROJECT', ''),
('AAC002410', 'AAC', 'VILLANUEVA, ARA MAE D.', 'RPP', ''),
('AAC002411', 'AAC', 'ANDRADA, RODEMIE V.', 'RPP', ''),
('AAC002412', 'AAC', 'CHUA, NEMFER LUIS A.', 'OUTSOURCING & GROWERSHIP', ''),
('AAC002413', 'AAC', 'MAGLUYAN, JENNY MAY A.', 'RPP', ''),
('AAC002414', 'AAC', 'CELESTE, RUSSEL JAKE G.', 'TECHNICAL SERVICES', ''),
('AAC002415', 'AAC', 'CASEÑAS, WINDY MAE G.', 'QA & C', ''),
('AAC002416', 'AAC', 'GADIANO, DIANNA KATE S.', 'QA & C', ''),
('AAC002417', 'AAC', 'DUMAYAO, DANICA .', 'SALES & MARKETING', ''),
('AAC002418', 'AAC', 'ALFAFARA, JHON JHON M.', 'ENGINEERING', ''),
('AAC002424', 'AAC', 'SIOCO, FELVIE A.', 'QA & C', ''),
('AAC002425', 'AAC', 'PAGONG, FLORANTE V.', 'INFORMATION TECHNOLOGY (IT)', ''),
('AAC002426', 'AAC', 'QUIAPO, RANNEL M.', 'MATERIALS MANAGEMENT', ''),
('AAC002428', 'AAC', 'HADJIOMAR, SAMSODEN B.', 'TECHNICAL SERVICES', ''),
('AAC002429', 'AAC', 'MAHILUM, SHINAROSE D.', 'QA & C', ''),
('AAC002437', 'AAC', 'MALLARI, RICHARD D.', 'MATERIALS MANAGEMENT', ''),
('AAC002438', 'AAC', 'BLANCO, RAYMUND PAUL F.', 'HUMAN RESOURCE & ADMIN', ''),
('AAC002446', 'AAC', 'DUMABOC, JUN A.', 'RPP', ''),
('AAC002447', 'AAC', 'ANDRINO, ANGIE O.', 'QA & C', ''),
('AAC002456', 'AAC', 'LUMOGDA, SYBIL B.', 'APP', ''),
('AAC002466', 'AAC', 'VILLARIZ, MARJORIE P.', 'QA & C', ''),
('AAC002467', 'AAC', 'GUADALQUIVER, MICHELLE F.', 'QA & C', ''),
('AAC002468', 'AAC', 'YATOT, JETO S.', 'GROW OUT', ''),
('AAC002478', 'AAC', 'BUCALI, MOEL L.', 'QA & C', ''),
('AAC002479', 'AAC', 'SINAJON, KEM RAYAN R.', 'FINANCE', ''),
('AAC002481', 'AAC', 'GONZAGA, REYNALDO P.', 'SALES & MARKETING', ''),
('AAC002482', 'AAC', 'CHEE KEE, JJ L.', 'HUMAN RESOURCE & ADMIN', ''),
('AAC002483', 'AAC', 'BARBA, CEDRICK A.', 'RESEARCH & DEVELOPMENT', ''),
('AAC002484', 'AAC', 'MASALON, AUDREY DYANN P.', 'TECHNICAL SERVICES', ''),
('AAC002485', 'AAC', 'ANGCACO, KAYLE LOU D.', 'GROW OUT', ''),
('AAC002492', 'AAC', 'DERAIN, ERICA JOYCE R.', 'APP', ''),
('AAC002493', 'AAC', 'CACHUELA, SHANELLE M.', 'QA & C', ''),
('AAC002494', 'AAC', 'DORONIO, IRISH JOHN L.', 'MATERIALS MANAGEMENT', ''),
('AAC002496', 'AAC', 'PURISIMA, ROY M.', 'MATERIALS MANAGEMENT', ''),
('AAC002497', 'AAC', 'PLANTIG, JIMUEL B.', 'TECHNICAL SERVICES', ''),
('AAC002500', 'AAC', 'MARTIN, FLORA MAE R.', 'SALES & MARKETING', ''),
('AAC002513', 'AAC', 'BELAIS, LEAH MARIE P.', 'RESEARCH & DEVELOPMENT', ''),
('AAC002516', 'AAC', 'DAGOHOY, BERNADETTE JANE C.', 'MATERIALS MANAGEMENT', ''),
('AAC002517', 'AAC', 'POLISTICO, RUFIBOY C.', 'FINANCE', ''),
('AAC002519', 'AAC', 'GANTE, PENNY C.', 'FINANCE', ''),
('AAC002525', 'AAC', 'SABANDO, JENIDA M.', 'QA & C', ''),
('AAC002526', 'AAC', 'LUMANTA, JAZEL V.', 'MATERIALS MANAGEMENT', ''),
('AAC002527', 'AAC', 'LUCAS, RONAZEL JANE M.', 'MATERIALS MANAGEMENT', ''),
('AAC002528', 'AAC', 'ESCUREL, MYLEEN E.', 'PPP', ''),
('AAC002531', 'AAC', 'ALABA, GINA ROSE D.', 'FINANCE', ''),
('AAC002532', 'AAC', 'DIAZ, ROVIAN MARIE C.', 'SALES & MARKETING', ''),
('AAC002542', 'AAC', 'CEDO, MAY ANNE A.', 'RPP', ''),
('AAC002543', 'AAC', 'FERNANDEZ, KRHYSTAL D.', 'TECHNICAL SERVICES', ''),
('AAC002544', 'AAC', 'TAMPOS, ROLITO S.', 'ENGINEERING', ''),
('AAC002545', 'AAC', 'GADIA, MACKENRAY .', 'FINANCE', ''),
('AAC002546', 'AAC', 'LARIOSA, GUILLERMO P.', 'ENGINEERING', ''),
('AAC002551', 'AAC', 'CUARESMA, KARL VINCENT D.', 'SALES & MARKETING', ''),
('AAC002552', 'AAC', 'SATOR, MARICAR C.', 'SALES & MARKETING', ''),
('AAC002553', 'AAC', 'ROSALES, CLUCHER A.', 'SALES & MARKETING', ''),
('AAC002554', 'AAC', 'ALDAY, MARLON JAMES V.', 'SALES & MARKETING', ''),
('AAC002555', 'AAC', 'PAGADUAN, MARC BRYAN A.', 'FINANCE', ''),
('AAC002556', 'AAC', 'REBLANDO, REINA MAE U.', 'FINANCE', ''),
('AAC002557', 'AAC', 'NASIBOG, APRIL JOY Q.', 'MATERIALS MANAGEMENT', ''),
('AAC002558', 'AAC', 'ZABALA, RUSTOM S.', 'FINANCE', ''),
('AAC002559', 'AAC', 'CANINDO, GREGORY ALLAN P.', 'HUMAN RESOURCE & ADMIN', ''),
('AAC002560', 'AAC', 'CATIPAY, SHIELA B.', 'GROW OUT', ''),
('AAC002563', 'AAC', 'TANDO, SERGIO C.', 'SPECIAL PROJECT', ''),
('AAC002570', 'AAC', 'LLAGUNO, JONELL C.', 'FINANCE', ''),
('AAC002572', 'AAC', 'HAPULAS, ROLANDO C.', 'ENGINEERING', ''),
('AAC002574', 'AAC', 'PARAN, RIZZA A.', 'SALES & MARKETING', ''),
('AAC002584', 'AAC', 'ALDAMAR, VIA MAE P.', 'SPECIAL PROJECT', ''),
('AAC002585', 'AAC', 'DELANTE, JEFFREY B.', 'MATERIALS MANAGEMENT', ''),
('AAC002586', 'AAC', 'YUSO, ALEXIS M.', 'MATERIALS MANAGEMENT', ''),
('AAC002597', 'AAC', 'ELICAN, APRIL MAE B.', 'FINANCE', ''),
('AAC002598', 'AAC', 'LINSANGAN, JAY M.', 'TECHNICAL SERVICES', ''),
('AAC002599', 'AAC', 'CALONIA, MEZIEL W.', 'GROW OUT', ''),
('AAC002601', 'AAC', 'YTAC, JASON S.', 'FINANCE', ''),
('AAC002602', 'AAC', 'LIM, ROSECHELLE T.', 'FINANCE', ''),
('AAC002603', 'AAC', 'ALITRE, RIZALITO B.', 'SUPPLY CHAIN', ''),
('AAC002604', 'AAC', 'VALETE, JIZZY V.', 'TECHNICAL SERVICES', ''),
('AAC002605', 'AAC', 'ESEO, JHONNEL T.', 'FINANCE', ''),
('AAC002606', 'AAC', 'TUMANDAN, VEDGEM .', 'FINANCE', ''),
('AAC002607', 'AAC', 'TRAZO, EDGAR S.', 'APP', ''),
('AAC002608', 'AAC', 'ALABA, ROLDAN M.', 'ENGINEERING', ''),
('AAC002611', 'AAC', 'DIONO, JUDY ANN D.', 'FINANCE', ''),
('AAC002629', 'AAC', 'ESTRADA, LOSXEL L.', 'TECHNICAL SERVICES', ''),
('AAC002630', 'AAC', 'BIGNOTIA, REA MAE V.', 'QA & C', ''),
('AAC002631', 'AAC', 'COMALING, MAR JUN P.', 'GROW OUT', ''),
('AAC002635', 'AAC', 'PALER, DAN RUZEL D.', 'TECHNICAL SERVICES', ''),
('AAC002637', 'AAC', 'BALLON, KEINTH JANN C.', 'SPECIAL PROJECT', ''),
('AAC052003', 'AAC', 'PALOMARES, CHARLES LEO H.', 'INFORMATION TECHNOLOGY (IT)', 'charlesleohermano@gmail.com'),
('ALD000001', 'ALDEV', 'ALIPOON,NEIL QUIRINO', 'BANANA', ''),
('ALD000003', 'ALDEV', 'ARONG JR.,DEMETRIO FERRAREN', 'TECHNICAL SERVICES', ''),
('ALD000006', 'ALDEV', 'SOMBILON,TRACLIO MEJIAS', 'BANANA', ''),
('ALD000007', 'ALDEV', 'EDULOG,ELLAINE MAE SEVIOLA', 'BANANA', ''),
('ALD000008', 'ALDEV', 'ROSARIO,ARNEL CRISTUTA', 'BANANA', ''),
('ALD000020', 'ALDEV', 'SINGIT,EDWIN EYA', 'BANANA', ''),
('ALD000023', 'ALDEV', 'SULIT,SHERYL SINDOL', 'ENGINEERING', ''),
('ALD000026', 'ALDEV', 'APOSTER,KEVIN LUMANTA', 'ENGINEERING', ''),
('ALD000031', 'ALDEV', 'MIGUEL,JULIEBEE PRIMAVERA', 'BANANA', ''),
('ALD000039', 'ALDEV', 'FUENTES ,RENATO   YUMUL', 'AGRI MGT. INFO. SYSTEM (AMIS)', ''),
('ALD000040', 'ALDEV', 'DANIEL,MARVIN  MANTON', 'AGRI MGT. INFO. SYSTEM (AMIS)', ''),
('ALD000046', 'ALDEV', 'ESTORQUE ,ARCHIE  FANTILANO', 'BANANA', ''),
('ALD000048', 'ALDEV', 'GUIROY,ANTHONY BAYO', 'OPERATIONS SERVICES', ''),
('ALD000050', 'ALDEV', 'VEQUILLA,ERICA JEAN DELA CRUZ', 'ENGINEERING', ''),
('ALD000051', 'ALDEV', 'TACADAO,DOROTEO ANTIQUERA', 'ENGINEERING', ''),
('ALD000052', 'ALDEV', 'MAGANA,RODRIGO DOMINGO', 'TECHNICAL SERVICES', ''),
('ARC000001', 'ARC', 'ORTIZ, JERRY BALAT', 'ARC Growout', ''),
('ARC000003', 'ARC', 'ABARIENTO, ANGELA GIRON', 'ARC Growout', ''),
('ARC000004', 'ARC', 'PRABAQUIL, PABLITO AVILA', 'ARC Growout', ''),
('ARC000008', 'ARC', 'ANIEL, JAIME BAROLO', 'ARC Growout', ''),
('ARC000010', 'ARC', 'DESOACEDO JR, TIMOTEO  RECABO', 'ARC Growout', ''),
('ARC000011', 'ARC', 'DAPITON, JOSE SEBASTIAN', 'ARC Growout', ''),
('ARC000012', 'ARC', 'JAYME, ANA LUZ SALAZAR', 'ARC Growout', ''),
('ARC000018', 'ARC', 'REGALADO, CYRUS REGALADO', 'ARC Growout', ''),
('ARC000020', 'ARC', 'POLINAR , ROLAND  VIAJANTE', 'ARC Growout', ''),
('ARC000021', 'ARC', 'MASAYA, ROBBY  JAYARI', 'ARC Growout', ''),
('ARC000022', 'ARC', 'MERIDA, REY  VEQUILLA', 'ARC Growout', ''),
('ARC000023', 'ARC', 'SOSMEÑA, JONAS  CALIXTRO', 'ARC Growout', ''),
('ARC000025', 'ARC', 'GULAY , JOSE LABIANG', 'ARC Growout', ''),
('ARC000026', 'ARC', 'ANZANO, CIPRIANO FONTILO', 'ARC - NURSERY', ''),
('ARC000027', 'ARC', 'DAPITON, BONIFACIO SEBASTIAN', 'ARC - NURSERY', ''),
('ARC000029', 'ARC', 'CABANA, DENNIS LONOY', 'ARC Growout', ''),
('ARC000031', 'ARC', 'FAJARDO, EARLE MOMONGAN', 'ARC Engineering', ''),
('ARC000032', 'ARC', 'CAPALLA, LYZZA COBACHA', 'ARC Growout', ''),
('ARC000033', 'ARC', 'CABANA, GERARDO LONOY', 'ARC Growout', ''),
('ARC000034', 'ARC', 'LUYAO, DIOMEDES GOLISAO', 'ARC Growout', ''),
('ARC000035', 'ARC', 'CAMADO, MAC WILSON CRUZ', 'ARC Growout', ''),
('ARC000036', 'ARC', 'BITOY, JAYSON FABELA', 'ARC Growout', ''),
('ARC000037', 'ARC', 'EPIFANIO, RUBEN MANGAS', 'ARC Growout', ''),
('FH000114', 'FHI', 'CASTILLO, ALEXIS BARAL', 'FHI FryTrading-Visayas', ''),
('FH000115', 'FHI', 'BUCOL, JAKE TRUMATA', 'FHI OM Office', ''),
('FHI000001', 'FHI', 'HIBUNE, JONATHAN OTERO', 'FHI Engineering', ''),
('FHI000002', 'FHI', 'YOUNG, RONALD UY', 'FHI OM Office', ''),
('FHI000004', 'FHI', 'NEBRES, ROMEO FELIX', 'FHI Production', ''),
('FHI000006', 'FHI', 'JUMAWAN, AL MIRAFLOR', 'FHI Production-Shipment', ''),
('FHI000008', 'FHI', 'GALLARDE, JIMMEL EDEROSAS', 'FHI Engineering', ''),
('FHI000013', 'FHI', 'CABADING, ROBERT CASAS', 'FHI Engineering', ''),
('FHI000017', 'FHI', 'CABILADAS, CARLO DUQUEZA', 'FHI Production', ''),
('FHI000018', 'FHI', 'CANO, CECELIO CASEÑAS', 'FHI Engineering', ''),
('FHI000019', 'FHI', 'DAGALA, GODOFREDO IGNACIO', 'FHI Engineering', ''),
('FHI000020', 'FHI', 'RENACIA, RENE DAPIGRAN', 'FHI Production-Natural Food (NF)', ''),
('FHI000021', 'FHI', 'CARMAN, JOSE GALGO', 'FHI Production-Broodstock', ''),
('FHI000023', 'FHI', 'VENUS, AIDA JUMAWAN', 'FHI Production', ''),
('FHI000025', 'FHI', 'FULLANTE, NORBERTO MATAO', 'FHI Production-Larval Rearing (LR)', ''),
('FHI000026', 'FHI', 'LAGARAS, ALLAN MOSQUEDA', 'FHI Production-Natural Food (NF)', ''),
('FHI000027', 'FHI', 'TANTE, ESER MACABOLOS', 'FHI Production-Natural Food (NF)', ''),
('FHI000028', 'FHI', 'MANRIQUE, MANUEL VIDAD', 'FHI Production-Packing House (PH)', ''),
('FHI000032', 'FHI', 'CABATANIA, JIMMY BARASBARAS', 'FHI Production-Broodstock', ''),
('FHI000034', 'FHI', 'CATIG, LEONARD MILLONA', 'FHI Production-Packing House (PH)', ''),
('FHI000035', 'FHI', 'LAGARAS, ALEX MOSQUEDA', 'FHI Production-Broodstock', ''),
('FHI000036', 'FHI', 'VIVA, MARCOS PACLE', 'FHI Production-Packing House (PH)', ''),
('FHI000037', 'FHI', 'VILLAMORA, GODOFREDO SARAJENA', 'FHI Production-Larval Rearing (LR)', ''),
('FHI000040', 'FHI', 'FRANCISCO, BELTRAN HAPINAT', 'FHI Production-Natural Food (NF)', ''),
('FHI000042', 'FHI', 'INGENTE, ELEUTERIO MANO-OD', 'FHI Production-Natural Food (NF)', ''),
('FHI000044', 'FHI', 'SALA, SALVADOR FELISILDA', 'FHI Engineering', ''),
('FHI000047', 'FHI', 'PASAYLO-ON, SANITO DERITCHO', 'FHI Production-Packing House (PH)', ''),
('FHI000052', 'FHI', 'DAYONDON, FELIX TUBURAN', 'FHI Production-Milkfish Larval(MF)', ''),
('FHI000053', 'FHI', 'PALOMA, JEMLANE PETOGO', 'FHI Engineering', ''),
('FHI000055', 'FHI', 'CADORNA, MANUEL RICAFORT', 'FHI Production-Algae', ''),
('FHI000056', 'FHI', 'EMBOLTORIO, ROBERT ESCORIDO', 'FHI Production-Milkfish Larval(MF)', ''),
('FHI000057', 'FHI', 'MANRIQUE, RONALD ARGUELLES', 'FHI Production-Larval Rearing (LR)', ''),
('FHI000059', 'FHI', 'RIAS, ROLANDO ADLAWON', 'FHI Production-Larval Rearing (LR)', ''),
('FHI000061', 'FHI', 'ADAYA, ROLDAN SAGARINO', 'FHI FryTrading-Mindanao', ''),
('FHI000062', 'FHI', 'NUÑEZA, SEGUNDITO LONZAGA', 'FHI FryTrading-Visayas', ''),
('FHI000063', 'FHI', 'BOCAYA, RENATO BONGON', 'SELLING & MARKETING DEPARTMENT', ''),
('FHI000064', 'FHI', 'BONTO, ALAN', 'SELLING & MARKETING DEPARTMENT', ''),
('FHI000065', 'FHI', 'CERVANTES, CRESENCIANO', 'SELLING & MARKETING DEPARTMENT', ''),
('FHI000066', 'FHI', 'CONDE, ARDEE ONRUBIA', 'SELLING & MARKETING DEPARTMENT', ''),
('FHI000067', 'FHI', 'GAA, JOSEPH', 'SELLING & MARKETING DEPARTMENT', ''),
('FHI000070', 'FHI', 'VINCOY, CRESENCIO', 'SELLING & MARKETING DEPARTMENT', ''),
('FHI000072', 'FHI', 'CANCINO, CHRYSTAL', 'SELLING & MARKETING DEPARTMENT', ''),
('FHI000073', 'FHI', 'BARTOLOME, JASON', 'SELLING & MARKETING DEPARTMENT', ''),
('FHI000074', 'FHI', 'BALLERA, RIO SUMAGAYSAY', 'FHI Production', ''),
('FHI000075', 'FHI', 'KOMALING, MIGUEL LAUDA', 'FHI Production-Shipment', ''),
('FHI000076', 'FHI', 'TRAYA, NOLITO GETIGAN', 'FHI Production-Harvest', ''),
('FHI000077', 'FHI', 'MAGBANUA, ROSALINA AGUIRRE', 'FHI FryTrading-Visayas', ''),
('FHI000079', 'FHI', 'RAMOS, RODEL DAYONDON', 'FHI Production-Broodstock', ''),
('FHI000080', 'FHI', 'FERNANDEZ, MARK CORTES', 'FHI Production-Broodstock', ''),
('FHI000081', 'FHI', 'APITONG, RHOEL DIGNADICE', 'FHI FryTrading-Visayas', ''),
('FHI000082', 'FHI', 'PENDON, JUNE BELORIA', 'FHI FryTrading-Mindanao', ''),
('FHI000083', 'FHI', 'RUFINO, ROMULO SICAD', 'FHI FryTrading-Mindanao', ''),
('FHI000084', 'FHI', 'FRANCISCO, REX TIANSON', 'FHI Engineering', ''),
('FHI000086', 'FHI', 'VILLARIAS, SAMUEL PATENTE', 'FHI FryTrading-Mindanao', ''),
('FHI000088', 'FHI', 'PAG-ONG, JOMAR DAYONDON', 'FHI Production-Broodstock', ''),
('FHI000090', 'FHI', 'ABENDAN, AZUR ALFERES', 'FHI Production-Algae', ''),
('FHI000092', 'FHI', 'BARANGAN, CHERRY MAE SENSANO', 'FHI Production-Algae', ''),
('FHI000094', 'FHI', 'MAGHANOY, LAWRENCE', 'SELLING & MARKETING DEPARTMENT', ''),
('FHI000095', 'FHI', 'RAMIREZ, FERNANDO', 'SELLING & MARKETING DEPARTMENT', ''),
('FHI000096', 'FHI', 'AMANTE, FRITZ DEMOCRITO', 'FHI FryTrading-Visayas', ''),
('FHI000098', 'FHI', 'CABASE, ARNEL FRANCO', 'FHI Production-Broodstock', ''),
('FHI000099', 'FHI', 'VICENTE, KENNY DASON', 'FHI Production-Milkfish Larval(MF)', ''),
('FHI000105', 'FHI', 'ESCUDO, MARK ANTHONY CASTILLO', 'FHI Production-Broodstock', ''),
('FHI000107', 'FHI', 'BARANGAN, LLOYD CANO', 'FHI Production-Broodstock', ''),
('FHI000110', 'FHI', 'CADORNA, JINKY SEBILO', 'FHI Production-Algae', ''),
('FHI000116', 'FHI', 'LUCAS, MARLOU CARIAGA', 'FHI FryTrading-Mindanao', ''),
('FHI000118', 'FHI', 'COLANO, MELBA YSON', 'FHI Production-Packing House (PH)', ''),
('FHI000120', 'FHI', 'DEL MONTE, ROSEMARIE JANE CASUPANG', 'FHI Production-Algae', ''),
('FHI000121', 'FHI', 'BANCAERIN, REYNANTE SABAN', 'FHI Production-Natural Food (NF)', ''),
('FHI000122', 'FHI', 'HIBUNE, ALVIN OTERO', 'FHI Production-Natural Food (NF)', ''),
('FHI000123', 'FHI', 'PLAZA, EDGARDO ALBAO', 'FHI Production-Natural Food (NF)', ''),
('FHI000124', 'FHI', 'TANOY, BENJIE LARIOSA', 'FHI Production-Natural Food (NF)', ''),
('FHI000125', 'FHI', 'DOCE, IVAN BESA', 'FHI Engineering', ''),
('FHI000126', 'FHI', 'ESPIRITUOSO, JOWIE ASUNCION', 'FHI Production-Packing House (PH)', ''),
('FHI000127', 'FHI', 'ALDEMITA, GENELYN CUDOG', 'FHI Production-Packing House (PH)', ''),
('FHI000128', 'FHI', 'TICAO, ISIDORA PALOMA', 'FHI OM Office', ''),
('FHI000129', 'FHI', 'BANCAERIN, BERNIE SABAN', 'FHI Production-Larval Rearing (LR)', ''),
('FHI000130', 'FHI', 'VELASCO, RAY MIANO', 'FHI Production-Broodstock', ''),
('FHI000131', 'FHI', 'FUENTEVILLA, JEROME MARCIAL', 'FHI Production-Larval Rearing (LR)', ''),
('FHI000132', 'FHI', 'TAMPUS, ADONIS BOHOLTS', 'FHI Production-Larval Rearing (LR)', ''),
('FHI000133', 'FHI', 'HIBUNE, MERVIN OTERO', 'FHI Production-Larval Rearing (LR)', ''),
('FHI000135', 'FHI', 'PULVERA, ALFREDO BENZAL', 'FHI Production-Fingerling Rearing (FR)', ''),
('FHI000136', 'FHI', 'COLANO, ALEX ARSOLA', 'FHI Production-Fingerling Rearing (FR)', ''),
('FHI000138', 'FHI', 'PIOQUINTO, HEZEL ALTAMARINO', 'FHI Production-Broodstock', ''),
('FHI000140', 'FHI', 'ALESNA, JIMMY VELARDE', 'FHI Engineering', ''),
('FHI000141', 'FHI', 'JUGARAP, DENNIS ABRIO', 'FHI Engineering', ''),
('FHI000142', 'FHI', 'CUDOG, ERNESTO GALANAGA', 'FHI Engineering', ''),
('FHI000143', 'FHI', 'SABANATE, ROLAND CARDENTE', 'FHI Engineering', ''),
('FHI000144', 'FHI', 'ALVERO, EMMANUEL REYES', 'FHI Engineering', ''),
('FHI000145', 'FHI', 'CABALO, JUN MORSAL', 'FHI Engineering', ''),
('FHI000146', 'FHI', 'ARSULA, LUIS CUARESMA', 'FHI Production-Broodstock', ''),
('FHI000147', 'FHI', 'MIOLE, JOEFREY CONSAD', 'FHI Engineering', ''),
('FHI000148', 'FHI', 'FRANCISCO, CROSALDO ZONIO', 'FHI Engineering', ''),
('FHI000149', 'FHI', 'YONSON, ROGELIO CORDOVA', 'FHI Engineering', ''),
('FHI000150', 'FHI', 'RODRIGUEZ, GILBERT UBAS', 'FHI Engineering', ''),
('FHI000151', 'FHI', 'VELASCO, ARWIN MIANO', 'FHI Engineering', ''),
('FHI000152', 'FHI', 'MIRAFUENTES, RODEL  CANETE', 'FHI Engineering', ''),
('FHI000153', 'FHI', 'EGUINTO, JEFFREY ONDONG', 'FHI Engineering', ''),
('FHI000154', 'FHI', 'MORENO, JERALD INTONG', 'FHI Engineering', ''),
('FHI000155', 'FHI', 'SIRLANA, JULIE CUIZON', 'FHI Engineering', ''),
('FHI000156', 'FHI', 'GUNDAY, HENRY PETOGO', 'FHI Engineering', ''),
('FHI000157', 'FHI', 'DELA PAZ, DAVID  MORENO', 'FHI Engineering', ''),
('FHI000158', 'FHI', 'ABENDAN, ANDREO  ALFEREZ', 'FHI Production-Larval Rearing (LR)', ''),
('FHI000159', 'FHI', 'BELINARIO, VINCENT  DAQUERA', 'FHI Production-Broodstock', ''),
('FHI000162', 'FHI', 'LARA, HOWELL BANDOJO', 'FHI Production-Broodstock', ''),
('FHI000163', 'FHI', 'BELLUGA , RUSSELL BOBB TORLAO', 'FHI Production-Broodstock', ''),
('FHI000164', 'FHI', 'ANGELES, KEVIN  HIBUNE', 'FHI Engineering', ''),
('FHI000165', 'FHI', 'WATIN, JHONRYL SAYLOON', 'FHI Production-Broodstock', ''),
('FHI000166', 'FHI', 'VIVA, MARK JAYSON MESAGRANDE', 'FHI Production-Algae', ''),
('FHI000167', 'FHI', 'NEBRES, PETER GIOVANNI OCONG', 'FHI Production-Algae', ''),
('FHI000168', 'FHI', 'JUMAWAN, KIT JAMES DAPAR', 'FHI Production-Packing House (PH)', ''),
('FHI000169', 'FHI', 'ENDRINA, ANGELO ARGOMEDO', 'FHI Production-Packing House (PH)', ''),
('FHI000171', 'FHI', 'ASUTILLA, ALBERTO SISTONA', 'FHI-Warehouse', ''),
('FHI000172', 'FHI', 'PEÑAFIEL, JOVENEL SEBASTIAN', 'FHI Production-Milkfish Larval(MF)', ''),
('FHI000174', 'FHI', 'PULVERA, JONRICK BENZAL', 'FHI Production-Broodstock', ''),
('FHI000176', 'FHI', 'JUNIO, MARK KEVIN SITCHON', 'FHI Production-Milkfish Larval(MF)', ''),
('FHI000177', 'FHI', 'SECRETARIA, PRIMO LAPUT', 'FHI Production-Natural Food (NF)', ''),
('FHI000178', 'FHI', 'ANDOYO JR, DANILO', 'FHI Production-Natural Food (NF)', ''),
('FHI000179', 'FHI', 'EMBIADO , ELMA JUMAWAN', 'FHI OM Office', ''),
('FHI000182', 'FHI', 'DELA CRUZ, IRHLY GERARMAN', 'FHI FryTrading-Visayas', ''),
('FHI000184', 'FHI', 'TACRAS, RICKY  BETITA', 'FHI Production-Broodstock', ''),
('FHI000185', 'FHI', 'PIODOS, JAY AR ALCANSADO', 'FHI Production-Broodstock', ''),
('FHI000186', 'FHI', 'CATIPAY, FAMIE VILLAHERMOSA', 'FHI Production-Broodstock', ''),
('FHI000187', 'FHI', 'ARANETA, JAIRUS LAGALCAN', 'FHI Production-Algae', ''),
('FHI000188', 'FHI', 'ABDULRADZAK, ABDURAKMAN DESAPOR', 'FHI Production-Algae', ''),
('FHI000189', 'FHI', 'PAELDIN, GELMAR MARAVILES', 'FHI Production-Packing House (PH)', ''),
('FHI000191', 'FHI', 'SORIANO, APRIL JHON GUTIERREZ', 'FHI Production-Natural Food (NF)', ''),
('FHI000193', 'FHI', 'ENCARNACION, ARCHEL MONTECILLO', 'FHI Production', ''),
('FHI000195', 'FHI', 'TAGALOG, FRANCISCO LANTUELE', 'FHI Production-Algae', ''),
('FHI000199', 'FHI', 'BENDULO, JONETTE CABALISA', 'FHI Production', ''),
('FHI000200', 'FHI', 'MEJIA, FLORAMIE RANES', 'FHI FryTrading-Mindanao', ''),
('FHI000201', 'FHI', 'REMANDO, JEFREY OREDA', 'FHI Engineering', ''),
('FHI000202', 'FHI', 'ARAMBALA, RUFO HIBAYA', 'FHI Production-Natural Food (NF)', ''),
('FHI000203', 'FHI', 'REQUILME, ARTURO CARACUT', 'FHI Production-Natural Food (NF)', ''),
('FHI000204', 'FHI', 'LOPEZ, ROY OCAYA', 'FHI Production-Natural Food (NF)', ''),
('FHI000207', 'FHI', 'NAVARRO, JERALD VILLARIN', 'FHI FryTrading-Visayas', ''),
('FHI000209', 'FHI', 'COROMPIDO, KENNETH BULANON', 'FHI FryTrading-Mindanao', ''),
('FHI000210', 'FHI', 'CUDOG , ERWIN GALANAGA', 'FHI FryTrading-Mindanao', ''),
('FHI000211', 'FHI', 'VERGARA, CHARLIE ASCURA', 'FHI FryTrading-Visayas', '');
INSERT INTO `employees` (`employee_id`, `company`, `employee_name`, `department`, `employee_email`) VALUES
('FHI000212', 'FHI', 'LASTIMOSO, JONARD ANDRADE', 'FHI-Warehouse', ''),
('FHI000213', 'FHI', 'UNAJAN, JOHNY FUENTEBELLA', 'FHI Production-Milkfish Larval(MF)', ''),
('FHI000214', 'FHI', 'ROCA, REYMOND RENACIA', 'FHI Production-Milkfish Larval(MF)', ''),
('FHI000215', 'FHI', 'VALDEZ, JIBRIL PAULO NASSER', 'FHI Production-Broodstock', ''),
('FHI000216', 'FHI', 'SARONITMAN, BUN STEPHEN LAO-LAO', 'FHI Production-Broodstock', ''),
('FHI000217', 'FHI', 'NARDO, DONARD ARSULA', 'FHI Production-Milkfish Larval(MF)', ''),
('FHI000218', 'FHI', 'TAPADO, GILDRED LUMAPAK', 'FHI Production-Algae', ''),
('FHI000219', 'FHI', 'NEBRES, MARLON POLINAR', 'FHI Engineering', ''),
('FHI000220', 'FHI', 'VIVA, REY ANTHONY MESAGRANDE', 'FHI Engineering', ''),
('FHI000221', 'FHI', 'PALOMA, ROMEL FABROS', 'FHI Engineering', ''),
('FHI000222', 'FHI', 'ARANETA, MARK JOY LAGALCAN', 'FHI Engineering', ''),
('FHI000223', 'FHI', 'ADOL, JIMMY ALA', 'FHI Production-Milkfish Larval(MF)', ''),
('FHI000224', 'FHI', 'DELA CRUZ, RUBEN', 'FHI Engineering', ''),
('FHI000225', 'FHI', 'NARDO, LEONARD MANCAO', 'FHI Production-Milkfish Larval(MF)', ''),
('FHI000226', 'FHI', 'GANTALAO, JOHN DAVE TACULOD', 'FHI Production-Packing House (PH)', ''),
('FHI000227', 'FHI', 'DEQUINA, JULIUS CAESAR CAGADAS', 'FHI Production-Packing House (PH)', ''),
('FHI000228', 'FHI', 'VELASCO, RONALD  MIANO', 'FHI Engineering', ''),
('SAC000032', 'SACI', 'FABROA, EDUMAR ECHAVEZ', 'OFFICE OF AVP', ''),
('SAV000002', 'ALDEV', 'DAMOLE,NELDRIN VILLANUEVA', 'BANANA', ''),
('SAV000004', 'SAVI', 'GECOSALA,ENRIQUE CASTILLON', 'BANANA MARIBULAN', ''),
('SAV000005', 'SAVI', 'PAGAY,ROLANDO TORRES', 'BANANA MARIBULAN', ''),
('SAV000006', 'SAVI', 'RODRIGO,RONNIE TOLENTINO', 'BANANA MARIBULAN', ''),
('SAV000008', 'SAVI', 'AYSON,JANINE PALCONITE', 'OPERATIONS SERVICES', ''),
('SAV000009', 'SAVI', 'BOCA,ETHEL JOY BAGUIO', 'OPERATIONS SERVICES', ''),
('SAV000011', 'SAVI', 'AMORES,ANTONIO SALVADOR ROMAN CHAVES', 'BANANA MARIBULAN', ''),
('SAV000017', 'SAVI', 'CURAZA,ELIEZER NUEVO', 'ENGINEERING', ''),
('SAV000027', 'SAVI', 'GUANSING,JULIUS JOHN FERENAL', 'BANANA MARIBULAN', ''),
('SAV000031', 'SAVI', 'BUNZO,ELIZABETH GELLEGAN', 'OPERATIONS SERVICES', ''),
('SAV000032', 'SAVI', 'LAO,MAE PAGARIGAN', 'TSD', ''),
('SAV000035', 'SAVI', 'CAÑETE,PRINCESS SARAH YORPO', 'BANANA MARIBULAN', ''),
('SAV000039', 'SAVI', 'BAQUEQUE,NARCISO TRISTE', 'TSD', ''),
('SAV000040', 'SAVI', 'VILLARUEL,JAY BASUGA', 'OPERATIONS SERVICES', ''),
('SAV000041', 'SAVI', 'MORANO,JEAN DOLUTAN', 'OPERATIONS SERVICES', ''),
('SAV000043', 'SAVI', 'BALORAN,ETHYL JOY TAER', 'OPERATIONS SERVICES', ''),
('SAV000044', 'SAVI', 'LACIERDA,JAMES ALDERITE', 'OPERATIONS SERVICES', ''),
('SAV000046', 'SAVI', 'LAO,LAWTON BONDAD', 'BANANA MARIBULAN', ''),
('SAV000047', 'SAVI', 'ORTIZ,JOEMARIE PADILLA', 'OPERATIONS SERVICES', ''),
('SAV000048', 'SAVI', 'CUTILLAR,CHRISTOPHER VILLAMORA', 'BANANA LEAVES', ''),
('SAV000049', 'SAVI', 'HIBUNE,LLOYD  OTERO', 'BANANA MARIBULAN', ''),
('SAV000052', 'SAVI', 'MONTERO,RALITO  MIRAFLOR', 'ENGINEERING', ''),
('SAV000053', 'SAVI', 'BASIO,NEILYN RHEA  MOLINA', 'G&A', ''),
('SAV000055', 'SAVI', 'SUTAN,LITO MOHONG', 'BANANA MARIBULAN', ''),
('SAV000104', 'SAVI', 'DELOS REYES,VANESSA DALISAY', 'BANANA MARIBULAN', ''),
('SAV000105', 'SAVI', 'SOLIS,KCLYN', 'TSD', ''),
('SAV000108', 'SAVI', 'RELLON,ROLANDO CHAVEZ', 'ENGINEERING', ''),
('SAV000109', 'SAVI', 'CORONADO,KIRTH JOHNDY TEMPROSA', 'G&A', ''),
('SAV000111', 'SAVI', 'EMPINADO,MELBERT PEÑARANDA', 'OPERATIONS SERVICES', ''),
('SAV000112', 'SAVI', 'JAMORE,JENNIFER ABOY', 'OPERATIONS SERVICES', ''),
('SAV000115', 'SAVI', 'BUDAY,ELOISA LYN NACION', 'BANANA MARIBULAN', ''),
('SAV000116', 'SAVI', 'ALINDAHAO,MICHELLE YUSORES', 'OPERATIONS SERVICES', ''),
('SAV000117', 'SAVI', 'OLAVER,RICHARD CAFE', 'ENGINEERING', ''),
('SAV000120', 'SAVI', 'VILLANUEVA,KEANNA PEARL QUIÑON', 'OPERATIONS SERVICES', ''),
('SCC000003', 'SCCI', 'DE LOS REYES,IAN MARK PARACUELLES', 'ENGINEERING', ''),
('SCC000006', 'SAVI', 'BINGHOY,CRYSTABEL JANE DIALINO', 'BANANA MARIBULAN', ''),
('SCC000012', 'SCCI', 'PARAGOSO,ERNESTO FELOVIDA', 'BANANA LANTON', ''),
('SCC000016', 'SCCI', 'NAQUILA,HERMILO MONTIPOLCA', 'BANANA LANTON', ''),
('SCC000017', 'SCCI', 'ANDO,ROBILLO LUMANSOC', 'BANANA LANTON', ''),
('SCC000018', 'SCCI', 'BULATITE,ARIEL BULLANDAY', 'BANANA LANTON', ''),
('SCC000021', 'SCCI', 'ROMEO,SHIELA MAE YBANEZ', 'TECHNICAL SERVICES', ''),
('SCC000022', 'SCCI', 'AGUANTA,ROGELIO JULIANE', 'CATTLE', ''),
('SCC000023', 'SCCI', 'DUASO,DINDO CANINDO', 'CATTLE', ''),
('SCC000025', 'SCCI', 'MALANG,ROLANDO RODRIGO', 'CATTLE', ''),
('SCC000029', 'SCCI', 'SEDONIO,MITCHE HERMOSO', 'BANANA LANTON', ''),
('SCC000030', 'SCCI', 'RELEVANTE,JUNIOR ABALLE', 'CATTLE', ''),
('SCC000032', 'SCCI', 'ANTIPORDA,ARNOLD LICANTO', 'CATTLE', ''),
('SCC000033', 'SCCI', 'LIMBA,ZALDY SAPAL', 'CATTLE', ''),
('SCC000034', 'SCCI', 'DAYADAY,WINDELYN LUNZON', 'BANANA LANTON', ''),
('SCC000036', 'SCCI', 'VERA,WILFRED IGLESIAS', 'BANANA LANTON', ''),
('SCC000039', 'SCCI', 'MENDOZA,DALLAS  ESPAÑOLA', 'PINEAPPLE', ''),
('SCC000047', 'SCCI', 'ARIZA,LENERIO AGUDA', 'PINEAPPLE', ''),
('SCC000048', 'SCCI', 'BELTRAN,CRISPIN LAPASANDA', 'CATTLE', ''),
('SCC000057', 'SCCI', 'ELPIDANG,JOSEPH MELMIDA', 'PINEAPPLE', ''),
('SCC000061', 'SCCI', 'SAMELIN,JAKE DEN SUMERA', 'BANANA LANTON', ''),
('SCC000065', 'SCCI', 'FUENTES,REY YUMOL', 'CATTLE', ''),
('SCC000066', 'SCCI', 'PALTI,ABEL LAGUILAYAN', 'BANANA LANTON', ''),
('SCC000068', 'SCCI', 'FLORENCONDIA,BERNARD CALBARIO', 'CATTLE', ''),
('SCC000070', 'SCCI', 'LOAYAN,MENCHO MASECAMPO', 'TECHNICAL SERVICES', ''),
('SCC000073', 'SCCI', 'TULA,REAH AMOR LACO', 'PINEAPPLE', ''),
('SCC000076', 'SCCI', 'ASAREZ,VICTORINO LECHEDO', 'PINEAPPLE', ''),
('SCC000079', 'SCCI', 'EMBODO,JIMBOY ROTULA', 'CATTLE', ''),
('SCC000081', 'SCCI', 'HECHANOVA,ANGELOU SAMILLANO', 'PINEAPPLE', ''),
('SCC000086', 'SCCI', 'TALACAY,LUIS WADAG', 'CATTLE', ''),
('SCC000087', 'SCCI', 'CASTRO,JOSE RONNIE GAELAN', 'BANANA LANTON', ''),
('SCC000089', 'SCCI', 'SIERA,FABIANO RAMIREZ', 'ENGINEERING', ''),
('SCC000097', 'SCCI', 'FUENTES,MELVIN CEBALLOS', 'CATTLE', ''),
('SCC000099', 'SCCI', 'TABACON,NOEL AVILLA', 'PINEAPPLE', ''),
('SCC000100', 'SCCI', 'AUTIDA,MICHAEL TAMPOY', 'OPERATIONS SERVICES', ''),
('SCC000101', 'SCCI', 'VILLASANTE,JADE TANALEON', 'OPERATIONS SERVICES', ''),
('SCC000102', 'SCCI', 'CUYA,ANNA MAE KUDARAT', 'OPERATIONS SERVICES', ''),
('SCC000103', 'SCCI', 'PICO,CHRISTIAN ALOLOR', 'ENGINEERING', ''),
('SCC000104', 'SCCI', 'BORNEA,EMCOR BAGAPURO', 'TECHNICAL SERVICES', ''),
('SCC000105', 'SCCI', 'TORRES,BRENDA LYN  ARINAS', 'ENGINEERING', ''),
('SCC000106', 'SCCI', 'DAAN,CERILLES DELA TORRE', 'ENGINEERING', ''),
('SCC000107', 'SCCI', 'LOZADA,JORGIE TAHUDAN', 'PINEAPPLE', ''),
('SCC000108', 'SCCI', 'CAYANONG,HAZEL JOY BOLADAS', 'OPERATIONS SERVICES', ''),
('SCC000109', 'SCCI', 'ARMADA,CHARLENE MARIANO', 'TECHNICAL SERVICES', ''),
('SCC000110', 'SCCI', 'MIRAVELES, RONNIE', 'TECHNICAL SERVICES', ''),
('SFC000001', 'SFC', 'ANTIPALA, NORMAN ABASOLO', 'PINEAPPLE', ''),
('SFC000002', 'SFC', 'ANTA, MELVIN RUIZ', 'PINEAPPLE', ''),
('SFC000004', 'SFC', 'CARPENTERO, FELVIN GAMBA', 'PINEAPPLE', ''),
('SFC000006', 'SFC', 'ERA, JENIVE JOY ARANAIZ', 'PINEAPPLE', ''),
('SFC000007', 'SFC', 'ERA, ARNOLD  SAJOL', 'PINEAPPLE', ''),
('SFC000008', 'SFC', 'PACINO, LOREN CONEJAR', 'PINEAPPLE', ''),
('SFC000010', 'SFC', 'TUMANDA, RICHARD  NORO', 'PINEAPPLE', ''),
('SFC000011', 'SFC', 'VILLAROYA, JOHNSON ENRIQUEZ', 'PINEAPPLE', ''),
('SFC000012', 'SFC', 'MOLINA , LOVELY  MAGLUYAN', 'PINEAPPLE', ''),
('SFC000014', 'SFC', 'CORDERO, MICHAEL LAYUGAN', 'PINEAPPLE', ''),
('SFC000017', 'SFC', 'SALUDAR, ELIAZAR GAWAT', 'PINEAPPLE', ''),
('SFC000018', 'SFC', 'FAJARDO, MARK AINE GEIL HIGAN', 'PINEAPPLE', ''),
('SFC000019', 'SFC', 'GALLARDO, JAYSON UYANGUREN', 'PINEAPPLE', ''),
('SFC000020', 'SFC', 'ESTORQUE, NIKKI CASIMERO', 'PINEAPPLE', ''),
('SFC000021', 'SFC', 'EDUAVE, ALLAN CABAÑEROS', 'PINEAPPLE', ''),
('SFC000022', 'SFC', 'BANTACULO, EDEM CADAY', 'PINEAPPLE', ''),
('SFC000023', 'SFC', 'ABAJA, BERNARD OCONG', 'PINEAPPLE', ''),
('SFC000024', 'SFC', 'GESIM, JANICE BORJA', 'PINEAPPLE', '');

-- --------------------------------------------------------

--
-- Table structure for table `employees_archive`
--

CREATE TABLE `employees_archive` (
  `archive_id` int(11) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `company` varchar(10) NOT NULL,
  `employee_name` varchar(255) NOT NULL,
  `department` varchar(255) NOT NULL,
  `employee_email` varchar(255) NOT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived_by` int(11) DEFAULT NULL,
  `archive_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees_archive`
--

INSERT INTO `employees_archive` (`archive_id`, `employee_id`, `company`, `employee_name`, `department`, `employee_email`, `archived_at`, `archived_by`, `archive_reason`) VALUES
(18, 'AAC000999', 'AAC', 'Jess Vitualla', 'INFORMATION TECHNOLOGY (IT)', 'jessvitualla@gmail.com', '2025-05-08 05:36:07', 3, 'qwe'),
(21, 'TEST123', 'AAC', 'TEST TEST1', 'INFORMATION TECHNOLOGY (IT)', 'test@gmail.com', '2025-05-08 05:44:52', 3, 'qwe'),
(22, 'AAC000000', 'AAC', 'TESTING', 'AGRI MGT. INFO. SYSTEM (AMIS)', 'TESTING@GMAIL.COM', '2025-05-08 06:32:27', 3, 'QWE');

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `report_id` int(11) NOT NULL,
  `request_id` int(11) DEFAULT NULL,
  `report_type` varchar(50) NOT NULL,
  `content` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `requests`
--

CREATE TABLE `requests` (
  `request_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','technical_support','process_owner','superior','requestor') NOT NULL DEFAULT 'requestor',
  `employee_id` varchar(20) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `employee_id`, `full_name`, `email`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NULL, 'System Administrator', 'admin@example.com', '2025-05-18 04:12:32', '2025-05-18 04:12:32'),
(2, 'tech_support', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'technical_support', NULL, 'Technical Support User', 'tech@example.com', '2025-05-18 04:12:32', '2025-05-18 04:12:32'),
(3, 'process_owner', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'process_owner', NULL, 'Process Owner User', 'process@example.com', '2025-05-18 04:12:32', '2025-05-18 04:12:32'),
(4, 'superior', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superior', NULL, 'Superior User', 'superior@example.com', '2025-05-18 04:12:32', '2025-05-18 04:12:32'),
(5, 'AAC052003', '$2y$10$oMj5zC9F5O/g53YGNLDSyeldKYLwM.uYIY02LxpL.RtecixUr7wn.', 'requestor', 'AAC052003', 'PALOMARES, CHARLES LEO H.', 'charlesleohermano@gmail.com', '2025-05-18 04:13:58', '2025-05-18 04:13:58');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `access_requests`
--
ALTER TABLE `access_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `status` (`status`),
  ADD KEY `reviewed_by` (`reviewed_by`);

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `approval_history`
--
ALTER TABLE `approval_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`employee_id`);

--
-- Indexes for table `employees_archive`
--
ALTER TABLE `employees_archive`
  ADD PRIMARY KEY (`archive_id`),
  ADD KEY `archived_by` (`archived_by`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `idx_reports_request` (`request_id`);

--
-- Indexes for table `requests`
--
ALTER TABLE `requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `idx_requests_status` (`status`),
  ADD KEY `idx_requests_user` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `employee_id` (`employee_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `access_requests`
--
ALTER TABLE `access_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `approval_history`
--
ALTER TABLE `approval_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `employees_archive`
--
ALTER TABLE `employees_archive`
  MODIFY `archive_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `requests`
--
ALTER TABLE `requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `approval_history`
--
ALTER TABLE `approval_history`
  ADD CONSTRAINT `approval_history_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`);

--
-- Constraints for table `employees_archive`
--
ALTER TABLE `employees_archive`
  ADD CONSTRAINT `employees_archive_ibfk_1` FOREIGN KEY (`archived_by`) REFERENCES `admin_users` (`id`);

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`request_id`);

--
-- Constraints for table `requests`
--
ALTER TABLE `requests`
  ADD CONSTRAINT `requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `admin_users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
