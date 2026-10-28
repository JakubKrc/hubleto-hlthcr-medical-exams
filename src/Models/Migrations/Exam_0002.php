<?php

namespace Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Models\Migrations;

use Hubleto\Framework\Migration;

// An employee with exams cannot be deleted (one legacy app), so no health record or its files are lost.
class Exam_0002 extends Migration
{
  public function upgradeSchema(): void
  {
  }

  public function downgradeSchema(): void
  {
  }

  public function upgradeForeignKeys(): void
  {
    $this->db->execute("ALTER TABLE `hlthcr_exams` DROP FOREIGN KEY `fk__hlthcr_exams__id_employee`");
    $this->db->execute("ALTER TABLE `hlthcr_exams` ADD CONSTRAINT `fk__hlthcr_exams__id_employee` FOREIGN KEY (`id_employee`) REFERENCES `hlthcr_employees` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT");
  }

  public function downgradeForeignKeys(): void
  {
    $this->db->execute("ALTER TABLE `hlthcr_exams` DROP FOREIGN KEY `fk__hlthcr_exams__id_employee`");
    $this->db->execute("ALTER TABLE `hlthcr_exams` ADD CONSTRAINT `fk__hlthcr_exams__id_employee` FOREIGN KEY (`id_employee`) REFERENCES `hlthcr_employees` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT");
  }
}
