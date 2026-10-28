<?php

namespace Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Models\Migrations;

use Hubleto\Framework\Migration;

// Foreign key of the exam's author (models and lookups review B6).
class Exam_0003 extends Migration
{
  public function upgradeSchema(): void
  {
    // Rows created on the command line (demo data) had no user, stored as 0.
    $this->db->execute("UPDATE `hlthcr_exams` SET `id_author` = NULL WHERE `id_author` = 0");
  }

  public function downgradeSchema(): void
  {
  }

  public function upgradeForeignKeys(): void
  {
    $this->db->execute("ALTER TABLE `hlthcr_exams` ADD CONSTRAINT `fk__hlthcr_exams__id_author` FOREIGN KEY (`id_author`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT");
  }

  public function downgradeForeignKeys(): void
  {
    $this->db->execute("ALTER TABLE `hlthcr_exams` DROP FOREIGN KEY `fk__hlthcr_exams__id_author`");
  }
}
