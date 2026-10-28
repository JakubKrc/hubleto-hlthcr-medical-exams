<?php

namespace Hubleto\App\External\WaiBlue\HlthcrMedicalExams\Models\Migrations;

use Hubleto\Framework\Migration;

class ExamHasHazard_0001 extends Migration
{
  public function upgradeSchema(): void
  {
    $this->db->execute("CREATE TABLE IF NOT EXISTS `hlthcr_exam_has_hazards` (
      `id` int(8) primary key auto_increment,
      `id_exam` int(8) NULL DEFAULT NULL,
      `id_hazard` int(8) NULL DEFAULT NULL,
      `level` int(1) NOT NULL DEFAULT 1,
      UNIQUE INDEX `exam_hazard` (`id_exam`, `id_hazard`)
    ) ENGINE=InnoDB");
  }

  public function downgradeSchema(): void
  {
    $this->db->execute("DROP TABLE IF EXISTS `hlthcr_exam_has_hazards`");
  }

  public function upgradeForeignKeys(): void
  {
    $this->db->execute("ALTER TABLE `hlthcr_exam_has_hazards` ADD CONSTRAINT `fk__hlthcr_exam_has_hazards__id_exam` FOREIGN KEY (`id_exam`) REFERENCES `hlthcr_exams` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT");
    $this->db->execute("ALTER TABLE `hlthcr_exam_has_hazards` ADD CONSTRAINT `fk__hlthcr_exam_has_hazards__id_hazard` FOREIGN KEY (`id_hazard`) REFERENCES `hlthcr_hazards` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT");
  }

  public function downgradeForeignKeys(): void
  {
    $this->db->execute("ALTER TABLE `hlthcr_exam_has_hazards` DROP FOREIGN KEY `fk__hlthcr_exam_has_hazards__id_exam`");
    $this->db->execute("ALTER TABLE `hlthcr_exam_has_hazards` DROP FOREIGN KEY `fk__hlthcr_exam_has_hazards__id_hazard`");
  }
}
