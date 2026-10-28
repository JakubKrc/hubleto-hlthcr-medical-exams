<?php

namespace Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Models\Migrations;

use Hubleto\Framework\Migration;

// The exam keeps the employee's job at the time of the exam (models and lookups review B1).
class Exam_0004 extends Migration
{
  public function upgradeSchema(): void
  {
    $this->db->execute("ALTER TABLE `hlthcr_exams` ADD COLUMN `id_job` int(8) NULL DEFAULT NULL AFTER `id_employee`");
    $this->db->execute("ALTER TABLE `hlthcr_exams` ADD INDEX `id_job` (`id_job`)");

    // Existing exams get the employee's current job, the best value there is.
    $this->db->execute("UPDATE `hlthcr_exams`
      JOIN `hlthcr_employees` ON `hlthcr_employees`.`id` = `hlthcr_exams`.`id_employee`
      SET `hlthcr_exams`.`id_job` = `hlthcr_employees`.`id_job`
      WHERE `hlthcr_exams`.`id_job` IS NULL");
  }

  public function downgradeSchema(): void
  {
    $this->db->execute("ALTER TABLE `hlthcr_exams` DROP COLUMN `id_job`");
  }

  public function upgradeForeignKeys(): void
  {
    $this->db->execute("ALTER TABLE `hlthcr_exams` ADD CONSTRAINT `fk__hlthcr_exams__id_job` FOREIGN KEY (`id_job`) REFERENCES `hlthcr_jobs` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT");
  }

  public function downgradeForeignKeys(): void
  {
    $this->db->execute("ALTER TABLE `hlthcr_exams` DROP FOREIGN KEY `fk__hlthcr_exams__id_job`");
  }
}
