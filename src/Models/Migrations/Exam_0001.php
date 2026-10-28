<?php

namespace Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Models\Migrations;

use Hubleto\Framework\Migration;

class Exam_0001 extends Migration
{
  public function upgradeSchema(): void
  {
    $this->db->execute("CREATE TABLE IF NOT EXISTS `hlthcr_exams` (
      `id` int(8) primary key auto_increment,
      `id_employee` int(8) NULL DEFAULT NULL,
      `exam_type` int(1) NOT NULL DEFAULT 1,
      `date_exam` date NULL DEFAULT NULL,
      `time_exam` time NULL DEFAULT NULL,
      `next_exam_type` int(1) NOT NULL DEFAULT 0,
      `date_next_exam` date NULL DEFAULT NULL,
      `next_exam_date_typed` int(1) NOT NULL DEFAULT 0,
      `id_planned_exam` int(8) NULL DEFAULT NULL,
      `result` int(1) NOT NULL DEFAULT 0,
      `note` varchar(255) NULL DEFAULT NULL,
      `id_clinic` int(8) NULL DEFAULT NULL,
      `document` varchar(255) NULL DEFAULT NULL,
      `scan` varchar(255) NULL DEFAULT NULL,
      `id_author` int(8) NULL DEFAULT NULL,
      `fitness_assessment_file` varchar(255) NULL DEFAULT NULL,
      INDEX `id_employee` (`id_employee`),
      INDEX `id_clinic` (`id_clinic`),
      INDEX `date_exam` (`date_exam`),
      INDEX `date_next_exam` (`date_next_exam`)
    ) ENGINE=InnoDB");
  }

  public function downgradeSchema(): void
  {
    $this->db->execute("DROP TABLE IF EXISTS `hlthcr_exams`");
  }

  public function upgradeForeignKeys(): void
  {
    $this->db->execute("ALTER TABLE `hlthcr_exams` ADD CONSTRAINT `fk__hlthcr_exams__id_employee` FOREIGN KEY (`id_employee`) REFERENCES `hlthcr_employees` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT");
    $this->db->execute("ALTER TABLE `hlthcr_exams` ADD CONSTRAINT `fk__hlthcr_exams__id_clinic` FOREIGN KEY (`id_clinic`) REFERENCES `hlthcr_clinics` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT");
    $this->db->execute("ALTER TABLE `hlthcr_exams` ADD CONSTRAINT `fk__hlthcr_exams__id_planned_exam` FOREIGN KEY (`id_planned_exam`) REFERENCES `hlthcr_exams` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT");
  }

  public function downgradeForeignKeys(): void
  {
    $this->db->execute("ALTER TABLE `hlthcr_exams` DROP FOREIGN KEY `fk__hlthcr_exams__id_employee`");
    $this->db->execute("ALTER TABLE `hlthcr_exams` DROP FOREIGN KEY `fk__hlthcr_exams__id_clinic`");
    $this->db->execute("ALTER TABLE `hlthcr_exams` DROP FOREIGN KEY `fk__hlthcr_exams__id_planned_exam`");
  }
}
