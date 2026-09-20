<?php
require_once 'c:/xampp/htdocs/legislative/CEPFMS/config/database.php';
$pdo = db();

echo "Starting CEPFMS 6-Module Core Schema Upgrade..." . PHP_EOL;

function addColumnIfNotExists($pdo, $table, $column, $definition) {
    $cols = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$column'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        echo "Added column $table.$column" . PHP_EOL;
    } else {
        echo "Column $table.$column already exists" . PHP_EOL;
    }
}

// 1. Feedback multi-dimensional CSAT
addColumnIfNotExists($pdo, 'cef_feedback_submissions', 'rating_speed', 'TINYINT NULL DEFAULT 5');
addColumnIfNotExists($pdo, 'cef_feedback_submissions', 'rating_courtesy', 'TINYINT NULL DEFAULT 5');
addColumnIfNotExists($pdo, 'cef_feedback_submissions', 'rating_facility', 'TINYINT NULL DEFAULT 5');
addColumnIfNotExists($pdo, 'cef_feedback_submissions', 'rating_process', 'TINYINT NULL DEFAULT 5');
addColumnIfNotExists($pdo, 'cef_feedback_submissions', 'upvote_count', 'INT NOT NULL DEFAULT 0');

// 2. Proposals feasibility & legislative conversion
addColumnIfNotExists($pdo, 'cef_proposals', 'legal_mandate_status', 'VARCHAR(50) NOT NULL DEFAULT "Compliant (LGC Sec. 16)"');
addColumnIfNotExists($pdo, 'cef_proposals', 'budget_impact', 'VARCHAR(50) NOT NULL DEFAULT "Low / Standard Allocation"');
addColumnIfNotExists($pdo, 'cef_proposals', 'impact_rating', 'TINYINT NOT NULL DEFAULT 4');
addColumnIfNotExists($pdo, 'cef_proposals', 'endorsement_count', 'INT NOT NULL DEFAULT 12');
addColumnIfNotExists($pdo, 'cef_proposals', 'converted_legislative_item_id', 'INT NULL DEFAULT NULL');

// 3. Complaints ARTA & Before/After Proof
addColumnIfNotExists($pdo, 'cef_complaints', 'arta_tier', 'VARCHAR(50) NOT NULL DEFAULT "Simple (3 Days)"');
addColumnIfNotExists($pdo, 'cef_complaints', 'before_photo_path', 'VARCHAR(255) NULL DEFAULT NULL');
addColumnIfNotExists($pdo, 'cef_complaints', 'after_photo_path', 'VARCHAR(255) NULL DEFAULT NULL');
addColumnIfNotExists($pdo, 'cef_complaints', 'latitude', 'DECIMAL(10,8) NULL DEFAULT 14.599512');
addColumnIfNotExists($pdo, 'cef_complaints', 'longitude', 'DECIMAL(11,8) NULL DEFAULT 120.984222');

// 4. Submissions Master-Child Merge
addColumnIfNotExists($pdo, 'cef_submissions', 'master_submission_id', 'BIGINT NULL DEFAULT NULL');

// 5. Responses Sign-Off Matrix
addColumnIfNotExists($pdo, 'cef_responses', 'signoff_role', 'VARCHAR(60) NULL DEFAULT NULL');
addColumnIfNotExists($pdo, 'cef_responses', 'signoff_notes', 'TEXT NULL DEFAULT NULL');
addColumnIfNotExists($pdo, 'cef_responses', 'is_signed_off', 'TINYINT(1) NOT NULL DEFAULT 0');

// 6. Canned Templates Table
$pdo->exec("CREATE TABLE IF NOT EXISTS `cef_canned_templates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `template_type` VARCHAR(40) NOT NULL DEFAULT 'Response',
    `category` VARCHAR(80) NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `content` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

echo "Canned templates table verified." . PHP_EOL;

// Seed initial canned templates if empty
$cnt = (int)$pdo->query("SELECT COUNT(*) FROM `cef_canned_templates`")->fetchColumn();
if ($cnt === 0) {
    $stm = $pdo->prepare("INSERT INTO `cef_canned_templates` (`template_type`, `category`, `title`, `content`) VALUES (?, ?, ?, ?)");
    $stm->execute([
        'Response',
        'Traffic & Parking',
        'MTPB Field Inspection & Enforcement Notice',
        "Magandang araw po. Nakarating na po sa tanggapan ng Manila Traffic and Parking Bureau (MTPB) ang inyong sumbong ukol sa ilegal na paradahan at daloy ng trapiko sa inyong lugar. Nagpadala na po ang aming Traffic Operations Division ng clearing team upang inspeksyunin at i-enforce ang umiiral na City Ordinance. Maraming salamat po sa inyong pakikipagtulungan para sa kaayusan ng Lungsod ng Maynila."
    ]);
    $stm->execute([
        'Response',
        'Waste Management',
        'DPS Garbage Collection Routing Adjustment',
        "Magandang araw. Ipinarating na po ng Pamahalaang Lungsod ng Maynila sa Department of Public Services (DPS) at sa ating accredited waste collection service provider ang inyong ulat ukol sa iskedyul ng pangongolekta ng basura. Na-adjust na po ang routing ng dump truck sa inyong barangay. Huwag mag-atubiling mag-ulat muli kung may pagkaantala. Salamat po."
    ]);
    $stm->execute([
        'Response',
        'City Engineering & Drainage',
        'City Engineering De-clogging & Maintenance Schedule',
        "Magandang araw. Ang inyong ulat ukol sa baradong drainage at pagbaha ay pormal nang naisama sa maintenance work order ng Manila City Engineering Department. Nagsimula na po ang clearing at desilting operation sa nasabing kalye. Patuloy po naming aayusin ang mga daluyan ng tubig upang maiwasan ang perwisyo sa komunidad."
    ]);
    $stm->execute([
        'Clarification',
        'General Moderation',
        'Request for Exact Landmark and Photo Evidence',
        "Magandang araw po. Upang mas mabilis na maaksyunan ng aming field inspectors ang inyong idinulog, maaari po bang magbigay ng pinakamalapit na landmark, eksaktong numero ng bahay/poste, o litrato ng naturang insidente? Maraming salamat po."
    ]);
    $stm->execute([
        'Clarification',
        'Jurisdiction',
        'National Highway / Non-LGU Jurisdiction Advisory',
        "Magandang araw po. Ang lokasyon ng inyong inireklamo ay saklaw ng pambansang ahensya (DPWH / MMDA / National Highway). Pormal po naming ini-endorse ang inyong sulat sa nasabing ahensya at patuloy po namin itong imomonitor sa tulong ng ating LGU liaison officer."
    ]);
    echo "Default canned templates seeded successfully." . PHP_EOL;
}

echo "Migration finished successfully!" . PHP_EOL;
